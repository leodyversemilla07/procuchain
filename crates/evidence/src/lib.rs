//! Hash-chained audit evidence and Merkle-batched anchoring.

use std::collections::BTreeMap;

use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use procuchain_shared::{ContentHash, UserId};

pub mod anchor;

pub use anchor::{
    ANCHOR_SCHEMA_VERSION, AnchorBatch, AnchorPublication, AnchorPublicationError, AnchorPublisher,
    AnchorReceipt, AnchorRecord, EvidenceAnchor, MerkleProofStep, MerkleSide, merkle_proof,
    merkle_root, verify_merkle_proof,
};

/// Append-only evidence record.
///
/// Each event hashes its own payload together with the previous event hash.
/// Verification therefore detects both payload changes and removed or
/// reordered events.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AuditEvent {
    pub seq: u64,
    pub event_type: String,
    pub actor: Option<UserId>,
    pub subject_kind: String,
    pub subject_id: String,
    pub occurred_at: OffsetDateTime,
    pub details: BTreeMap<String, String>,
    pub previous_hash: ContentHash,
    pub event_hash: ContentHash,
}

impl AuditEvent {
    #[allow(clippy::too_many_arguments)]
    fn compute_hash(
        seq: u64,
        event_type: &str,
        actor: Option<UserId>,
        subject_kind: &str,
        subject_id: &str,
        occurred_at: OffsetDateTime,
        details: &BTreeMap<String, String>,
        previous_hash: &ContentHash,
    ) -> ContentHash {
        let mut canonical = String::new();
        canonical.push_str("procuchain:audit:event:v1\n");
        canonical.push_str(&seq.to_string());
        canonical.push('\n');
        canonical.push_str(event_type);
        canonical.push('\n');
        canonical.push_str(actor.map(|id| id.to_string()).unwrap_or_default().as_str());
        canonical.push('\n');
        canonical.push_str(subject_kind);
        canonical.push('\n');
        canonical.push_str(subject_id);
        canonical.push('\n');
        canonical.push_str(
            &occurred_at
                .unix_timestamp_nanos()
                .div_euclid(1_000)
                .to_string(),
        );
        canonical.push('\n');
        for (key, value) in details {
            canonical.push_str(key);
            canonical.push('=');
            canonical.push_str(value);
            canonical.push('\n');
        }
        canonical.push_str(previous_hash.as_str());
        ContentHash::sha256(canonical.as_bytes())
    }
}

#[derive(Clone, Debug, Default, PartialEq, Eq, Serialize, Deserialize)]
pub struct AuditLog {
    events: Vec<AuditEvent>,
}

impl AuditLog {
    pub fn new() -> Self {
        Self { events: Vec::new() }
    }

    /// Rehydrate a verified audit chain from durable storage.
    pub fn restore(events: Vec<AuditEvent>) -> Result<Self, procuchain_shared::DomainError> {
        if !Self::verify_events(&events) {
            return Err(procuchain_shared::DomainError::rule(
                "persisted audit chain failed verification",
            ));
        }
        Ok(Self { events })
    }

    pub fn append(
        &mut self,
        event_type: impl Into<String>,
        actor: Option<UserId>,
        subject_kind: impl Into<String>,
        subject_id: impl Into<String>,
        occurred_at: OffsetDateTime,
        details: BTreeMap<String, String>,
    ) -> &AuditEvent {
        let seq = self.events.len() as u64 + 1;
        let previous_hash = self
            .events
            .last()
            .map(|event| event.event_hash.clone())
            .unwrap_or_else(ContentHash::audit_genesis);
        let event_type = event_type.into();
        let subject_kind = subject_kind.into();
        let subject_id = subject_id.into();
        let event_hash = AuditEvent::compute_hash(
            seq,
            &event_type,
            actor,
            &subject_kind,
            &subject_id,
            occurred_at,
            &details,
            &previous_hash,
        );
        self.events.push(AuditEvent {
            seq,
            event_type,
            actor,
            subject_kind,
            subject_id,
            occurred_at,
            details,
            previous_hash,
            event_hash,
        });
        self.events
            .last()
            .expect("an event was just appended to the audit log")
    }

    pub fn events(&self) -> &[AuditEvent] {
        &self.events
    }

    pub fn len(&self) -> usize {
        self.events.len()
    }

    pub fn is_empty(&self) -> bool {
        self.events.is_empty()
    }

    pub fn verify_chain(&self) -> bool {
        Self::verify_events(&self.events)
    }

    pub fn verify_events(events: &[AuditEvent]) -> bool {
        let mut previous_hash = ContentHash::audit_genesis();
        for (index, event) in events.iter().enumerate() {
            if event.seq != index as u64 + 1 {
                return false;
            }
            if event.previous_hash != previous_hash {
                return false;
            }
            let expected = AuditEvent::compute_hash(
                event.seq,
                &event.event_type,
                event.actor,
                &event.subject_kind,
                &event.subject_id,
                event.occurred_at,
                &event.details,
                &event.previous_hash,
            );
            if event.event_hash != expected {
                return false;
            }
            previous_hash = event.event_hash.clone();
        }
        true
    }
}

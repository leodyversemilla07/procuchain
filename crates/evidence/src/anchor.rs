//! Merkle-batched evidence anchoring.
//!
//! The audit log is already a hash chain. Anchoring commits a contiguous range
//! of audit events to a single Merkle root so the range can be timestamped,
//! published, or corroborated by an external notary without exposing the
//! events themselves.
//!
//! This boundary is deliberately narrow: documents, approvals, signatures, and
//! workflow state never leave PostgreSQL. Only the root, the covered sequence
//! range, and the previous anchor hash are eligible to be anchored elsewhere.

use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use procuchain_shared::{AnchorId, ContentHash, DomainError, TenantId};

/// Schema identifier pinned into every anchor record.
pub const ANCHOR_SCHEMA_VERSION: &str = "procuchain:anchor:v1";

const LEAF_PREFIX: &str = "procuchain:anchor:leaf:v1\n";
const NODE_PREFIX: &str = "procuchain:anchor:node:v1\n";

/// A contiguous run of audit events submitted for anchoring.
#[derive(Clone, Debug, PartialEq, Eq)]
pub struct AnchorBatch {
    tenant_id: TenantId,
    from_seq: u64,
    to_seq: u64,
    event_hashes: Vec<ContentHash>,
}

impl AnchorBatch {
    pub fn new(
        tenant_id: TenantId,
        from_seq: u64,
        to_seq: u64,
        event_hashes: Vec<ContentHash>,
    ) -> Result<Self, DomainError> {
        if from_seq == 0 {
            return Err(DomainError::rule("anchor range must start at seq 1"));
        }
        if to_seq < from_seq {
            return Err(DomainError::rule(
                "anchor range must not end before it starts",
            ));
        }
        let expected = to_seq - from_seq + 1;
        if event_hashes.len() as u64 != expected {
            return Err(DomainError::rule(
                "anchor range must contain exactly one hash per audit sequence",
            ));
        }
        Ok(Self {
            tenant_id,
            from_seq,
            to_seq,
            event_hashes,
        })
    }

    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }

    pub fn from_seq(&self) -> u64 {
        self.from_seq
    }

    pub fn to_seq(&self) -> u64 {
        self.to_seq
    }

    pub fn event_hashes(&self) -> &[ContentHash] {
        &self.event_hashes
    }

    pub fn event_count(&self) -> u64 {
        self.event_hashes.len() as u64
    }

    /// Root over the batch in audit-sequence order.
    pub fn merkle_root(&self) -> ContentHash {
        merkle_root(&self.event_hashes).expect("a validated anchor batch is never empty")
    }

    /// Inclusion proof for the event at `index` within this batch.
    pub fn proof(&self, index: usize) -> Option<Vec<MerkleProofStep>> {
        merkle_proof(&self.event_hashes, index)
    }
}

/// Side of a Merkle proof step relative to the running hash.
#[derive(Clone, Copy, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum MerkleSide {
    Left,
    Right,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct MerkleProofStep {
    sibling: ContentHash,
    side: MerkleSide,
}

impl MerkleProofStep {
    pub fn sibling(&self) -> &ContentHash {
        &self.sibling
    }

    pub fn side(&self) -> MerkleSide {
        self.side
    }
}

/// Canonical Merkle root over audit-event hashes.
///
/// Leaves pair left-to-right. An unpaired node is promoted unchanged to the
/// next level, which keeps the tree shape unambiguous for every batch size.
pub fn merkle_root(leaves: &[ContentHash]) -> Option<ContentHash> {
    if leaves.is_empty() {
        return None;
    }
    let mut level: Vec<ContentHash> = leaves.iter().map(leaf_hash).collect();
    while level.len() > 1 {
        level = next_level(&level);
    }
    level.pop()
}

/// Inclusion proof for one leaf, or `None` when the index is out of range.
pub fn merkle_proof(leaves: &[ContentHash], index: usize) -> Option<Vec<MerkleProofStep>> {
    if index >= leaves.len() {
        return None;
    }
    let mut level: Vec<ContentHash> = leaves.iter().map(leaf_hash).collect();
    let mut position = index;
    let mut proof = Vec::new();
    while level.len() > 1 {
        let sibling = position ^ 1;
        if sibling < level.len() {
            proof.push(MerkleProofStep {
                sibling: level[sibling].clone(),
                side: if (position & 1) == 0 {
                    MerkleSide::Right
                } else {
                    MerkleSide::Left
                },
            });
        }
        level = next_level(&level);
        position /= 2;
    }
    Some(proof)
}

/// Recompute the root from one leaf and its proof.
pub fn verify_merkle_proof(
    leaf: &ContentHash,
    proof: &[MerkleProofStep],
    root: &ContentHash,
) -> bool {
    let mut current = leaf_hash(leaf);
    for step in proof {
        current = match step.side {
            MerkleSide::Left => node_hash(&step.sibling, &current),
            MerkleSide::Right => node_hash(&current, &step.sibling),
        };
    }
    &current == root
}

fn next_level(level: &[ContentHash]) -> Vec<ContentHash> {
    level
        .chunks(2)
        .map(|pair| match pair {
            [left, right] => node_hash(left, right),
            [only] => only.clone(),
            _ => unreachable!("chunks of two contain one or two items"),
        })
        .collect()
}

fn leaf_hash(event_hash: &ContentHash) -> ContentHash {
    ContentHash::sha256(format!("{LEAF_PREFIX}{}", event_hash.as_str()).as_bytes())
}

fn node_hash(left: &ContentHash, right: &ContentHash) -> ContentHash {
    ContentHash::sha256(format!("{NODE_PREFIX}{}\n{}", left.as_str(), right.as_str()).as_bytes())
}

/// Durable commitment to one anchored audit range.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AnchorRecord {
    anchor_id: AnchorId,
    tenant_id: TenantId,
    from_seq: u64,
    to_seq: u64,
    event_count: u64,
    merkle_root: ContentHash,
    previous_anchor_hash: ContentHash,
    schema_version: String,
    created_at: OffsetDateTime,
}

/// The metadata that may cross the external anchoring boundary.
///
/// This deliberately excludes audit-event payloads and all application data.
/// A Fabric adapter can serialize this value into a chaincode transaction
/// without making the core evidence model depend on Fabric SDK types.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AnchorPublication {
    anchor_id: AnchorId,
    tenant_id: TenantId,
    from_seq: u64,
    to_seq: u64,
    event_count: u64,
    merkle_root: ContentHash,
    previous_anchor_hash: ContentHash,
    schema_version: String,
    created_at: OffsetDateTime,
}

impl AnchorPublication {
    pub fn from_record(record: &AnchorRecord) -> Self {
        Self {
            anchor_id: record.anchor_id(),
            tenant_id: record.tenant_id(),
            from_seq: record.from_seq(),
            to_seq: record.to_seq(),
            event_count: record.event_count(),
            merkle_root: record.merkle_root().clone(),
            previous_anchor_hash: record.previous_anchor_hash().clone(),
            schema_version: record.schema_version().to_owned(),
            created_at: record.created_at(),
        }
    }

    pub fn anchor_id(&self) -> AnchorId {
        self.anchor_id
    }

    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }

    pub fn from_seq(&self) -> u64 {
        self.from_seq
    }

    pub fn to_seq(&self) -> u64 {
        self.to_seq
    }

    pub fn event_count(&self) -> u64 {
        self.event_count
    }

    pub fn merkle_root(&self) -> &ContentHash {
        &self.merkle_root
    }

    pub fn previous_anchor_hash(&self) -> &ContentHash {
        &self.previous_anchor_hash
    }

    pub fn schema_version(&self) -> &str {
        &self.schema_version
    }

    pub fn created_at(&self) -> OffsetDateTime {
        self.created_at
    }
}

impl AnchorRecord {
    pub fn create(
        batch: &AnchorBatch,
        anchor_id: AnchorId,
        previous_anchor_hash: ContentHash,
        created_at: OffsetDateTime,
    ) -> Self {
        Self {
            anchor_id,
            tenant_id: batch.tenant_id(),
            from_seq: batch.from_seq(),
            to_seq: batch.to_seq(),
            event_count: batch.event_count(),
            merkle_root: batch.merkle_root(),
            previous_anchor_hash,
            schema_version: ANCHOR_SCHEMA_VERSION.to_owned(),
            created_at,
        }
    }

    /// Rehydrate a stored record after checking its internal invariants.
    #[allow(clippy::too_many_arguments)]
    pub fn restore(
        anchor_id: AnchorId,
        tenant_id: TenantId,
        from_seq: u64,
        to_seq: u64,
        event_count: u64,
        merkle_root: ContentHash,
        previous_anchor_hash: ContentHash,
        schema_version: String,
        created_at: OffsetDateTime,
    ) -> Result<Self, DomainError> {
        if from_seq == 0 || to_seq < from_seq {
            return Err(DomainError::rule("persisted anchor has an invalid range"));
        }
        if event_count != to_seq - from_seq + 1 {
            return Err(DomainError::rule(
                "persisted anchor count does not match its range",
            ));
        }
        if schema_version != ANCHOR_SCHEMA_VERSION {
            return Err(DomainError::rule(format!(
                "unsupported anchor schema version: {schema_version}"
            )));
        }
        Ok(Self {
            anchor_id,
            tenant_id,
            from_seq,
            to_seq,
            event_count,
            merkle_root,
            previous_anchor_hash,
            schema_version,
            created_at,
        })
    }

    pub fn anchor_id(&self) -> AnchorId {
        self.anchor_id
    }

    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }

    pub fn from_seq(&self) -> u64 {
        self.from_seq
    }

    pub fn to_seq(&self) -> u64 {
        self.to_seq
    }

    pub fn event_count(&self) -> u64 {
        self.event_count
    }

    pub fn merkle_root(&self) -> &ContentHash {
        &self.merkle_root
    }

    pub fn previous_anchor_hash(&self) -> &ContentHash {
        &self.previous_anchor_hash
    }

    pub fn schema_version(&self) -> &str {
        &self.schema_version
    }

    pub fn created_at(&self) -> OffsetDateTime {
        self.created_at
    }

    /// Hash that the next anchor chains to.
    pub fn record_hash(&self) -> ContentHash {
        let mut canonical = String::new();
        canonical.push_str(ANCHOR_SCHEMA_VERSION);
        canonical.push('\n');
        canonical.push_str(&self.anchor_id.to_string());
        canonical.push('\n');
        canonical.push_str(&self.tenant_id.to_string());
        canonical.push('\n');
        canonical.push_str(&self.from_seq.to_string());
        canonical.push('\n');
        canonical.push_str(&self.to_seq.to_string());
        canonical.push('\n');
        canonical.push_str(&self.event_count.to_string());
        canonical.push('\n');
        canonical.push_str(self.merkle_root.as_str());
        canonical.push('\n');
        canonical.push_str(self.previous_anchor_hash.as_str());
        canonical.push('\n');
        // Microseconds are the storage precision of timestamptz. Hashing a
        // finer value would make a reloaded record fail its own hash check.
        canonical.push_str(
            &self
                .created_at
                .unix_timestamp_nanos()
                .div_euclid(1_000)
                .to_string(),
        );
        ContentHash::sha256(canonical.as_bytes())
    }
}

/// Corroboration returned by an external anchor provider.
///
/// The receipt is stored next to the local anchor. It never replaces the
/// anchor itself, so the platform keeps working when the provider is
/// unavailable or is replaced.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AnchorReceipt {
    anchor_id: AnchorId,
    provider: String,
    provider_ref: String,
    received_at: OffsetDateTime,
    token: Option<Vec<u8>>,
}

impl AnchorReceipt {
    pub fn new(
        anchor_id: AnchorId,
        provider: impl Into<String>,
        provider_ref: impl Into<String>,
        received_at: OffsetDateTime,
        token: Option<Vec<u8>>,
    ) -> Result<Self, DomainError> {
        let provider = provider.into();
        let provider_ref = provider_ref.into();
        if provider.trim().is_empty() {
            return Err(DomainError::rule("anchor provider must not be empty"));
        }
        if provider_ref.trim().is_empty() {
            return Err(DomainError::rule(
                "anchor provider reference must not be empty",
            ));
        }
        if token.as_ref().is_some_and(|token| token.is_empty()) {
            return Err(DomainError::rule(
                "an anchor receipt token must not be empty when supplied",
            ));
        }
        Ok(Self {
            anchor_id,
            provider,
            provider_ref,
            received_at,
            token,
        })
    }

    pub fn anchor_id(&self) -> AnchorId {
        self.anchor_id
    }

    pub fn provider(&self) -> &str {
        &self.provider
    }

    pub fn provider_ref(&self) -> &str {
        &self.provider_ref
    }

    pub fn received_at(&self) -> OffsetDateTime {
        self.received_at
    }

    pub fn token(&self) -> Option<&[u8]> {
        self.token.as_deref()
    }

    /// Ensure a provider receipt is attached to the anchor being published.
    pub fn ensure_for(&self, anchor_id: AnchorId) -> Result<(), DomainError> {
        if self.anchor_id != anchor_id {
            return Err(DomainError::rule(
                "anchor provider receipt references a different anchor",
            ));
        }

        Ok(())
    }
}

/// Pluggable evidence-anchor port.
///
/// The default implementation is local PostgreSQL persistence. An RFC 3161
/// timestamp authority or a consortium ledger adapter can implement the same
/// port without changing the domain: anchoring is corroboration, not the
/// system of record.
#[allow(async_fn_in_trait)]
pub trait EvidenceAnchor {
    type Error;

    /// Anchor every audit event that follows the last stored anchor.
    ///
    /// Returns `None` when there is nothing new to anchor.
    async fn anchor_pending(
        &self,
        tenant_id: TenantId,
        anchor_id: AnchorId,
        created_at: OffsetDateTime,
    ) -> Result<Option<AnchorRecord>, Self::Error>;

    /// Recompute stored anchors and Merkle roots from the audit log.
    async fn verify_anchors(&self, tenant_id: TenantId) -> Result<bool, Self::Error>;
}

/// Provider-neutral port for externally corroborating a local anchor.
///
/// Implementations must be idempotent for a given anchor ID and return a
/// receipt that can be persisted next to the local anchor. The local database
/// remains authoritative if the provider is unavailable.
#[allow(async_fn_in_trait)]
pub trait AnchorPublisher {
    type Error;

    async fn publish(&self, anchor: &AnchorPublication) -> Result<AnchorReceipt, Self::Error>;

    /// Publish an anchor and reject a receipt bound to another anchor.
    async fn publish_verified(
        &self,
        anchor: &AnchorPublication,
    ) -> Result<AnchorReceipt, AnchorPublicationError<Self::Error>> {
        let receipt = self
            .publish(anchor)
            .await
            .map_err(AnchorPublicationError::Provider)?;
        receipt
            .ensure_for(anchor.anchor_id())
            .map_err(AnchorPublicationError::InvalidReceipt)?;
        Ok(receipt)
    }
}

#[derive(Debug, thiserror::Error)]
pub enum AnchorPublicationError<E> {
    #[error("anchor provider failed: {0}")]
    Provider(E),
    #[error("anchor provider returned an invalid receipt: {0}")]
    InvalidReceipt(DomainError),
}

#[cfg(test)]
mod tests {
    use super::*;

    fn hash(label: &str) -> ContentHash {
        ContentHash::sha256(label.as_bytes())
    }

    fn leaves(count: usize) -> Vec<ContentHash> {
        (0..count)
            .map(|index| hash(&format!("event-{index}")))
            .collect()
    }

    #[test]
    fn merkle_root_is_deterministic_and_order_sensitive() {
        let first = leaves(5);
        let mut second = first.clone();
        second.swap(1, 3);

        assert_eq!(merkle_root(&first), merkle_root(&first));
        assert_ne!(merkle_root(&first), merkle_root(&second));
        assert_eq!(merkle_root(&[]), None);
    }

    #[test]
    fn inclusion_proofs_verify_for_every_batch_size() {
        for count in 1..=9 {
            let hashes = leaves(count);
            let root = merkle_root(&hashes).expect("non-empty batch has a root");
            for index in 0..count {
                let proof = merkle_proof(&hashes, index).expect("index is in range");
                assert!(
                    verify_merkle_proof(&hashes[index], &proof, &root),
                    "proof failed for batch size {count}, index {index}"
                );
            }
        }
    }

    #[test]
    fn inclusion_proof_rejects_a_tampered_leaf() {
        let hashes = leaves(6);
        let root = merkle_root(&hashes).expect("non-empty batch has a root");
        let proof = merkle_proof(&hashes, 4).expect("index is in range");

        assert!(!verify_merkle_proof(&hash("tampered"), &proof, &root));
    }

    #[test]
    fn anchor_records_chain_to_their_predecessor() {
        let tenant_id = TenantId::new();
        let first_batch = AnchorBatch::new(tenant_id, 1, 3, leaves(3)).expect("batch is valid");
        let first = AnchorRecord::create(
            &first_batch,
            AnchorId::new(),
            ContentHash::anchor_genesis(),
            OffsetDateTime::from_unix_timestamp(1_800_000_000).expect("timestamp is valid"),
        );
        let second_batch = AnchorBatch::new(
            tenant_id,
            4,
            6,
            (3..6)
                .map(|index| hash(&format!("event-{index}")))
                .collect(),
        )
        .expect("batch is valid");
        let second = AnchorRecord::create(
            &second_batch,
            AnchorId::new(),
            first.record_hash(),
            OffsetDateTime::from_unix_timestamp(1_800_000_100).expect("timestamp is valid"),
        );

        assert_eq!(first.event_count(), 3);
        assert_eq!(second.previous_anchor_hash(), &first.record_hash());
        assert_ne!(first.merkle_root(), second.merkle_root());
    }

    #[test]
    fn anchor_batch_rejects_a_range_mismatch() {
        let error = AnchorBatch::new(TenantId::new(), 2, 4, leaves(2))
            .expect_err("three sequence numbers need three hashes");
        assert!(matches!(error, DomainError::RuleViolation(_)));
    }

    #[test]
    fn restored_anchor_rejects_an_unsupported_schema() {
        let error = AnchorRecord::restore(
            AnchorId::new(),
            TenantId::new(),
            1,
            1,
            1,
            hash("root"),
            ContentHash::anchor_genesis(),
            "procuchain:anchor:v0".to_owned(),
            OffsetDateTime::from_unix_timestamp(1_800_000_000).expect("timestamp is valid"),
        )
        .expect_err("unknown schema versions must not be accepted");
        assert!(matches!(error, DomainError::RuleViolation(_)));
    }

    #[test]
    fn publication_contains_only_anchor_metadata() {
        let batch = AnchorBatch::new(TenantId::new(), 1, 2, leaves(2)).expect("batch is valid");
        let record = AnchorRecord::create(
            &batch,
            AnchorId::new(),
            ContentHash::anchor_genesis(),
            OffsetDateTime::from_unix_timestamp(1_800_000_000).expect("timestamp is valid"),
        );

        let publication = AnchorPublication::from_record(&record);
        assert_eq!(publication.anchor_id(), record.anchor_id());
        assert_eq!(publication.tenant_id(), record.tenant_id());
        assert_eq!(publication.merkle_root(), record.merkle_root());
        assert_eq!(publication.to_seq(), 2);
        assert_eq!(publication.event_count(), 2);
    }

    struct WrongAnchorPublisher {
        receipt: AnchorReceipt,
    }

    impl AnchorPublisher for WrongAnchorPublisher {
        type Error = std::convert::Infallible;

        async fn publish(&self, _anchor: &AnchorPublication) -> Result<AnchorReceipt, Self::Error> {
            Ok(self.receipt.clone())
        }
    }

    #[tokio::test]
    async fn verified_publication_rejects_a_receipt_for_another_anchor() {
        let first_batch =
            AnchorBatch::new(TenantId::new(), 1, 1, leaves(1)).expect("batch is valid");
        let first = AnchorRecord::create(
            &first_batch,
            AnchorId::new(),
            ContentHash::anchor_genesis(),
            OffsetDateTime::from_unix_timestamp(1_800_000_000).expect("timestamp is valid"),
        );
        let second_batch =
            AnchorBatch::new(TenantId::new(), 1, 1, leaves(1)).expect("batch is valid");
        let second = AnchorRecord::create(
            &second_batch,
            AnchorId::new(),
            ContentHash::anchor_genesis(),
            OffsetDateTime::from_unix_timestamp(1_800_000_001).expect("timestamp is valid"),
        );
        let publisher = WrongAnchorPublisher {
            receipt: AnchorReceipt::new(
                second.anchor_id(),
                "fabric",
                "tx-2",
                second.created_at(),
                None,
            )
            .expect("receipt is valid"),
        };

        let error = publisher
            .publish_verified(&AnchorPublication::from_record(&first))
            .await
            .expect_err("a receipt for another anchor must be rejected");
        assert!(matches!(
            error,
            AnchorPublicationError::InvalidReceipt(DomainError::RuleViolation(_))
        ));
    }
}

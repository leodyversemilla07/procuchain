//! Provider-neutral boundaries for external publication and evidence retention.
//!
//! External systems receive hashes and references, never procurement documents
//! or personal data. A worker can call [`OutboxDispatcher`] from any async
//! runtime without coupling the domain to a provider SDK.

use std::{fs, io, path::PathBuf};

use procuchain_shared::{ContentHash, TenantId};
use serde::{Deserialize, Serialize};
use serde_json::Value;
use thiserror::Error;
use time::OffsetDateTime;
use uuid::Uuid;

pub const PUBLICATION_REQUESTED: &str = "publication.requested";
pub const TIMESTAMP_REQUESTED: &str = "evidence.timestamp.requested";
pub const RETENTION_REQUESTED: &str = "evidence.retention.requested";

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct EvidenceEnvelope {
    pub event_id: Uuid,
    pub tenant_id: TenantId,
    pub subject_ref: String,
    pub content_hash: ContentHash,
    pub requested_at: OffsetDateTime,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct ProviderReceipt {
    pub provider: String,
    pub provider_ref: String,
    pub received_at: OffsetDateTime,
    pub token: Option<Vec<u8>>,
}

#[derive(Debug, Error)]
pub enum IntegrationError {
    #[error("invalid integration event payload: {0}")]
    InvalidPayload(String),
    #[error("integration provider failed: {0}")]
    Provider(String),
}

pub trait PublicationPort {
    fn publish(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError>;
}

pub trait TimestampPort {
    fn timestamp(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError>;
}

pub trait RetentionPort {
    fn retain(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError>;
}

#[derive(Clone, Debug, PartialEq, Eq)]
pub enum DispatchResult {
    Ignored,
    Published(ProviderReceipt),
    Timestamped(ProviderReceipt),
    Retained(ProviderReceipt),
}

pub struct OutboxDispatcher<P, T, R> {
    publication: P,
    timestamp: T,
    retention: R,
}

impl<P, T, R> OutboxDispatcher<P, T, R>
where
    P: PublicationPort,
    T: TimestampPort,
    R: RetentionPort,
{
    pub fn new(publication: P, timestamp: T, retention: R) -> Self {
        Self {
            publication,
            timestamp,
            retention,
        }
    }

    pub fn dispatch(
        &self,
        event_id: Uuid,
        tenant_id: TenantId,
        event_type: &str,
        payload: Value,
    ) -> Result<DispatchResult, IntegrationError> {
        let envelope = envelope(event_id, tenant_id, payload)?;
        match event_type {
            PUBLICATION_REQUESTED => self
                .publication
                .publish(&envelope)
                .map(DispatchResult::Published),
            TIMESTAMP_REQUESTED => self
                .timestamp
                .timestamp(&envelope)
                .map(DispatchResult::Timestamped),
            RETENTION_REQUESTED => self
                .retention
                .retain(&envelope)
                .map(DispatchResult::Retained),
            _ => Ok(DispatchResult::Ignored),
        }
    }
}

fn envelope(
    event_id: Uuid,
    tenant_id: TenantId,
    payload: Value,
) -> Result<EvidenceEnvelope, IntegrationError> {
    let subject_ref = payload
        .get("subject_ref")
        .and_then(Value::as_str)
        .filter(|value| !value.is_empty())
        .ok_or_else(|| IntegrationError::InvalidPayload("subject_ref is required".to_owned()))?;
    let hash = payload
        .get("content_hash")
        .and_then(Value::as_str)
        .ok_or_else(|| IntegrationError::InvalidPayload("content_hash is required".to_owned()))?;
    Ok(EvidenceEnvelope {
        event_id,
        tenant_id,
        subject_ref: subject_ref.to_owned(),
        content_hash: ContentHash::from_hex(hash)
            .map_err(|error| IntegrationError::InvalidPayload(error.to_string()))?,
        requested_at: OffsetDateTime::now_utc(),
    })
}

/// Local publication adapter. It writes a hash-only manifest and can later be
/// replaced by a PhilGEPS or public-posting client without changing callers.
#[derive(Clone, Debug)]
pub struct FilesystemPublicationAdapter {
    root: PathBuf,
}

impl FilesystemPublicationAdapter {
    pub fn new(root: impl Into<PathBuf>) -> Result<Self, IntegrationError> {
        let root = root.into();
        fs::create_dir_all(&root).map_err(|error| IntegrationError::Provider(error.to_string()))?;
        Ok(Self { root })
    }
}

impl PublicationPort for FilesystemPublicationAdapter {
    fn publish(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError> {
        let path = self.root.join(format!("{}.json", envelope.event_id));
        let body = serde_json::to_vec(envelope)
            .map_err(|error| IntegrationError::Provider(error.to_string()))?;
        if !path.exists() {
            fs::write(path, body).map_err(|error| IntegrationError::Provider(error.to_string()))?;
        }
        Ok(ProviderReceipt {
            provider: "filesystem-publication".to_owned(),
            provider_ref: envelope.event_id.to_string(),
            received_at: OffsetDateTime::now_utc(),
            token: None,
        })
    }
}

pub trait Rfc3161Client {
    fn timestamp_imprint(&self, imprint: &ContentHash) -> Result<(String, Vec<u8>), String>;
}

pub struct Rfc3161TimestampAdapter<C> {
    client: C,
}

impl<C> Rfc3161TimestampAdapter<C> {
    pub fn new(client: C) -> Self {
        Self { client }
    }
}

impl<C: Rfc3161Client> TimestampPort for Rfc3161TimestampAdapter<C> {
    fn timestamp(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError> {
        let (reference, token) = self
            .client
            .timestamp_imprint(&envelope.content_hash)
            .map_err(IntegrationError::Provider)?;
        Ok(ProviderReceipt {
            provider: "rfc3161".to_owned(),
            provider_ref: reference,
            received_at: OffsetDateTime::now_utc(),
            token: Some(token),
        })
    }
}

/// Append-only local retention adapter. Existing objects are never overwritten
/// or deleted, making it suitable for a local WORM contract test.
#[derive(Clone, Debug)]
pub struct FilesystemWormAdapter {
    root: PathBuf,
}

impl FilesystemWormAdapter {
    pub fn new(root: impl Into<PathBuf>) -> Result<Self, IntegrationError> {
        let root = root.into();
        fs::create_dir_all(&root).map_err(|error| IntegrationError::Provider(error.to_string()))?;
        Ok(Self { root })
    }
}

impl RetentionPort for FilesystemWormAdapter {
    fn retain(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError> {
        let path = self.root.join(format!("{}.json", envelope.content_hash));
        let body = serde_json::to_vec(envelope)
            .map_err(|error| IntegrationError::Provider(error.to_string()))?;
        match fs::OpenOptions::new()
            .write(true)
            .create_new(true)
            .open(&path)
        {
            Ok(mut file) => {
                use std::io::Write;
                file.write_all(&body)
                    .map_err(|error| IntegrationError::Provider(error.to_string()))?;
            }
            Err(error) if error.kind() == io::ErrorKind::AlreadyExists => {}
            Err(error) => return Err(IntegrationError::Provider(error.to_string())),
        }
        Ok(ProviderReceipt {
            provider: "filesystem-worm".to_owned(),
            provider_ref: envelope.content_hash.to_string(),
            received_at: OffsetDateTime::now_utc(),
            token: None,
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[derive(Clone, Default)]
    struct RecordingProvider;

    impl RecordingProvider {
        fn receipt(&self, envelope: &EvidenceEnvelope, name: &str) -> ProviderReceipt {
            ProviderReceipt {
                provider: name.to_owned(),
                provider_ref: envelope.event_id.to_string(),
                received_at: OffsetDateTime::now_utc(),
                token: None,
            }
        }
    }

    impl PublicationPort for RecordingProvider {
        fn publish(
            &self,
            envelope: &EvidenceEnvelope,
        ) -> Result<ProviderReceipt, IntegrationError> {
            Ok(self.receipt(envelope, "publication-test"))
        }
    }
    impl TimestampPort for RecordingProvider {
        fn timestamp(
            &self,
            envelope: &EvidenceEnvelope,
        ) -> Result<ProviderReceipt, IntegrationError> {
            Ok(self.receipt(envelope, "timestamp-test"))
        }
    }
    impl RetentionPort for RecordingProvider {
        fn retain(&self, envelope: &EvidenceEnvelope) -> Result<ProviderReceipt, IntegrationError> {
            Ok(self.receipt(envelope, "retention-test"))
        }
    }

    fn payload() -> Value {
        serde_json::json!({"subject_ref": "anchor:001", "content_hash": ContentHash::sha256(b"evidence").to_string()})
    }

    #[test]
    fn dispatcher_routes_each_external_event_type() {
        let dispatcher =
            OutboxDispatcher::new(RecordingProvider, RecordingProvider, RecordingProvider);
        let tenant = TenantId::new();
        let event_id = Uuid::now_v7();
        assert!(matches!(
            dispatcher.dispatch(event_id, tenant, PUBLICATION_REQUESTED, payload()),
            Ok(DispatchResult::Published(_))
        ));
        assert!(matches!(
            dispatcher.dispatch(event_id, tenant, TIMESTAMP_REQUESTED, payload()),
            Ok(DispatchResult::Timestamped(_))
        ));
        assert!(matches!(
            dispatcher.dispatch(event_id, tenant, RETENTION_REQUESTED, payload()),
            Ok(DispatchResult::Retained(_))
        ));
        assert_eq!(
            dispatcher
                .dispatch(event_id, tenant, "unknown.event", payload())
                .expect("unknown event"),
            DispatchResult::Ignored
        );
    }

    #[test]
    fn dispatcher_rejects_payloads_without_hashes() {
        let dispatcher =
            OutboxDispatcher::new(RecordingProvider, RecordingProvider, RecordingProvider);
        let error = dispatcher
            .dispatch(
                Uuid::now_v7(),
                TenantId::new(),
                PUBLICATION_REQUESTED,
                serde_json::json!({"subject_ref": "anchor:001"}),
            )
            .expect_err("hash is required");
        assert!(matches!(error, IntegrationError::InvalidPayload(_)));
    }

    struct FakeTimestampAuthority;

    impl Rfc3161Client for FakeTimestampAuthority {
        fn timestamp_imprint(&self, imprint: &ContentHash) -> Result<(String, Vec<u8>), String> {
            Ok((format!("tsa:{}", imprint), b"fake-rfc3161-token".to_vec()))
        }
    }

    #[test]
    fn concrete_adapters_preserve_hash_only_contracts() {
        let root = std::env::temp_dir().join(format!("procuchain-adapters-{}", std::process::id()));
        let envelope = EvidenceEnvelope {
            event_id: Uuid::now_v7(),
            tenant_id: TenantId::new(),
            subject_ref: "anchor:001".to_owned(),
            content_hash: ContentHash::sha256(b"evidence"),
            requested_at: OffsetDateTime::now_utc(),
        };
        let publication = FilesystemPublicationAdapter::new(root.join("publication"))
            .expect("publication adapter");
        let retention = FilesystemWormAdapter::new(root.join("worm")).expect("worm adapter");
        let timestamp = Rfc3161TimestampAdapter::new(FakeTimestampAuthority);
        assert_eq!(
            publication
                .publish(&envelope)
                .expect("publication")
                .provider,
            "filesystem-publication"
        );
        assert_eq!(
            retention.retain(&envelope).expect("retention").provider,
            "filesystem-worm"
        );
        let receipt = timestamp.timestamp(&envelope).expect("timestamp");
        assert_eq!(receipt.provider, "rfc3161");
        assert_eq!(receipt.token, Some(b"fake-rfc3161-token".to_vec()));
        fs::remove_dir_all(root).expect("adapter test cleanup");
    }
}

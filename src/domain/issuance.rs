use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use crate::shared::{
    AcknowledgementId, Actor, ArtifactId, ContentHash, DistributionId, DocumentVersionId,
    DomainError, IssuanceId, RecipientId,
};

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum RecipientKind {
    InternalOffice,
    ExternalParty,
    Public,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Recipient {
    id: RecipientId,
    kind: RecipientKind,
    name: String,
    address: Option<String>,
}

impl Recipient {
    pub fn new(
        id: RecipientId,
        kind: RecipientKind,
        name: impl Into<String>,
        address: Option<String>,
    ) -> Result<Self, DomainError> {
        let name = name.into();
        if name.trim().is_empty() {
            return Err(DomainError::rule("recipient name must not be empty"));
        }
        if let Some(address) = &address {
            if address.trim().is_empty() {
                return Err(DomainError::rule(
                    "recipient address must not be empty when supplied",
                ));
            }
        }
        Ok(Self {
            id,
            kind,
            name,
            address,
        })
    }

    pub fn id(&self) -> RecipientId {
        self.id
    }

    pub fn kind(&self) -> RecipientKind {
        self.kind
    }

    pub fn name(&self) -> &str {
        &self.name
    }

    pub fn address(&self) -> Option<&str> {
        self.address.as_deref()
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum DistributionChannel {
    Portal,
    Email,
    PrintedCopy,
    PeWebsite,
    PhilGeps,
}

impl DistributionChannel {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Portal => "portal",
            Self::Email => "email",
            Self::PrintedCopy => "printed_copy",
            Self::PeWebsite => "pe_website",
            Self::PhilGeps => "philgeps",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum IssuanceStatus {
    Active,
    Superseded,
    Withdrawn,
}

impl IssuanceStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Active => "active",
            Self::Superseded => "superseded",
            Self::Withdrawn => "withdrawn",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct IssuanceArtifact {
    artifact_id: ArtifactId,
    media_type: String,
    hash: ContentHash,
}

impl IssuanceArtifact {
    pub fn new(
        artifact_id: ArtifactId,
        media_type: impl Into<String>,
        hash: ContentHash,
    ) -> Result<Self, DomainError> {
        let media_type = media_type.into();
        if media_type.trim().is_empty() {
            return Err(DomainError::rule("artifact media type must not be empty"));
        }
        Ok(Self {
            artifact_id,
            media_type,
            hash,
        })
    }

    pub fn artifact_id(&self) -> ArtifactId {
        self.artifact_id
    }

    pub fn media_type(&self) -> &str {
        &self.media_type
    }

    pub fn hash(&self) -> &ContentHash {
        &self.hash
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct IssuanceRecipient {
    recipient: Recipient,
    channel: DistributionChannel,
    acknowledgement_required: bool,
}

impl IssuanceRecipient {
    pub fn new(
        recipient: Recipient,
        channel: DistributionChannel,
        acknowledgement_required: bool,
    ) -> Result<Self, DomainError> {
        if recipient.kind() == RecipientKind::Public && acknowledgement_required {
            return Err(DomainError::rule(
                "public publication cannot require a named-recipient acknowledgement",
            ));
        }
        Ok(Self {
            recipient,
            channel,
            acknowledgement_required,
        })
    }

    pub fn recipient(&self) -> &Recipient {
        &self.recipient
    }

    pub fn channel(&self) -> DistributionChannel {
        self.channel
    }

    pub fn acknowledgement_required(&self) -> bool {
        self.acknowledgement_required
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum DistributionStatus {
    Planned,
    Delivered,
    Failed,
    Acknowledged,
    Superseded,
}

impl DistributionStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Planned => "planned",
            Self::Delivered => "delivered",
            Self::Failed => "failed",
            Self::Acknowledged => "acknowledged",
            Self::Superseded => "superseded",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum DeliveryResult {
    Delivered,
    Failed { reason: String },
}

impl DeliveryResult {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Delivered => "delivered",
            Self::Failed { .. } => "failed",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct DeliveryAttempt {
    attempted_at: OffsetDateTime,
    channel_ref: String,
    result: DeliveryResult,
}

impl DeliveryAttempt {
    pub fn new(
        attempted_at: OffsetDateTime,
        channel_ref: impl Into<String>,
        result: DeliveryResult,
    ) -> Result<Self, DomainError> {
        let channel_ref = channel_ref.into();
        if channel_ref.trim().is_empty() {
            return Err(DomainError::rule(
                "delivery attempt must contain a channel reference",
            ));
        }
        if let DeliveryResult::Failed { reason } = &result {
            if reason.trim().is_empty() {
                return Err(DomainError::rule("a failed delivery must record a reason"));
            }
        }
        Ok(Self {
            attempted_at,
            channel_ref,
            result,
        })
    }

    pub fn attempted_at(&self) -> OffsetDateTime {
        self.attempted_at
    }

    pub fn channel_ref(&self) -> &str {
        &self.channel_ref
    }

    pub fn result(&self) -> &DeliveryResult {
        &self.result
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Distribution {
    id: DistributionId,
    issuance_id: IssuanceId,
    version_id: DocumentVersionId,
    recipient: Recipient,
    channel: DistributionChannel,
    acknowledgement_required: bool,
    status: DistributionStatus,
    attempts: Vec<DeliveryAttempt>,
}

impl Distribution {
    #[allow(clippy::too_many_arguments)]
    pub(crate) fn new(
        id: DistributionId,
        issuance_id: IssuanceId,
        version_id: DocumentVersionId,
        recipient: Recipient,
        channel: DistributionChannel,
        acknowledgement_required: bool,
    ) -> Self {
        Self {
            id,
            issuance_id,
            version_id,
            recipient,
            channel,
            acknowledgement_required,
            status: DistributionStatus::Planned,
            attempts: Vec::new(),
        }
    }

    pub fn id(&self) -> DistributionId {
        self.id
    }

    pub fn issuance_id(&self) -> IssuanceId {
        self.issuance_id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn recipient(&self) -> &Recipient {
        &self.recipient
    }

    pub fn channel(&self) -> DistributionChannel {
        self.channel
    }

    pub fn acknowledgement_required(&self) -> bool {
        self.acknowledgement_required
    }

    pub fn status(&self) -> DistributionStatus {
        self.status
    }

    pub fn attempts(&self) -> &[DeliveryAttempt] {
        &self.attempts
    }

    pub fn record_attempt(&mut self, attempt: DeliveryAttempt) -> Result<(), DomainError> {
        if matches!(
            self.status,
            DistributionStatus::Acknowledged | DistributionStatus::Superseded
        ) {
            return Err(DomainError::invalid_state(
                "distribution",
                self.status.label(),
                "planned, delivered, or failed",
            ));
        }
        self.status = match attempt.result() {
            DeliveryResult::Delivered => DistributionStatus::Delivered,
            DeliveryResult::Failed { .. } => DistributionStatus::Failed,
        };
        self.attempts.push(attempt);
        Ok(())
    }

    pub(crate) fn mark_acknowledged(&mut self) -> Result<(), DomainError> {
        if self.status != DistributionStatus::Delivered {
            return Err(DomainError::invalid_state(
                "distribution",
                self.status.label(),
                "delivered",
            ));
        }
        self.status = DistributionStatus::Acknowledged;
        Ok(())
    }

    pub(crate) fn supersede(&mut self) {
        self.status = DistributionStatus::Superseded;
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Issuance {
    id: IssuanceId,
    version_id: DocumentVersionId,
    version_hash: ContentHash,
    issuer: Actor,
    authority_ref: String,
    issued_at: OffsetDateTime,
    artifacts: Vec<IssuanceArtifact>,
    recipients: Vec<IssuanceRecipient>,
    distribution_ids: Vec<DistributionId>,
    manifest_hash: ContentHash,
    status: IssuanceStatus,
}

impl Issuance {
    #[allow(clippy::too_many_arguments)]
    pub(crate) fn new(
        id: IssuanceId,
        version_id: DocumentVersionId,
        version_hash: ContentHash,
        issuer: Actor,
        authority_ref: impl Into<String>,
        issued_at: OffsetDateTime,
        artifacts: Vec<IssuanceArtifact>,
        recipients: Vec<IssuanceRecipient>,
        distribution_ids: Vec<DistributionId>,
    ) -> Result<Self, DomainError> {
        let authority_ref = authority_ref.into();
        if authority_ref.trim().is_empty() {
            return Err(DomainError::rule(
                "issuance authority reference must not be empty",
            ));
        }
        if artifacts.is_empty() {
            return Err(DomainError::rule("issuance requires at least one artifact"));
        }
        if recipients.is_empty() {
            return Err(DomainError::rule(
                "issuance requires at least one recipient",
            ));
        }
        if distribution_ids.len() != recipients.len() {
            return Err(DomainError::rule(
                "each issuance recipient must have exactly one distribution record",
            ));
        }
        let manifest_hash = Self::compute_manifest_hash(
            version_id,
            &version_hash,
            &issuer,
            &authority_ref,
            issued_at,
            &artifacts,
            &recipients,
        );
        Ok(Self {
            id,
            version_id,
            version_hash,
            issuer,
            authority_ref,
            issued_at,
            artifacts,
            recipients,
            distribution_ids,
            manifest_hash,
            status: IssuanceStatus::Active,
        })
    }

    fn compute_manifest_hash(
        version_id: DocumentVersionId,
        version_hash: &ContentHash,
        issuer: &Actor,
        authority_ref: &str,
        issued_at: OffsetDateTime,
        artifacts: &[IssuanceArtifact],
        recipients: &[IssuanceRecipient],
    ) -> ContentHash {
        let mut canonical = String::new();
        canonical.push_str("procuchain:issuance:manifest:v1\n");
        canonical.push_str(&version_id.to_string());
        canonical.push('\n');
        canonical.push_str(version_hash.as_str());
        canonical.push('\n');
        canonical.push_str(&issuer.user_id().to_string());
        canonical.push('\n');
        canonical.push_str(&issued_at.unix_timestamp_nanos().to_string());
        canonical.push('\n');
        canonical.push_str(authority_ref);
        canonical.push('\n');
        for artifact in artifacts {
            canonical.push_str(&artifact.artifact_id.to_string());
            canonical.push('|');
            canonical.push_str(&artifact.hash.to_string());
            canonical.push('|');
            canonical.push_str(&artifact.media_type);
            canonical.push('\n');
        }
        for recipient in recipients {
            canonical.push_str(&recipient.recipient.id.to_string());
            canonical.push('|');
            canonical.push_str(recipient.channel.label());
            canonical.push('|');
            canonical.push_str(if recipient.acknowledgement_required {
                "ack"
            } else {
                "no_ack"
            });
            canonical.push('\n');
        }
        ContentHash::sha256(canonical.as_bytes())
    }

    pub fn id(&self) -> IssuanceId {
        self.id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn version_hash(&self) -> &ContentHash {
        &self.version_hash
    }

    pub fn issuer(&self) -> &Actor {
        &self.issuer
    }

    pub fn authority_ref(&self) -> &str {
        &self.authority_ref
    }

    pub fn issued_at(&self) -> OffsetDateTime {
        self.issued_at
    }

    pub fn artifacts(&self) -> &[IssuanceArtifact] {
        &self.artifacts
    }

    pub fn recipients(&self) -> &[IssuanceRecipient] {
        &self.recipients
    }

    pub fn distribution_ids(&self) -> &[DistributionId] {
        &self.distribution_ids
    }

    pub fn manifest_hash(&self) -> &ContentHash {
        &self.manifest_hash
    }

    pub fn status(&self) -> IssuanceStatus {
        self.status
    }

    pub fn is_active(&self) -> bool {
        self.status == IssuanceStatus::Active
    }

    pub fn has_artifact_hash(&self, hash: &ContentHash) -> bool {
        self.artifacts.iter().any(|artifact| &artifact.hash == hash)
    }

    pub(crate) fn supersede(&mut self) {
        self.status = IssuanceStatus::Superseded;
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum AcknowledgementMethod {
    Portal,
    Email,
    SignedReceipt,
    Other(String),
}

impl AcknowledgementMethod {
    pub fn label(&self) -> &str {
        match self {
            Self::Portal => "portal",
            Self::Email => "email",
            Self::SignedReceipt => "signed_receipt",
            Self::Other(value) => value,
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum AcknowledgementStatus {
    Active,
    Invalidated,
}

impl AcknowledgementStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Active => "active",
            Self::Invalidated => "invalidated",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Acknowledgement {
    id: AcknowledgementId,
    distribution_id: DistributionId,
    issuance_id: IssuanceId,
    version_id: DocumentVersionId,
    artifact_hash: ContentHash,
    acknowledged_by: RecipientId,
    acknowledged_at: OffsetDateTime,
    method: AcknowledgementMethod,
    declaration: Option<String>,
    status: AcknowledgementStatus,
}

impl Acknowledgement {
    #[allow(clippy::too_many_arguments)]
    pub(crate) fn new(
        id: AcknowledgementId,
        distribution_id: DistributionId,
        issuance_id: IssuanceId,
        version_id: DocumentVersionId,
        artifact_hash: ContentHash,
        acknowledged_by: RecipientId,
        acknowledged_at: OffsetDateTime,
        method: AcknowledgementMethod,
        declaration: Option<String>,
    ) -> Result<Self, DomainError> {
        if let AcknowledgementMethod::Other(value) = &method {
            if value.trim().is_empty() {
                return Err(DomainError::rule(
                    "an acknowledgement method must not be an empty label",
                ));
            }
        }
        if let Some(declaration) = &declaration {
            if declaration.trim().is_empty() {
                return Err(DomainError::rule(
                    "an acknowledgement declaration must not be empty when supplied",
                ));
            }
        }
        Ok(Self {
            id,
            distribution_id,
            issuance_id,
            version_id,
            artifact_hash,
            acknowledged_by,
            acknowledged_at,
            method,
            declaration,
            status: AcknowledgementStatus::Active,
        })
    }

    pub fn id(&self) -> AcknowledgementId {
        self.id
    }

    pub fn distribution_id(&self) -> DistributionId {
        self.distribution_id
    }

    pub fn issuance_id(&self) -> IssuanceId {
        self.issuance_id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn artifact_hash(&self) -> &ContentHash {
        &self.artifact_hash
    }

    pub fn acknowledged_by(&self) -> RecipientId {
        self.acknowledged_by
    }

    pub fn acknowledged_at(&self) -> OffsetDateTime {
        self.acknowledged_at
    }

    pub fn method(&self) -> &AcknowledgementMethod {
        &self.method
    }

    pub fn declaration(&self) -> Option<&str> {
        self.declaration.as_deref()
    }

    pub fn status(&self) -> AcknowledgementStatus {
        self.status
    }

    pub(crate) fn invalidate(&mut self) {
        self.status = AcknowledgementStatus::Invalidated;
    }
}

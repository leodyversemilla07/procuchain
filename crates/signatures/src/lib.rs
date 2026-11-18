use std::collections::BTreeMap;

use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use procuchain_records::DocumentType;
use procuchain_shared::error::DomainError;
use procuchain_shared::{
    Actor, ActorRole, ContentHash, DocumentVersionId, SignatureId, SignatureRequestId, UserId,
};

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum SignatureMethod {
    WetSignature,
    AdvancedElectronicSignature,
    QualifiedElectronicSignature,
}

impl SignatureMethod {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::WetSignature => "wet_signature",
            Self::AdvancedElectronicSignature => "advanced_electronic_signature",
            Self::QualifiedElectronicSignature => "qualified_electronic_signature",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum SignaturePurpose {
    Execution,
    Attestation,
    Approval,
}

impl SignaturePurpose {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Execution => "execution",
            Self::Attestation => "attestation",
            Self::Approval => "approval",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct SignatureRule {
    required_roles: Vec<ActorRole>,
    allowed_methods: Vec<SignatureMethod>,
}

impl SignatureRule {
    pub fn new(
        required_roles: Vec<ActorRole>,
        allowed_methods: Vec<SignatureMethod>,
    ) -> Result<Self, DomainError> {
        if required_roles.is_empty() {
            return Err(DomainError::rule(
                "a signature rule must identify at least one required signer role",
            ));
        }
        if allowed_methods.is_empty() {
            return Err(DomainError::rule(
                "a signature rule must identify at least one approved signature method",
            ));
        }
        let mut unique_roles = required_roles.clone();
        unique_roles.sort();
        unique_roles.dedup();
        if unique_roles.len() != required_roles.len() {
            return Err(DomainError::duplicate(
                "a signature rule contains a duplicated signer role",
            ));
        }
        let mut unique_methods = allowed_methods.clone();
        unique_methods.sort();
        unique_methods.dedup();
        if unique_methods.len() != allowed_methods.len() {
            return Err(DomainError::duplicate(
                "a signature rule contains a duplicated signature method",
            ));
        }
        Ok(Self {
            required_roles,
            allowed_methods,
        })
    }

    pub fn required_roles(&self) -> &[ActorRole] {
        &self.required_roles
    }

    pub fn allowed_methods(&self) -> &[SignatureMethod] {
        &self.allowed_methods
    }

    pub fn allows_method(&self, method: SignatureMethod) -> bool {
        self.allowed_methods.contains(&method)
    }

    pub fn requires_role(&self, role: ActorRole) -> bool {
        self.required_roles.contains(&role)
    }
}

/// Procuring-Entity-approved signature matrix.
///
/// The empty policy is intentionally deny-by-default: PLAT-064 and CMP-127
/// require an explicit legal and policy decision for each document class and
/// signature method. The platform must not infer permission from a generic
/// e-signature law or issuance.
#[derive(Clone, Debug, Default, PartialEq, Eq, Serialize, Deserialize)]
pub struct SignaturePolicy {
    rules: BTreeMap<DocumentType, SignatureRule>,
}

impl SignaturePolicy {
    pub fn new() -> Self {
        Self {
            rules: BTreeMap::new(),
        }
    }

    pub fn with_rule(
        mut self,
        document_type: DocumentType,
        required_roles: Vec<ActorRole>,
        allowed_methods: Vec<SignatureMethod>,
    ) -> Result<Self, DomainError> {
        self.rules.insert(
            document_type,
            SignatureRule::new(required_roles, allowed_methods)?,
        );
        Ok(self)
    }

    pub fn rule_for(&self, document_type: DocumentType) -> Option<&SignatureRule> {
        self.rules.get(&document_type)
    }

    pub fn requires_signature(&self, document_type: DocumentType) -> bool {
        self.rule_for(document_type).is_some()
    }

    pub fn allows(
        &self,
        document_type: DocumentType,
        role: ActorRole,
        method: SignatureMethod,
    ) -> bool {
        self.rule_for(document_type)
            .map(|rule| rule.requires_role(role) && rule.allows_method(method))
            .unwrap_or(false)
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum SignatureRequestStatus {
    Requested,
    Signed,
    Declined,
    VerificationFailed,
    Cancelled,
    Expired,
    Superseded,
}

impl SignatureRequestStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Requested => "requested",
            Self::Signed => "signed",
            Self::Declined => "declined",
            Self::VerificationFailed => "verification_failed",
            Self::Cancelled => "cancelled",
            Self::Expired => "expired",
            Self::Superseded => "superseded",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum SignatureValidation {
    Valid,
    VerificationFailed,
    Revoked,
    Expired,
    SignerMismatch,
}

impl SignatureValidation {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Valid => "valid",
            Self::VerificationFailed => "verification_failed",
            Self::Revoked => "revoked",
            Self::Expired => "expired",
            Self::SignerMismatch => "signer_mismatch",
        }
    }

    pub const fn is_valid(&self) -> bool {
        matches!(self, Self::Valid)
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum SignatureStatus {
    Valid,
    Invalidated,
    Failed,
}

impl SignatureStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Valid => "valid",
            Self::Invalidated => "invalidated",
            Self::Failed => "failed",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct SignatureRequest {
    id: SignatureRequestId,
    version_id: DocumentVersionId,
    content_hash: ContentHash,
    signer: UserId,
    signer_role: ActorRole,
    signer_name: String,
    purpose: SignaturePurpose,
    method: SignatureMethod,
    declaration: String,
    requested_at: OffsetDateTime,
    deadline: OffsetDateTime,
    status: SignatureRequestStatus,
}

impl SignatureRequest {
    #[allow(clippy::too_many_arguments)]
    pub fn new(
        id: SignatureRequestId,
        version_id: DocumentVersionId,
        content_hash: ContentHash,
        signer: Actor,
        purpose: SignaturePurpose,
        method: SignatureMethod,
        declaration: String,
        requested_at: OffsetDateTime,
        deadline: OffsetDateTime,
    ) -> Result<Self, DomainError> {
        if declaration.trim().is_empty() {
            return Err(DomainError::rule(
                "a signature request must contain the requested declaration",
            ));
        }
        if deadline <= requested_at {
            return Err(DomainError::rule(
                "a signature request deadline must be after the request time",
            ));
        }
        Ok(Self {
            id,
            version_id,
            content_hash,
            signer: signer.user_id(),
            signer_role: signer.role(),
            signer_name: signer.display_name().to_owned(),
            purpose,
            method,
            declaration,
            requested_at,
            deadline,
            status: SignatureRequestStatus::Requested,
        })
    }

    /// Rehydrate a signature request from durable storage.
    #[allow(clippy::too_many_arguments)]
    pub fn restore(
        id: SignatureRequestId,
        version_id: DocumentVersionId,
        content_hash: ContentHash,
        signer: UserId,
        signer_role: ActorRole,
        signer_name: String,
        purpose: SignaturePurpose,
        method: SignatureMethod,
        declaration: String,
        requested_at: OffsetDateTime,
        deadline: OffsetDateTime,
        status: SignatureRequestStatus,
    ) -> Self {
        Self {
            id,
            version_id,
            content_hash,
            signer,
            signer_role,
            signer_name,
            purpose,
            method,
            declaration,
            requested_at,
            deadline,
            status,
        }
    }

    pub fn id(&self) -> SignatureRequestId {
        self.id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn content_hash(&self) -> &ContentHash {
        &self.content_hash
    }

    pub fn signer(&self) -> UserId {
        self.signer
    }

    pub fn signer_role(&self) -> ActorRole {
        self.signer_role
    }

    pub fn signer_name(&self) -> &str {
        &self.signer_name
    }

    pub fn purpose(&self) -> SignaturePurpose {
        self.purpose
    }

    pub fn method(&self) -> SignatureMethod {
        self.method
    }

    pub fn declaration(&self) -> &str {
        &self.declaration
    }

    pub fn requested_at(&self) -> OffsetDateTime {
        self.requested_at
    }

    pub fn deadline(&self) -> OffsetDateTime {
        self.deadline
    }

    pub fn status(&self) -> SignatureRequestStatus {
        self.status
    }

    pub fn is_open(&self) -> bool {
        self.status == SignatureRequestStatus::Requested
    }

    pub fn set_status(&mut self, status: SignatureRequestStatus) {
        self.status = status;
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Signature {
    id: SignatureId,
    request_id: SignatureRequestId,
    version_id: DocumentVersionId,
    content_hash: ContentHash,
    signer: UserId,
    signer_role: ActorRole,
    signer_name: String,
    method: SignatureMethod,
    signed_at: OffsetDateTime,
    provider_transaction: String,
    credential_ref: String,
    signed_artifact_hash: ContentHash,
    validation: SignatureValidation,
    status: SignatureStatus,
}

impl Signature {
    #[allow(clippy::too_many_arguments)]
    pub fn new(
        id: SignatureId,
        request: &SignatureRequest,
        signed_at: OffsetDateTime,
        provider_transaction: String,
        credential_ref: String,
        signed_artifact_hash: ContentHash,
        validation: SignatureValidation,
    ) -> Result<Self, DomainError> {
        if provider_transaction.trim().is_empty() {
            return Err(DomainError::rule(
                "a completed signature must retain a provider transaction reference",
            ));
        }
        if credential_ref.trim().is_empty() {
            return Err(DomainError::rule(
                "a completed signature must retain a credential or certificate reference",
            ));
        }
        let status = if validation.is_valid() {
            SignatureStatus::Valid
        } else {
            SignatureStatus::Failed
        };
        Ok(Self {
            id,
            request_id: request.id,
            version_id: request.version_id,
            content_hash: request.content_hash.clone(),
            signer: request.signer,
            signer_role: request.signer_role,
            signer_name: request.signer_name.clone(),
            method: request.method,
            signed_at,
            provider_transaction,
            credential_ref,
            signed_artifact_hash,
            validation,
            status,
        })
    }

    /// Rehydrate a completed signature from durable storage.
    #[allow(clippy::too_many_arguments)]
    pub fn restore(
        id: SignatureId,
        request_id: SignatureRequestId,
        version_id: DocumentVersionId,
        content_hash: ContentHash,
        signer: UserId,
        signer_role: ActorRole,
        signer_name: String,
        method: SignatureMethod,
        signed_at: OffsetDateTime,
        provider_transaction: String,
        credential_ref: String,
        signed_artifact_hash: ContentHash,
        validation: SignatureValidation,
        status: SignatureStatus,
    ) -> Self {
        Self {
            id,
            request_id,
            version_id,
            content_hash,
            signer,
            signer_role,
            signer_name,
            method,
            signed_at,
            provider_transaction,
            credential_ref,
            signed_artifact_hash,
            validation,
            status,
        }
    }

    pub fn id(&self) -> SignatureId {
        self.id
    }

    pub fn request_id(&self) -> SignatureRequestId {
        self.request_id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn content_hash(&self) -> &ContentHash {
        &self.content_hash
    }

    pub fn signer(&self) -> UserId {
        self.signer
    }

    pub fn signer_role(&self) -> ActorRole {
        self.signer_role
    }

    pub fn signer_name(&self) -> &str {
        &self.signer_name
    }

    pub fn method(&self) -> SignatureMethod {
        self.method
    }

    pub fn signed_at(&self) -> OffsetDateTime {
        self.signed_at
    }

    pub fn provider_transaction(&self) -> &str {
        &self.provider_transaction
    }

    pub fn credential_ref(&self) -> &str {
        &self.credential_ref
    }

    pub fn signed_artifact_hash(&self) -> &ContentHash {
        &self.signed_artifact_hash
    }

    pub fn validation(&self) -> SignatureValidation {
        self.validation
    }

    pub fn status(&self) -> SignatureStatus {
        self.status
    }

    pub fn is_valid(&self) -> bool {
        self.status == SignatureStatus::Valid && self.validation.is_valid()
    }

    pub fn invalidate(&mut self) {
        self.status = SignatureStatus::Invalidated;
    }
}

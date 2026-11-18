use std::collections::{BTreeMap, BTreeSet};

use time::OffsetDateTime;

use crate::domain::approval::{Approval, ApprovalDecision};
use crate::domain::audit::{AuditEvent, AuditLog};
use crate::domain::bac::{BacMembershipSnapshot, BacResolution, BacResolutionStatus, BacVotes};
use crate::domain::document::{
    CanonicalContent, Document, DocumentType, DocumentVersion, RulePackRef, SchemaRef, TemplateRef,
    VersionStatus,
};
use crate::domain::issuance::{
    Acknowledgement, AcknowledgementMethod, DeliveryAttempt, DeliveryResult, Distribution,
    DistributionStatus, Issuance, IssuanceArtifact, IssuanceRecipient,
};
use crate::domain::signature::{
    Signature, SignatureMethod, SignaturePolicy, SignaturePurpose, SignatureRequest,
    SignatureRequestStatus, SignatureValidation,
};
use crate::shared::{
    AcknowledgementId, Actor, ActorRole, ApprovalId, BacResolutionId, ContentHash, DistributionId,
    DocumentId, DocumentVersionId, DomainError, IssuanceId, RecipientId, SignatureId,
    SignatureRequestId, TenantId,
};

/// In-memory application service for the first vertical slice.
///
/// This is deliberately not a persistence adapter. It holds the aggregate
/// state and enforces the cross-aggregate gates that must survive when the
/// repositories are implemented:
///
/// - approval and signature are separate actions;
/// - the HoPE cannot approve an APP without an active BAC recommendation for
///   the exact version and content hash;
/// - issuance requires the approval and, where configured, signatures;
/// - acknowledgement requires a delivered distribution and the exact issued
///   artifact hash;
/// - corrections create a new immutable version and preserve the old records
///   with an explicit superseded/invalidated status.
pub struct Platform {
    tenant_id: TenantId,
    signature_policy: SignaturePolicy,
    documents: BTreeMap<DocumentId, Document>,
    versions: BTreeMap<DocumentVersionId, DocumentVersion>,
    approvals: BTreeMap<ApprovalId, Approval>,
    approvals_by_version: BTreeMap<DocumentVersionId, Vec<ApprovalId>>,
    bac_resolutions: BTreeMap<BacResolutionId, BacResolution>,
    bac_resolutions_by_version: BTreeMap<DocumentVersionId, Vec<BacResolutionId>>,
    signature_requests: BTreeMap<SignatureRequestId, SignatureRequest>,
    signature_requests_by_version: BTreeMap<DocumentVersionId, Vec<SignatureRequestId>>,
    signatures: BTreeMap<SignatureId, Signature>,
    signatures_by_version: BTreeMap<DocumentVersionId, Vec<SignatureId>>,
    issuances: BTreeMap<IssuanceId, Issuance>,
    issuances_by_version: BTreeMap<DocumentVersionId, Vec<IssuanceId>>,
    distributions: BTreeMap<DistributionId, Distribution>,
    acknowledgements: BTreeMap<AcknowledgementId, Acknowledgement>,
    audit: AuditLog,
}

struct VersionContext {
    document_id: DocumentId,
    document_type: DocumentType,
    status: VersionStatus,
    content_hash: ContentHash,
}

impl Platform {
    pub fn new(tenant_id: TenantId, signature_policy: SignaturePolicy) -> Self {
        Self {
            tenant_id,
            signature_policy,
            documents: BTreeMap::new(),
            versions: BTreeMap::new(),
            approvals: BTreeMap::new(),
            approvals_by_version: BTreeMap::new(),
            bac_resolutions: BTreeMap::new(),
            bac_resolutions_by_version: BTreeMap::new(),
            signature_requests: BTreeMap::new(),
            signature_requests_by_version: BTreeMap::new(),
            signatures: BTreeMap::new(),
            signatures_by_version: BTreeMap::new(),
            issuances: BTreeMap::new(),
            issuances_by_version: BTreeMap::new(),
            distributions: BTreeMap::new(),
            acknowledgements: BTreeMap::new(),
            audit: AuditLog::new(),
        }
    }

    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }

    pub fn signature_policy(&self) -> &SignaturePolicy {
        &self.signature_policy
    }

    pub fn documents(&self) -> &BTreeMap<DocumentId, Document> {
        &self.documents
    }

    pub fn document(&self, id: DocumentId) -> Option<&Document> {
        self.documents.get(&id)
    }

    pub fn version(&self, id: DocumentVersionId) -> Option<&DocumentVersion> {
        self.versions.get(&id)
    }

    pub fn approval(&self, id: ApprovalId) -> Option<&Approval> {
        self.approvals.get(&id)
    }

    pub fn bac_resolution(&self, id: BacResolutionId) -> Option<&BacResolution> {
        self.bac_resolutions.get(&id)
    }

    pub fn signature_request(&self, id: SignatureRequestId) -> Option<&SignatureRequest> {
        self.signature_requests.get(&id)
    }

    pub fn signature(&self, id: SignatureId) -> Option<&Signature> {
        self.signatures.get(&id)
    }

    pub fn issuance(&self, id: IssuanceId) -> Option<&Issuance> {
        self.issuances.get(&id)
    }

    pub fn distribution(&self, id: DistributionId) -> Option<&Distribution> {
        self.distributions.get(&id)
    }

    pub fn acknowledgements(&self) -> &BTreeMap<AcknowledgementId, Acknowledgement> {
        &self.acknowledgements
    }

    pub fn audit_log(&self) -> &AuditLog {
        &self.audit
    }

    pub fn audit_events(&self) -> &[AuditEvent] {
        self.audit.events()
    }

    pub fn verify_audit_chain(&self) -> bool {
        self.audit.verify_chain()
    }

    pub fn approvals_for_version(&self, version_id: DocumentVersionId) -> Vec<&Approval> {
        self.approvals_by_version
            .get(&version_id)
            .into_iter()
            .flatten()
            .filter_map(|id| self.approvals.get(id))
            .collect()
    }

    pub fn bac_resolutions_for_version(
        &self,
        version_id: DocumentVersionId,
    ) -> Vec<&BacResolution> {
        self.bac_resolutions_by_version
            .get(&version_id)
            .into_iter()
            .flatten()
            .filter_map(|id| self.bac_resolutions.get(id))
            .collect()
    }

    pub fn signature_requests_for_version(
        &self,
        version_id: DocumentVersionId,
    ) -> Vec<&SignatureRequest> {
        self.signature_requests_by_version
            .get(&version_id)
            .into_iter()
            .flatten()
            .filter_map(|id| self.signature_requests.get(id))
            .collect()
    }

    pub fn signatures_for_version(&self, version_id: DocumentVersionId) -> Vec<&Signature> {
        self.signatures_by_version
            .get(&version_id)
            .into_iter()
            .flatten()
            .filter_map(|id| self.signatures.get(id))
            .collect()
    }

    /// Create the first immutable version of a new logical document.
    #[allow(clippy::too_many_arguments)]
    pub fn create_draft(
        &mut self,
        document_type: DocumentType,
        title: impl Into<String>,
        template: TemplateRef,
        schema: SchemaRef,
        content: CanonicalContent,
        author: Actor,
        rule_pack: RulePackRef,
    ) -> Result<DocumentVersionId, DomainError> {
        let document_id = DocumentId::new();
        let version_id = DocumentVersionId::new();
        let now = OffsetDateTime::now_utc();
        let mut document = Document::new(document_id, self.tenant_id, document_type, title)?;
        let version_number = document.register_version(version_id)?;
        let content_hash = content.hash();
        let version = DocumentVersion::new(
            version_id,
            document_id,
            version_number,
            content,
            template,
            schema,
            author.user_id(),
            now,
            rule_pack,
            None,
            None,
        );
        let rule_pack_snapshot = version.rule_pack().snapshot_id().to_string();
        let rule_pack_hash = version.rule_pack().manifest_hash().to_string();
        self.documents.insert(document_id, document);
        self.versions.insert(version_id, version);
        self.audit.append(
            "document.created",
            Some(author.user_id()),
            "document",
            document_id.to_string(),
            now,
            details([
                ("document_type", document_type.label().to_owned()),
                ("version_id", version_id.to_string()),
                ("version_number", version_number.to_string()),
                ("content_hash", content_hash.to_string()),
                ("rule_pack_snapshot", rule_pack_snapshot),
                ("rule_pack_hash", rule_pack_hash),
            ]),
        );
        Ok(version_id)
    }

    pub fn set_business_id(
        &mut self,
        document_id: DocumentId,
        business_id: impl Into<String>,
    ) -> Result<(), DomainError> {
        let document = self
            .documents
            .get_mut(&document_id)
            .ok_or_else(|| DomainError::not_found("document", document_id))?;
        document.set_business_id(business_id)?;
        Ok(())
    }

    pub fn submit_version(
        &mut self,
        version_id: DocumentVersionId,
        actor: &Actor,
    ) -> Result<(), DomainError> {
        let context = self.version_context(version_id)?;
        let version = self
            .versions
            .get(&version_id)
            .ok_or_else(|| DomainError::not_found("document version", version_id))?;
        if !matches!(
            actor.role(),
            ActorRole::EndUser | ActorRole::EndUserHead | ActorRole::Secretariat | ActorRole::Hope
        ) {
            return Err(DomainError::unauthorized(
                "only a responsible procurement actor may submit a document",
            ));
        }
        if actor.user_id() != version.author()
            && !matches!(actor.role(), ActorRole::Secretariat | ActorRole::Hope)
        {
            return Err(DomainError::unauthorized(
                "only the author, Secretariat, or HoPE may submit this version",
            ));
        }
        let now = OffsetDateTime::now_utc();
        self.versions
            .get_mut(&version_id)
            .expect("version exists")
            .transition_to(&[VersionStatus::Draft], VersionStatus::InReview)?;
        self.audit.append(
            "version.submitted",
            Some(actor.user_id()),
            "document_version",
            version_id.to_string(),
            now,
            details([
                ("document_id", context.document_id.to_string()),
                ("content_hash", context.content_hash.to_string()),
            ]),
        );
        Ok(())
    }

    /// Approve a PPMP by the End-User head.
    ///
    /// This is a governed document-approval action. It is not the HoPE APP
    /// approval and it does not require a BAC recommendation.
    pub fn approve_ppmp(
        &mut self,
        version_id: DocumentVersionId,
        approver: &Actor,
        authority_ref: impl Into<String>,
        grounds: Option<String>,
    ) -> Result<ApprovalId, DomainError> {
        let context = self.version_context(version_id)?;
        if context.document_type != DocumentType::Ppmp {
            return Err(DomainError::rule("this approval action is only for a PPMP"));
        }
        if approver.role() != ActorRole::EndUserHead {
            return Err(DomainError::unauthorized(
                "the PPMP must be approved by the End-User head",
            ));
        }
        let authority_ref = require_authority(authority_ref)?;
        self.ensure_status(version_id, VersionStatus::InReview)?;
        let approval = Approval::new(
            ApprovalId::new(),
            version_id,
            context.content_hash.clone(),
            approver.clone(),
            ApprovalDecision::Approved,
            OffsetDateTime::now_utc(),
            authority_ref,
            grounds,
        );
        self.versions
            .get_mut(&version_id)
            .expect("version exists")
            .transition_to(&[VersionStatus::InReview], VersionStatus::Approved)?;
        self.approvals.insert(approval.id(), approval.clone());
        self.approvals_by_version
            .entry(version_id)
            .or_default()
            .push(approval.id());
        self.audit.append(
            "ppmp.approved",
            Some(approver.user_id()),
            "document_version",
            version_id.to_string(),
            OffsetDateTime::now_utc(),
            details([
                ("approval_id", approval.id().to_string()),
                ("content_hash", context.content_hash.to_string()),
            ]),
        );
        Ok(approval.id())
    }

    /// Record a BAC recommendation/resolution for an APP version.
    ///
    /// The membership snapshot is frozen into the resolution. IRR 42.2
    /// quorum and the Chair/Vice-Chair presence rule are enforced by the
    /// domain object.
    #[allow(clippy::too_many_arguments)]
    pub fn bac_recommend_app(
        &mut self,
        version_id: DocumentVersionId,
        resolution_number: impl Into<String>,
        membership: BacMembershipSnapshot,
        attendance: BTreeSet<crate::shared::UserId>,
        votes: BacVotes,
        presiding_actor: &Actor,
    ) -> Result<BacResolutionId, DomainError> {
        let context = self.version_context(version_id)?;
        if context.document_type != DocumentType::AnnualProcurementPlan {
            return Err(DomainError::rule(
                "a BAC APP recommendation requires an Annual Procurement Plan version",
            ));
        }
        self.ensure_status(version_id, VersionStatus::InReview)?;
        if self
            .active_bac_resolution(version_id)
            .is_some_and(|resolution| resolution.is_recommended())
        {
            return Err(DomainError::duplicate(
                "an active BAC recommendation already exists for this APP version",
            ));
        }
        let resolution = BacResolution::record(
            BacResolutionId::new(),
            version_id,
            context.content_hash.clone(),
            resolution_number,
            membership,
            attendance,
            votes,
            presiding_actor,
            OffsetDateTime::now_utc(),
        )?;
        let outcome = resolution.outcome();
        let resolution_id = resolution.id();
        let attendees = resolution.attendance().len();
        let quorum_required = resolution.quorum_required();
        self.bac_resolutions.insert(resolution_id, resolution);
        self.bac_resolutions_by_version
            .entry(version_id)
            .or_default()
            .push(resolution_id);
        self.audit.append(
            "bac.recommended",
            Some(presiding_actor.user_id()),
            "document_version",
            version_id.to_string(),
            OffsetDateTime::now_utc(),
            details([
                ("bac_resolution_id", resolution_id.to_string()),
                ("content_hash", context.content_hash.to_string()),
                ("outcome", format!("{outcome:?}")),
                ("attendees", attendees.to_string()),
                ("quorum_required", quorum_required.to_string()),
            ]),
        );
        Ok(resolution_id)
    }

    /// HoPE approval of an APP version after an active BAC recommendation.
    pub fn hope_approve_app(
        &mut self,
        version_id: DocumentVersionId,
        approver: &Actor,
        authority_ref: impl Into<String>,
        grounds: Option<String>,
    ) -> Result<ApprovalId, DomainError> {
        let context = self.version_context(version_id)?;
        if context.document_type != DocumentType::AnnualProcurementPlan {
            return Err(DomainError::rule(
                "this approval action is only for an Annual Procurement Plan",
            ));
        }
        if approver.role() != ActorRole::Hope {
            return Err(DomainError::unauthorized(
                "the APP must be approved by the Head of the Procuring Entity",
            ));
        }
        let authority_ref = require_authority(authority_ref)?;
        self.ensure_status(version_id, VersionStatus::InReview)?;
        let recommendation = self.active_bac_resolution(version_id).ok_or_else(|| {
            DomainError::missing(
                "an active BAC recommendation for the exact APP version and content hash",
            )
        })?;
        if !recommendation.is_recommended() {
            return Err(DomainError::missing(
                "the BAC recommendation does not recommend approval",
            ));
        }
        if !recommendation.binds(version_id, &context.content_hash) {
            return Err(DomainError::ContentHashMismatch);
        }
        let bac_resolution_id = recommendation.id();
        let approval = Approval::new(
            ApprovalId::new(),
            version_id,
            context.content_hash.clone(),
            approver.clone(),
            ApprovalDecision::Approved,
            OffsetDateTime::now_utc(),
            authority_ref,
            grounds,
        );
        self.versions
            .get_mut(&version_id)
            .expect("version exists")
            .transition_to(&[VersionStatus::InReview], VersionStatus::Approved)?;
        self.approvals.insert(approval.id(), approval.clone());
        self.approvals_by_version
            .entry(version_id)
            .or_default()
            .push(approval.id());
        self.audit.append(
            "app.approved",
            Some(approver.user_id()),
            "document_version",
            version_id.to_string(),
            OffsetDateTime::now_utc(),
            details([
                ("approval_id", approval.id().to_string()),
                ("content_hash", context.content_hash.to_string()),
                ("bac_resolution_id", bac_resolution_id.to_string()),
            ]),
        );
        Ok(approval.id())
    }

    /// Request a signature. The signature method must be in the PE-approved
    /// matrix for this document class and signer role.
    #[allow(clippy::too_many_arguments)]
    pub fn request_signature(
        &mut self,
        version_id: DocumentVersionId,
        signer: &Actor,
        purpose: SignaturePurpose,
        method: SignatureMethod,
        declaration: impl Into<String>,
        deadline: OffsetDateTime,
    ) -> Result<SignatureRequestId, DomainError> {
        let context = self.version_context(version_id)?;
        self.ensure_status(version_id, VersionStatus::Approved)?;
        let rule = self
            .signature_policy
            .rule_for(context.document_type)
            .ok_or_else(|| {
                DomainError::missing(format!(
                    "an approved signature-method matrix for document class {}",
                    context.document_type.label()
                ))
            })?;
        if !rule.requires_role(signer.role()) {
            return Err(DomainError::unauthorized(format!(
                "role {} is not a required signer for {}",
                signer.role().label(),
                context.document_type.label()
            )));
        }
        if !rule.allows_method(method) {
            return Err(DomainError::rule(format!(
                "signature method {} is not approved for {}",
                method.label(),
                context.document_type.label()
            )));
        }
        if self
            .signatures_for_version(version_id)
            .iter()
            .any(|signature| signature.signer_role() == signer.role() && signature.is_valid())
        {
            return Err(DomainError::duplicate(
                "this signer role has already produced a valid signature",
            ));
        }
        if self
            .signature_requests_for_version(version_id)
            .iter()
            .any(|request| request.signer_role() == signer.role() && request.is_open())
        {
            return Err(DomainError::duplicate(
                "an open signature request already exists for this signer role",
            ));
        }
        let now = OffsetDateTime::now_utc();
        let request = SignatureRequest::new(
            SignatureRequestId::new(),
            version_id,
            context.content_hash.clone(),
            signer.clone(),
            purpose,
            method,
            declaration.into(),
            now,
            deadline,
        )?;
        let request_id = request.id();
        self.signature_requests.insert(request_id, request);
        self.signature_requests_by_version
            .entry(version_id)
            .or_default()
            .push(request_id);
        self.audit.append(
            "signature.requested",
            Some(signer.user_id()),
            "document_version",
            version_id.to_string(),
            now,
            details([
                ("signature_request_id", request_id.to_string()),
                ("content_hash", context.content_hash.to_string()),
                ("method", method.label().to_owned()),
                ("purpose", purpose.label().to_owned()),
            ]),
        );
        Ok(request_id)
    }

    /// Complete a signature request with provider evidence.
    ///
    /// A signature does not imply approval. The version must already be
    /// Approved, and only a valid signature can advance it to Signed.
    #[allow(clippy::too_many_arguments)]
    pub fn complete_signature(
        &mut self,
        request_id: SignatureRequestId,
        signed_at: OffsetDateTime,
        provider_transaction: impl Into<String>,
        credential_ref: impl Into<String>,
        signed_artifact_hash: ContentHash,
        validation: SignatureValidation,
    ) -> Result<SignatureId, DomainError> {
        let request = self
            .signature_requests
            .get(&request_id)
            .cloned()
            .ok_or_else(|| DomainError::not_found("signature request", request_id))?;
        if request.status() != SignatureRequestStatus::Requested {
            return Err(DomainError::invalid_state(
                "signature request",
                request.status().label(),
                "requested",
            ));
        }
        if signed_at < request.requested_at() {
            return Err(DomainError::rule(
                "a signature cannot be completed before its request",
            ));
        }
        if signed_at > request.deadline() {
            self.signature_requests
                .get_mut(&request_id)
                .expect("request exists")
                .set_status(SignatureRequestStatus::Expired);
            self.audit.append(
                "signature.expired",
                Some(request.signer()),
                "signature_request",
                request_id.to_string(),
                signed_at,
                details([("reason", "deadline passed before completion".to_owned())]),
            );
            return Err(DomainError::invalid_state(
                "signature request",
                "expired",
                "requested",
            ));
        }
        if signed_artifact_hash != request.content_hash().clone() {
            self.signature_requests
                .get_mut(&request_id)
                .expect("request exists")
                .set_status(SignatureRequestStatus::VerificationFailed);
            return Err(DomainError::ContentHashMismatch);
        }
        if validation.is_valid()
            && self
                .signatures_for_version(request.version_id())
                .iter()
                .any(|signature| {
                    signature.signer_role() == request.signer_role() && signature.is_valid()
                })
        {
            return Err(DomainError::duplicate(
                "a valid signature already exists for this signer role",
            ));
        }
        let signature = Signature::new(
            SignatureId::new(),
            &request,
            signed_at,
            provider_transaction.into(),
            credential_ref.into(),
            signed_artifact_hash,
            validation,
        )?;
        let signature_id = signature.id();
        self.signatures.insert(signature_id, signature);
        self.signatures_by_version
            .entry(request.version_id())
            .or_default()
            .push(signature_id);
        let request_status = if validation.is_valid() {
            SignatureRequestStatus::Signed
        } else {
            SignatureRequestStatus::VerificationFailed
        };
        self.signature_requests
            .get_mut(&request_id)
            .expect("request exists")
            .set_status(request_status);
        if validation.is_valid() {
            self.audit.append(
                "signature.completed",
                Some(request.signer()),
                "document_version",
                request.version_id().to_string(),
                signed_at,
                details([
                    ("signature_id", signature_id.to_string()),
                    ("signature_request_id", request_id.to_string()),
                    ("signer_role", request.signer_role().label().to_owned()),
                    ("method", request.method().label().to_owned()),
                    ("content_hash", request.content_hash().to_string()),
                ]),
            );
            if self.signatures_complete(request.version_id())? {
                self.versions
                    .get_mut(&request.version_id())
                    .expect("version exists")
                    .transition_to(&[VersionStatus::Approved], VersionStatus::Signed)?;
                self.audit.append(
                    "version.signed",
                    Some(request.signer()),
                    "document_version",
                    request.version_id().to_string(),
                    signed_at,
                    details([("content_hash", request.content_hash().to_string())]),
                );
            }
        } else {
            self.audit.append(
                "signature.failed",
                Some(request.signer()),
                "signature_request",
                request_id.to_string(),
                signed_at,
                details([("validation", validation.label().to_owned())]),
            );
        }
        Ok(signature_id)
    }

    /// Issue an approved and, where required, signed version.
    #[allow(clippy::too_many_arguments)]
    pub fn issue(
        &mut self,
        version_id: DocumentVersionId,
        issuer: &Actor,
        authority_ref: impl Into<String>,
        artifacts: Vec<IssuanceArtifact>,
        recipients: Vec<IssuanceRecipient>,
    ) -> Result<IssuanceId, DomainError> {
        let context = self.version_context(version_id)?;
        if !matches!(
            issuer.role(),
            ActorRole::Hope | ActorRole::Secretariat | ActorRole::RecordsOfficer
        ) {
            return Err(DomainError::unauthorized(
                "only the HoPE, Secretariat, or Records Officer may issue a document",
            ));
        }
        let requires_signature = self
            .signature_policy
            .requires_signature(context.document_type);
        if requires_signature {
            if context.status != VersionStatus::Signed {
                return Err(DomainError::missing(format!(
                    "the configured signature requirements are complete (current state: {})",
                    context.status.label()
                )));
            }
            if !self.signatures_complete(version_id)? {
                return Err(DomainError::missing(
                    "all required signer roles have a valid signature",
                ));
            }
        } else if !matches!(
            context.status,
            VersionStatus::Approved | VersionStatus::Signed
        ) {
            return Err(DomainError::invalid_state(
                "document version",
                context.status.label(),
                "approved or signed",
            ));
        }
        if context.document_type == DocumentType::AnnualProcurementPlan {
            let recommendation = self.active_bac_resolution(version_id).ok_or_else(|| {
                DomainError::missing(
                    "an active BAC recommendation for the exact APP version and content hash",
                )
            })?;
            if !recommendation.is_recommended()
                || !recommendation.binds(version_id, &context.content_hash)
            {
                return Err(DomainError::missing(
                    "the active BAC recommendation recommends this exact APP version",
                ));
            }
            let hope_approval = self.active_hope_approval(version_id).ok_or_else(|| {
                DomainError::missing(
                    "an active HoPE approval for the exact APP version and content hash",
                )
            })?;
            if !hope_approval.binds(version_id, &context.content_hash) {
                return Err(DomainError::ContentHashMismatch);
            }
        }
        let authority_ref = require_authority(authority_ref)?;
        if artifacts.is_empty() {
            return Err(DomainError::rule("issuance requires at least one artifact"));
        }
        if recipients.is_empty() {
            return Err(DomainError::rule(
                "issuance requires at least one recipient",
            ));
        }
        let mut recipient_ids = BTreeSet::new();
        for recipient in &recipients {
            if !recipient_ids.insert(recipient.recipient().id()) {
                return Err(DomainError::duplicate(format!(
                    "recipient {} appears more than once in the issuance",
                    recipient.recipient().name()
                )));
            }
        }
        let issuance_id = IssuanceId::new();
        let mut distribution_ids = Vec::with_capacity(recipients.len());
        let mut distributions = Vec::with_capacity(recipients.len());
        for recipient in &recipients {
            let distribution_id = DistributionId::new();
            distribution_ids.push(distribution_id);
            distributions.push(Distribution::new(
                distribution_id,
                issuance_id,
                version_id,
                recipient.recipient().clone(),
                recipient.channel(),
                recipient.acknowledgement_required(),
            ));
        }
        let now = OffsetDateTime::now_utc();
        let issuance = Issuance::new(
            issuance_id,
            version_id,
            context.content_hash.clone(),
            issuer.clone(),
            authority_ref,
            now,
            artifacts,
            recipients,
            distribution_ids.clone(),
        )?;
        let manifest_hash = issuance.manifest_hash().clone();
        let distribution_count = distributions.len();
        self.issuances.insert(issuance_id, issuance);
        self.issuances_by_version
            .entry(version_id)
            .or_default()
            .push(issuance_id);
        for distribution in distributions {
            self.distributions.insert(distribution.id(), distribution);
        }
        self.versions
            .get_mut(&version_id)
            .expect("version exists")
            .transition_to(
                &[VersionStatus::Approved, VersionStatus::Signed],
                VersionStatus::Issued,
            )?;
        self.audit.append(
            "document.issued",
            Some(issuer.user_id()),
            "document_version",
            version_id.to_string(),
            now,
            details([
                ("issuance_id", issuance_id.to_string()),
                ("manifest_hash", manifest_hash.to_string()),
                ("content_hash", context.content_hash.to_string()),
                ("distributions", distribution_count.to_string()),
            ]),
        );
        Ok(issuance_id)
    }

    /// Record one delivery attempt against a distribution.
    pub fn record_delivery(
        &mut self,
        distribution_id: DistributionId,
        channel_ref: impl Into<String>,
        result: DeliveryResult,
        attempted_at: OffsetDateTime,
        actor: &Actor,
    ) -> Result<(), DomainError> {
        let attempt = DeliveryAttempt::new(attempted_at, channel_ref, result)?;
        let distribution = self
            .distributions
            .get_mut(&distribution_id)
            .ok_or_else(|| DomainError::not_found("distribution", distribution_id))?;
        let result_label = attempt.result().label().to_owned();
        let issuance_id = distribution.issuance_id();
        let version_id = distribution.version_id();
        distribution.record_attempt(attempt)?;
        self.audit.append(
            "distribution.delivery_recorded",
            Some(actor.user_id()),
            "distribution",
            distribution_id.to_string(),
            attempted_at,
            details([
                ("issuance_id", issuance_id.to_string()),
                ("version_id", version_id.to_string()),
                ("result", result_label),
            ]),
        );
        Ok(())
    }

    /// Record an acknowledgement for a delivered distribution. The exact
    /// issued artifact hash is required.
    #[allow(clippy::too_many_arguments)]
    pub fn acknowledge(
        &mut self,
        distribution_id: DistributionId,
        acknowledged_by: RecipientId,
        artifact_hash: ContentHash,
        method: AcknowledgementMethod,
        declaration: Option<String>,
        acknowledged_at: OffsetDateTime,
    ) -> Result<crate::shared::AcknowledgementId, DomainError> {
        let distribution = self
            .distributions
            .get(&distribution_id)
            .cloned()
            .ok_or_else(|| DomainError::not_found("distribution", distribution_id))?;
        if distribution.status() != DistributionStatus::Delivered {
            return Err(DomainError::invalid_state(
                "distribution",
                distribution.status().label(),
                "delivered",
            ));
        }
        if distribution.recipient().id() != acknowledged_by {
            return Err(DomainError::unauthorized(
                "the acknowledgement must be made by the intended recipient",
            ));
        }
        let issuance = self
            .issuances
            .get(&distribution.issuance_id())
            .ok_or_else(|| DomainError::not_found("issuance", distribution.issuance_id()))?;
        if !issuance.is_active() {
            return Err(DomainError::invalid_state(
                "issuance",
                issuance.status().label(),
                "active",
            ));
        }
        if !issuance.has_artifact_hash(&artifact_hash) {
            return Err(DomainError::ContentHashMismatch);
        }
        let artifact_hash_label = artifact_hash.to_string();
        let acknowledgement = Acknowledgement::new(
            crate::shared::AcknowledgementId::new(),
            distribution_id,
            distribution.issuance_id(),
            distribution.version_id(),
            artifact_hash,
            acknowledged_by,
            acknowledged_at,
            method,
            declaration,
        )?;
        let acknowledgement_id = acknowledgement.id();
        self.acknowledgements
            .insert(acknowledgement_id, acknowledgement);
        self.distributions
            .get_mut(&distribution_id)
            .expect("distribution exists")
            .mark_acknowledged()?;
        self.audit.append(
            "acknowledgement.recorded",
            None,
            "distribution",
            distribution_id.to_string(),
            acknowledged_at,
            details([
                ("acknowledgement_id", acknowledgement_id.to_string()),
                ("recipient_id", acknowledged_by.to_string()),
                ("artifact_hash", artifact_hash_label),
            ]),
        );
        Ok(acknowledgement_id)
    }

    /// Create a corrected draft version after approval, signature, or
    /// issuance. The old records are preserved but explicitly superseded or
    /// invalidated.
    #[allow(clippy::too_many_arguments)]
    pub fn create_correction(
        &mut self,
        version_id: DocumentVersionId,
        content: CanonicalContent,
        author: &Actor,
        template: TemplateRef,
        schema: SchemaRef,
        rule_pack: RulePackRef,
    ) -> Result<DocumentVersionId, DomainError> {
        let old = self
            .versions
            .get(&version_id)
            .cloned()
            .ok_or_else(|| DomainError::not_found("document version", version_id))?;
        if !matches!(
            old.status(),
            VersionStatus::Approved | VersionStatus::Signed | VersionStatus::Issued
        ) {
            return Err(DomainError::invalid_state(
                "document version",
                old.status().label(),
                "approved, signed, or issued",
            ));
        }
        let document_id = old.document_id();
        let new_version_id = DocumentVersionId::new();
        let now = OffsetDateTime::now_utc();
        let document = self
            .documents
            .get_mut(&document_id)
            .ok_or_else(|| DomainError::not_found("document", document_id))?;
        let version_number = document.register_version(new_version_id)?;
        let content_hash = content.hash();
        let new_version = DocumentVersion::new(
            new_version_id,
            document_id,
            version_number,
            content,
            template,
            schema,
            author.user_id(),
            now,
            rule_pack,
            Some(version_id),
            Some(version_id),
        );
        self.versions.insert(new_version_id, new_version);
        self.versions
            .get_mut(&version_id)
            .expect("old version exists")
            .set_status(VersionStatus::Superseded);

        let mut superseded_approvals = 0usize;
        if let Some(ids) = self.approvals_by_version.get(&version_id).cloned() {
            for id in ids {
                if let Some(approval) = self.approvals.get_mut(&id) {
                    if approval.is_active() {
                        approval.supersede();
                        superseded_approvals += 1;
                    }
                }
            }
        }
        let mut superseded_resolutions = 0usize;
        if let Some(ids) = self.bac_resolutions_by_version.get(&version_id).cloned() {
            for id in ids {
                if let Some(resolution) = self.bac_resolutions.get_mut(&id) {
                    if resolution.is_active() {
                        resolution.supersede();
                        superseded_resolutions += 1;
                    }
                }
            }
        }
        let mut superseded_requests = 0usize;
        if let Some(ids) = self.signature_requests_by_version.get(&version_id).cloned() {
            for id in ids {
                if let Some(request) = self.signature_requests.get_mut(&id) {
                    if request.status() != SignatureRequestStatus::Superseded {
                        request.set_status(SignatureRequestStatus::Superseded);
                        superseded_requests += 1;
                    }
                }
            }
        }
        let mut invalidated_signatures = 0usize;
        if let Some(ids) = self.signatures_by_version.get(&version_id).cloned() {
            for id in ids {
                if let Some(signature) = self.signatures.get_mut(&id) {
                    if signature.is_valid() {
                        signature.invalidate();
                        invalidated_signatures += 1;
                    }
                }
            }
        }
        let mut superseded_issuances = 0usize;
        if let Some(ids) = self.issuances_by_version.get(&version_id).cloned() {
            for id in ids {
                if let Some(issuance) = self.issuances.get_mut(&id) {
                    if issuance.is_active() {
                        issuance.supersede();
                        superseded_issuances += 1;
                        for distribution_id in issuance.distribution_ids().to_vec() {
                            if let Some(distribution) = self.distributions.get_mut(&distribution_id)
                            {
                                distribution.supersede();
                            }
                        }
                    }
                }
            }
        }
        let mut invalidated_acknowledgements = 0usize;
        for acknowledgement in self.acknowledgements.values_mut() {
            if acknowledgement.version_id() == version_id {
                acknowledgement.invalidate();
                invalidated_acknowledgements += 1;
            }
        }
        self.audit.append(
            "version.correction_created",
            Some(author.user_id()),
            "document_version",
            new_version_id.to_string(),
            now,
            details([
                ("document_id", document_id.to_string()),
                ("supersedes", version_id.to_string()),
                ("content_hash", content_hash.to_string()),
            ]),
        );
        self.audit.append(
            "version.superseded",
            Some(author.user_id()),
            "document_version",
            version_id.to_string(),
            now,
            details([
                ("superseded_approvals", superseded_approvals.to_string()),
                (
                    "superseded_bac_resolutions",
                    superseded_resolutions.to_string(),
                ),
                (
                    "superseded_signature_requests",
                    superseded_requests.to_string(),
                ),
                ("invalidated_signatures", invalidated_signatures.to_string()),
                ("superseded_issuances", superseded_issuances.to_string()),
                (
                    "invalidated_acknowledgements",
                    invalidated_acknowledgements.to_string(),
                ),
            ]),
        );
        Ok(new_version_id)
    }

    fn version_context(
        &self,
        version_id: DocumentVersionId,
    ) -> Result<VersionContext, DomainError> {
        let version = self
            .versions
            .get(&version_id)
            .ok_or_else(|| DomainError::not_found("document version", version_id))?;
        let document = self
            .documents
            .get(&version.document_id())
            .ok_or_else(|| DomainError::not_found("document", version.document_id()))?;
        Ok(VersionContext {
            document_id: document.id(),
            document_type: document.document_type(),
            status: version.status(),
            content_hash: version.content_hash().clone(),
        })
    }

    fn ensure_status(
        &self,
        version_id: DocumentVersionId,
        expected: VersionStatus,
    ) -> Result<(), DomainError> {
        let context = self.version_context(version_id)?;
        if context.status == expected {
            Ok(())
        } else {
            Err(DomainError::invalid_state(
                "document version",
                context.status.label(),
                expected.label(),
            ))
        }
    }

    fn active_bac_resolution(&self, version_id: DocumentVersionId) -> Option<&BacResolution> {
        self.bac_resolutions_by_version
            .get(&version_id)?
            .iter()
            .filter_map(|id| self.bac_resolutions.get(id))
            .find(|resolution| {
                resolution.is_active() && resolution.status() == BacResolutionStatus::Active
            })
    }

    fn active_hope_approval(&self, version_id: DocumentVersionId) -> Option<&Approval> {
        self.approvals_by_version
            .get(&version_id)?
            .iter()
            .filter_map(|id| self.approvals.get(id))
            .find(|approval| {
                approval.is_active()
                    && approval.approver_role() == ActorRole::Hope
                    && approval.decision() == ApprovalDecision::Approved
            })
    }

    fn signatures_complete(&self, version_id: DocumentVersionId) -> Result<bool, DomainError> {
        let context = self.version_context(version_id)?;
        let Some(rule) = self.signature_policy.rule_for(context.document_type) else {
            return Ok(true);
        };
        let signatures = self.signatures_for_version(version_id);
        Ok(rule.required_roles().iter().all(|role| {
            signatures
                .iter()
                .any(|signature| signature.signer_role() == *role && signature.is_valid())
        }))
    }
}

fn details<const N: usize>(pairs: [(&str, String); N]) -> BTreeMap<String, String> {
    pairs
        .into_iter()
        .map(|(key, value)| (key.to_owned(), value))
        .collect()
}

fn require_authority(authority_ref: impl Into<String>) -> Result<String, DomainError> {
    let authority_ref = authority_ref.into();
    if authority_ref.trim().is_empty() {
        Err(DomainError::rule("authority reference must not be empty"))
    } else {
        Ok(authority_ref)
    }
}

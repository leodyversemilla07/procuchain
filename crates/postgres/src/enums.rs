//! Text encodings for enum-like columns.
//!
//! The database stores stable labels, not Rust variant names.

use procuchain_issuance::{
    AcknowledgementMethod, AcknowledgementStatus, DeliveryResult, DistributionChannel,
    DistributionStatus, IssuanceStatus, RecipientKind,
};
use procuchain_records::{DocumentType, VersionStatus};
use procuchain_shared::ActorRole;
use procuchain_signatures::{
    SignatureMethod, SignaturePurpose, SignatureRequestStatus, SignatureStatus, SignatureValidation,
};
use procuchain_workflow::{
    ApprovalDecision, ApprovalStatus, BacOutcome, BacResolutionStatus, BacSeat, BacVote,
};

use crate::StoreError;

macro_rules! parse_enum {
    ($name:ident, $ty:ty, { $($text:literal => $variant:expr),+ $(,)? }) => {
        pub(crate) fn $name(value: &str) -> Result<$ty, StoreError> {
            match value {
                $($text => Ok($variant),)+
                other => Err(StoreError::invalid_value(format!(
                    "unknown {} value: {other}",
                    stringify!($ty)
                ))),
            }
        }
    };
}

parse_enum!(document_type, DocumentType, {
    "ppmp" => DocumentType::Ppmp,
    "annual_procurement_plan" => DocumentType::AnnualProcurementPlan,
    "bac_resolution" => DocumentType::BacResolution,
    "contract" => DocumentType::Contract,
    "other" => DocumentType::Other,
});

parse_enum!(version_status, VersionStatus, {
    "draft" => VersionStatus::Draft,
    "in_review" => VersionStatus::InReview,
    "approved" => VersionStatus::Approved,
    "signed" => VersionStatus::Signed,
    "issued" => VersionStatus::Issued,
    "rejected" => VersionStatus::Rejected,
    "superseded" => VersionStatus::Superseded,
    "withdrawn" => VersionStatus::Withdrawn,
    "archived" => VersionStatus::Archived,
});

parse_enum!(actor_role, ActorRole, {
    "end_user" => ActorRole::EndUser,
    "end_user_head" => ActorRole::EndUserHead,
    "bac_chairperson" => ActorRole::BacChairperson,
    "bac_vice_chairperson" => ActorRole::BacViceChairperson,
    "bac_member" => ActorRole::BacMember,
    "hope" => ActorRole::Hope,
    "secretariat" => ActorRole::Secretariat,
    "records_officer" => ActorRole::RecordsOfficer,
    "system" => ActorRole::System,
});

parse_enum!(approval_decision, ApprovalDecision, {
    "approved" => ApprovalDecision::Approved,
    "rejected" => ApprovalDecision::Rejected,
    "returned" => ApprovalDecision::Returned,
    "abstained" => ApprovalDecision::Abstained,
});

parse_enum!(approval_status, ApprovalStatus, {
    "active" => ApprovalStatus::Active,
    "superseded" => ApprovalStatus::Superseded,
    "invalidated" => ApprovalStatus::Invalidated,
    "withdrawn" => ApprovalStatus::Withdrawn,
});

parse_enum!(bac_seat, BacSeat, {
    "chairperson" => BacSeat::Chairperson,
    "vice_chairperson" => BacSeat::ViceChairperson,
    "member" => BacSeat::Member,
});

pub(crate) const fn bac_seat_label(value: BacSeat) -> &'static str {
    match value {
        BacSeat::Chairperson => "chairperson",
        BacSeat::ViceChairperson => "vice_chairperson",
        BacSeat::Member => "member",
    }
}

parse_enum!(bac_vote, BacVote, {
    "recommend" => BacVote::Recommend,
    "do_not_recommend" => BacVote::DoNotRecommend,
    "abstain" => BacVote::Abstain,
});

pub(crate) const fn bac_vote_label(value: BacVote) -> &'static str {
    match value {
        BacVote::Recommend => "recommend",
        BacVote::DoNotRecommend => "do_not_recommend",
        BacVote::Abstain => "abstain",
    }
}

parse_enum!(bac_outcome, BacOutcome, {
    "recommended" => BacOutcome::Recommended,
    "not_recommended" => BacOutcome::NotRecommended,
    "no_decision" => BacOutcome::NoDecision,
});

pub(crate) const fn bac_outcome_label(value: BacOutcome) -> &'static str {
    match value {
        BacOutcome::Recommended => "recommended",
        BacOutcome::NotRecommended => "not_recommended",
        BacOutcome::NoDecision => "no_decision",
    }
}

parse_enum!(bac_resolution_status, BacResolutionStatus, {
    "active" => BacResolutionStatus::Active,
    "superseded" => BacResolutionStatus::Superseded,
    "invalidated" => BacResolutionStatus::Invalidated,
});

pub(crate) const fn bac_resolution_status_label(value: BacResolutionStatus) -> &'static str {
    match value {
        BacResolutionStatus::Active => "active",
        BacResolutionStatus::Superseded => "superseded",
        BacResolutionStatus::Invalidated => "invalidated",
    }
}

parse_enum!(signature_method, SignatureMethod, {
    "wet_signature" => SignatureMethod::WetSignature,
    "advanced_electronic_signature" => SignatureMethod::AdvancedElectronicSignature,
    "qualified_electronic_signature" => SignatureMethod::QualifiedElectronicSignature,
});

parse_enum!(signature_purpose, SignaturePurpose, {
    "execution" => SignaturePurpose::Execution,
    "attestation" => SignaturePurpose::Attestation,
    "approval" => SignaturePurpose::Approval,
});

parse_enum!(signature_request_status, SignatureRequestStatus, {
    "requested" => SignatureRequestStatus::Requested,
    "signed" => SignatureRequestStatus::Signed,
    "declined" => SignatureRequestStatus::Declined,
    "verification_failed" => SignatureRequestStatus::VerificationFailed,
    "cancelled" => SignatureRequestStatus::Cancelled,
    "expired" => SignatureRequestStatus::Expired,
    "superseded" => SignatureRequestStatus::Superseded,
});

parse_enum!(signature_validation, SignatureValidation, {
    "valid" => SignatureValidation::Valid,
    "verification_failed" => SignatureValidation::VerificationFailed,
    "revoked" => SignatureValidation::Revoked,
    "expired" => SignatureValidation::Expired,
    "signer_mismatch" => SignatureValidation::SignerMismatch,
});

parse_enum!(signature_status, SignatureStatus, {
    "valid" => SignatureStatus::Valid,
    "invalidated" => SignatureStatus::Invalidated,
    "failed" => SignatureStatus::Failed,
});

parse_enum!(recipient_kind, RecipientKind, {
    "internal_office" => RecipientKind::InternalOffice,
    "external_party" => RecipientKind::ExternalParty,
    "public" => RecipientKind::Public,
});

pub(crate) const fn recipient_kind_label(value: RecipientKind) -> &'static str {
    match value {
        RecipientKind::InternalOffice => "internal_office",
        RecipientKind::ExternalParty => "external_party",
        RecipientKind::Public => "public",
    }
}

parse_enum!(distribution_channel, DistributionChannel, {
    "portal" => DistributionChannel::Portal,
    "email" => DistributionChannel::Email,
    "printed_copy" => DistributionChannel::PrintedCopy,
    "pe_website" => DistributionChannel::PeWebsite,
    "philgeps" => DistributionChannel::PhilGeps,
});

parse_enum!(issuance_status, IssuanceStatus, {
    "active" => IssuanceStatus::Active,
    "superseded" => IssuanceStatus::Superseded,
    "withdrawn" => IssuanceStatus::Withdrawn,
});

parse_enum!(distribution_status, DistributionStatus, {
    "planned" => DistributionStatus::Planned,
    "delivered" => DistributionStatus::Delivered,
    "failed" => DistributionStatus::Failed,
    "acknowledged" => DistributionStatus::Acknowledged,
    "superseded" => DistributionStatus::Superseded,
});

pub(crate) fn parse_acknowledgement_method(
    method: &str,
    other: Option<&str>,
) -> Result<AcknowledgementMethod, StoreError> {
    match method {
        "portal" => Ok(AcknowledgementMethod::Portal),
        "email" => Ok(AcknowledgementMethod::Email),
        "signed_receipt" => Ok(AcknowledgementMethod::SignedReceipt),
        "other" => other
            .filter(|value| !value.trim().is_empty())
            .map(|value| AcknowledgementMethod::Other(value.to_owned()))
            .ok_or_else(|| {
                StoreError::invalid_value("acknowledgement method 'other' lacks a label")
            }),
        other => Err(StoreError::invalid_value(format!(
            "unknown acknowledgement method: {other}"
        ))),
    }
}

parse_enum!(acknowledgement_status, AcknowledgementStatus, {
    "active" => AcknowledgementStatus::Active,
    "invalidated" => AcknowledgementStatus::Invalidated,
});

pub(crate) fn delivery_result_parts(value: &DeliveryResult) -> (&'static str, Option<&str>) {
    match value {
        DeliveryResult::Delivered => ("delivered", None),
        DeliveryResult::Failed { reason } => ("failed", Some(reason)),
    }
}

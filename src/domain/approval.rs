use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use crate::shared::{Actor, ActorRole, ApprovalId, ContentHash, DocumentVersionId, UserId};

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum ApprovalDecision {
    Approved,
    Rejected,
    Returned,
    Abstained,
}

impl ApprovalDecision {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Approved => "approved",
            Self::Rejected => "rejected",
            Self::Returned => "returned",
            Self::Abstained => "abstained",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum ApprovalStatus {
    Active,
    Superseded,
    Invalidated,
    Withdrawn,
}

impl ApprovalStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Active => "active",
            Self::Superseded => "superseded",
            Self::Invalidated => "invalidated",
            Self::Withdrawn => "withdrawn",
        }
    }
}

/// A recorded approval decision, bound to one exact document version and
/// content hash. Approval is not a signature and never creates one.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Approval {
    id: ApprovalId,
    version_id: DocumentVersionId,
    content_hash: ContentHash,
    approver: UserId,
    approver_role: ActorRole,
    approver_name: String,
    decision: ApprovalDecision,
    decided_at: OffsetDateTime,
    authority_ref: String,
    grounds: Option<String>,
    status: ApprovalStatus,
}

impl Approval {
    #[allow(clippy::too_many_arguments)]
    pub(crate) fn new(
        id: ApprovalId,
        version_id: DocumentVersionId,
        content_hash: ContentHash,
        approver: Actor,
        decision: ApprovalDecision,
        decided_at: OffsetDateTime,
        authority_ref: String,
        grounds: Option<String>,
    ) -> Self {
        Self {
            id,
            version_id,
            content_hash,
            approver: approver.user_id(),
            approver_role: approver.role(),
            approver_name: approver.display_name().to_owned(),
            decision,
            decided_at,
            authority_ref,
            grounds,
            status: ApprovalStatus::Active,
        }
    }

    pub fn id(&self) -> ApprovalId {
        self.id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn content_hash(&self) -> &ContentHash {
        &self.content_hash
    }

    pub fn approver(&self) -> UserId {
        self.approver
    }

    pub fn approver_role(&self) -> ActorRole {
        self.approver_role
    }

    pub fn approver_name(&self) -> &str {
        &self.approver_name
    }

    pub fn decision(&self) -> ApprovalDecision {
        self.decision
    }

    pub fn decided_at(&self) -> OffsetDateTime {
        self.decided_at
    }

    pub fn authority_ref(&self) -> &str {
        &self.authority_ref
    }

    pub fn grounds(&self) -> Option<&str> {
        self.grounds.as_deref()
    }

    pub fn status(&self) -> ApprovalStatus {
        self.status
    }

    pub fn is_active(&self) -> bool {
        self.status == ApprovalStatus::Active
    }

    pub fn binds(&self, version_id: DocumentVersionId, content_hash: &ContentHash) -> bool {
        self.version_id == version_id && &self.content_hash == content_hash
    }

    pub(crate) fn supersede(&mut self) {
        self.status = ApprovalStatus::Superseded;
    }
}

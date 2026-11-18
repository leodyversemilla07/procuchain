use std::collections::{BTreeMap, BTreeSet};

use serde::{Deserialize, Serialize};
use time::OffsetDateTime;

use procuchain_shared::{
    Actor, ActorRole, BacResolutionId, CommitteeId, ContentHash, DocumentVersionId, DomainError,
    UserId,
};

pub type BacVotes = BTreeMap<UserId, BacVote>;

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum BacSeat {
    Chairperson,
    ViceChairperson,
    Member,
}

impl BacSeat {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Chairperson => "chairperson",
            Self::ViceChairperson => "vice_chairperson",
            Self::Member => "member",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum BacVote {
    Recommend,
    DoNotRecommend,
    Abstain,
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum BacOutcome {
    Recommended,
    NotRecommended,
    /// No valid decision was reached because no option attained a majority of
    /// the members present, or a tie was left unbroken.
    NoDecision,
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum BacResolutionStatus {
    Active,
    Superseded,
    Invalidated,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct BacMemberSnapshot {
    user_id: UserId,
    seat: BacSeat,
    display_name: String,
}

impl BacMemberSnapshot {
    pub fn new(user_id: UserId, seat: BacSeat, display_name: impl Into<String>) -> Self {
        Self {
            user_id,
            seat,
            display_name: display_name.into(),
        }
    }

    pub fn user_id(&self) -> UserId {
        self.user_id
    }

    pub fn seat(&self) -> BacSeat {
        self.seat
    }

    pub fn display_name(&self) -> &str {
        &self.display_name
    }
}

/// Frozen roster used for one BAC action.
///
/// IRR 42.2 computes quorum from the total composition designated by the
/// HoPE and requires the Chairperson or Vice-Chairperson to be present.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct BacMembershipSnapshot {
    committee_id: CommitteeId,
    members: Vec<BacMemberSnapshot>,
}

impl BacMembershipSnapshot {
    pub fn new(
        committee_id: CommitteeId,
        members: Vec<BacMemberSnapshot>,
    ) -> Result<Self, DomainError> {
        if !matches!(members.len(), 5 | 7) {
            return Err(DomainError::rule(
                "BAC composition must contain exactly five or seven members",
            ));
        }
        let mut ids = BTreeSet::new();
        let mut chairs = 0usize;
        let mut vice_chairs = 0usize;
        for member in &members {
            if !ids.insert(member.user_id) {
                return Err(DomainError::duplicate(format!(
                    "BAC membership contains user {} more than once",
                    member.user_id
                )));
            }
            match member.seat {
                BacSeat::Chairperson => chairs += 1,
                BacSeat::ViceChairperson => vice_chairs += 1,
                BacSeat::Member => {}
            }
        }
        if chairs != 1 {
            return Err(DomainError::rule(
                "BAC composition must designate exactly one Chairperson",
            ));
        }
        if vice_chairs != 1 {
            return Err(DomainError::rule(
                "BAC composition must designate exactly one Vice-Chairperson",
            ));
        }
        Ok(Self {
            committee_id,
            members,
        })
    }

    pub fn committee_id(&self) -> CommitteeId {
        self.committee_id
    }

    pub fn members(&self) -> &[BacMemberSnapshot] {
        &self.members
    }

    pub fn designated_total(&self) -> usize {
        self.members.len()
    }

    pub fn quorum_required(&self) -> usize {
        self.members.len() / 2 + 1
    }

    pub fn member(&self, user_id: UserId) -> Option<&BacMemberSnapshot> {
        self.members.iter().find(|member| member.user_id == user_id)
    }

    pub fn chair(&self) -> Option<&BacMemberSnapshot> {
        self.members
            .iter()
            .find(|member| member.seat == BacSeat::Chairperson)
    }

    pub fn vice_chair(&self) -> Option<&BacMemberSnapshot> {
        self.members
            .iter()
            .find(|member| member.seat == BacSeat::ViceChairperson)
    }

    pub fn presiding_officer(&self, attendance: &BTreeSet<UserId>) -> Option<&BacMemberSnapshot> {
        if let Some(chair) = self.chair() {
            if attendance.contains(&chair.user_id) {
                return Some(chair);
            }
        }
        self.vice_chair()
            .filter(|vice| attendance.contains(&vice.user_id))
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct BacResolution {
    id: BacResolutionId,
    version_id: DocumentVersionId,
    version_hash: ContentHash,
    resolution_number: String,
    membership: BacMembershipSnapshot,
    attendance: BTreeSet<UserId>,
    votes: BacVotes,
    outcome: BacOutcome,
    quorum_required: usize,
    resolved_at: OffsetDateTime,
    status: BacResolutionStatus,
}

impl BacResolution {
    #[allow(clippy::too_many_arguments)]
    pub fn record(
        id: BacResolutionId,
        version_id: DocumentVersionId,
        version_hash: ContentHash,
        resolution_number: impl Into<String>,
        membership: BacMembershipSnapshot,
        attendance: BTreeSet<UserId>,
        votes: BacVotes,
        presiding_actor: &Actor,
        resolved_at: OffsetDateTime,
    ) -> Result<Self, DomainError> {
        let resolution_number = resolution_number.into();
        if resolution_number.trim().is_empty() {
            return Err(DomainError::rule("BAC resolution number must not be empty"));
        }
        if attendance.is_empty() {
            return Err(DomainError::rule("BAC attendance must not be empty"));
        }
        for attendee in &attendance {
            if membership.member(*attendee).is_none() {
                return Err(DomainError::rule(format!(
                    "BAC attendee {attendee} is not in the membership snapshot"
                )));
            }
        }
        let quorum_required = membership.quorum_required();
        if attendance.len() < quorum_required {
            return Err(DomainError::rule(format!(
                "BAC quorum is not met: {} present, {quorum_required} required",
                attendance.len()
            )));
        }
        let presiding = membership.presiding_officer(&attendance).ok_or_else(|| {
            DomainError::rule(
                "the BAC Chairperson or Vice-Chairperson must be present to transact business",
            )
        })?;
        if presiding.user_id != presiding_actor.user_id() {
            return Err(DomainError::unauthorized(format!(
                "BAC action must be presided over by {}, not {}",
                presiding.user_id,
                presiding_actor.user_id()
            )));
        }
        let expected_role = match presiding.seat {
            BacSeat::Chairperson => ActorRole::BacChairperson,
            BacSeat::ViceChairperson => ActorRole::BacViceChairperson,
            BacSeat::Member => {
                return Err(DomainError::rule(
                    "the presiding officer must hold a Chairperson or Vice-Chairperson seat",
                ));
            }
        };
        if presiding_actor.role() != expected_role {
            return Err(DomainError::unauthorized(
                "the presiding officer's recorded role does not match the BAC seat",
            ));
        }

        for voter in votes.keys() {
            if !attendance.contains(voter) {
                return Err(DomainError::rule(format!(
                    "BAC voter {voter} is not recorded as present"
                )));
            }
        }

        let mut recommends = 0usize;
        let mut does_not_recommend = 0usize;
        let mut presiding_vote: Option<BacVote> = None;
        for (voter, vote) in &votes {
            let member = membership
                .member(*voter)
                .expect("voter membership was checked above");
            if member.user_id == presiding.user_id {
                if vote == &BacVote::Abstain {
                    return Err(DomainError::rule(
                        "the presiding officer may vote only to break a tie",
                    ));
                }
                presiding_vote = Some(*vote);
                continue;
            }
            if member.seat == BacSeat::Chairperson {
                return Err(DomainError::rule(
                    "the Chairperson may vote only when presiding over the meeting",
                ));
            }
            match vote {
                BacVote::Recommend => recommends += 1,
                BacVote::DoNotRecommend => does_not_recommend += 1,
                BacVote::Abstain => {}
            }
        }

        // IRR 42.3 requires a majority of the members present; the
        // Chairperson, or the Vice-Chairperson when presiding in the
        // Chairperson's absence, votes only to break a tie.
        let majority_required = attendance.len() / 2 + 1;
        let tied = recommends == does_not_recommend;
        if !tied && presiding_vote.is_some() {
            return Err(DomainError::rule(
                "the presiding officer may vote only when the regular members are tied",
            ));
        }

        let outcome = if tied {
            match presiding_vote {
                Some(BacVote::Recommend) if recommends + 1 >= majority_required => {
                    BacOutcome::Recommended
                }
                Some(BacVote::DoNotRecommend) if does_not_recommend + 1 >= majority_required => {
                    BacOutcome::NotRecommended
                }
                _ => BacOutcome::NoDecision,
            }
        } else if recommends > does_not_recommend {
            if recommends >= majority_required {
                BacOutcome::Recommended
            } else {
                BacOutcome::NoDecision
            }
        } else if does_not_recommend >= majority_required {
            BacOutcome::NotRecommended
        } else {
            BacOutcome::NoDecision
        };

        Ok(Self {
            id,
            version_id,
            version_hash,
            resolution_number,
            membership,
            attendance,
            votes,
            outcome,
            quorum_required,
            resolved_at,
            status: BacResolutionStatus::Active,
        })
    }

    /// Rehydrate a recorded BAC resolution from durable storage.
    #[allow(clippy::too_many_arguments)]
    pub fn restore(
        id: BacResolutionId,
        version_id: DocumentVersionId,
        version_hash: ContentHash,
        resolution_number: String,
        membership: BacMembershipSnapshot,
        attendance: BTreeSet<UserId>,
        votes: BacVotes,
        outcome: BacOutcome,
        quorum_required: usize,
        resolved_at: OffsetDateTime,
        status: BacResolutionStatus,
    ) -> Self {
        Self {
            id,
            version_id,
            version_hash,
            resolution_number,
            membership,
            attendance,
            votes,
            outcome,
            quorum_required,
            resolved_at,
            status,
        }
    }

    pub fn id(&self) -> BacResolutionId {
        self.id
    }

    pub fn version_id(&self) -> DocumentVersionId {
        self.version_id
    }

    pub fn version_hash(&self) -> &ContentHash {
        &self.version_hash
    }

    pub fn resolution_number(&self) -> &str {
        &self.resolution_number
    }

    pub fn membership(&self) -> &BacMembershipSnapshot {
        &self.membership
    }

    pub fn attendance(&self) -> &BTreeSet<UserId> {
        &self.attendance
    }

    pub fn votes(&self) -> &BacVotes {
        &self.votes
    }

    pub fn outcome(&self) -> BacOutcome {
        self.outcome
    }

    pub fn quorum_required(&self) -> usize {
        self.quorum_required
    }

    pub fn resolved_at(&self) -> OffsetDateTime {
        self.resolved_at
    }

    pub fn status(&self) -> BacResolutionStatus {
        self.status
    }

    pub fn is_active(&self) -> bool {
        self.status == BacResolutionStatus::Active
    }

    pub fn is_recommended(&self) -> bool {
        self.outcome == BacOutcome::Recommended
    }

    pub fn binds(&self, version_id: DocumentVersionId, version_hash: &ContentHash) -> bool {
        self.version_id == version_id && &self.version_hash == version_hash
    }

    pub fn supersede(&mut self) {
        self.status = BacResolutionStatus::Superseded;
    }
}

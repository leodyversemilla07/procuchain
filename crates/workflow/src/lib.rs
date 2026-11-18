//! Approval records and BAC composition, quorum, and decisions.

pub mod approvals;
pub mod bac;

pub use approvals::{Approval, ApprovalDecision, ApprovalStatus};
pub use bac::{
    BacMemberSnapshot, BacMembershipSnapshot, BacOutcome, BacResolution, BacResolutionStatus,
    BacSeat, BacVote, BacVotes,
};

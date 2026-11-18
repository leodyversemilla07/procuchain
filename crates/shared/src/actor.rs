use serde::{Deserialize, Serialize};

use crate::ids::UserId;

/// The actor role at the moment a governed action is performed.
///
/// Roles are not inferred from the current roster after the fact. The audit
/// event records both the actor and the role used for the decision.
#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum ActorRole {
    EndUser,
    EndUserHead,
    BacChairperson,
    BacViceChairperson,
    BacMember,
    Hope,
    Secretariat,
    RecordsOfficer,
    System,
}

impl ActorRole {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::EndUser => "end_user",
            Self::EndUserHead => "end_user_head",
            Self::BacChairperson => "bac_chairperson",
            Self::BacViceChairperson => "bac_vice_chairperson",
            Self::BacMember => "bac_member",
            Self::Hope => "hope",
            Self::Secretariat => "secretariat",
            Self::RecordsOfficer => "records_officer",
            Self::System => "system",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Actor {
    user_id: UserId,
    role: ActorRole,
    display_name: String,
}

impl Actor {
    pub fn new(user_id: UserId, role: ActorRole, display_name: impl Into<String>) -> Self {
        Self {
            user_id,
            role,
            display_name: display_name.into(),
        }
    }

    pub fn user_id(&self) -> UserId {
        self.user_id
    }

    pub fn role(&self) -> ActorRole {
        self.role
    }

    pub fn display_name(&self) -> &str {
        &self.display_name
    }
}

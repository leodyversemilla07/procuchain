use std::fmt;

use serde::{Deserialize, Serialize};
use uuid::Uuid;

macro_rules! define_id {
    ($name:ident) => {
        #[derive(
            Clone, Copy, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize,
        )]
        #[serde(transparent)]
        pub struct $name(Uuid);

        impl $name {
            pub fn new() -> Self {
                Self(Uuid::now_v7())
            }

            pub fn from_uuid(value: Uuid) -> Self {
                Self(value)
            }

            pub fn as_uuid(&self) -> Uuid {
                self.0
            }
        }

        impl Default for $name {
            fn default() -> Self {
                Self::new()
            }
        }

        impl fmt::Display for $name {
            fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
                self.0.fmt(formatter)
            }
        }
    };
}

define_id!(TenantId);
define_id!(DocumentId);
define_id!(DocumentVersionId);
define_id!(UserId);
define_id!(CommitteeId);
define_id!(ApprovalId);
define_id!(BacResolutionId);
define_id!(SignatureRequestId);
define_id!(SignatureId);
define_id!(IssuanceId);
define_id!(DistributionId);
define_id!(AcknowledgementId);
define_id!(AnchorId);
define_id!(ArtifactId);
define_id!(RecipientId);
define_id!(RulePackSnapshotId);

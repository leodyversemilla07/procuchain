//! Procuring-Entity committee and BAC membership persistence.

use procuchain_shared::{CommitteeId, TenantId, UserId};
use procuchain_workflow::BacSeat;
use sqlx::Row;
use time::OffsetDateTime;

use crate::{PgStore, StoreError, UnitOfWork};

#[derive(Clone, Debug, PartialEq, Eq)]
pub struct CommitteeMember {
    pub user_id: UserId,
    pub display_name: String,
    pub seat: BacSeat,
}

impl PgStore {
    pub async fn committee_members(
        &self,
        tenant_id: TenantId,
        committee_id: CommitteeId,
    ) -> Result<Vec<CommitteeMember>, StoreError> {
        let rows = sqlx::query(
            "SELECT committee_memberships.user_id, users.display_name, committee_memberships.seat FROM committee_memberships JOIN users ON users.tenant_id = committee_memberships.tenant_id AND users.id = committee_memberships.user_id WHERE committee_memberships.tenant_id = $1 AND committee_id = $2 AND active = true ORDER BY seat, display_name",
        )
        .bind(tenant_id.as_uuid())
        .bind(committee_id.as_uuid())
        .fetch_all(self.pool())
        .await?;
        rows.iter()
            .map(|row| {
                Ok(CommitteeMember {
                    user_id: UserId::from_uuid(row.try_get("user_id")?),
                    display_name: row.try_get("display_name")?,
                    seat: parse_seat(row.try_get("seat")?)?,
                })
            })
            .collect()
    }
}

impl UnitOfWork<'_> {
    pub async fn assign_committee_member(
        &mut self,
        tenant_id: TenantId,
        committee_id: CommitteeId,
        user_id: UserId,
        seat: BacSeat,
        assigned_at: OffsetDateTime,
    ) -> Result<(), StoreError> {
        sqlx::query(
            "INSERT INTO committee_memberships (tenant_id, committee_id, user_id, seat, assigned_at) VALUES ($1, $2, $3, $4, $5) ON CONFLICT (tenant_id, committee_id, user_id) DO UPDATE SET seat = EXCLUDED.seat, active = true, assigned_at = EXCLUDED.assigned_at",
        )
        .bind(tenant_id.as_uuid())
        .bind(committee_id.as_uuid())
        .bind(user_id.as_uuid())
        .bind(seat.label())
        .bind(assigned_at)
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

fn parse_seat(value: String) -> Result<BacSeat, StoreError> {
    match value.as_str() {
        "chairperson" => Ok(BacSeat::Chairperson),
        "vice_chairperson" => Ok(BacSeat::ViceChairperson),
        "member" => Ok(BacSeat::Member),
        other => Err(StoreError::invalid_value(format!(
            "unknown BAC seat: {other}"
        ))),
    }
}

//! Durable users and role assignments for the MVP identity boundary.

use procuchain_shared::{ActorRole, ContentHash, TenantId, UserId};
use sqlx::Row;
use time::OffsetDateTime;

use crate::{PgStore, StoreError, UnitOfWork};

#[derive(Clone, Debug, PartialEq, Eq)]
pub struct UserIdentity {
    pub user_id: UserId,
    pub tenant_id: TenantId,
    pub display_name: String,
    pub active: bool,
    pub roles: Vec<ActorRole>,
}

impl PgStore {
    pub async fn resolve_api_token(
        &self,
        tenant_id: TenantId,
        token: &str,
    ) -> Result<Option<UserIdentity>, StoreError> {
        let token_hash = ContentHash::sha256(token.as_bytes());
        let user_id = sqlx::query_scalar(
            "SELECT user_id FROM api_tokens WHERE tenant_id = $1 AND token_hash = $2 AND status = 'active'",
        )
        .bind(tenant_id.as_uuid())
        .bind(token_hash.as_str())
        .fetch_optional(self.pool())
        .await?;
        let Some(user_id) = user_id else {
            return Ok(None);
        };
        self.load_user_identity(tenant_id, UserId::from_uuid(user_id))
            .await
    }

    pub async fn load_user_identity(
        &self,
        tenant_id: TenantId,
        user_id: UserId,
    ) -> Result<Option<UserIdentity>, StoreError> {
        let row =
            sqlx::query("SELECT display_name, status FROM users WHERE tenant_id = $1 AND id = $2")
                .bind(tenant_id.as_uuid())
                .bind(user_id.as_uuid())
                .fetch_optional(self.pool())
                .await?;
        let Some(row) = row else {
            return Ok(None);
        };

        let role_rows = sqlx::query(
            "SELECT role FROM user_roles WHERE tenant_id = $1 AND user_id = $2 ORDER BY role",
        )
        .bind(tenant_id.as_uuid())
        .bind(user_id.as_uuid())
        .fetch_all(self.pool())
        .await?;
        let roles = role_rows
            .iter()
            .map(|role| parse_role(role.try_get("role")?))
            .collect::<Result<Vec<_>, StoreError>>()?;
        Ok(Some(UserIdentity {
            user_id,
            tenant_id,
            display_name: row.try_get("display_name")?,
            active: row.try_get::<String, _>("status")? == "active",
            roles,
        }))
    }
}

impl UnitOfWork<'_> {
    pub async fn create_user(
        &mut self,
        tenant_id: TenantId,
        user_id: UserId,
        display_name: &str,
    ) -> Result<(), StoreError> {
        sqlx::query("INSERT INTO users (id, tenant_id, display_name) VALUES ($1, $2, $3)")
            .bind(user_id.as_uuid())
            .bind(tenant_id.as_uuid())
            .bind(display_name)
            .execute(&mut *self.transaction)
            .await?;
        Ok(())
    }

    pub async fn create_api_token(
        &mut self,
        tenant_id: TenantId,
        token_id: uuid::Uuid,
        user_id: UserId,
        token: &str,
    ) -> Result<(), StoreError> {
        let token_hash = ContentHash::sha256(token.as_bytes());
        sqlx::query(
            "INSERT INTO api_tokens (id, tenant_id, user_id, token_hash) VALUES ($1, $2, $3, $4)",
        )
        .bind(token_id)
        .bind(tenant_id.as_uuid())
        .bind(user_id.as_uuid())
        .bind(token_hash.as_str())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }

    pub async fn assign_role(
        &mut self,
        tenant_id: TenantId,
        user_id: UserId,
        role: ActorRole,
        assigned_at: OffsetDateTime,
    ) -> Result<(), StoreError> {
        sqlx::query(
            "INSERT INTO user_roles (tenant_id, user_id, role, assigned_at) VALUES ($1, $2, $3, $4) ON CONFLICT DO NOTHING",
        )
        .bind(tenant_id.as_uuid())
        .bind(user_id.as_uuid())
        .bind(role.label())
        .bind(assigned_at)
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

fn parse_role(value: String) -> Result<ActorRole, StoreError> {
    let role = match value.as_str() {
        "end_user" => ActorRole::EndUser,
        "end_user_head" => ActorRole::EndUserHead,
        "bac_chairperson" => ActorRole::BacChairperson,
        "bac_vice_chairperson" => ActorRole::BacViceChairperson,
        "bac_member" => ActorRole::BacMember,
        "hope" => ActorRole::Hope,
        "secretariat" => ActorRole::Secretariat,
        "records_officer" => ActorRole::RecordsOfficer,
        "system" => ActorRole::System,
        other => {
            return Err(StoreError::invalid_value(format!(
                "unknown user role: {other}"
            )));
        }
    };
    Ok(role)
}

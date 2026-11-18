//! PostgreSQL persistence adapters for the ProcuChain core.
//!
//! Reads use a shared pool. Writes go through [`UnitOfWork`] so that aggregate
//! updates, audit events, and outbox records can share one transaction.

use procuchain_integrations::{
    DispatchResult, IntegrationError, OutboxDispatcher, PublicationPort, RetentionPort,
    TimestampPort,
};
use procuchain_shared::{ContentHash, DomainError, TenantId, UserId};
use sqlx::postgres::{PgPool, PgPoolOptions};
use sqlx::{Postgres, Row, Transaction};
use thiserror::Error;
use time::OffsetDateTime;
use uuid::Uuid;

pub mod anchors;
pub mod audit;
pub mod committees;
pub mod documents;
pub mod enums;
pub mod identity;
pub mod issuance;
pub mod signatures;
pub mod tasks;
pub mod workflow;

#[derive(Debug, Error)]
pub enum StoreError {
    #[error(transparent)]
    Database(#[from] sqlx::Error),
    #[error(transparent)]
    Migration(#[from] sqlx::migrate::MigrateError),
    #[error(transparent)]
    Json(#[from] serde_json::Error),
    #[error(transparent)]
    Domain(#[from] DomainError),
    #[error(transparent)]
    Integration(#[from] IntegrationError),
    #[error("invalid persisted value: {0}")]
    InvalidValue(String),
}

impl StoreError {
    pub(crate) fn invalid_value(message: impl Into<String>) -> Self {
        Self::InvalidValue(message.into())
    }
}

#[derive(Clone)]
pub struct PgStore {
    pool: PgPool,
}

impl PgStore {
    pub async fn connect(database_url: &str) -> Result<Self, StoreError> {
        let pool = PgPoolOptions::new()
            .max_connections(10)
            .connect(database_url)
            .await?;
        Ok(Self { pool })
    }

    pub fn from_pool(pool: PgPool) -> Self {
        Self { pool }
    }

    pub fn pool(&self) -> &PgPool {
        &self.pool
    }

    pub async fn migrate(&self) -> Result<(), StoreError> {
        sqlx::migrate!("./migrations").run(&self.pool).await?;
        Ok(())
    }

    /// Provision the single development principal used by the standalone Development.
    ///
    /// Production deployments should replace this bootstrap with a real
    /// identity provider, but a fresh local database must be usable immediately
    /// after migrations. Every statement is idempotent so restarting the API
    /// does not create duplicate users, roles, or tokens.
    pub async fn ensure_development_identity(
        &self,
        tenant_id: TenantId,
        user_id: UserId,
        display_name: &str,
        bearer_token: &str,
    ) -> Result<(), StoreError> {
        let mut transaction = self.pool.begin().await?;
        sqlx::query("INSERT INTO tenants (id, name) VALUES ($1, $2) ON CONFLICT (id) DO NOTHING")
            .bind(tenant_id.as_uuid())
            .bind("ProcuChain Development Tenant")
            .execute(&mut *transaction)
            .await?;
        sqlx::query(
            "INSERT INTO users (id, tenant_id, display_name) VALUES ($1, $2, $3) ON CONFLICT (tenant_id, id) DO UPDATE SET display_name = EXCLUDED.display_name, status = 'active'",
        )
        .bind(user_id.as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(display_name)
        .execute(&mut *transaction)
        .await?;
        for role in ["bac_chairperson", "hope"] {
            sqlx::query(
                "INSERT INTO user_roles (tenant_id, user_id, role) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING",
            )
            .bind(tenant_id.as_uuid())
            .bind(user_id.as_uuid())
            .bind(role)
            .execute(&mut *transaction)
            .await?;
        }
        let token_hash = ContentHash::sha256(bearer_token.as_bytes());
        sqlx::query(
            "INSERT INTO api_tokens (id, tenant_id, user_id, token_hash) VALUES ($1, $2, $3, $4) ON CONFLICT (token_hash) DO UPDATE SET tenant_id = EXCLUDED.tenant_id, user_id = EXCLUDED.user_id, status = 'active'",
        )
        .bind(Uuid::now_v7())
        .bind(tenant_id.as_uuid())
        .bind(user_id.as_uuid())
        .bind(token_hash.as_str())
        .execute(&mut *transaction)
        .await?;
        transaction.commit().await?;
        Ok(())
    }

    pub async fn begin(&self) -> Result<UnitOfWork<'_>, StoreError> {
        Ok(UnitOfWork {
            transaction: self.pool.begin().await?,
        })
    }
}

pub struct UnitOfWork<'a> {
    transaction: Transaction<'a, Postgres>,
}

#[derive(Clone, Debug)]
pub struct PendingOutboxEvent {
    pub id: Uuid,
    pub tenant_id: procuchain_shared::TenantId,
    pub event_type: String,
    pub payload: serde_json::Value,
}

impl PgStore {
    pub async fn pending_outbox_events(
        &self,
        limit: i64,
    ) -> Result<Vec<PendingOutboxEvent>, StoreError> {
        let rows = sqlx::query(
            "SELECT id, tenant_id, event_type, payload FROM outbox_events WHERE published_at IS NULL ORDER BY occurred_at, id LIMIT $1",
        )
        .bind(limit)
        .fetch_all(self.pool())
        .await?;
        rows.into_iter()
            .map(|row| {
                Ok(PendingOutboxEvent {
                    id: row.try_get("id")?,
                    tenant_id: procuchain_shared::TenantId::from_uuid(row.try_get("tenant_id")?),
                    event_type: row.try_get("event_type")?,
                    payload: row.try_get("payload")?,
                })
            })
            .collect()
    }

    /// Process one bounded batch of outbox events and mark successful events
    /// published in their own transaction. Provider failures leave events
    /// pending for retry.
    pub async fn dispatch_pending_outbox<P, T, R>(
        &self,
        dispatcher: &OutboxDispatcher<P, T, R>,
        limit: i64,
    ) -> Result<usize, StoreError>
    where
        P: PublicationPort,
        T: TimestampPort,
        R: RetentionPort,
    {
        let events = self.pending_outbox_events(limit).await?;
        let mut published = 0;
        for event in events {
            let result =
                dispatcher.dispatch(event.id, event.tenant_id, &event.event_type, event.payload)?;
            if matches!(result, DispatchResult::Ignored) {
                continue;
            }
            let mut unit = self.begin().await?;
            if unit
                .mark_outbox_published(event.id, OffsetDateTime::now_utc())
                .await?
            {
                unit.commit().await?;
                published += 1;
            } else {
                unit.rollback().await?;
            }
        }
        Ok(published)
    }
}

impl UnitOfWork<'_> {
    pub async fn mark_outbox_published(
        &mut self,
        event_id: Uuid,
        published_at: OffsetDateTime,
    ) -> Result<bool, StoreError> {
        let result = sqlx::query(
            "UPDATE outbox_events SET published_at = $2, attempts = attempts + 1 WHERE id = $1 AND published_at IS NULL",
        )
        .bind(event_id)
        .bind(published_at)
        .execute(&mut *self.transaction)
        .await?;
        Ok(result.rows_affected() == 1)
    }

    /// Reserve an idempotency key inside the same transaction as the command.
    ///
    /// A completed record is returned for safe replay. A record without a
    /// response is another request currently owning the key and must not be
    /// executed twice.
    pub async fn claim_idempotency(
        &mut self,
        tenant_id: procuchain_shared::TenantId,
        key: &str,
        command_type: &str,
        request_hash: &str,
    ) -> Result<IdempotencyClaim, StoreError> {
        sqlx::query(
            "INSERT INTO idempotency_records (tenant_id, idempotency_key, command_type, request_hash) VALUES ($1, $2, $3, $4) ON CONFLICT (tenant_id, idempotency_key) DO NOTHING",
        )
        .bind(tenant_id.as_uuid())
        .bind(key)
        .bind(command_type)
        .bind(request_hash)
        .execute(&mut *self.transaction)
        .await?;

        let row = sqlx::query(
            "SELECT command_type, request_hash, response_status, response_body FROM idempotency_records WHERE tenant_id = $1 AND idempotency_key = $2 FOR UPDATE",
        )
        .bind(tenant_id.as_uuid())
        .bind(key)
        .fetch_one(&mut *self.transaction)
        .await?;

        let stored_command: String = row.try_get("command_type")?;
        let stored_hash: String = row.try_get("request_hash")?;
        if stored_command != command_type || stored_hash != request_hash {
            return Ok(IdempotencyClaim::Conflict);
        }

        let status: Option<i32> = row.try_get("response_status")?;
        let body: Option<serde_json::Value> = row.try_get("response_body")?;
        match (status, body) {
            (Some(status), Some(body)) => Ok(IdempotencyClaim::Replay { status, body }),
            (None, None) => Ok(IdempotencyClaim::Owner),
            _ => Ok(IdempotencyClaim::Conflict),
        }
    }

    pub async fn complete_idempotency(
        &mut self,
        tenant_id: procuchain_shared::TenantId,
        key: &str,
        status: i32,
        body: &serde_json::Value,
    ) -> Result<(), StoreError> {
        sqlx::query(
            "UPDATE idempotency_records SET response_status = $3, response_body = $4 WHERE tenant_id = $1 AND idempotency_key = $2",
        )
        .bind(tenant_id.as_uuid())
        .bind(key)
        .bind(status)
        .bind(body)
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }

    pub async fn commit(self) -> Result<(), StoreError> {
        self.transaction.commit().await?;
        Ok(())
    }

    /// Explicitly discard the transaction's writes.
    ///
    /// Dropping a unit of work rolls back implicitly; this method exists so
    /// callers can make the decision explicit and test atomicity.
    pub async fn rollback(self) -> Result<(), StoreError> {
        self.transaction.rollback().await?;
        Ok(())
    }
}

#[derive(Debug, PartialEq)]
pub enum IdempotencyClaim {
    Owner,
    Replay {
        status: i32,
        body: serde_json::Value,
    },
    Conflict,
}

#[derive(Clone, Debug)]
pub struct NewOutboxEvent {
    pub id: Uuid,
    pub aggregate_kind: String,
    pub aggregate_id: Uuid,
    pub event_type: String,
    pub payload: serde_json::Value,
    pub occurred_at: OffsetDateTime,
}

impl NewOutboxEvent {
    pub fn new(
        aggregate_kind: impl Into<String>,
        aggregate_id: Uuid,
        event_type: impl Into<String>,
        payload: serde_json::Value,
        occurred_at: OffsetDateTime,
    ) -> Self {
        Self {
            id: Uuid::now_v7(),
            aggregate_kind: aggregate_kind.into(),
            aggregate_id,
            event_type: event_type.into(),
            payload,
            occurred_at,
        }
    }
}

impl UnitOfWork<'_> {
    pub async fn enqueue_outbox(
        &mut self,
        tenant_id: procuchain_shared::TenantId,
        event: &NewOutboxEvent,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO outbox_events (
                id, tenant_id, aggregate_kind, aggregate_id, event_type, payload, occurred_at
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7)
            "#,
        )
        .bind(event.id)
        .bind(tenant_id.as_uuid())
        .bind(&event.aggregate_kind)
        .bind(event.aggregate_id)
        .bind(&event.event_type)
        .bind(&event.payload)
        .bind(event.occurred_at)
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

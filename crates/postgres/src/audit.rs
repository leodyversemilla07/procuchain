//! Append-only audit-chain persistence.
//!
//! New events are linked to the stored chain inside the caller's transaction.
//! A per-tenant advisory lock serialises appends so two writers cannot claim
//! the same sequence number.

use std::collections::BTreeMap;

use procuchain_evidence::{AuditEvent, AuditLog};
use procuchain_shared::{ContentHash, TenantId, UserId};
use sqlx::Row;
use time::OffsetDateTime;

use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn load_audit_log(&self, tenant_id: TenantId) -> Result<AuditLog, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                seq, event_type, actor_id, subject_kind, subject_id, occurred_at,
                details, previous_hash, event_hash
            FROM audit_events
            WHERE tenant_id = $1
            ORDER BY seq
            "#,
        )
        .bind(tenant_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let events = rows
            .iter()
            .map(audit_event_from_row)
            .collect::<Result<Vec<_>, StoreError>>()?;
        Ok(AuditLog::restore(events)?)
    }
}

impl UnitOfWork<'_> {
    /// Append one event to the tenant's audit chain and persist it.
    ///
    /// Returns the event that was written so callers can use its hash.
    #[allow(clippy::too_many_arguments)]
    pub async fn append_audit_event(
        &mut self,
        tenant_id: TenantId,
        event_type: impl Into<String>,
        actor: Option<UserId>,
        subject_kind: impl Into<String>,
        subject_id: impl Into<String>,
        occurred_at: OffsetDateTime,
        details: BTreeMap<String, String>,
    ) -> Result<AuditEvent, StoreError> {
        sqlx::query("SELECT pg_advisory_xact_lock(hashtextextended($1, 0))")
            .bind(tenant_id.as_uuid().to_string())
            .execute(&mut *self.transaction)
            .await?;

        let rows = sqlx::query(
            r#"
            SELECT
                seq, event_type, actor_id, subject_kind, subject_id, occurred_at,
                details, previous_hash, event_hash
            FROM audit_events
            WHERE tenant_id = $1
            ORDER BY seq
            "#,
        )
        .bind(tenant_id.as_uuid())
        .fetch_all(&mut *self.transaction)
        .await?;

        let events = rows
            .iter()
            .map(audit_event_from_row)
            .collect::<Result<Vec<_>, StoreError>>()?;
        let mut log = AuditLog::restore(events)?;

        let event = log
            .append(
                event_type,
                actor,
                subject_kind,
                subject_id,
                occurred_at,
                details,
            )
            .clone();

        let details = serde_json::to_value(&event.details)?;
        sqlx::query(
            r#"
            INSERT INTO audit_events (
                tenant_id, seq, event_type, actor_id, subject_kind, subject_id,
                occurred_at, details, previous_hash, event_hash
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(event.seq as i64)
        .bind(&event.event_type)
        .bind(event.actor.map(|id| id.as_uuid()))
        .bind(&event.subject_kind)
        .bind(&event.subject_id)
        .bind(event.occurred_at)
        .bind(&details)
        .bind(event.previous_hash.as_str())
        .bind(event.event_hash.as_str())
        .execute(&mut *self.transaction)
        .await?;

        Ok(event)
    }
}

fn audit_event_from_row(row: &sqlx::postgres::PgRow) -> Result<AuditEvent, StoreError> {
    let details: serde_json::Value = row.try_get("details")?;
    let previous_hash: String = row.try_get("previous_hash")?;
    let event_hash: String = row.try_get("event_hash")?;
    let details: BTreeMap<String, String> = serde_json::from_value(details)?;
    let actor_id: Option<uuid::Uuid> = row.try_get("actor_id")?;

    Ok(AuditEvent {
        seq: row.try_get::<i64, _>("seq")? as u64,
        event_type: row.try_get("event_type")?,
        actor: actor_id.map(UserId::from_uuid),
        subject_kind: row.try_get("subject_kind")?,
        subject_id: row.try_get("subject_id")?,
        occurred_at: row.try_get("occurred_at")?,
        details,
        previous_hash: ContentHash::from_hex(&previous_hash)?,
        event_hash: ContentHash::from_hex(&event_hash)?,
    })
}

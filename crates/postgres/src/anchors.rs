//! Evidence-anchor persistence.
//!
//! Anchoring is the narrow boundary where evidence leaves PostgreSQL for
//! independent corroboration. The local anchor is authoritative for the
//! platform; receipts from an external provider are stored beside it and never
//! replace it.

use procuchain_evidence::{AnchorBatch, AnchorReceipt, AnchorRecord, EvidenceAnchor};
use procuchain_shared::{AnchorId, ContentHash, TenantId};
use sqlx::Row;
use time::OffsetDateTime;
use uuid::Uuid;

use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn load_anchors(&self, tenant_id: TenantId) -> Result<Vec<AnchorRecord>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, tenant_id, from_seq, to_seq, event_count, merkle_root,
                previous_anchor_hash, anchor_hash, schema_version, created_at
            FROM evidence_anchors
            WHERE tenant_id = $1
            ORDER BY from_seq
            "#,
        )
        .bind(tenant_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter().map(anchor_from_row).collect()
    }

    pub async fn load_anchor_receipts(
        &self,
        tenant_id: TenantId,
        anchor_id: AnchorId,
    ) -> Result<Vec<AnchorReceipt>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT anchor_id, provider, provider_ref, received_at, token
            FROM evidence_anchor_receipts
            WHERE tenant_id = $1 AND anchor_id = $2
            ORDER BY received_at, provider, provider_ref
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(anchor_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter()
            .map(|row| {
                Ok(AnchorReceipt::new(
                    AnchorId::from_uuid(row.try_get("anchor_id")?),
                    row.try_get::<String, _>("provider")?,
                    row.try_get::<String, _>("provider_ref")?,
                    row.try_get("received_at")?,
                    row.try_get("token")?,
                )?)
            })
            .collect()
    }
}

impl UnitOfWork<'_> {
    /// Anchor every audit event that follows the last stored anchor.
    ///
    /// The audit advisory lock is shared with [`Self::append_audit_event`], so
    /// a concurrent append either lands before this range or after it.
    pub async fn anchor_next_audit_batch(
        &mut self,
        tenant_id: TenantId,
        anchor_id: AnchorId,
        created_at: OffsetDateTime,
    ) -> Result<Option<AnchorRecord>, StoreError> {
        sqlx::query("SELECT pg_advisory_xact_lock(hashtextextended($1, 0))")
            .bind(tenant_id.as_uuid().to_string())
            .execute(&mut *self.transaction)
            .await?;

        let last: Option<(i64, String)> = sqlx::query_as(
            r#"
            SELECT to_seq, anchor_hash
            FROM evidence_anchors
            WHERE tenant_id = $1
            ORDER BY to_seq DESC
            LIMIT 1
            "#,
        )
        .bind(tenant_id.as_uuid())
        .fetch_optional(&mut *self.transaction)
        .await?;

        let (last_seq, previous_anchor_hash) = match last {
            Some((seq, hash)) => (seq as u64, ContentHash::from_hex(hash)?),
            None => (0, ContentHash::anchor_genesis()),
        };

        let event_hashes: Vec<String> = sqlx::query_scalar(
            r#"
            SELECT event_hash
            FROM audit_events
            WHERE tenant_id = $1 AND seq > $2
            ORDER BY seq
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(last_seq as i64)
        .fetch_all(&mut *self.transaction)
        .await?;

        if event_hashes.is_empty() {
            return Ok(None);
        }

        let from_seq = last_seq + 1;
        let to_seq = last_seq + event_hashes.len() as u64;
        let event_hashes = event_hashes
            .into_iter()
            .map(ContentHash::from_hex)
            .collect::<Result<Vec<_>, _>>()?;
        let batch = AnchorBatch::new(tenant_id, from_seq, to_seq, event_hashes)?;
        let record = AnchorRecord::create(&batch, anchor_id, previous_anchor_hash, created_at);

        sqlx::query(
            r#"
            INSERT INTO evidence_anchors (
                id, tenant_id, from_seq, to_seq, event_count, merkle_root,
                previous_anchor_hash, anchor_hash, schema_version, created_at
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
            "#,
        )
        .bind(record.anchor_id().as_uuid())
        .bind(record.tenant_id().as_uuid())
        .bind(record.from_seq() as i64)
        .bind(record.to_seq() as i64)
        .bind(record.event_count() as i64)
        .bind(record.merkle_root().as_str())
        .bind(record.previous_anchor_hash().as_str())
        .bind(record.record_hash().as_str())
        .bind(record.schema_version())
        .bind(record.created_at())
        .execute(&mut *self.transaction)
        .await?;

        Ok(Some(record))
    }

    pub async fn save_anchor_receipt(
        &mut self,
        tenant_id: TenantId,
        receipt: &AnchorReceipt,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO evidence_anchor_receipts (
                anchor_id, tenant_id, provider, provider_ref, received_at, token
            )
            VALUES ($1, $2, $3, $4, $5, $6)
            ON CONFLICT (anchor_id, provider, provider_ref) DO NOTHING
            "#,
        )
        .bind(receipt.anchor_id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(receipt.provider())
        .bind(receipt.provider_ref())
        .bind(receipt.received_at())
        .bind(receipt.token())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

impl EvidenceAnchor for PgStore {
    type Error = StoreError;

    async fn anchor_pending(
        &self,
        tenant_id: TenantId,
        anchor_id: AnchorId,
        created_at: OffsetDateTime,
    ) -> Result<Option<AnchorRecord>, Self::Error> {
        let mut unit = self.begin().await?;
        let record = unit
            .anchor_next_audit_batch(tenant_id, anchor_id, created_at)
            .await?;
        let record = match record {
            Some(record) => {
                unit.commit().await?;
                Some(record)
            }
            None => {
                unit.rollback().await?;
                None
            }
        };
        Ok(record)
    }

    async fn verify_anchors(&self, tenant_id: TenantId) -> Result<bool, Self::Error> {
        let records = self.load_anchors(tenant_id).await?;
        let mut previous_anchor_hash = ContentHash::anchor_genesis();
        let mut next_seq = 1u64;

        for record in &records {
            if record.tenant_id() != tenant_id
                || record.from_seq() != next_seq
                || record.previous_anchor_hash() != &previous_anchor_hash
            {
                return Ok(false);
            }

            let event_hashes: Vec<String> = sqlx::query_scalar(
                r#"
                SELECT event_hash
                FROM audit_events
                WHERE tenant_id = $1 AND seq BETWEEN $2 AND $3
                ORDER BY seq
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(record.from_seq() as i64)
            .bind(record.to_seq() as i64)
            .fetch_all(self.pool())
            .await?;

            if event_hashes.len() as u64 != record.event_count() {
                return Ok(false);
            }

            let event_hashes = event_hashes
                .into_iter()
                .map(ContentHash::from_hex)
                .collect::<Result<Vec<_>, _>>()?;
            let batch =
                AnchorBatch::new(tenant_id, record.from_seq(), record.to_seq(), event_hashes)?;
            if &batch.merkle_root() != record.merkle_root() {
                return Ok(false);
            }

            previous_anchor_hash = record.record_hash();
            next_seq = record.to_seq() + 1;
        }

        Ok(true)
    }
}

fn anchor_from_row(row: &sqlx::postgres::PgRow) -> Result<AnchorRecord, StoreError> {
    let anchor_id: Uuid = row.try_get("id")?;
    let tenant_id: Uuid = row.try_get("tenant_id")?;
    let merkle_root: String = row.try_get("merkle_root")?;
    let previous_anchor_hash: String = row.try_get("previous_anchor_hash")?;
    let anchor_hash: String = row.try_get("anchor_hash")?;

    let record = AnchorRecord::restore(
        AnchorId::from_uuid(anchor_id),
        TenantId::from_uuid(tenant_id),
        row.try_get::<i64, _>("from_seq")? as u64,
        row.try_get::<i64, _>("to_seq")? as u64,
        row.try_get::<i64, _>("event_count")? as u64,
        ContentHash::from_hex(merkle_root)?,
        ContentHash::from_hex(previous_anchor_hash)?,
        row.try_get("schema_version")?,
        row.try_get("created_at")?,
    )?;

    if record.record_hash().as_str() != anchor_hash {
        return Err(StoreError::invalid_value(
            "persisted anchor hash does not match its record",
        ));
    }

    Ok(record)
}

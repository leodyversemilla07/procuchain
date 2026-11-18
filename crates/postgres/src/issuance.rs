//! Issuance, distribution, delivery, and acknowledgement persistence.

use procuchain_issuance::{
    Acknowledgement, AcknowledgementMethod, DeliveryAttempt, DeliveryResult, Distribution,
    Issuance, IssuanceArtifact, IssuanceRecipient, Recipient,
};
use procuchain_shared::{
    AcknowledgementId, Actor, ContentHash, DistributionId, DocumentVersionId, IssuanceId,
    RecipientId, TenantId, UserId,
};
use procuchain_storage::StoredObject;
use sqlx::Row;
use uuid::Uuid;

use crate::enums::{
    acknowledgement_status, actor_role, delivery_result_parts, distribution_channel,
    distribution_status, issuance_status, parse_acknowledgement_method, recipient_kind,
    recipient_kind_label,
};
use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn distribution_row_version(
        &self,
        tenant_id: TenantId,
        distribution_id: DistributionId,
    ) -> Result<Option<i64>, StoreError> {
        let row =
            sqlx::query("SELECT row_version FROM distributions WHERE tenant_id = $1 AND id = $2")
                .bind(tenant_id.as_uuid())
                .bind(distribution_id.as_uuid())
                .fetch_optional(self.pool())
                .await?;
        row.map(|row| row.try_get("row_version"))
            .transpose()
            .map_err(StoreError::from)
    }

    pub async fn load_issuance(
        &self,
        tenant_id: TenantId,
        id: IssuanceId,
    ) -> Result<Option<Issuance>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                version_id, version_hash, issuer_id, issuer_role, issuer_name,
                authority_ref, issued_at, manifest_hash, status
            FROM issuances
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        let Some(row) = row else {
            return Ok(None);
        };

        let artifact_rows = sqlx::query(
            r#"
            SELECT artifact_id, media_type, hash
            FROM issuance_artifacts
            WHERE tenant_id = $1 AND issuance_id = $2
            ORDER BY ordinal
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let mut artifacts = Vec::with_capacity(artifact_rows.len());
        for artifact_row in artifact_rows {
            let hash: String = artifact_row.try_get("hash")?;
            artifacts.push(IssuanceArtifact::new(
                procuchain_shared::ArtifactId::from_uuid(artifact_row.try_get("artifact_id")?),
                artifact_row.try_get::<String, _>("media_type")?,
                ContentHash::from_hex(&hash)?,
            )?);
        }

        let recipient_rows = sqlx::query(
            r#"
            SELECT recipient_id, kind, name, address, channel, acknowledgement_required
            FROM issuance_recipients
            WHERE tenant_id = $1 AND issuance_id = $2
            ORDER BY ordinal
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let mut recipients = Vec::with_capacity(recipient_rows.len());
        for recipient_row in recipient_rows {
            let kind: String = recipient_row.try_get("kind")?;
            let channel: String = recipient_row.try_get("channel")?;
            let recipient = Recipient::new(
                RecipientId::from_uuid(recipient_row.try_get("recipient_id")?),
                recipient_kind(&kind)?,
                recipient_row.try_get::<String, _>("name")?,
                recipient_row.try_get("address")?,
            )?;
            recipients.push(IssuanceRecipient::new(
                recipient,
                distribution_channel(&channel)?,
                recipient_row.try_get("acknowledgement_required")?,
            )?);
        }

        let distribution_ids: Vec<Uuid> = sqlx::query_scalar(
            r#"
            SELECT id
            FROM distributions
            WHERE tenant_id = $1 AND issuance_id = $2
            ORDER BY id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let version_hash: String = row.try_get("version_hash")?;
        let manifest_hash: String = row.try_get("manifest_hash")?;
        let issuer_role: String = row.try_get("issuer_role")?;
        let status: String = row.try_get("status")?;
        let issuer = Actor::new(
            UserId::from_uuid(row.try_get("issuer_id")?),
            actor_role(&issuer_role)?,
            row.try_get::<String, _>("issuer_name")?,
        );

        Ok(Some(Issuance::restore(
            id,
            DocumentVersionId::from_uuid(row.try_get("version_id")?),
            ContentHash::from_hex(&version_hash)?,
            issuer,
            row.try_get("authority_ref")?,
            row.try_get("issued_at")?,
            artifacts,
            recipients,
            distribution_ids
                .into_iter()
                .map(DistributionId::from_uuid)
                .collect(),
            ContentHash::from_hex(&manifest_hash)?,
            issuance_status(&status)?,
        )))
    }

    pub async fn load_distribution(
        &self,
        tenant_id: TenantId,
        id: DistributionId,
    ) -> Result<Option<Distribution>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT issuance_id, recipient_id, version_id, channel, acknowledgement_required, status
            FROM distributions
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        let Some(row) = row else {
            return Ok(None);
        };

        let issuance_id = IssuanceId::from_uuid(row.try_get("issuance_id")?);
        let recipient_id = RecipientId::from_uuid(row.try_get("recipient_id")?);

        let recipient_row = sqlx::query(
            r#"
            SELECT kind, name, address
            FROM issuance_recipients
            WHERE tenant_id = $1 AND issuance_id = $2 AND recipient_id = $3
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(issuance_id.as_uuid())
        .bind(recipient_id.as_uuid())
        .fetch_one(self.pool())
        .await?;

        let kind: String = recipient_row.try_get("kind")?;
        let recipient = Recipient::new(
            recipient_id,
            recipient_kind(&kind)?,
            recipient_row.try_get::<String, _>("name")?,
            recipient_row.try_get("address")?,
        )?;

        let attempt_rows = sqlx::query(
            r#"
            SELECT attempted_at, channel_ref, result, failure_reason
            FROM delivery_attempts
            WHERE tenant_id = $1 AND distribution_id = $2
            ORDER BY ordinal
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let mut attempts = Vec::with_capacity(attempt_rows.len());
        for attempt_row in attempt_rows {
            let result: String = attempt_row.try_get("result")?;
            let failure_reason: Option<String> = attempt_row.try_get("failure_reason")?;
            let result = match result.as_str() {
                "delivered" => DeliveryResult::Delivered,
                "failed" => DeliveryResult::Failed {
                    reason: failure_reason.ok_or_else(|| {
                        StoreError::invalid_value("failed delivery attempt lacks a reason")
                    })?,
                },
                other => {
                    return Err(StoreError::invalid_value(format!(
                        "unknown delivery result: {other}"
                    )));
                }
            };
            attempts.push(DeliveryAttempt::new(
                attempt_row.try_get("attempted_at")?,
                attempt_row.try_get::<String, _>("channel_ref")?,
                result,
            )?);
        }

        let channel: String = row.try_get("channel")?;
        let status: String = row.try_get("status")?;
        Ok(Some(Distribution::restore(
            id,
            issuance_id,
            DocumentVersionId::from_uuid(row.try_get("version_id")?),
            recipient,
            distribution_channel(&channel)?,
            row.try_get("acknowledgement_required")?,
            distribution_status(&status)?,
            attempts,
        )))
    }

    pub async fn acknowledgements_for_issuance(
        &self,
        tenant_id: TenantId,
        issuance_id: IssuanceId,
    ) -> Result<Vec<Acknowledgement>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, distribution_id, issuance_id, version_id, artifact_hash, acknowledged_by,
                acknowledged_at, method, method_other, declaration, status
            FROM acknowledgements
            WHERE tenant_id = $1 AND issuance_id = $2
            ORDER BY acknowledged_at, id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(issuance_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter().map(acknowledgement_from_row).collect()
    }

    pub async fn load_acknowledgement(
        &self,
        tenant_id: TenantId,
        id: AcknowledgementId,
    ) -> Result<Option<Acknowledgement>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                id, distribution_id, issuance_id, version_id, artifact_hash, acknowledged_by,
                acknowledged_at, method, method_other, declaration, status
            FROM acknowledgements
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        row.as_ref().map(acknowledgement_from_row).transpose()
    }
}

impl UnitOfWork<'_> {
    /// Persist the immutable object-store key after the artifact bytes have
    /// been written and verified by an [`ObjectStore`](procuchain_storage::ObjectStore).
    pub async fn link_artifact_object(
        &mut self,
        tenant_id: TenantId,
        issuance_id: IssuanceId,
        artifact_id: procuchain_shared::ArtifactId,
        object: &StoredObject,
    ) -> Result<(), StoreError> {
        let result = sqlx::query(
            "UPDATE issuance_artifacts SET object_key = $4 WHERE tenant_id = $1 AND issuance_id = $2 AND artifact_id = $3",
        )
        .bind(tenant_id.as_uuid())
        .bind(issuance_id.as_uuid())
        .bind(artifact_id.as_uuid())
        .bind(object.key())
        .execute(&mut *self.transaction)
        .await?;
        if result.rows_affected() != 1 {
            return Err(StoreError::invalid_value(
                "issuance artifact does not exist",
            ));
        }
        Ok(())
    }

    pub async fn save_issuance(
        &mut self,
        tenant_id: TenantId,
        issuance: &Issuance,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO issuances (
                id, tenant_id, version_id, version_hash, issuer_id, issuer_role, issuer_name,
                authority_ref, issued_at, manifest_hash, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = issuances.row_version + 1
            "#,
        )
        .bind(issuance.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(issuance.version_id().as_uuid())
        .bind(issuance.version_hash().as_str())
        .bind(issuance.issuer().user_id().as_uuid())
        .bind(issuance.issuer().role().label())
        .bind(issuance.issuer().display_name())
        .bind(issuance.authority_ref())
        .bind(issuance.issued_at())
        .bind(issuance.manifest_hash().as_str())
        .bind(issuance.status().label())
        .execute(&mut *self.transaction)
        .await?;

        for (ordinal, artifact) in issuance.artifacts().iter().enumerate() {
            sqlx::query(
                r#"
                INSERT INTO issuance_artifacts (
                    tenant_id, issuance_id, artifact_id, media_type, hash, ordinal
                )
                VALUES ($1, $2, $3, $4, $5, $6)
                ON CONFLICT (issuance_id, artifact_id) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(issuance.id().as_uuid())
            .bind(artifact.artifact_id().as_uuid())
            .bind(artifact.media_type())
            .bind(artifact.hash().as_str())
            .bind(ordinal as i32)
            .execute(&mut *self.transaction)
            .await?;
        }

        for (ordinal, recipient) in issuance.recipients().iter().enumerate() {
            sqlx::query(
                r#"
                INSERT INTO issuance_recipients (
                    tenant_id, issuance_id, recipient_id, kind, name, address, channel,
                    acknowledgement_required, ordinal
                )
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
                ON CONFLICT (issuance_id, recipient_id) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(issuance.id().as_uuid())
            .bind(recipient.recipient().id().as_uuid())
            .bind(recipient_kind_label(recipient.recipient().kind()))
            .bind(recipient.recipient().name())
            .bind(recipient.recipient().address())
            .bind(recipient.channel().label())
            .bind(recipient.acknowledgement_required())
            .bind(ordinal as i32)
            .execute(&mut *self.transaction)
            .await?;
        }

        Ok(())
    }

    pub async fn save_distribution(
        &mut self,
        tenant_id: TenantId,
        distribution: &Distribution,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO distributions (
                id, tenant_id, issuance_id, recipient_id, version_id, channel,
                acknowledgement_required, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = distributions.row_version + 1
            "#,
        )
        .bind(distribution.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(distribution.issuance_id().as_uuid())
        .bind(distribution.recipient().id().as_uuid())
        .bind(distribution.version_id().as_uuid())
        .bind(distribution.channel().label())
        .bind(distribution.acknowledgement_required())
        .bind(distribution.status().label())
        .execute(&mut *self.transaction)
        .await?;

        for (ordinal, attempt) in distribution.attempts().iter().enumerate() {
            let (result, failure_reason) = delivery_result_parts(attempt.result());
            sqlx::query(
                r#"
                INSERT INTO delivery_attempts (
                    tenant_id, distribution_id, attempted_at, channel_ref, result,
                    failure_reason, ordinal
                )
                VALUES ($1, $2, $3, $4, $5, $6, $7)
                ON CONFLICT (distribution_id, ordinal) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(distribution.id().as_uuid())
            .bind(attempt.attempted_at())
            .bind(attempt.channel_ref())
            .bind(result)
            .bind(failure_reason)
            .bind(ordinal as i32)
            .execute(&mut *self.transaction)
            .await?;
        }

        Ok(())
    }

    pub async fn save_acknowledgement(
        &mut self,
        tenant_id: TenantId,
        acknowledgement: &Acknowledgement,
    ) -> Result<(), StoreError> {
        let (method, method_other) = acknowledgement_columns(acknowledgement.method());
        sqlx::query(
            r#"
            INSERT INTO acknowledgements (
                id, tenant_id, distribution_id, issuance_id, version_id, artifact_hash,
                acknowledged_by, acknowledged_at, method, method_other, declaration, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = acknowledgements.row_version + 1
            "#,
        )
        .bind(acknowledgement.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(acknowledgement.distribution_id().as_uuid())
        .bind(acknowledgement.issuance_id().as_uuid())
        .bind(acknowledgement.version_id().as_uuid())
        .bind(acknowledgement.artifact_hash().as_str())
        .bind(acknowledgement.acknowledged_by().as_uuid())
        .bind(acknowledgement.acknowledged_at())
        .bind(method)
        .bind(method_other)
        .bind(acknowledgement.declaration())
        .bind(acknowledgement.status().label())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

fn acknowledgement_columns(method: &AcknowledgementMethod) -> (&str, Option<&str>) {
    match method {
        AcknowledgementMethod::Portal => ("portal", None),
        AcknowledgementMethod::Email => ("email", None),
        AcknowledgementMethod::SignedReceipt => ("signed_receipt", None),
        AcknowledgementMethod::Other(value) => ("other", Some(value)),
    }
}

fn acknowledgement_from_row(row: &sqlx::postgres::PgRow) -> Result<Acknowledgement, StoreError> {
    let artifact_hash: String = row.try_get("artifact_hash")?;
    let method: String = row.try_get("method")?;
    let method_other: Option<String> = row.try_get("method_other")?;
    let status: String = row.try_get("status")?;

    Ok(Acknowledgement::restore(
        AcknowledgementId::from_uuid(row.try_get("id")?),
        DistributionId::from_uuid(row.try_get("distribution_id")?),
        IssuanceId::from_uuid(row.try_get("issuance_id")?),
        DocumentVersionId::from_uuid(row.try_get("version_id")?),
        ContentHash::from_hex(&artifact_hash)?,
        RecipientId::from_uuid(row.try_get("acknowledged_by")?),
        row.try_get("acknowledged_at")?,
        parse_acknowledgement_method(&method, method_other.as_deref())?,
        row.try_get("declaration")?,
        acknowledgement_status(&status)?,
    ))
}

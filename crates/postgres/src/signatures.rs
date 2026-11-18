//! Signature request and signature persistence.

use procuchain_shared::{
    ContentHash, DocumentVersionId, SignatureId, SignatureRequestId, TenantId, UserId,
};
use procuchain_signatures::{Signature, SignatureRequest};
use sqlx::Row;

use crate::enums::{
    actor_role, signature_method, signature_purpose, signature_request_status, signature_status,
    signature_validation,
};
use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn load_signature_request(
        &self,
        tenant_id: TenantId,
        id: SignatureRequestId,
    ) -> Result<Option<SignatureRequest>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                id, version_id, content_hash, signer_id, signer_role, signer_name,
                purpose, method, declaration, requested_at, deadline, status
            FROM signature_requests
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        row.as_ref().map(signature_request_from_row).transpose()
    }

    pub async fn signature_requests_for_version(
        &self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
    ) -> Result<Vec<SignatureRequest>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, version_id, content_hash, signer_id, signer_role, signer_name,
                purpose, method, declaration, requested_at, deadline, status
            FROM signature_requests
            WHERE tenant_id = $1 AND version_id = $2
            ORDER BY requested_at, id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter().map(signature_request_from_row).collect()
    }

    pub async fn signatures_for_version(
        &self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
    ) -> Result<Vec<Signature>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, request_id, version_id, content_hash, signer_id, signer_role, signer_name,
                method, signed_at, provider_transaction, credential_ref, signed_artifact_hash,
                validation, status
            FROM signatures
            WHERE tenant_id = $1 AND version_id = $2
            ORDER BY signed_at, id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter().map(signature_from_row).collect()
    }

    pub async fn load_signature(
        &self,
        tenant_id: TenantId,
        id: SignatureId,
    ) -> Result<Option<Signature>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                id, request_id, version_id, content_hash, signer_id, signer_role, signer_name,
                method, signed_at, provider_transaction, credential_ref, signed_artifact_hash,
                validation, status
            FROM signatures
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        row.as_ref().map(signature_from_row).transpose()
    }
}

impl UnitOfWork<'_> {
    pub async fn save_signature_request(
        &mut self,
        tenant_id: TenantId,
        request: &SignatureRequest,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO signature_requests (
                id, tenant_id, version_id, content_hash, signer_id, signer_role, signer_name,
                purpose, method, declaration, requested_at, deadline, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = signature_requests.row_version + 1
            "#,
        )
        .bind(request.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(request.version_id().as_uuid())
        .bind(request.content_hash().as_str())
        .bind(request.signer().as_uuid())
        .bind(request.signer_role().label())
        .bind(request.signer_name())
        .bind(request.purpose().label())
        .bind(request.method().label())
        .bind(request.declaration())
        .bind(request.requested_at())
        .bind(request.deadline())
        .bind(request.status().label())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }

    pub async fn save_signature(
        &mut self,
        tenant_id: TenantId,
        signature: &Signature,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO signatures (
                id, tenant_id, request_id, version_id, content_hash, signer_id, signer_role,
                signer_name, method, signed_at, provider_transaction, credential_ref,
                signed_artifact_hash, validation, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = signatures.row_version + 1
            "#,
        )
        .bind(signature.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(signature.request_id().as_uuid())
        .bind(signature.version_id().as_uuid())
        .bind(signature.content_hash().as_str())
        .bind(signature.signer().as_uuid())
        .bind(signature.signer_role().label())
        .bind(signature.signer_name())
        .bind(signature.method().label())
        .bind(signature.signed_at())
        .bind(signature.provider_transaction())
        .bind(signature.credential_ref())
        .bind(signature.signed_artifact_hash().as_str())
        .bind(signature.validation().label())
        .bind(signature.status().label())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

fn signature_request_from_row(row: &sqlx::postgres::PgRow) -> Result<SignatureRequest, StoreError> {
    let content_hash: String = row.try_get("content_hash")?;
    let signer_role: String = row.try_get("signer_role")?;
    let purpose: String = row.try_get("purpose")?;
    let method: String = row.try_get("method")?;
    let status: String = row.try_get("status")?;

    Ok(SignatureRequest::restore(
        SignatureRequestId::from_uuid(row.try_get("id")?),
        DocumentVersionId::from_uuid(row.try_get("version_id")?),
        ContentHash::from_hex(&content_hash)?,
        UserId::from_uuid(row.try_get("signer_id")?),
        actor_role(&signer_role)?,
        row.try_get("signer_name")?,
        signature_purpose(&purpose)?,
        signature_method(&method)?,
        row.try_get("declaration")?,
        row.try_get("requested_at")?,
        row.try_get("deadline")?,
        signature_request_status(&status)?,
    ))
}

fn signature_from_row(row: &sqlx::postgres::PgRow) -> Result<Signature, StoreError> {
    let content_hash: String = row.try_get("content_hash")?;
    let signer_role: String = row.try_get("signer_role")?;
    let method: String = row.try_get("method")?;
    let signed_artifact_hash: String = row.try_get("signed_artifact_hash")?;
    let validation: String = row.try_get("validation")?;
    let status: String = row.try_get("status")?;

    Ok(Signature::restore(
        SignatureId::from_uuid(row.try_get("id")?),
        SignatureRequestId::from_uuid(row.try_get("request_id")?),
        DocumentVersionId::from_uuid(row.try_get("version_id")?),
        ContentHash::from_hex(&content_hash)?,
        UserId::from_uuid(row.try_get("signer_id")?),
        actor_role(&signer_role)?,
        row.try_get("signer_name")?,
        signature_method(&method)?,
        row.try_get("signed_at")?,
        row.try_get("provider_transaction")?,
        row.try_get("credential_ref")?,
        ContentHash::from_hex(&signed_artifact_hash)?,
        signature_validation(&validation)?,
        signature_status(&status)?,
    ))
}

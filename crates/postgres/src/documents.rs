//! Document and document-version persistence.

use std::collections::BTreeMap;

use procuchain_records::{
    CanonicalContent, Document, DocumentVersion, RulePackRef, SchemaRef, TemplateRef,
};
use procuchain_shared::{ContentHash, DocumentId, DocumentVersionId, RulePackSnapshotId, TenantId};
use sqlx::Row;
use uuid::Uuid;

use crate::enums::{document_type, version_status};
use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn document_version_row_version(
        &self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
    ) -> Result<Option<i64>, StoreError> {
        let row = sqlx::query(
            "SELECT row_version FROM document_versions WHERE tenant_id = $1 AND id = $2",
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .fetch_optional(self.pool())
        .await?;
        row.map(|row| row.try_get("row_version"))
            .transpose()
            .map_err(StoreError::from)
    }

    pub async fn load_document(
        &self,
        tenant_id: TenantId,
        id: DocumentId,
    ) -> Result<Option<Document>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT document_type, title, business_id, current_version_id
            FROM documents
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

        let version_ids: Vec<Uuid> = sqlx::query_scalar(
            r#"
            SELECT id
            FROM document_versions
            WHERE tenant_id = $1 AND document_id = $2
            ORDER BY version_number
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let current_version_id: Option<Uuid> = row.try_get("current_version_id")?;
        let document_type_text: String = row.try_get("document_type")?;

        Ok(Some(Document::restore(
            id,
            tenant_id,
            document_type(&document_type_text)?,
            row.try_get("title")?,
            row.try_get("business_id")?,
            version_ids
                .into_iter()
                .map(DocumentVersionId::from_uuid)
                .collect(),
            current_version_id.map(DocumentVersionId::from_uuid),
        )))
    }

    pub async fn load_document_version(
        &self,
        tenant_id: TenantId,
        id: DocumentVersionId,
    ) -> Result<Option<DocumentVersion>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                document_id, version_number, status, content, content_hash,
                template_id, template_version, template_authority_ref,
                schema_id, schema_version,
                rule_pack_snapshot_id, rule_pack_version_label, rule_pack_manifest_hash,
                author_id, authored_at, correction_of, supersedes
            FROM document_versions
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

        Ok(Some(version_from_row(&row, id)?))
    }

    pub async fn versions_for_document(
        &self,
        tenant_id: TenantId,
        document_id: DocumentId,
    ) -> Result<Vec<DocumentVersion>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, document_id, version_number, status, content, content_hash,
                template_id, template_version, template_authority_ref,
                schema_id, schema_version,
                rule_pack_snapshot_id, rule_pack_version_label, rule_pack_manifest_hash,
                author_id, authored_at, correction_of, supersedes
            FROM document_versions
            WHERE tenant_id = $1 AND document_id = $2
            ORDER BY version_number
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(document_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter()
            .map(|row| {
                let id = DocumentVersionId::from_uuid(row.try_get("id")?);
                version_from_row(row, id)
            })
            .collect()
    }
}

impl UnitOfWork<'_> {
    pub async fn update_document_version_status_if_row_version(
        &mut self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
        status: &str,
        expected_row_version: i64,
    ) -> Result<bool, StoreError> {
        let result = sqlx::query(
            "UPDATE document_versions SET status = $3, row_version = row_version + 1 WHERE tenant_id = $1 AND id = $2 AND row_version = $4",
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .bind(status)
        .bind(expected_row_version)
        .execute(&mut *self.transaction)
        .await?;
        Ok(result.rows_affected() == 1)
    }

    pub async fn save_document(&mut self, document: &Document) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO documents (
                id, tenant_id, document_type, title, business_id, current_version_id
            )
            VALUES ($1, $2, $3, $4, $5, $6)
            ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title,
                business_id = EXCLUDED.business_id,
                current_version_id = EXCLUDED.current_version_id,
                row_version = documents.row_version + 1
            "#,
        )
        .bind(document.id().as_uuid())
        .bind(document.tenant_id().as_uuid())
        .bind(document.document_type().label())
        .bind(document.title())
        .bind(document.business_id())
        .bind(document.current_version_id().map(|id| id.as_uuid()))
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }

    pub async fn save_document_version(
        &mut self,
        tenant_id: TenantId,
        version: &DocumentVersion,
    ) -> Result<(), StoreError> {
        let content = serde_json::to_value(version.content().fields())?;

        sqlx::query(
            r#"
            INSERT INTO document_versions (
                id, tenant_id, document_id, version_number, status, content, content_hash,
                template_id, template_version, template_authority_ref,
                schema_id, schema_version,
                rule_pack_snapshot_id, rule_pack_version_label, rule_pack_manifest_hash,
                author_id, authored_at, correction_of, supersedes
            )
            VALUES (
                $1, $2, $3, $4, $5, $6, $7,
                $8, $9, $10,
                $11, $12,
                $13, $14, $15,
                $16, $17, $18, $19
            )
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = document_versions.row_version + 1
            "#,
        )
        .bind(version.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(version.document_id().as_uuid())
        .bind(version.version_number() as i32)
        .bind(version.status().label())
        .bind(&content)
        .bind(version.content_hash().as_str())
        .bind(version.template().template_id())
        .bind(version.template().version())
        .bind(version.template().authority_ref())
        .bind(version.schema().schema_id())
        .bind(version.schema().version())
        .bind(version.rule_pack().snapshot_id().as_uuid())
        .bind(version.rule_pack().version_label())
        .bind(version.rule_pack().manifest_hash().as_str())
        .bind(version.author().as_uuid())
        .bind(version.authored_at())
        .bind(version.correction_of().map(|id| id.as_uuid()))
        .bind(version.supersedes().map(|id| id.as_uuid()))
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }
}

fn version_from_row(
    row: &sqlx::postgres::PgRow,
    id: DocumentVersionId,
) -> Result<DocumentVersion, StoreError> {
    let content_json: serde_json::Value = row.try_get("content")?;
    let fields: BTreeMap<String, serde_json::Value> = serde_json::from_value(content_json)?;
    let content_hash_text: String = row.try_get("content_hash")?;
    let template_id: String = row.try_get("template_id")?;
    let template_version: String = row.try_get("template_version")?;
    let template_authority_ref: String = row.try_get("template_authority_ref")?;
    let schema_id: String = row.try_get("schema_id")?;
    let schema_version: String = row.try_get("schema_version")?;
    let rule_pack_snapshot_id: Uuid = row.try_get("rule_pack_snapshot_id")?;
    let rule_pack_version_label: String = row.try_get("rule_pack_version_label")?;
    let rule_pack_manifest_hash: String = row.try_get("rule_pack_manifest_hash")?;
    let status_text: String = row.try_get("status")?;
    let author_id: Uuid = row.try_get("author_id")?;
    let correction_of: Option<Uuid> = row.try_get("correction_of")?;
    let supersedes: Option<Uuid> = row.try_get("supersedes")?;

    let template = TemplateRef::new(template_id, template_version, template_authority_ref)?;
    let schema = SchemaRef::new(schema_id, schema_version)?;
    let rule_pack = RulePackRef::new(
        RulePackSnapshotId::from_uuid(rule_pack_snapshot_id),
        rule_pack_version_label,
        ContentHash::from_hex(&rule_pack_manifest_hash)?,
    )?;

    Ok(DocumentVersion::restore(
        id,
        DocumentId::from_uuid(row.try_get("document_id")?),
        row.try_get::<i32, _>("version_number")? as u32,
        version_status(&status_text)?,
        CanonicalContent::new(fields),
        ContentHash::from_hex(&content_hash_text)?,
        template,
        schema,
        procuchain_shared::UserId::from_uuid(author_id),
        row.try_get("authored_at")?,
        rule_pack,
        correction_of.map(DocumentVersionId::from_uuid),
        supersedes.map(DocumentVersionId::from_uuid),
    )?)
}

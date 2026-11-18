//! Approval and BAC persistence.

use std::collections::{BTreeMap, BTreeSet};

use procuchain_shared::{
    ApprovalId, BacResolutionId, CommitteeId, ContentHash, DocumentVersionId, TenantId, UserId,
};
use procuchain_workflow::{Approval, BacMemberSnapshot, BacMembershipSnapshot, BacResolution};
use sqlx::Row;
use uuid::Uuid;

use crate::enums::{
    actor_role, approval_decision, approval_status, bac_outcome, bac_outcome_label,
    bac_resolution_status, bac_resolution_status_label, bac_seat, bac_seat_label, bac_vote,
    bac_vote_label,
};
use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn approvals_for_version(
        &self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
    ) -> Result<Vec<Approval>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, version_id, content_hash, approver_id, approver_role, approver_name,
                decision, decided_at, authority_ref, grounds, status
            FROM approvals
            WHERE tenant_id = $1 AND version_id = $2
            ORDER BY decided_at, id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        rows.iter().map(approval_from_row).collect()
    }

    pub async fn load_approval(
        &self,
        tenant_id: TenantId,
        id: ApprovalId,
    ) -> Result<Option<Approval>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                id, version_id, content_hash, approver_id, approver_role, approver_name,
                decision, decided_at, authority_ref, grounds, status
            FROM approvals
            WHERE tenant_id = $1 AND id = $2
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_optional(self.pool())
        .await?;

        row.as_ref().map(approval_from_row).transpose()
    }

    pub async fn bac_resolutions_for_version(
        &self,
        tenant_id: TenantId,
        version_id: DocumentVersionId,
    ) -> Result<Vec<BacResolution>, StoreError> {
        let rows = sqlx::query(
            r#"
            SELECT
                id, version_id, version_hash, committee_id, resolution_number, outcome,
                quorum_required, resolved_at, status
            FROM bac_resolutions
            WHERE tenant_id = $1 AND version_id = $2
            ORDER BY resolved_at, id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(version_id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let mut resolutions = Vec::with_capacity(rows.len());
        for row in rows {
            let id = BacResolutionId::from_uuid(row.try_get("id")?);
            resolutions.push(self.load_bac_resolution_parts(tenant_id, id, &row).await?);
        }
        Ok(resolutions)
    }

    pub async fn load_bac_resolution(
        &self,
        tenant_id: TenantId,
        id: BacResolutionId,
    ) -> Result<Option<BacResolution>, StoreError> {
        let row = sqlx::query(
            r#"
            SELECT
                id, version_id, version_hash, committee_id, resolution_number, outcome,
                quorum_required, resolved_at, status
            FROM bac_resolutions
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
        Ok(Some(
            self.load_bac_resolution_parts(tenant_id, id, &row).await?,
        ))
    }

    async fn load_bac_resolution_parts(
        &self,
        tenant_id: TenantId,
        id: BacResolutionId,
        row: &sqlx::postgres::PgRow,
    ) -> Result<BacResolution, StoreError> {
        let member_rows = sqlx::query(
            r#"
            SELECT user_id, seat, display_name
            FROM bac_resolution_members
            WHERE tenant_id = $1 AND resolution_id = $2
            ORDER BY ordinal
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;

        let mut members = Vec::with_capacity(member_rows.len());
        for member_row in member_rows {
            let seat_text: String = member_row.try_get("seat")?;
            members.push(BacMemberSnapshot::new(
                UserId::from_uuid(member_row.try_get("user_id")?),
                bac_seat(&seat_text)?,
                member_row.try_get::<String, _>("display_name")?,
            ));
        }

        let committee_id: Uuid = row.try_get("committee_id")?;
        let membership = BacMembershipSnapshot::new(CommitteeId::from_uuid(committee_id), members)?;

        let attendance_rows: Vec<Uuid> = sqlx::query_scalar(
            r#"
            SELECT user_id
            FROM bac_resolution_attendance
            WHERE tenant_id = $1 AND resolution_id = $2
            ORDER BY user_id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;
        let attendance: BTreeSet<UserId> =
            attendance_rows.into_iter().map(UserId::from_uuid).collect();

        let vote_rows = sqlx::query(
            r#"
            SELECT user_id, vote
            FROM bac_resolution_votes
            WHERE tenant_id = $1 AND resolution_id = $2
            ORDER BY user_id
            "#,
        )
        .bind(tenant_id.as_uuid())
        .bind(id.as_uuid())
        .fetch_all(self.pool())
        .await?;
        let mut votes = BTreeMap::new();
        for vote_row in vote_rows {
            let vote_text: String = vote_row.try_get("vote")?;
            votes.insert(
                UserId::from_uuid(vote_row.try_get("user_id")?),
                bac_vote(&vote_text)?,
            );
        }

        let outcome_text: String = row.try_get("outcome")?;
        let status_text: String = row.try_get("status")?;
        let version_hash: String = row.try_get("version_hash")?;

        Ok(BacResolution::restore(
            id,
            DocumentVersionId::from_uuid(row.try_get("version_id")?),
            ContentHash::from_hex(&version_hash)?,
            row.try_get("resolution_number")?,
            membership,
            attendance,
            votes,
            bac_outcome(&outcome_text)?,
            row.try_get::<i32, _>("quorum_required")? as usize,
            row.try_get("resolved_at")?,
            bac_resolution_status(&status_text)?,
        ))
    }
}

impl UnitOfWork<'_> {
    pub async fn save_approval(
        &mut self,
        tenant_id: TenantId,
        approval: &Approval,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO approvals (
                id, tenant_id, version_id, content_hash, approver_id, approver_role,
                approver_name, decision, decided_at, authority_ref, grounds, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = approvals.row_version + 1
            "#,
        )
        .bind(approval.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(approval.version_id().as_uuid())
        .bind(approval.content_hash().as_str())
        .bind(approval.approver().as_uuid())
        .bind(approval.approver_role().label())
        .bind(approval.approver_name())
        .bind(approval.decision().label())
        .bind(approval.decided_at())
        .bind(approval.authority_ref())
        .bind(approval.grounds())
        .bind(approval.status().label())
        .execute(&mut *self.transaction)
        .await?;
        Ok(())
    }

    pub async fn save_bac_resolution(
        &mut self,
        tenant_id: TenantId,
        resolution: &BacResolution,
    ) -> Result<(), StoreError> {
        sqlx::query(
            r#"
            INSERT INTO bac_resolutions (
                id, tenant_id, version_id, version_hash, committee_id, resolution_number,
                outcome, quorum_required, resolved_at, status
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                row_version = bac_resolutions.row_version + 1
            "#,
        )
        .bind(resolution.id().as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(resolution.version_id().as_uuid())
        .bind(resolution.version_hash().as_str())
        .bind(resolution.membership().committee_id().as_uuid())
        .bind(resolution.resolution_number())
        .bind(bac_outcome_label(resolution.outcome()))
        .bind(resolution.quorum_required() as i32)
        .bind(resolution.resolved_at())
        .bind(bac_resolution_status_label(resolution.status()))
        .execute(&mut *self.transaction)
        .await?;

        for (ordinal, member) in resolution.membership().members().iter().enumerate() {
            sqlx::query(
                r#"
                INSERT INTO bac_resolution_members (
                    tenant_id, resolution_id, user_id, seat, display_name, ordinal
                )
                VALUES ($1, $2, $3, $4, $5, $6)
                ON CONFLICT (resolution_id, user_id) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(resolution.id().as_uuid())
            .bind(member.user_id().as_uuid())
            .bind(bac_seat_label(member.seat()))
            .bind(member.display_name())
            .bind(ordinal as i32)
            .execute(&mut *self.transaction)
            .await?;
        }

        for attendee in resolution.attendance() {
            sqlx::query(
                r#"
                INSERT INTO bac_resolution_attendance (tenant_id, resolution_id, user_id)
                VALUES ($1, $2, $3)
                ON CONFLICT (resolution_id, user_id) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(resolution.id().as_uuid())
            .bind(attendee.as_uuid())
            .execute(&mut *self.transaction)
            .await?;
        }

        for (voter, vote) in resolution.votes() {
            sqlx::query(
                r#"
                INSERT INTO bac_resolution_votes (tenant_id, resolution_id, user_id, vote)
                VALUES ($1, $2, $3, $4)
                ON CONFLICT (resolution_id, user_id) DO NOTHING
                "#,
            )
            .bind(tenant_id.as_uuid())
            .bind(resolution.id().as_uuid())
            .bind(voter.as_uuid())
            .bind(bac_vote_label(*vote))
            .execute(&mut *self.transaction)
            .await?;
        }

        Ok(())
    }
}

fn approval_from_row(row: &sqlx::postgres::PgRow) -> Result<Approval, StoreError> {
    let content_hash: String = row.try_get("content_hash")?;
    let approver_role_text: String = row.try_get("approver_role")?;
    let decision_text: String = row.try_get("decision")?;
    let status_text: String = row.try_get("status")?;

    Ok(Approval::restore(
        ApprovalId::from_uuid(row.try_get("id")?),
        DocumentVersionId::from_uuid(row.try_get("version_id")?),
        ContentHash::from_hex(&content_hash)?,
        UserId::from_uuid(row.try_get("approver_id")?),
        actor_role(&approver_role_text)?,
        row.try_get("approver_name")?,
        approval_decision(&decision_text)?,
        row.try_get("decided_at")?,
        row.try_get("authority_ref")?,
        row.try_get("grounds")?,
        approval_status(&status_text)?,
    ))
}

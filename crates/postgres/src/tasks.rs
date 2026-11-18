use procuchain_shared::{TenantId, UserId, WorkflowTaskId};
use procuchain_workflow::{TaskStatus, WorkflowTask};
use sqlx::Row;

use crate::{PgStore, StoreError, UnitOfWork};

impl PgStore {
    pub async fn load_workflow_task(
        &self,
        tenant_id: TenantId,
        id: WorkflowTaskId,
    ) -> Result<Option<WorkflowTask>, StoreError> {
        let row = sqlx::query("SELECT task_type, subject_ref, assignee_id, status, outcome, row_version FROM workflow_tasks WHERE tenant_id = $1 AND id = $2")
            .bind(tenant_id.as_uuid()).bind(id.as_uuid()).fetch_optional(self.pool()).await?;
        row.map(|row| restore_task(id, tenant_id, row)).transpose()
    }
}

impl UnitOfWork<'_> {
    pub async fn insert_workflow_task(&mut self, task: &WorkflowTask) -> Result<(), StoreError> {
        sqlx::query("INSERT INTO workflow_tasks (id, tenant_id, task_type, subject_ref, assignee_id, status, outcome, row_version) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)")
            .bind(task.id().as_uuid()).bind(task.tenant_id().as_uuid()).bind(task.task_type()).bind(task.subject_ref())
            .bind(task.assignee().map(|id| id.as_uuid())).bind(task.status().label()).bind(task.outcome()).bind(task.row_version())
            .execute(&mut *self.transaction).await?;
        Ok(())
    }

    pub async fn update_workflow_task_if_row_version(
        &mut self,
        task: &WorkflowTask,
        expected: i64,
    ) -> Result<bool, StoreError> {
        let result = sqlx::query("UPDATE workflow_tasks SET assignee_id = $3, status = $4, outcome = $5, row_version = $6, updated_at = now() WHERE tenant_id = $1 AND id = $2 AND row_version = $7")
            .bind(task.tenant_id().as_uuid()).bind(task.id().as_uuid()).bind(task.assignee().map(|id| id.as_uuid()))
            .bind(task.status().label()).bind(task.outcome()).bind(task.row_version()).bind(expected).execute(&mut *self.transaction).await?;
        Ok(result.rows_affected() == 1)
    }
}

fn restore_task(
    id: WorkflowTaskId,
    tenant_id: TenantId,
    row: sqlx::postgres::PgRow,
) -> Result<WorkflowTask, StoreError> {
    let status = match row.try_get::<String, _>("status")?.as_str() {
        "pending" => TaskStatus::Pending,
        "claimed" => TaskStatus::Claimed,
        "completed" => TaskStatus::Completed,
        value => {
            return Err(StoreError::invalid_value(format!(
                "unknown workflow task status: {value}"
            )));
        }
    };
    Ok(WorkflowTask::restore(
        id,
        tenant_id,
        row.try_get("task_type")?,
        row.try_get("subject_ref")?,
        row.try_get::<Option<uuid::Uuid>, _>("assignee_id")?
            .map(UserId::from_uuid),
        status,
        row.try_get("outcome")?,
        row.try_get("row_version")?,
    )?)
}

use procuchain_shared::{DomainError, TenantId, UserId, WorkflowTaskId};
use serde::{Deserialize, Serialize};

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum TaskStatus {
    Pending,
    Claimed,
    Completed,
}

impl TaskStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Pending => "pending",
            Self::Claimed => "claimed",
            Self::Completed => "completed",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct WorkflowTask {
    id: WorkflowTaskId,
    tenant_id: TenantId,
    task_type: String,
    subject_ref: String,
    assignee: Option<UserId>,
    status: TaskStatus,
    outcome: Option<String>,
    row_version: i64,
}

impl WorkflowTask {
    pub fn create(
        id: WorkflowTaskId,
        tenant_id: TenantId,
        task_type: impl Into<String>,
        subject_ref: impl Into<String>,
    ) -> Result<Self, DomainError> {
        let task_type = task_type.into();
        let subject_ref = subject_ref.into();
        if task_type.trim().is_empty() {
            return Err(DomainError::rule("workflow task type must not be empty"));
        }
        if subject_ref.trim().is_empty() {
            return Err(DomainError::rule("workflow task subject must not be empty"));
        }
        Ok(Self {
            id,
            tenant_id,
            task_type,
            subject_ref,
            assignee: None,
            status: TaskStatus::Pending,
            outcome: None,
            row_version: 0,
        })
    }

    pub fn restore(
        id: WorkflowTaskId,
        tenant_id: TenantId,
        task_type: String,
        subject_ref: String,
        assignee: Option<UserId>,
        status: TaskStatus,
        outcome: Option<String>,
        row_version: i64,
    ) -> Result<Self, DomainError> {
        let mut task = Self::create(id, tenant_id, task_type, subject_ref)?;
        if row_version < 0 {
            return Err(DomainError::rule(
                "workflow task row version must not be negative",
            ));
        }
        if matches!(status, TaskStatus::Pending) && assignee.is_some() {
            return Err(DomainError::rule(
                "pending workflow task must not have an assignee",
            ));
        }
        if matches!(status, TaskStatus::Completed) && outcome.as_deref().is_none_or(str::is_empty) {
            return Err(DomainError::rule(
                "completed workflow task must have an outcome",
            ));
        }
        task.assignee = assignee;
        task.status = status;
        task.outcome = outcome;
        task.row_version = row_version;
        Ok(task)
    }

    pub fn claim(&mut self, user_id: UserId) -> Result<(), DomainError> {
        if !matches!(self.status, TaskStatus::Pending) {
            return Err(DomainError::rule("workflow task is no longer pending"));
        }
        self.assignee = Some(user_id);
        self.status = TaskStatus::Claimed;
        self.row_version += 1;
        Ok(())
    }

    pub fn complete(
        &mut self,
        user_id: UserId,
        outcome: impl Into<String>,
    ) -> Result<(), DomainError> {
        if !matches!(self.status, TaskStatus::Claimed) {
            return Err(DomainError::rule(
                "only a claimed workflow task can be completed",
            ));
        }
        if self.assignee != Some(user_id) {
            return Err(DomainError::unauthorized(
                "only the assigned user can complete this workflow task",
            ));
        }
        let outcome = outcome.into();
        if outcome.trim().is_empty() {
            return Err(DomainError::rule("workflow task outcome must not be empty"));
        }
        self.outcome = Some(outcome);
        self.status = TaskStatus::Completed;
        self.row_version += 1;
        Ok(())
    }

    pub fn id(&self) -> WorkflowTaskId {
        self.id
    }
    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }
    pub fn task_type(&self) -> &str {
        &self.task_type
    }
    pub fn subject_ref(&self) -> &str {
        &self.subject_ref
    }
    pub fn assignee(&self) -> Option<UserId> {
        self.assignee
    }
    pub fn status(&self) -> &TaskStatus {
        &self.status
    }
    pub fn outcome(&self) -> Option<&str> {
        self.outcome.as_deref()
    }
    pub fn row_version(&self) -> i64 {
        self.row_version
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn task_claim_and_completion_are_assignee_bound() {
        let tenant = TenantId::new();
        let assignee = UserId::new();
        let other = UserId::new();
        let mut task =
            WorkflowTask::create(WorkflowTaskId::new(), tenant, "review", "ppmp:1").unwrap();
        task.claim(assignee).unwrap();
        assert!(task.complete(other, "approved").is_err());
        task.complete(assignee, "approved").unwrap();
        assert_eq!(task.status(), &TaskStatus::Completed);
    }
}

CREATE TABLE workflow_tasks (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL REFERENCES tenants (id),
    task_type text NOT NULL CHECK (btrim(task_type) <> ''),
    subject_ref text NOT NULL CHECK (btrim(subject_ref) <> ''),
    assignee_id uuid,
    status text NOT NULL CHECK (status IN ('pending', 'claimed', 'completed')),
    outcome text,
    row_version bigint NOT NULL DEFAULT 0 CHECK (row_version >= 0),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, id),
    FOREIGN KEY (tenant_id, assignee_id) REFERENCES users (tenant_id, id),
    CHECK ((status = 'pending' AND assignee_id IS NULL) OR status <> 'pending'),
    CHECK ((status = 'completed' AND btrim(coalesce(outcome, '')) <> '') OR status <> 'completed')
);

CREATE INDEX workflow_tasks_inbox ON workflow_tasks (tenant_id, status, assignee_id, updated_at);

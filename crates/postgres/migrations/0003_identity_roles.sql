-- Durable Development identity and role assignments.

CREATE TABLE user_roles (
    tenant_id uuid NOT NULL REFERENCES tenants (id),
    user_id uuid NOT NULL,
    role text NOT NULL CHECK (
        role IN (
            'end_user', 'end_user_head', 'bac_chairperson',
            'bac_vice_chairperson', 'bac_member', 'hope',
            'secretariat', 'records_officer', 'system'
        )
    ),
    assigned_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, user_id, role),
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id)
);

CREATE INDEX user_roles_lookup ON user_roles (tenant_id, user_id);

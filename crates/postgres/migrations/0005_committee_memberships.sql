-- HoPE-designated BAC membership used to build action-time snapshots.

CREATE TABLE committee_memberships (
    tenant_id uuid NOT NULL REFERENCES tenants (id),
    committee_id uuid NOT NULL,
    user_id uuid NOT NULL,
    seat text NOT NULL CHECK (seat IN ('chairperson', 'vice_chairperson', 'member')),
    active boolean NOT NULL DEFAULT true,
    assigned_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, committee_id, user_id),
    FOREIGN KEY (tenant_id, committee_id) REFERENCES committees (tenant_id, id),
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id)
);

CREATE INDEX committee_memberships_lookup
    ON committee_memberships (tenant_id, committee_id, active, seat);

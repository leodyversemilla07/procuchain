-- Evidence anchoring: Merkle roots over contiguous audit ranges.
--
-- Anchors are the only evidence that may be corroborated outside the
-- database. They contain no document content, personal data, or bid data:
-- only a sequence range, a Merkle root, and the previous anchor hash.

CREATE TABLE evidence_anchors (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL REFERENCES tenants (id),
    from_seq bigint NOT NULL CHECK (from_seq > 0),
    to_seq bigint NOT NULL CHECK (to_seq >= from_seq),
    event_count bigint NOT NULL CHECK (event_count = to_seq - from_seq + 1),
    merkle_root text NOT NULL CHECK (merkle_root ~ '^[0-9a-f]{64}$'),
    previous_anchor_hash text NOT NULL CHECK (previous_anchor_hash ~ '^[0-9a-f]{64}$'),
    anchor_hash text NOT NULL CHECK (anchor_hash ~ '^[0-9a-f]{64}$'),
    schema_version text NOT NULL CHECK (btrim(schema_version) <> ''),
    created_at timestamptz NOT NULL,
    UNIQUE (tenant_id, id),
    UNIQUE (tenant_id, from_seq),
    UNIQUE (tenant_id, to_seq)
);

CREATE TABLE evidence_anchor_receipts (
    anchor_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    provider text NOT NULL CHECK (btrim(provider) <> ''),
    provider_ref text NOT NULL CHECK (btrim(provider_ref) <> ''),
    received_at timestamptz NOT NULL,
    token bytea CHECK (token IS NULL OR octet_length(token) > 0),
    PRIMARY KEY (anchor_id, provider, provider_ref),
    FOREIGN KEY (tenant_id, anchor_id) REFERENCES evidence_anchors (tenant_id, id)
);

CREATE INDEX evidence_anchors_tenant_range_idx ON evidence_anchors (tenant_id, to_seq);

-- Anchors and their receipts are append-only. A corrected audit interpretation
-- creates a new anchor later in the chain; it never rewrites an existing one.
CREATE FUNCTION reject_anchor_mutation() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION '% rows are append-only', TG_TABLE_NAME;
END;
$$;

CREATE TRIGGER evidence_anchors_no_mutation
BEFORE UPDATE OR DELETE ON evidence_anchors
FOR EACH ROW EXECUTE FUNCTION reject_anchor_mutation();

CREATE TRIGGER evidence_anchor_receipts_no_mutation
BEFORE UPDATE OR DELETE ON evidence_anchor_receipts
FOR EACH ROW EXECUTE FUNCTION reject_anchor_mutation();

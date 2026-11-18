ALTER TABLE issuance_artifacts
    ADD COLUMN object_key text;

ALTER TABLE issuance_artifacts
    ADD CONSTRAINT issuance_artifacts_object_key_not_blank
    CHECK (object_key IS NULL OR btrim(object_key) <> '');

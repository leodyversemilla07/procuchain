//! Persistence contract tests for the first governed procurement slice.
//!
//! These tests need a dedicated PostgreSQL database. Set
//! `PROCUCHAIN_TEST_DATABASE_URL` (for example
//! `postgres://procuchain:procuchain_local_dev@127.0.0.1:5432/procuchain_test`).
//! When it is absent the tests skip instead of failing, so `cargo test` still
//! works on a machine without PostgreSQL.
//!
//! Every test uses fresh identifiers, so a shared test database does not need
//! to be reset between runs.

use std::collections::{BTreeMap, BTreeSet};

use procuchain_evidence::{AnchorBatch, AnchorReceipt, AnchorRecord, EvidenceAnchor};
use procuchain_issuance::{
    Acknowledgement, AcknowledgementMethod, DeliveryAttempt, DeliveryResult, Distribution,
    DistributionChannel, Issuance, IssuanceArtifact, IssuanceRecipient, Recipient, RecipientKind,
};
use procuchain_postgres::{NewOutboxEvent, PgStore, StoreError};
use procuchain_records::{
    CanonicalContent, Document, DocumentType, DocumentVersion, RulePackRef, SchemaRef, TemplateRef,
};
use procuchain_shared::{
    AcknowledgementId, Actor, ActorRole, AnchorId, ApprovalId, ArtifactId, BacResolutionId,
    CommitteeId, ContentHash, DistributionId, DocumentId, DocumentVersionId, DomainError,
    IssuanceId, RecipientId, RulePackSnapshotId, SignatureId, SignatureRequestId, TenantId, UserId,
};
use procuchain_signatures::{
    Signature, SignatureMethod, SignaturePurpose, SignatureRequest, SignatureRequestStatus,
    SignatureValidation,
};
use procuchain_workflow::{
    Approval, ApprovalDecision, ApprovalStatus, BacMemberSnapshot, BacMembershipSnapshot,
    BacResolution, BacResolutionStatus, BacSeat, BacVote,
};
use time::OffsetDateTime;

const PRIORITY_OFFSET: i64 = 1_800_000_000;

fn timestamp(offset_seconds: i64) -> OffsetDateTime {
    OffsetDateTime::from_unix_timestamp(PRIORITY_OFFSET + offset_seconds)
        .expect("fixed test timestamp is valid")
}

async fn test_store() -> Option<PgStore> {
    let Ok(url) = std::env::var("PROCUCHAIN_TEST_DATABASE_URL") else {
        eprintln!("skipping persistence test: PROCUCHAIN_TEST_DATABASE_URL is not set");
        return None;
    };
    let store = PgStore::connect(&url)
        .await
        .expect("test database must be reachable");
    store.migrate().await.expect("migrations must apply");
    Some(store)
}

struct Seed {
    tenant_id: TenantId,
    author: UserId,
    hope: UserId,
    chair: UserId,
    vice_chair: UserId,
    member_a: UserId,
    member_b: UserId,
    member_c: UserId,
    committee_id: CommitteeId,
}

impl Seed {
    async fn new(store: &PgStore) -> Self {
        let tenant_id = TenantId::new();
        sqlx::query("INSERT INTO tenants (id, name) VALUES ($1, $2)")
            .bind(tenant_id.as_uuid())
            .bind("Test Municipality")
            .execute(store.pool())
            .await
            .expect("tenant insert");

        let author = insert_user(store, tenant_id, "Document Author").await;
        let hope = insert_user(store, tenant_id, "Head of the Procuring Entity").await;
        let chair = insert_user(store, tenant_id, "BAC Chairperson").await;
        let vice_chair = insert_user(store, tenant_id, "BAC Vice-Chairperson").await;
        let member_a = insert_user(store, tenant_id, "BAC Member A").await;
        let member_b = insert_user(store, tenant_id, "BAC Member B").await;
        let member_c = insert_user(store, tenant_id, "BAC Member C").await;

        let committee_id = CommitteeId::new();
        sqlx::query("INSERT INTO committees (id, tenant_id, name) VALUES ($1, $2, $3)")
            .bind(committee_id.as_uuid())
            .bind(tenant_id.as_uuid())
            .bind("Municipal BAC")
            .execute(store.pool())
            .await
            .expect("committee insert");

        Self {
            tenant_id,
            author,
            hope,
            chair,
            vice_chair,
            member_a,
            member_b,
            member_c,
            committee_id,
        }
    }

    fn membership(&self) -> BacMembershipSnapshot {
        BacMembershipSnapshot::new(
            self.committee_id,
            vec![
                BacMemberSnapshot::new(self.chair, BacSeat::Chairperson, "BAC Chairperson"),
                BacMemberSnapshot::new(
                    self.vice_chair,
                    BacSeat::ViceChairperson,
                    "BAC Vice-Chairperson",
                ),
                BacMemberSnapshot::new(self.member_a, BacSeat::Member, "BAC Member A"),
                BacMemberSnapshot::new(self.member_b, BacSeat::Member, "BAC Member B"),
                BacMemberSnapshot::new(self.member_c, BacSeat::Member, "BAC Member C"),
            ],
        )
        .expect("five-member BAC is valid")
    }
}

async fn insert_user(store: &PgStore, tenant_id: TenantId, display_name: &str) -> UserId {
    let user_id = UserId::new();
    sqlx::query("INSERT INTO users (id, tenant_id, display_name) VALUES ($1, $2, $3)")
        .bind(user_id.as_uuid())
        .bind(tenant_id.as_uuid())
        .bind(display_name)
        .execute(store.pool())
        .await
        .expect("user insert");
    user_id
}

fn rule_pack() -> RulePackRef {
    RulePackRef::new(
        RulePackSnapshotId::new(),
        "rp-2026-a",
        ContentHash::sha256(b"rule-pack-2026-a"),
    )
    .expect("rule pack is valid")
}

fn document_fixture(seed: &Seed) -> (Document, DocumentVersion) {
    let document_id = DocumentId::new();
    let mut document = Document::new(
        document_id,
        seed.tenant_id,
        DocumentType::AnnualProcurementPlan,
        "Final APP FY 2026",
    )
    .expect("document is valid");
    document
        .set_business_id(format!("APP-2026-{document_id}"))
        .expect("business id is valid");

    let version_id = DocumentVersionId::new();
    let version_number = document
        .register_version(version_id)
        .expect("first version registers");
    let version = DocumentVersion::new(
        version_id,
        document_id,
        version_number,
        CanonicalContent::from_pairs([
            ("edition", serde_json::json!("final")),
            ("fiscal_year", serde_json::json!(2026)),
        ]),
        TemplateRef::new("app-template", "2026.1", "PE-controlled GPPB form register")
            .expect("template is valid"),
        SchemaRef::new("app-schema", "1.0").expect("schema is valid"),
        seed.author,
        timestamp(0),
        rule_pack(),
        None,
        None,
    );
    (document, version)
}

async fn save_document(store: &PgStore, seed: &Seed) -> (Document, DocumentVersion) {
    let (document, version) = document_fixture(seed);
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_document(&document).await.expect("document saves");
    unit.save_document_version(seed.tenant_id, &version)
        .await
        .expect("version saves");
    unit.commit().await.expect("transaction commits");
    (document, version)
}

#[tokio::test]
async fn document_and_version_round_trip() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (document, version) = save_document(&store, &seed).await;

    let loaded = store
        .load_document(seed.tenant_id, document.id())
        .await
        .expect("document loads")
        .expect("document exists");
    assert_eq!(loaded, document);
    assert_eq!(loaded.current_version_id(), Some(version.id()));

    let loaded_version = store
        .load_document_version(seed.tenant_id, version.id())
        .await
        .expect("version loads")
        .expect("version exists");
    assert_eq!(loaded_version, version);
    assert_eq!(loaded_version.content_hash(), version.content_hash());

    let versions = store
        .versions_for_document(seed.tenant_id, document.id())
        .await
        .expect("versions load");
    assert_eq!(versions, vec![version]);

    assert!(
        store
            .load_document(seed.tenant_id, DocumentId::new())
            .await
            .expect("lookup succeeds")
            .is_none()
    );
}

#[tokio::test]
async fn user_roles_round_trip_from_postgres() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let mut unit = store.begin().await.expect("transaction begins");
    unit.assign_role(
        seed.tenant_id,
        seed.author,
        ActorRole::EndUser,
        timestamp(0),
    )
    .await
    .expect("role assignment persists");
    unit.commit().await.expect("transaction commits");

    let identity = store
        .load_user_identity(seed.tenant_id, seed.author)
        .await
        .expect("identity loads")
        .expect("identity exists");
    assert_eq!(identity.user_id, seed.author);
    assert!(identity.active);
    assert!(identity.roles.contains(&ActorRole::EndUser));
}

#[tokio::test]
async fn stored_version_content_cannot_be_changed_or_deleted() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (_, version) = save_document(&store, &seed).await;

    let update =
        sqlx::query("UPDATE document_versions SET content = $1 WHERE tenant_id = $2 AND id = $3")
            .bind(serde_json::json!({"tampered": true}))
            .bind(seed.tenant_id.as_uuid())
            .bind(version.id().as_uuid())
            .execute(store.pool())
            .await;
    assert!(
        update.is_err(),
        "content updates must be rejected by trigger"
    );

    let delete = sqlx::query("DELETE FROM document_versions WHERE tenant_id = $1 AND id = $2")
        .bind(seed.tenant_id.as_uuid())
        .bind(version.id().as_uuid())
        .execute(store.pool())
        .await;
    assert!(
        delete.is_err(),
        "version deletes must be rejected by trigger"
    );

    // Status transitions remain allowed and are observable after rehydration.
    sqlx::query(
        "UPDATE document_versions SET status = 'approved' WHERE tenant_id = $1 AND id = $2",
    )
    .bind(seed.tenant_id.as_uuid())
    .bind(version.id().as_uuid())
    .execute(store.pool())
    .await
    .expect("status transitions are allowed");
    let loaded = store
        .load_document_version(seed.tenant_id, version.id())
        .await
        .expect("version loads")
        .expect("version exists");
    assert_eq!(loaded.status(), procuchain_records::VersionStatus::Approved);
}

#[tokio::test]
async fn reading_a_version_recomputes_the_content_hash() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (document, version) = save_document(&store, &seed).await;
    let forged_id = DocumentVersionId::new();

    sqlx::query(
        r#"
        INSERT INTO document_versions (
            id, tenant_id, document_id, version_number, status, content, content_hash,
            template_id, template_version, template_authority_ref,
            schema_id, schema_version,
            rule_pack_snapshot_id, rule_pack_version_label, rule_pack_manifest_hash,
            author_id, authored_at
        )
        VALUES (
            $1, $2, $3, 2, 'draft', $4, $5,
            'app-template', '2026.1', 'PE-controlled GPPB form register',
            'app-schema', '1.0',
            $6, 'rp-2026-a', $7,
            $8, $9
        )
        "#,
    )
    .bind(forged_id.as_uuid())
    .bind(seed.tenant_id.as_uuid())
    .bind(document.id().as_uuid())
    .bind(serde_json::json!({"fiscal_year": 2027}))
    .bind(ContentHash::sha256(b"a-hash-that-does-not-match-the-content").as_str())
    .bind(version.rule_pack().snapshot_id().as_uuid())
    .bind(version.rule_pack().manifest_hash().as_str())
    .bind(seed.author.as_uuid())
    .bind(timestamp(5))
    .execute(store.pool())
    .await
    .expect("forged row can be inserted");

    let error = store
        .load_document_version(seed.tenant_id, forged_id)
        .await
        .expect_err("a mismatched content hash must be rejected");
    assert!(matches!(
        error,
        StoreError::Domain(DomainError::ContentHashMismatch)
    ));
}

#[tokio::test]
async fn approval_and_bac_resolution_round_trip() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (_, version) = save_document(&store, &seed).await;

    let approval = Approval::new(
        ApprovalId::new(),
        version.id(),
        version.content_hash().clone(),
        Actor::new(seed.hope, ActorRole::Hope, "Head of the Procuring Entity"),
        ApprovalDecision::Approved,
        timestamp(10),
        "RA 12009 / IRR 7.7.2-7.7.5".to_owned(),
        Some("Approved for signature and issuance.".to_owned()),
    );
    let attendance = BTreeSet::from([seed.chair, seed.member_a, seed.member_b]);
    let votes = BTreeMap::from([
        (seed.member_a, BacVote::Recommend),
        (seed.member_b, BacVote::Recommend),
    ]);
    let resolution = BacResolution::record(
        BacResolutionId::new(),
        version.id(),
        version.content_hash().clone(),
        "BAC Resolution No. 2026-001",
        seed.membership(),
        attendance,
        votes,
        &Actor::new(seed.chair, ActorRole::BacChairperson, "BAC Chairperson"),
        timestamp(5),
    )
    .expect("BAC resolution is valid");

    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_approval(seed.tenant_id, &approval)
        .await
        .expect("approval saves");
    unit.save_bac_resolution(seed.tenant_id, &resolution)
        .await
        .expect("resolution saves");
    unit.commit().await.expect("transaction commits");

    let approvals = store
        .approvals_for_version(seed.tenant_id, version.id())
        .await
        .expect("approvals load");
    assert_eq!(approvals, vec![approval.clone()]);
    assert_eq!(
        store
            .load_approval(seed.tenant_id, approval.id())
            .await
            .expect("approval loads"),
        Some(approval.clone())
    );

    let resolutions = store
        .bac_resolutions_for_version(seed.tenant_id, version.id())
        .await
        .expect("resolutions load");
    assert_eq!(resolutions, vec![resolution.clone()]);
    let loaded = resolutions.first().expect("resolution exists");
    assert_eq!(
        loaded.membership().members(),
        resolution.membership().members()
    );
    assert_eq!(loaded.attendance(), resolution.attendance());
    assert_eq!(loaded.votes(), resolution.votes());
    assert_eq!(loaded.status(), BacResolutionStatus::Active);

    let mut superseded_approval = approval;
    superseded_approval.supersede();
    let mut superseded_resolution = resolution;
    superseded_resolution.supersede();
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_approval(seed.tenant_id, &superseded_approval)
        .await
        .expect("approval status updates");
    unit.save_bac_resolution(seed.tenant_id, &superseded_resolution)
        .await
        .expect("resolution status updates");
    unit.commit().await.expect("transaction commits");

    assert_eq!(
        store
            .load_approval(seed.tenant_id, superseded_approval.id())
            .await
            .expect("approval loads")
            .expect("approval exists")
            .status(),
        ApprovalStatus::Superseded
    );
}

#[tokio::test]
async fn signature_request_and_signature_round_trip() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (_, version) = save_document(&store, &seed).await;

    let request = SignatureRequest::new(
        SignatureRequestId::new(),
        version.id(),
        version.content_hash().clone(),
        Actor::new(seed.hope, ActorRole::Hope, "Head of the Procuring Entity"),
        SignaturePurpose::Execution,
        SignatureMethod::QualifiedElectronicSignature,
        "I approve and execute this final APP.".to_owned(),
        timestamp(10),
        timestamp(10 + 3 * 86_400),
    )
    .expect("signature request is valid");
    let signature = Signature::new(
        SignatureId::new(),
        &request,
        timestamp(20),
        "provider-transaction-001".to_owned(),
        "certificate-hope-001".to_owned(),
        version.content_hash().clone(),
        SignatureValidation::Valid,
    )
    .expect("signature is valid");

    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_signature_request(seed.tenant_id, &request)
        .await
        .expect("request saves");
    unit.save_signature(seed.tenant_id, &signature)
        .await
        .expect("signature saves");
    unit.commit().await.expect("transaction commits");

    assert_eq!(
        store
            .signature_requests_for_version(seed.tenant_id, version.id())
            .await
            .expect("requests load"),
        vec![request.clone()]
    );
    assert_eq!(
        store
            .signatures_for_version(seed.tenant_id, version.id())
            .await
            .expect("signatures load"),
        vec![signature.clone()]
    );
    assert_eq!(
        store
            .load_signature(seed.tenant_id, signature.id())
            .await
            .expect("signature loads"),
        Some(signature)
    );

    let mut signed_request = request;
    signed_request.set_status(SignatureRequestStatus::Signed);
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_signature_request(seed.tenant_id, &signed_request)
        .await
        .expect("request status updates");
    unit.commit().await.expect("transaction commits");
    assert_eq!(
        store
            .signature_requests_for_version(seed.tenant_id, version.id())
            .await
            .expect("requests load")
            .first()
            .expect("request exists")
            .status(),
        SignatureRequestStatus::Signed
    );
}

#[tokio::test]
async fn issuance_distribution_delivery_and_acknowledgement_round_trip() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (_, version) = save_document(&store, &seed).await;

    let recipient = Recipient::new(
        RecipientId::new(),
        RecipientKind::InternalOffice,
        "Municipal Budget Office",
        Some("budget@example.gov.ph".to_owned()),
    )
    .expect("recipient is valid");
    let recipient_id = recipient.id();
    let issuance = Issuance::new(
        IssuanceId::new(),
        version.id(),
        version.content_hash().clone(),
        Actor::new(seed.author, ActorRole::Secretariat, "BAC Secretariat"),
        "HoPE approval and IRR posting authority".to_owned(),
        timestamp(30),
        vec![
            IssuanceArtifact::new(
                ArtifactId::new(),
                "application/pdf",
                version.content_hash().clone(),
            )
            .expect("artifact is valid"),
        ],
        vec![
            IssuanceRecipient::new(recipient.clone(), DistributionChannel::Portal, true)
                .expect("issuance recipient is valid"),
        ],
        vec![DistributionId::new()],
    )
    .expect("issuance is valid");
    let distribution_id = issuance.distribution_ids()[0];
    let mut distribution = Distribution::new(
        distribution_id,
        issuance.id(),
        version.id(),
        recipient,
        DistributionChannel::Portal,
        true,
    );
    distribution
        .record_attempt(
            DeliveryAttempt::new(
                timestamp(40),
                "portal-receipt-001",
                DeliveryResult::Delivered,
            )
            .expect("delivery attempt is valid"),
        )
        .expect("delivery is recorded");
    let acknowledgement = Acknowledgement::new(
        AcknowledgementId::new(),
        distribution_id,
        issuance.id(),
        version.id(),
        version.content_hash().clone(),
        recipient_id,
        timestamp(50),
        AcknowledgementMethod::Portal,
        Some("Received the issued final APP.".to_owned()),
    )
    .expect("acknowledgement is valid");

    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_issuance(seed.tenant_id, &issuance)
        .await
        .expect("issuance saves");
    unit.save_distribution(seed.tenant_id, &distribution)
        .await
        .expect("distribution saves");
    unit.save_acknowledgement(seed.tenant_id, &acknowledgement)
        .await
        .expect("acknowledgement saves");
    unit.commit().await.expect("transaction commits");

    assert_eq!(
        store
            .load_issuance(seed.tenant_id, issuance.id())
            .await
            .expect("issuance loads"),
        Some(issuance.clone())
    );
    assert_eq!(
        store
            .load_distribution(seed.tenant_id, distribution_id)
            .await
            .expect("distribution loads"),
        Some(distribution)
    );
    assert_eq!(
        store
            .acknowledgements_for_issuance(seed.tenant_id, issuance.id())
            .await
            .expect("acknowledgements load"),
        vec![acknowledgement.clone()]
    );
    assert_eq!(
        store
            .load_acknowledgement(seed.tenant_id, acknowledgement.id())
            .await
            .expect("acknowledgement loads"),
        Some(acknowledgement)
    );
}

#[tokio::test]
async fn audit_chain_persists_and_rejects_mutation() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    let (document, _) = save_document(&store, &seed).await;

    let mut unit = store.begin().await.expect("transaction begins");
    for index in 0..3 {
        unit.append_audit_event(
            seed.tenant_id,
            format!("document.event.{index}"),
            Some(seed.author),
            "document",
            document.id().to_string(),
            timestamp(index),
            BTreeMap::from([("index".to_owned(), index.to_string())]),
        )
        .await
        .expect("audit event appends");
    }
    unit.commit().await.expect("transaction commits");

    let log = store
        .load_audit_log(seed.tenant_id)
        .await
        .expect("audit log loads");
    assert_eq!(log.len(), 3);
    assert!(log.verify_chain());
    assert_eq!(log.events()[0].previous_hash, ContentHash::audit_genesis());
    assert_eq!(log.events()[2].previous_hash, log.events()[1].event_hash);

    let mut unit = store.begin().await.expect("transaction begins");
    unit.append_audit_event(
        seed.tenant_id,
        "document.event.3",
        Some(seed.author),
        "document",
        document.id().to_string(),
        timestamp(3),
        BTreeMap::new(),
    )
    .await
    .expect("audit event appends");
    unit.commit().await.expect("transaction commits");

    let log = store
        .load_audit_log(seed.tenant_id)
        .await
        .expect("audit log loads");
    assert_eq!(log.len(), 4);
    assert!(log.verify_chain());
    assert_eq!(log.events()[3].previous_hash, log.events()[2].event_hash);

    let update =
        sqlx::query("UPDATE audit_events SET details = $1 WHERE tenant_id = $2 AND seq = 1")
            .bind(serde_json::json!({"tampered": "true"}))
            .bind(seed.tenant_id.as_uuid())
            .execute(store.pool())
            .await;
    assert!(update.is_err(), "audit updates must be rejected");

    let delete = sqlx::query("DELETE FROM audit_events WHERE tenant_id = $1 AND seq = 1")
        .bind(seed.tenant_id.as_uuid())
        .execute(store.pool())
        .await;
    assert!(delete.is_err(), "audit deletes must be rejected");
}

async fn outbox_count(store: &PgStore, tenant_id: TenantId) -> i64 {
    sqlx::query_scalar("SELECT count(*) FROM outbox_events WHERE tenant_id = $1")
        .bind(tenant_id.as_uuid())
        .fetch_one(store.pool())
        .await
        .expect("outbox count")
}

#[tokio::test]
async fn outbox_commits_atomically_and_rollback_discards_writes() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;
    save_document(&store, &seed).await;

    let (committed_document, committed_version) = document_fixture(&seed);
    let committed_event = NewOutboxEvent::new(
        "document",
        committed_document.id().as_uuid(),
        "document.created",
        serde_json::json!({"document_id": committed_document.id().to_string()}),
        timestamp(60),
    );
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_document(&committed_document)
        .await
        .expect("document saves");
    unit.save_document_version(seed.tenant_id, &committed_version)
        .await
        .expect("version saves");
    unit.enqueue_outbox(seed.tenant_id, &committed_event)
        .await
        .expect("outbox event enqueues");
    unit.commit().await.expect("transaction commits");

    assert!(
        store
            .load_document(seed.tenant_id, committed_document.id())
            .await
            .expect("document loads")
            .is_some()
    );
    assert_eq!(outbox_count(&store, seed.tenant_id).await, 1);
    let published_at: Option<OffsetDateTime> =
        sqlx::query_scalar("SELECT published_at FROM outbox_events WHERE id = $1")
            .bind(committed_event.id)
            .fetch_one(store.pool())
            .await
            .expect("outbox row loads");
    assert!(published_at.is_none());

    let (rolled_back_document, rolled_back_version) = document_fixture(&seed);
    let rolled_back_event = NewOutboxEvent::new(
        "document",
        rolled_back_document.id().as_uuid(),
        "document.created",
        serde_json::json!({"document_id": rolled_back_document.id().to_string()}),
        timestamp(70),
    );
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_document(&rolled_back_document)
        .await
        .expect("document saves");
    unit.save_document_version(seed.tenant_id, &rolled_back_version)
        .await
        .expect("version saves");
    unit.enqueue_outbox(seed.tenant_id, &rolled_back_event)
        .await
        .expect("outbox event enqueues");
    unit.rollback().await.expect("transaction rolls back");

    assert!(
        store
            .load_document(seed.tenant_id, rolled_back_document.id())
            .await
            .expect("document lookup succeeds")
            .is_none(),
        "rollback must discard the document"
    );
    assert_eq!(
        outbox_count(&store, seed.tenant_id).await,
        1,
        "rollback must discard the outbox row"
    );
}

#[tokio::test]
async fn anchoring_covers_new_audit_ranges_and_verifies_the_chain() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;

    let mut unit = store.begin().await.expect("transaction begins");
    for index in 0..3 {
        unit.append_audit_event(
            seed.tenant_id,
            format!("anchor.event.{index}"),
            None,
            "document",
            "app-2026-001",
            timestamp(index),
            BTreeMap::new(),
        )
        .await
        .expect("audit event appends");
    }
    unit.commit().await.expect("transaction commits");

    let first = store
        .anchor_pending(seed.tenant_id, AnchorId::new(), timestamp(10))
        .await
        .expect("first anchor succeeds")
        .expect("three events need anchoring");
    assert_eq!(first.from_seq(), 1);
    assert_eq!(first.to_seq(), 3);
    assert_eq!(first.event_count(), 3);
    assert_eq!(first.previous_anchor_hash(), &ContentHash::anchor_genesis());
    assert!(store.verify_anchors(seed.tenant_id).await.expect("verify"));

    assert!(
        store
            .anchor_pending(seed.tenant_id, AnchorId::new(), timestamp(11))
            .await
            .expect("second anchor attempt succeeds")
            .is_none(),
        "an unchanged audit log must not create an empty anchor"
    );

    let mut unit = store.begin().await.expect("transaction begins");
    for index in 3..5 {
        unit.append_audit_event(
            seed.tenant_id,
            format!("anchor.event.{index}"),
            None,
            "document",
            "app-2026-001",
            timestamp(index),
            BTreeMap::new(),
        )
        .await
        .expect("audit event appends");
    }
    unit.commit().await.expect("transaction commits");

    let second = store
        .anchor_pending(seed.tenant_id, AnchorId::new(), timestamp(20))
        .await
        .expect("second anchor succeeds")
        .expect("two events need anchoring");
    assert_eq!(second.from_seq(), 4);
    assert_eq!(second.to_seq(), 5);
    assert_eq!(second.previous_anchor_hash(), &first.record_hash());

    let anchors = store
        .load_anchors(seed.tenant_id)
        .await
        .expect("anchors load");
    assert_eq!(anchors, vec![first, second.clone()]);
    assert!(store.verify_anchors(seed.tenant_id).await.expect("verify"));

    let receipt = AnchorReceipt::new(
        second.anchor_id(),
        "rfc3161",
        "tsa-token-001",
        timestamp(21),
        Some(vec![0x30, 0x82, 0x01]),
    )
    .expect("receipt is valid");
    let mut unit = store.begin().await.expect("transaction begins");
    unit.save_anchor_receipt(seed.tenant_id, &receipt)
        .await
        .expect("receipt saves");
    unit.commit().await.expect("transaction commits");
    assert_eq!(
        store
            .load_anchor_receipts(seed.tenant_id, second.anchor_id())
            .await
            .expect("receipts load"),
        vec![receipt]
    );

    let update = sqlx::query(
        "UPDATE evidence_anchors SET merkle_root = $1 WHERE tenant_id = $2 AND id = $3",
    )
    .bind(ContentHash::sha256(b"rewritten").as_str())
    .bind(seed.tenant_id.as_uuid())
    .bind(second.anchor_id().as_uuid())
    .execute(store.pool())
    .await;
    assert!(update.is_err(), "anchor updates must be rejected");

    let delete = sqlx::query("DELETE FROM evidence_anchors WHERE tenant_id = $1 AND id = $2")
        .bind(seed.tenant_id.as_uuid())
        .bind(second.anchor_id().as_uuid())
        .execute(store.pool())
        .await;
    assert!(delete.is_err(), "anchor deletes must be rejected");
}

#[tokio::test]
async fn anchor_verification_detects_a_forged_root() {
    let Some(store) = test_store().await else {
        return;
    };
    let seed = Seed::new(&store).await;

    let mut unit = store.begin().await.expect("transaction begins");
    for index in 0..3 {
        unit.append_audit_event(
            seed.tenant_id,
            format!("forged.event.{index}"),
            None,
            "document",
            "app-2026-001",
            timestamp(index),
            BTreeMap::new(),
        )
        .await
        .expect("audit event appends");
    }
    unit.commit().await.expect("transaction commits");

    let first = store
        .anchor_pending(seed.tenant_id, AnchorId::new(), timestamp(10))
        .await
        .expect("anchor succeeds")
        .expect("events need anchoring");
    assert!(store.verify_anchors(seed.tenant_id).await.expect("verify"));

    let mut unit = store.begin().await.expect("transaction begins");
    unit.append_audit_event(
        seed.tenant_id,
        "forged.event.3",
        None,
        "document",
        "app-2026-001",
        timestamp(3),
        BTreeMap::new(),
    )
    .await
    .expect("audit event appends");
    unit.commit().await.expect("transaction commits");

    // A forged record with a self-consistent hash but a root that does not
    // commit to the stored audit event.
    let forged_batch = AnchorBatch::new(
        seed.tenant_id,
        4,
        4,
        vec![ContentHash::sha256(b"an-event-that-never-happened")],
    )
    .expect("forged batch is structurally valid");
    let forged = AnchorRecord::create(
        &forged_batch,
        AnchorId::new(),
        first.record_hash(),
        timestamp(11),
    );
    sqlx::query(
        r#"
        INSERT INTO evidence_anchors (
            id, tenant_id, from_seq, to_seq, event_count, merkle_root,
            previous_anchor_hash, anchor_hash, schema_version, created_at
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
        "#,
    )
    .bind(forged.anchor_id().as_uuid())
    .bind(seed.tenant_id.as_uuid())
    .bind(forged.from_seq() as i64)
    .bind(forged.to_seq() as i64)
    .bind(forged.event_count() as i64)
    .bind(forged.merkle_root().as_str())
    .bind(forged.previous_anchor_hash().as_str())
    .bind(forged.record_hash().as_str())
    .bind(forged.schema_version())
    .bind(forged.created_at())
    .execute(store.pool())
    .await
    .expect("forged anchor can be inserted");

    assert!(
        !store.verify_anchors(seed.tenant_id).await.expect("verify"),
        "verification must reject a root that does not match the audit events"
    );
}

use std::collections::{BTreeMap, BTreeSet};

use procuchain_app::Platform;
use procuchain_evidence::AuditLog;
use procuchain_issuance::{
    AcknowledgementMethod, DeliveryResult, DistributionChannel, DistributionStatus,
    IssuanceArtifact, IssuanceRecipient, IssuanceStatus, Recipient, RecipientKind,
};
use procuchain_procurement::{AppContent, AppEdition, AppLine, PpmpContent, PpmpLine};
use procuchain_records::{DocumentType, RulePackRef, VersionStatus};
use procuchain_shared::{
    Actor, ActorRole, Amount, ArtifactId, CommitteeId, ContentHash, DocumentVersionId, DomainError,
    RecipientId, RulePackSnapshotId, TenantId, UserId,
};
use procuchain_signatures::{
    SignatureMethod, SignaturePolicy, SignaturePurpose, SignatureStatus, SignatureValidation,
};
use procuchain_workflow::{
    ApprovalStatus, BacMemberSnapshot, BacMembershipSnapshot, BacOutcome, BacSeat, BacVote,
};
use time::{Duration, OffsetDateTime};

struct Fixture {
    platform: Platform,
    end_user: Actor,
    end_user_head: Actor,
    chair: Actor,
    vice: Actor,
    members: Vec<Actor>,
    hope: Actor,
    secretariat: Actor,
    membership: BacMembershipSnapshot,
    attendance: BTreeSet<UserId>,
    votes: BTreeMap<UserId, BacVote>,
    rule_pack_a: RulePackRef,
    rule_pack_b: RulePackRef,
}

impl Fixture {
    fn new(signature_methods: Vec<SignatureMethod>) -> Result<Self, DomainError> {
        let policy = SignaturePolicy::new().with_rule(
            DocumentType::AnnualProcurementPlan,
            vec![ActorRole::Hope],
            signature_methods,
        )?;
        let end_user = Actor::new(UserId::new(), ActorRole::EndUser, "End-User Representative");
        let end_user_head = Actor::new(UserId::new(), ActorRole::EndUserHead, "End-User Head");
        let chair = Actor::new(UserId::new(), ActorRole::BacChairperson, "BAC Chairperson");
        let vice = Actor::new(
            UserId::new(),
            ActorRole::BacViceChairperson,
            "BAC Vice-Chairperson",
        );
        let members = vec![
            Actor::new(UserId::new(), ActorRole::BacMember, "BAC Member 1"),
            Actor::new(UserId::new(), ActorRole::BacMember, "BAC Member 2"),
            Actor::new(UserId::new(), ActorRole::BacMember, "BAC Member 3"),
        ];
        let hope = Actor::new(
            UserId::new(),
            ActorRole::Hope,
            "Head of the Procuring Entity",
        );
        let secretariat = Actor::new(UserId::new(), ActorRole::Secretariat, "BAC Secretariat");
        let membership = BacMembershipSnapshot::new(
            CommitteeId::new(),
            vec![
                BacMemberSnapshot::new(chair.user_id(), BacSeat::Chairperson, "BAC Chairperson"),
                BacMemberSnapshot::new(
                    vice.user_id(),
                    BacSeat::ViceChairperson,
                    "BAC Vice-Chairperson",
                ),
                BacMemberSnapshot::new(members[0].user_id(), BacSeat::Member, "BAC Member 1"),
                BacMemberSnapshot::new(members[1].user_id(), BacSeat::Member, "BAC Member 2"),
                BacMemberSnapshot::new(members[2].user_id(), BacSeat::Member, "BAC Member 3"),
            ],
        )?;
        let attendance =
            BTreeSet::from([chair.user_id(), members[0].user_id(), members[1].user_id()]);
        let votes = BTreeMap::from([
            (members[0].user_id(), BacVote::Recommend),
            (members[1].user_id(), BacVote::Recommend),
        ]);
        let rule_pack_a = RulePackRef::new(
            RulePackSnapshotId::new(),
            "rp-2026-a",
            ContentHash::sha256(b"rule-pack-2026-a"),
        )?;
        let rule_pack_b = RulePackRef::new(
            RulePackSnapshotId::new(),
            "rp-2026-b",
            ContentHash::sha256(b"rule-pack-2026-b"),
        )?;
        Ok(Self {
            platform: Platform::new(TenantId::new(), policy),
            end_user,
            end_user_head,
            chair,
            vice,
            members,
            hope,
            secretariat,
            membership,
            attendance,
            votes,
            rule_pack_a,
            rule_pack_b,
        })
    }

    fn template(name: &str) -> procuchain_records::TemplateRef {
        procuchain_records::TemplateRef::new(
            format!("{name}-template"),
            "2026.1",
            "PE-controlled GPPB form register",
        )
        .expect("test template is valid")
    }

    fn schema(name: &str) -> procuchain_records::SchemaRef {
        procuchain_records::SchemaRef::new(format!("{name}-schema"), "1.0")
            .expect("test schema is valid")
    }

    fn ppmp_content() -> PpmpContent {
        PpmpContent {
            fiscal_year: 2026,
            end_user_unit: "Municipal Engineering Office".to_owned(),
            project_title: "Rehabilitation of the public market drainage system".to_owned(),
            scope: "Supply and installation of drainage lines and covers".to_owned(),
            market_scoping_ref: "MS-2026-001".to_owned(),
            abc_basis: "Approved 2026 appropriation and market scoping estimate".to_owned(),
            schedule: "Q1-Q2 2026".to_owned(),
            risks: vec!["Right-of-way coordination".to_owned()],
            lines: vec![PpmpLine {
                line_id: "PPMP-001".to_owned(),
                description: "Drainage system rehabilitation".to_owned(),
                quantity: 1,
                unit: "lot".to_owned(),
                estimated_cost: Amount::php_pesos(1_500_000).expect("amount is valid"),
                schedule: "Q2 2026".to_owned(),
                mode_consideration: "Competitive Bidding".to_owned(),
            }],
        }
    }

    fn create_ppmp(&mut self) -> Result<DocumentVersionId, DomainError> {
        let version_id = self.platform.create_draft(
            DocumentType::Ppmp,
            "PPMP FY 2026 - Municipal Engineering Office",
            Self::template("ppmp"),
            Self::schema("ppmp"),
            Self::ppmp_content().to_canonical()?,
            self.end_user.clone(),
            self.rule_pack_a.clone(),
        )?;
        self.platform.submit_version(version_id, &self.end_user)?;
        self.platform.approve_ppmp(
            version_id,
            &self.end_user_head,
            "End-User head approval of PPMP FY 2026",
            Some("PPMP facts and market-scoping basis reviewed.".to_owned()),
        )?;
        Ok(version_id)
    }

    fn indicative_content(ppmp_version_id: DocumentVersionId) -> AppContent {
        AppContent {
            fiscal_year: 2026,
            edition: AppEdition::Indicative,
            lines: vec![AppLine {
                line_id: "APP-001".to_owned(),
                source_ppmp_version_id: ppmp_version_id,
                source_ppmp_line_id: "PPMP-001".to_owned(),
                description: "Drainage system rehabilitation".to_owned(),
                abc: Amount::php_pesos(1_500_000).expect("amount is valid"),
                schedule: "Q2 2026".to_owned(),
                mode: "Competitive Bidding".to_owned(),
            }],
            basis_version_id: None,
        }
    }

    fn create_indicative_app(
        &mut self,
        ppmp_version_id: DocumentVersionId,
    ) -> Result<DocumentVersionId, DomainError> {
        let version_id = self.platform.create_draft(
            DocumentType::AnnualProcurementPlan,
            "Indicative APP FY 2026",
            Self::template("app"),
            Self::schema("app"),
            Self::indicative_content(ppmp_version_id).to_canonical()?,
            self.secretariat.clone(),
            self.rule_pack_a.clone(),
        )?;
        self.platform
            .submit_version(version_id, &self.secretariat)?;
        Ok(version_id)
    }

    fn bac_recommend(&mut self, version_id: DocumentVersionId) -> Result<(), DomainError> {
        self.platform.bac_recommend_app(
            version_id,
            "BAC Resolution No. 2026-001",
            self.membership.clone(),
            self.attendance.clone(),
            self.votes.clone(),
            &self.chair,
        )?;
        Ok(())
    }

    fn approve_app(&mut self, version_id: DocumentVersionId) -> Result<(), DomainError> {
        self.bac_recommend(version_id)?;
        self.platform.hope_approve_app(
            version_id,
            &self.hope,
            "RA 12009 / IRR 7.7.2-7.7.5; HoPE approval authority",
            Some("Approved for signature and issuance.".to_owned()),
        )?;
        Ok(())
    }
}

#[test]
fn life_160_first_vertical_slice_is_auditable_and_separates_actions() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;

    // 1. PPMP: structured authoring and governed End-User head approval.
    let ppmp_version = fixture.create_ppmp()?;
    assert_eq!(
        fixture
            .platform
            .version(ppmp_version)
            .expect("PPMP exists")
            .status(),
        VersionStatus::Approved
    );

    // 2. Indicative APP: consolidated from the PPMP version and submitted.
    let indicative_version = fixture.create_indicative_app(ppmp_version)?;
    assert_eq!(
        fixture
            .platform
            .version(indicative_version)
            .expect("Indicative APP exists")
            .status(),
        VersionStatus::InReview
    );

    // 3. BAC recommendation: quorum and membership snapshot are recorded.
    fixture.bac_recommend(indicative_version)?;
    let resolutions = fixture
        .platform
        .bac_resolutions_for_version(indicative_version);
    let resolution = resolutions.first().expect("BAC resolution exists");
    assert!(resolution.is_recommended());
    assert_eq!(resolution.quorum_required(), 3);
    assert_eq!(resolution.membership().designated_total(), 5);
    assert_eq!(resolution.membership().members().len(), 5);

    // 4. HoPE approval binds the exact version and does not create a signature.
    fixture.platform.hope_approve_app(
        indicative_version,
        &fixture.hope,
        "RA 12009 / IRR 7.7.2-7.7.5; HoPE approval authority",
        Some("Indicative APP approved for EPA posting.".to_owned()),
    )?;
    assert_eq!(
        fixture
            .platform
            .version(indicative_version)
            .expect("Indicative APP exists")
            .status(),
        VersionStatus::Approved
    );
    assert_eq!(
        fixture
            .platform
            .approvals_for_version(indicative_version)
            .len(),
        1
    );
    assert!(
        fixture
            .platform
            .signatures_for_version(indicative_version)
            .is_empty(),
        "HoPE approval must not create a signature"
    );

    // 5. Final APP: derived from the approved Indicative APP and independently
    //    recommended, approved, signed, issued, delivered, and acknowledged.
    let final_content = AppContent::from_indicative(
        &Fixture::indicative_content(ppmp_version),
        indicative_version,
    )?;
    let final_version = fixture.platform.create_draft(
        DocumentType::AnnualProcurementPlan,
        "Final APP FY 2026",
        Fixture::template("app"),
        Fixture::schema("app"),
        final_content.to_canonical()?,
        fixture.secretariat.clone(),
        fixture.rule_pack_b.clone(),
    )?;
    fixture
        .platform
        .submit_version(final_version, &fixture.secretariat)?;
    fixture.approve_app(final_version)?;

    // Approval remains distinct from signature.
    assert_eq!(
        fixture
            .platform
            .version(final_version)
            .expect("Final APP exists")
            .status(),
        VersionStatus::Approved
    );
    assert!(
        fixture
            .platform
            .signatures_for_version(final_version)
            .is_empty()
    );

    let signature_request = fixture.platform.request_signature(
        final_version,
        &fixture.hope,
        SignaturePurpose::Execution,
        SignatureMethod::QualifiedElectronicSignature,
        "I approve and execute this final APP.",
        OffsetDateTime::now_utc() + Duration::days(3),
    )?;
    assert_eq!(
        fixture
            .platform
            .version(final_version)
            .expect("Final APP exists")
            .status(),
        VersionStatus::Approved,
        "requesting a signature does not sign the version"
    );
    let final_hash = fixture
        .platform
        .version(final_version)
        .expect("Final APP exists")
        .content_hash()
        .clone();
    let signature_id = fixture.platform.complete_signature(
        signature_request,
        OffsetDateTime::now_utc(),
        "provider-transaction-001",
        "certificate-hope-001",
        final_hash.clone(),
        SignatureValidation::Valid,
    )?;
    assert_eq!(
        fixture
            .platform
            .version(final_version)
            .expect("Final APP exists")
            .status(),
        VersionStatus::Signed
    );
    assert!(
        fixture
            .platform
            .signature(signature_id)
            .expect("signature exists")
            .is_valid()
    );
    assert_eq!(
        fixture.platform.approvals_for_version(final_version).len(),
        1,
        "signing must not replace the approval record"
    );

    // Issuance is blocked until the required signature exists.
    let artifact = IssuanceArtifact::new(ArtifactId::new(), "application/pdf", final_hash.clone())?;
    let internal_recipient = Recipient::new(
        RecipientId::new(),
        RecipientKind::InternalOffice,
        "Municipal Budget Office",
        Some("budget@example.gov.ph".to_owned()),
    )?;
    let public_recipient = Recipient::new(
        RecipientId::new(),
        RecipientKind::Public,
        "PE public website",
        None,
    )?;
    let internal_recipient_id = internal_recipient.id();
    let recipients = vec![
        IssuanceRecipient::new(internal_recipient, DistributionChannel::Portal, true)?,
        IssuanceRecipient::new(public_recipient, DistributionChannel::PeWebsite, false)?,
    ];
    let issuance_id = fixture.platform.issue(
        final_version,
        &fixture.secretariat,
        "HoPE approval and IRR 7.7.5 posting authority",
        vec![artifact],
        recipients,
    )?;
    assert_eq!(
        fixture
            .platform
            .version(final_version)
            .expect("Final APP exists")
            .status(),
        VersionStatus::Issued
    );
    let issuance = fixture
        .platform
        .issuance(issuance_id)
        .expect("issuance exists");
    assert_eq!(issuance.manifest_hash().as_str().len(), 64);
    assert_eq!(issuance.distribution_ids().len(), 2);
    let issuance_version_id = issuance.version_id();
    let issuance_version_hash = issuance.version_hash().clone();
    let issuance_issuer = issuance.issuer().user_id();
    let internal_distribution_id = issuance.distribution_ids()[0];

    // An acknowledgement before delivery is rejected.
    let premature = fixture
        .platform
        .acknowledge(
            internal_distribution_id,
            internal_recipient_id,
            final_hash.clone(),
            AcknowledgementMethod::Portal,
            None,
            OffsetDateTime::now_utc(),
        )
        .expect_err("undelivered distribution cannot be acknowledged");
    assert!(matches!(premature, DomainError::InvalidState { .. }));

    fixture.platform.record_delivery(
        internal_distribution_id,
        "portal-receipt-001",
        DeliveryResult::Delivered,
        OffsetDateTime::now_utc(),
        &fixture.secretariat,
    )?;
    assert_eq!(
        fixture
            .platform
            .distribution(internal_distribution_id)
            .expect("distribution exists")
            .status(),
        DistributionStatus::Delivered
    );

    let acknowledgement_id = fixture.platform.acknowledge(
        internal_distribution_id,
        internal_recipient_id,
        ContentHash::sha256(b"a-different-artifact"),
        AcknowledgementMethod::Portal,
        None,
        OffsetDateTime::now_utc(),
    );
    assert!(
        matches!(acknowledgement_id, Err(DomainError::ContentHashMismatch)),
        "an acknowledgement must bind to the exact issued artifact"
    );
    assert_eq!(
        fixture
            .platform
            .distribution(internal_distribution_id)
            .expect("distribution exists")
            .status(),
        DistributionStatus::Delivered,
        "a rejected acknowledgement must not mark the distribution acknowledged"
    );

    let acknowledgement_id = fixture.platform.acknowledge(
        internal_distribution_id,
        internal_recipient_id,
        final_hash.clone(),
        AcknowledgementMethod::Portal,
        Some("Received the issued final APP.".to_owned()),
        OffsetDateTime::now_utc(),
    )?;
    assert_eq!(
        fixture
            .platform
            .distribution(internal_distribution_id)
            .expect("distribution exists")
            .status(),
        DistributionStatus::Acknowledged
    );
    assert!(
        fixture
            .platform
            .acknowledgements()
            .contains_key(&acknowledgement_id)
    );

    // Audit reconstruction: every downstream record binds to the exact final
    // APP version and content hash, and the source records remain queryable.
    let final_record = fixture
        .platform
        .version(final_version)
        .expect("Final APP exists");
    assert_eq!(final_record.author(), fixture.secretariat.user_id());
    let final_resolution = fixture
        .platform
        .bac_resolutions_for_version(final_version)
        .into_iter()
        .next()
        .expect("final APP BAC resolution exists");
    assert_eq!(final_resolution.version_hash(), final_record.content_hash());
    assert!(final_resolution.is_recommended());
    assert_eq!(final_resolution.quorum_required(), 3);
    let final_approval = fixture
        .platform
        .approvals_for_version(final_version)
        .into_iter()
        .next()
        .expect("final APP approval exists");
    assert_eq!(final_approval.approver_role(), ActorRole::Hope);
    assert!(final_approval.binds(final_version, final_record.content_hash()));
    let final_signature = fixture
        .platform
        .signature(signature_id)
        .expect("signature exists");
    assert_eq!(final_signature.version_id(), final_version);
    assert_eq!(final_signature.content_hash(), final_record.content_hash());
    assert_eq!(final_signature.signer(), fixture.hope.user_id());
    assert_eq!(issuance_version_id, final_version);
    assert_eq!(&issuance_version_hash, final_record.content_hash());
    assert_eq!(issuance_issuer, fixture.secretariat.user_id());
    let distribution = fixture
        .platform
        .distribution(internal_distribution_id)
        .expect("distribution exists");
    assert_eq!(distribution.recipient().id(), internal_recipient_id);
    let acknowledgement = fixture
        .platform
        .acknowledgements()
        .get(&acknowledgement_id)
        .expect("acknowledgement exists");
    assert_eq!(acknowledgement.version_id(), final_version);
    assert_eq!(acknowledgement.artifact_hash(), final_record.content_hash());
    assert_eq!(acknowledgement.acknowledged_by(), internal_recipient_id);

    // Rule-pack snapshots are pinned per version and are not retroactively
    // changed by the later pack used for the final APP.
    assert_eq!(
        fixture
            .platform
            .version(indicative_version)
            .expect("Indicative APP exists")
            .rule_pack()
            .version_label(),
        "rp-2026-a"
    );
    assert_eq!(
        fixture
            .platform
            .version(final_version)
            .expect("Final APP exists")
            .rule_pack()
            .version_label(),
        "rp-2026-b"
    );

    // Audit chain covers the material actions and detects tampering.
    assert!(fixture.platform.verify_audit_chain());
    let event_types: Vec<&str> = fixture
        .platform
        .audit_events()
        .iter()
        .map(|event| event.event_type.as_str())
        .collect();
    for expected in [
        "document.created",
        "version.submitted",
        "ppmp.approved",
        "bac.recommended",
        "app.approved",
        "signature.requested",
        "signature.completed",
        "version.signed",
        "document.issued",
        "distribution.delivery_recorded",
        "acknowledgement.recorded",
    ] {
        assert!(
            event_types.contains(&expected),
            "audit log is missing {expected}"
        );
    }
    let mut tampered = fixture.platform.audit_events().to_vec();
    tampered[0]
        .details
        .insert("tampered".to_owned(), "true".to_owned());
    assert!(
        !AuditLog::verify_events(&tampered),
        "audit verification must detect a changed event"
    );

    Ok(())
}

#[test]
fn hope_approval_requires_an_active_bac_recommendation() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let error = fixture
        .platform
        .hope_approve_app(app_version, &fixture.hope, "HoPE approval authority", None)
        .expect_err("BAC recommendation is a prerequisite");
    assert!(matches!(error, DomainError::MissingPrerequisite(_)));
    assert_eq!(
        fixture
            .platform
            .version(app_version)
            .expect("APP exists")
            .status(),
        VersionStatus::InReview
    );
    Ok(())
}

#[test]
fn bac_quorum_and_chair_presence_are_enforced() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let mut below_quorum = BTreeSet::new();
    below_quorum.insert(fixture.chair.user_id());
    below_quorum.insert(fixture.members[0].user_id());
    let error = fixture
        .platform
        .bac_recommend_app(
            app_version,
            "BAC Resolution No. 2026-010",
            fixture.membership.clone(),
            below_quorum,
            BTreeMap::from([(fixture.members[0].user_id(), BacVote::Recommend)]),
            &fixture.chair,
        )
        .expect_err("two of five is not a quorum");
    assert!(matches!(error, DomainError::RuleViolation(_)));

    let mut no_presiding_officer = BTreeSet::new();
    no_presiding_officer.insert(fixture.members[0].user_id());
    no_presiding_officer.insert(fixture.members[1].user_id());
    no_presiding_officer.insert(fixture.members[2].user_id());
    let error = fixture
        .platform
        .bac_recommend_app(
            app_version,
            "BAC Resolution No. 2026-011",
            fixture.membership.clone(),
            no_presiding_officer,
            BTreeMap::from([
                (fixture.members[0].user_id(), BacVote::Recommend),
                (fixture.members[1].user_id(), BacVote::Recommend),
            ]),
            &fixture.chair,
        )
        .expect_err("the Chair or Vice-Chair must be present");
    assert!(matches!(error, DomainError::RuleViolation(_)));
    assert!(
        fixture
            .platform
            .bac_resolutions_for_version(app_version)
            .is_empty()
    );
    Ok(())
}

#[test]
fn vice_chair_may_preside_in_the_chairs_absence() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let attendance = BTreeSet::from([
        fixture.vice.user_id(),
        fixture.members[0].user_id(),
        fixture.members[2].user_id(),
    ]);
    let votes = BTreeMap::from([
        (fixture.members[0].user_id(), BacVote::Recommend),
        (fixture.members[2].user_id(), BacVote::Recommend),
    ]);
    fixture.platform.bac_recommend_app(
        app_version,
        "BAC Resolution No. 2026-012",
        fixture.membership.clone(),
        attendance,
        votes,
        &fixture.vice,
    )?;
    let resolution = fixture
        .platform
        .bac_resolutions_for_version(app_version)
        .into_iter()
        .next()
        .expect("BAC resolution exists");
    assert!(resolution.is_recommended());
    assert_eq!(resolution.attendance().len(), 3);
    Ok(())
}

#[test]
fn non_presiding_vice_chair_votes_as_a_regular_member() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let attendance = BTreeSet::from([
        fixture.chair.user_id(),
        fixture.vice.user_id(),
        fixture.members[0].user_id(),
        fixture.members[1].user_id(),
    ]);
    let votes = BTreeMap::from([
        (fixture.vice.user_id(), BacVote::Recommend),
        (fixture.members[0].user_id(), BacVote::Recommend),
        (fixture.members[1].user_id(), BacVote::Recommend),
    ]);
    fixture.platform.bac_recommend_app(
        app_version,
        "BAC Resolution No. 2026-013",
        fixture.membership.clone(),
        attendance,
        votes,
        &fixture.chair,
    )?;
    let resolution = fixture
        .platform
        .bac_resolutions_for_version(app_version)
        .into_iter()
        .next()
        .expect("BAC resolution exists");
    assert!(resolution.is_recommended());
    assert_eq!(resolution.attendance().len(), 4);
    Ok(())
}

#[test]
fn chair_breaks_a_tie_only() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let attendance = BTreeSet::from([
        fixture.chair.user_id(),
        fixture.members[0].user_id(),
        fixture.members[1].user_id(),
    ]);
    let tied_votes = BTreeMap::from([
        (fixture.members[0].user_id(), BacVote::Recommend),
        (fixture.members[1].user_id(), BacVote::DoNotRecommend),
    ]);
    let non_tied_votes = BTreeMap::from([
        (fixture.members[0].user_id(), BacVote::Recommend),
        (fixture.chair.user_id(), BacVote::Recommend),
    ]);

    let error = fixture
        .platform
        .bac_recommend_app(
            app_version,
            "BAC Resolution No. 2026-014",
            fixture.membership.clone(),
            attendance.clone(),
            non_tied_votes,
            &fixture.chair,
        )
        .expect_err("the Chair may not vote when the regular members are not tied");
    assert!(matches!(error, DomainError::RuleViolation(_)));

    let mut tie_break = tied_votes;
    tie_break.insert(fixture.chair.user_id(), BacVote::Recommend);
    fixture.platform.bac_recommend_app(
        app_version,
        "BAC Resolution No. 2026-015",
        fixture.membership.clone(),
        attendance,
        tie_break,
        &fixture.chair,
    )?;
    let resolution = fixture
        .platform
        .bac_resolutions_for_version(app_version)
        .into_iter()
        .next()
        .expect("BAC resolution exists");
    assert!(resolution.is_recommended());
    assert_eq!(
        resolution.outcome(),
        BacOutcome::Recommended,
        "the Chair's tie-breaking vote completes the majority"
    );
    Ok(())
}

#[test]
fn bac_decision_requires_a_majority_of_members_present() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;

    let attendance = BTreeSet::from([
        fixture.chair.user_id(),
        fixture.vice.user_id(),
        fixture.members[0].user_id(),
        fixture.members[1].user_id(),
    ]);
    let votes = BTreeMap::from([
        (fixture.members[0].user_id(), BacVote::Recommend),
        (fixture.members[1].user_id(), BacVote::Abstain),
    ]);
    fixture.platform.bac_recommend_app(
        app_version,
        "BAC Resolution No. 2026-016",
        fixture.membership.clone(),
        attendance,
        votes,
        &fixture.chair,
    )?;
    let resolution = fixture
        .platform
        .bac_resolutions_for_version(app_version)
        .into_iter()
        .next()
        .expect("BAC resolution exists");
    assert_eq!(resolution.outcome(), BacOutcome::NoDecision);
    assert!(!resolution.is_recommended());

    let error = fixture
        .platform
        .hope_approve_app(app_version, &fixture.hope, "HoPE approval authority", None)
        .expect_err("a no-decision resolution cannot support HoPE approval");
    assert!(matches!(error, DomainError::MissingPrerequisite(_)));
    Ok(())
}

#[test]
fn signature_method_must_be_in_the_approved_matrix() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::WetSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;
    fixture.approve_app(app_version)?;

    let error = fixture
        .platform
        .request_signature(
            app_version,
            &fixture.hope,
            SignaturePurpose::Execution,
            SignatureMethod::QualifiedElectronicSignature,
            "Execute the APP.",
            OffsetDateTime::now_utc() + Duration::days(3),
        )
        .expect_err("qualified e-signature is not in this matrix");
    assert!(matches!(error, DomainError::RuleViolation(_)));
    assert!(
        fixture
            .platform
            .signature_requests_for_version(app_version)
            .is_empty()
    );
    Ok(())
}

#[test]
fn unconfigured_document_class_denies_signature_requests() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;

    let error = fixture
        .platform
        .request_signature(
            ppmp_version,
            &fixture.hope,
            SignaturePurpose::Approval,
            SignatureMethod::QualifiedElectronicSignature,
            "Approve the PPMP.",
            OffsetDateTime::now_utc() + Duration::days(3),
        )
        .expect_err("no approved signature matrix exists for the PPMP class");
    assert!(matches!(error, DomainError::MissingPrerequisite(_)));
    assert!(
        fixture
            .platform
            .signature_requests_for_version(ppmp_version)
            .is_empty()
    );
    Ok(())
}

#[test]
fn issuance_requires_the_configured_signature() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;
    fixture.approve_app(app_version)?;

    let content_hash = fixture
        .platform
        .version(app_version)
        .expect("APP exists")
        .content_hash()
        .clone();
    let error = fixture
        .platform
        .issue(
            app_version,
            &fixture.secretariat,
            "Issuance authority",
            vec![IssuanceArtifact::new(
                ArtifactId::new(),
                "application/pdf",
                content_hash.clone(),
            )?],
            vec![IssuanceRecipient::new(
                Recipient::new(
                    RecipientId::new(),
                    RecipientKind::InternalOffice,
                    "Records Office",
                    None,
                )?,
                DistributionChannel::Portal,
                true,
            )?],
        )
        .expect_err("approval alone is not enough when the policy requires a signature");
    assert!(matches!(error, DomainError::MissingPrerequisite(_)));
    assert_eq!(
        fixture
            .platform
            .version(app_version)
            .expect("APP exists")
            .status(),
        VersionStatus::Approved,
        "a rejected issuance must not change the version state"
    );

    let signature_request = fixture.platform.request_signature(
        app_version,
        &fixture.hope,
        SignaturePurpose::Execution,
        SignatureMethod::QualifiedElectronicSignature,
        "Execute the APP.",
        OffsetDateTime::now_utc() + Duration::days(3),
    )?;
    fixture.platform.complete_signature(
        signature_request,
        OffsetDateTime::now_utc(),
        "provider-transaction-003",
        "certificate-hope-003",
        content_hash.clone(),
        SignatureValidation::Valid,
    )?;
    let issuance_id = fixture.platform.issue(
        app_version,
        &fixture.secretariat,
        "Issuance authority",
        vec![IssuanceArtifact::new(
            ArtifactId::new(),
            "application/pdf",
            content_hash,
        )?],
        vec![IssuanceRecipient::new(
            Recipient::new(
                RecipientId::new(),
                RecipientKind::InternalOffice,
                "Records Office",
                None,
            )?,
            DistributionChannel::Portal,
            true,
        )?],
    )?;
    assert!(fixture.platform.issuance(issuance_id).is_some());
    assert_eq!(
        fixture
            .platform
            .version(app_version)
            .expect("APP exists")
            .status(),
        VersionStatus::Issued
    );
    Ok(())
}

#[test]
fn correction_preserves_and_invalidates_prior_evidence() -> Result<(), DomainError> {
    let mut fixture = Fixture::new(vec![SignatureMethod::QualifiedElectronicSignature])?;
    let ppmp_version = fixture.create_ppmp()?;
    let app_version = fixture.create_indicative_app(ppmp_version)?;
    fixture.approve_app(app_version)?;
    let signature_request = fixture.platform.request_signature(
        app_version,
        &fixture.hope,
        SignaturePurpose::Execution,
        SignatureMethod::QualifiedElectronicSignature,
        "Execute the APP.",
        OffsetDateTime::now_utc() + Duration::days(3),
    )?;
    let content_hash = fixture
        .platform
        .version(app_version)
        .expect("APP exists")
        .content_hash()
        .clone();
    fixture.platform.complete_signature(
        signature_request,
        OffsetDateTime::now_utc(),
        "provider-transaction-002",
        "certificate-hope-002",
        content_hash.clone(),
        SignatureValidation::Valid,
    )?;
    let issuance_id = fixture.platform.issue(
        app_version,
        &fixture.secretariat,
        "Issuance authority",
        vec![IssuanceArtifact::new(
            ArtifactId::new(),
            "application/pdf",
            content_hash,
        )?],
        vec![IssuanceRecipient::new(
            Recipient::new(
                RecipientId::new(),
                RecipientKind::InternalOffice,
                "Records Office",
                None,
            )?,
            DistributionChannel::Portal,
            true,
        )?],
    )?;
    let distribution_id = fixture
        .platform
        .issuance(issuance_id)
        .expect("issuance exists")
        .distribution_ids()[0];
    let corrected_content = AppContent {
        fiscal_year: 2026,
        edition: AppEdition::Indicative,
        lines: vec![AppLine {
            line_id: "APP-001".to_owned(),
            source_ppmp_version_id: ppmp_version,
            source_ppmp_line_id: "PPMP-001".to_owned(),
            description: "Drainage system rehabilitation - corrected scope".to_owned(),
            abc: Amount::php_pesos(1_500_000).expect("amount is valid"),
            schedule: "Q3 2026".to_owned(),
            mode: "Competitive Bidding".to_owned(),
        }],
        basis_version_id: None,
    };
    let corrected_version = fixture.platform.create_correction(
        app_version,
        corrected_content.to_canonical()?,
        &fixture.secretariat,
        Fixture::template("app"),
        Fixture::schema("app"),
        fixture.rule_pack_b.clone(),
    )?;

    assert_eq!(
        fixture
            .platform
            .version(app_version)
            .expect("old version exists")
            .status(),
        VersionStatus::Superseded
    );
    assert_eq!(
        fixture
            .platform
            .version(corrected_version)
            .expect("new version exists")
            .status(),
        VersionStatus::Draft
    );
    assert_eq!(
        fixture
            .platform
            .version(corrected_version)
            .expect("new version exists")
            .correction_of(),
        Some(app_version)
    );
    assert!(
        fixture
            .platform
            .approvals_for_version(app_version)
            .iter()
            .all(|approval| approval.status() == ApprovalStatus::Superseded)
    );
    assert!(
        fixture
            .platform
            .signatures_for_version(app_version)
            .iter()
            .all(|signature| signature.status() == SignatureStatus::Invalidated)
    );
    assert!(
        fixture
            .platform
            .issuance(issuance_id)
            .expect("issuance exists")
            .status()
            == IssuanceStatus::Superseded
    );
    assert_eq!(
        fixture
            .platform
            .distribution(distribution_id)
            .expect("distribution exists")
            .status(),
        DistributionStatus::Superseded
    );
    let event_types: Vec<&str> = fixture
        .platform
        .audit_events()
        .iter()
        .map(|event| event.event_type.as_str())
        .collect();
    assert!(event_types.contains(&"version.correction_created"));
    assert!(event_types.contains(&"version.superseded"));
    assert!(fixture.platform.verify_audit_chain());
    Ok(())
}

# ProcuChain Implementation TODO

> **Baseline:** `18cb8c28` — governed APP vertical slice implemented in the Rust domain core, in-memory only.
> **Normative source:** [`docs/spec/`](docs/spec/README.md), especially [`acceptance-criteria.md`](docs/spec/acceptance-criteria.md) §18 and [`architecture-spec.md`](docs/spec/architecture-spec.md) §23.
> **Rule:** legal ambiguity is marked `[L]` and must not be turned into settled behavior without review.

## Status legend

- `[x]` — implemented in the domain/application core and covered by automated tests.
- `[~]` — partially implemented; the remaining production obligation is described.
- `[ ]` — not started.
- `[L]` — requires legal/compliance review before activation.

`[x]` does **not** mean production-ready. The current code has no durable storage, authenticated principals, HTTP API, React workbench, rendering pipeline, publication adapter, or evidence export.

---

## 1. Current baseline

- [x] Specification suite committed.
- [x] Typed identifiers, canonical SHA-256 content hashing, money, actors, and domain errors.
- [x] Immutable documents and versions; correction lineage; rule-pack references pinned per version.
- [x] Append-only hash-chained audit log with tamper detection.
- [x] Version-bound approvals and BAC resolutions.
- [x] BAC composition, quorum, majority-decision, Chair/Vice-Chair presence, and tie-break rules.
- [x] Signature policy, signature requests, signature evidence fields, and method-matrix checks.
- [x] Issuance manifests, distributions, delivery attempts, and artifact-bound acknowledgements.
- [x] PPMP and APP typed content, including indicative-to-final APP derivation.
- [x] In-memory `Platform` service covering PPMP → indicative APP → BAC → HoPE → final APP → signature → issuance → delivery → acknowledgement.
- [x] Correction flow invalidating approvals, signatures, issuances, distributions, and acknowledgements.
- [x] 11 integration tests covering the slice and key negative gates.

---

## 2. Next up — recommended order

### 2.1 Persist the vertical slice (P0)

The in-memory service works; persistence now rehydrates the same aggregates from PostgreSQL.

- [x] Choose and document the persistence stack: PostgreSQL + SQLx + migrations.
- [x] Add local PostgreSQL via Docker Compose and a CI service container. Local development can use `postgres://procuchain:procuchain_local_dev@127.0.0.1:5433/procuchain` and `PROCUCHAIN_TEST_DATABASE_URL`.
- [x] Create migrations for:
  - tenants, users, roles, organizational units, committees, and memberships;
  - documents, document versions, canonical content, template/schema/rule-pack references;
  - approvals, BAC resolutions, attendance, votes, and membership snapshots;
  - signature requests, signatures, and validation evidence;
  - issuances, issuance artifacts, distributions, delivery attempts, and acknowledgements;
  - audit events, per-tenant audit sequence, outbox, and idempotency records.
- [x] Define repository ports in the application layer and SQLx adapters in infrastructure.
- [x] Introduce a unit-of-work boundary that commits domain state, audit events, and outbox rows atomically.
- [x] Store canonical content as JSONB or immutable objects, with the hash recomputed on read.
- [x] Add repository contract tests and restart/rehydration tests (`crates/postgres/tests/persistence.rs`).
- [ ] Add migration and rollback checks to CI.
- [ ] Persist the remaining `Platform` orchestration state so commands can rebuild the full slice from PostgreSQL alone, not only per-aggregate round trips.
- [ ] Replace caller-supplied timestamps with server-controlled clocks at the API boundary.

### 2.2 Identity, tenancy, and authorization (P0)

The current platform trusts an `Actor` supplied by the caller. A real API must resolve the authenticated principal server-side.

- [ ] Model tenant/Procuring Entity, organizational unit, user, role, assignment, authority instrument, committee, and membership.
- [ ] Persist BAC designations and derive the membership snapshot from the designation rather than accepting it from the caller.
- [ ] Replace caller-asserted `Actor` values with authenticated principals resolved by the API layer.
- [ ] Implement role- and attribute-based authorization, including segregation of duties.
- [ ] Add conflict-of-interest declarations, recusal, delegation, and alternate-member rules.
- [ ] Enforce tenant isolation and object-level authorization.
- [ ] Audit sensitive views, downloads, exports, and disclosures.

### 2.3 API and React workbench (P0)

- [~] Choose Axum (or document an alternative) and add versioned `/api/v1` command/resource endpoints. Axum is now the transport boundary; only the initial health endpoint exists.
- [ ] Add OpenAPI generation and contract tests.
- [~] Add idempotency keys and optimistic concurrency for state-changing commands. PPMP creation, indicative APP creation, BAC resolution creation, HoPE approval, signature-request creation, signature completion, and document submission support transactional idempotency; submission, APP derivation, BAC resolution, HoPE approval, signature-request creation, and signature completion validate `If-Match` row-version preconditions. Remaining commands still need keys and version preconditions.
- [~] Add the stable API error model: machine code, message, correlation ID, field violations. Machine codes and correlation IDs are implemented; field-level violations remain.
- [ ] Add authentication/session handling, CSRF protection, security headers, and rate limits.
- [ ] Build React/TypeScript workbench pages for:
  - my tasks and overdue actions;
  - document/version view with content hash and rule-pack pin;
  - PPMP authoring and validation;
  - indicative/final APP preparation;
  - BAC membership, attendance, votes, and resolution;
  - HoPE approval;
  - signature request and evidence;
  - issuance, distribution, delivery, acknowledgement;
  - audit/evidence reconstruction and printable export.
- [ ] Add browser/API end-to-end tests for AC-120.

### 2.4 Legal and compliance foundation (P1)

- [ ] Build the legal-source register with authority, instrument, version, provision, provenance, and effective dates.
- [ ] Implement legal review items and block activation of uncertain interpretations.
- [ ] Implement requirements, controls, rules, and control outcomes.
- [ ] Implement rule-pack assembly, immutable snapshots, hashing, and historical lookup.
- [ ] Move BAC, signature-method, and deadline formulas out of ad hoc domain code into the compliance module.
- [ ] Implement impact analysis for legal changes and affected active workflows.
- [ ] Implement an exception register with authority, scope, expiry, and compensating controls.
- [ ] Add legal fixtures with citations and positive, negative, and boundary cases.

### 2.5 Complete the G2 evidence chain (P1)

- [ ] Register official GPPB template identifiers and versions; do not invent form numbers.
- [ ] Add template/schema validation, mandatory fields, and completeness checks.
- [ ] Implement deterministic PDF/DOCX rendering and artifact hashing.
- [ ] Implement the workflow engine: definitions, instances, steps, tasks, claim/complete, parallel/quorum steps, return for correction, escalation, and timers.
- [ ] Add approval sets and recorded quorum results.
- [ ] Implement a provider-neutral signature adapter, callback security, verification, revocation, and evidence bundles.
- [ ] Implement signature-method matrices per PE and document class.
- [ ] Implement issuance withdrawal/reissue and per-distribution artifact binding.
- [ ] Implement publication records, PhilGEPS adapter boundary, manual fallback, and reconciliation evidence.
- [ ] Implement evidence-bundle assembly, hashed/signed manifests, export, and printable evidence views.
- [x] Implement the audit hash chain, Merkle-batched anchors, inclusion proofs, and anchor verification.
- [ ] Configure an RFC 3161 timestamp authority and store its receipts. `[L]` Provider selection and admissibility need legal and records-management review.
- [ ] Add WORM retention and public posting of anchor roots and inclusion proofs. `[L]` Publishing channel and legal effect need review.
- [ ] Run an end-to-end browser/API acceptance test and independent audit reconstruction.

---

## 3. Release-gate backlog

### G0 — Legal baseline approved

- [ ] **AC-010** Authoritative source baseline.
- [ ] **AC-011** Withdrawn unofficial IRR rejected.
- [~] **AC-012** Source-to-control traceability — rule-pack refs and audit pins exist, but rule packs are opaque and have no legal-source provenance.
- [ ] **AC-013** No generic override.
- [L] **AC-014** Legal review blocks uncertain interpretation.
- [~] **AC-015** Historical rule snapshot — snapshot IDs/hashes are pinned per version; snapshot contents are not stored or retrievable.
- [ ] **AC-016** Legal change impact analysis.
- [ ] **AC-017** Exception controls.

### G1 — Platform foundation

#### Document lifecycle

- [~] **AC-020** Structured document creation — canonical content exists; no template/schema validation or structured editor.
- [~] **AC-021** Immutable version — enforced in memory; not yet enforced by durable storage permissions.
- [ ] **AC-022** Reproducible artifact.
- [~] **AC-023** Correction lineage — correction creates a new version and preserves the old one; no comparison view.
- [ ] **AC-024** Template deviation control.
- [ ] **AC-025** External submission distinction.
- [~] **AC-026** Invalidated downstream decision — domain invalidation works; no persisted history/query surface.

#### Workflow, approval, and quorum

- [ ] **AC-030** Versioned workflow execution.
- [x] **AC-031** Approval and signature separation.
- [~] **AC-032** BAC composition — 5/7 seats, exactly one Chair and Vice-Chair enforced; roster is caller-supplied, not a persisted HoPE designation.
- [x] **AC-033** BAC quorum.
- [x] **AC-034** BAC decision rule.
- [~] **AC-035** Quorum cannot be spoofed by workflow configuration — currently hardcoded, not yet a dedicated reviewed control.
- [ ] **AC-036** Conflict and recusal.
- [ ] **AC-037** Return for correction.
- [ ] **AC-038** Delegation limits.
- [ ] **AC-039** Task inbox.

#### Electronic signatures

- [x] **AC-040** Version-bound signature request.
- [ ] **AC-041** Provider-neutral adapter.
- [~] **AC-042** Signature evidence — fields exist; no provider evidence package or durable artifact.
- [~] **AC-043** Signature verification — validation states exist; no provider verification or revocation check.
- [~] **AC-044** Signature-method matrix — in-memory policy; not persisted, versioned, or PE-scoped.
- [L] **AC-045** Legal method review.
- [x] **AC-046** Failed verification not shown as valid.

#### Issuance, distribution, and acknowledgement

- [~] **AC-050** Issuance prerequisites — approval/signature gates enforced in memory; no aggregate list of every unmet prerequisite.
- [~] **AC-051** Issue manifest — deterministic manifest hash exists; not persisted or externally verifiable.
- [ ] **AC-052** Separate publication and distribution.
- [~] **AC-053** Delivery evidence — attempts, failures, receipts, and acknowledgements exist in memory; no overdue tracking or external portal.
- [x] **AC-054** Non-receipt is not receipt.
- [ ] **AC-055** External matter isolation.

#### Audit, evidence, and integrity

- [~] **AC-130** Append-only audit — hash chain exists; no database role restriction or per-tenant sequence.
- [ ] **AC-131** Evidence bundle completeness.
- [~] **AC-132** Tamper detection — audit chain and anchor verification detect mismatches; no storage-level alerting.
- [~] **AC-133** Anchoring is supplementary — anchors, receipts, and inclusion proofs exist; no external timestamp authority or public posting yet.
- [~] **AC-134** Anchor ranges are contiguous and non-duplicated — the advisory lock and range check enforce this; concurrency test still needed.

#### Security and privacy

- [~] **AC-150** Tenant isolation — tenant ID exists; no isolation enforcement across storage, search, jobs, or exports.
- [ ] **AC-151** Object authorization.
- [ ] **AC-152** Malware quarantine.
- [ ] **AC-153** Privileged action re-authentication.
- [ ] **AC-154** Audit of access and disclosure.
- [L] **AC-155** Legal privacy review.

#### Availability and operations

- [ ] **AC-160** Restore test.
- [ ] **AC-161** Queue recovery.
- [ ] **AC-162** Integration outage.
- [ ] **AC-163** Observability and correlation.
- [ ] **AC-164** Monitoring and alerts.

### G2 — First vertical slice

- [~] **AC-090** APP linkage hard gate — APP line links to a PPMP version; no procurement-case linkage yet.
- [ ] **AC-091** EPA restrictions.
- [~] **AC-091A** APP recommendation, posting, and submission — recommendation and HoPE approval exist; posting/submission evidence does not.
- [~] **AC-120** End-to-end PPMP to acknowledgement — in-memory integration test only; needs browser/API verification.
- [~] **AC-121** Vertical-slice audit reconstruction — domain records and audit chain are asserted; no evidence bundle or independent reconstruction tool.
- [~] **AC-122** Vertical-slice correction — domain invalidation works; needs durable history and renewed-approval gate test through the API.

> Spec defect: G2 lists **AC-130–AC-136**, but **AC-135** and **AC-136** are not defined anywhere in `acceptance-criteria.md`. Resolve the gate reference before sign-off.

### G3 — Planning and readiness

- [ ] **AC-092** Pre-procurement conference threshold.
- [ ] **AC-092A** Conference recording and livestream.
- [ ] **AC-092B** Anti-splitting and CSE-source controls.
- [ ] **AC-093** Award criterion lock.
- [ ] **AC-094** Bidding Documents completeness.
- [ ] **AC-095** Equal access before release.
- [ ] **AC-096** Publication window.
- [ ] **AC-096A** Pre-bid conference threshold and timing.
- [ ] **AC-096B** Clarification and bulletin deadlines.
- [ ] **AC-096C** Publication channels and posting periods.
- [ ] **AC-096D** Electronic bidding certification.
- [ ] **AC-096E** Invitation to Bid content.

### G4 — Bidding through award

- [ ] **AC-097** Late bid handling.
- [ ] **AC-097A** Maximum bid-receipt periods.
- [ ] **AC-097B** Bid receipt notice.
- [ ] **AC-097C** Bid security and validity.
- [ ] **AC-097D** Relations and beneficial-ownership disclosure.
- [ ] **AC-097E** Eligibility document set and thresholds.
- [ ] **AC-097F** Ownership, joint venture, and foreign-document rules.
- [ ] **AC-097G** Qualification reassessment.
- [ ] **AC-097H** Envelope structure and Omnibus Sworn Statement.
- [ ] **AC-097I** Modification and withdrawal.
- [ ] **AC-098** ABC ceiling.
- [ ] **AC-098A** Award-criterion-specific opening.
- [ ] **AC-098B** Preliminary-examination pass/fail.
- [ ] **AC-099** Evaluation reproducibility.
- [ ] **AC-100** Post-qualification cascade.
- [ ] **AC-101** Disapproval grounds.
- [ ] **AC-101A** Reconsideration after disapproval or inaction.
- [ ] **AC-101B** Failure of bidding and re-bidding.
- [ ] **AC-101C** Single responsive bid scenario.
- [ ] **AC-102** Award preconditions.
- [ ] **AC-102A** Goods amendment-to-order limits.
- [ ] **AC-102B** Goods liquidated damages.
- [ ] **AC-102B1** Infrastructure variation-order limits.
- [ ] **AC-102C** Goods warranty period and security.
- [ ] **AC-102C1** Infrastructure warranty obligations and structural-defect periods.
- [ ] **AC-102C2** Contract liquidated damages.
- [ ] **AC-102C3** Fixed price and escalation path.
- [ ] **AC-102D** Performance-security form, amount, and release.
- [ ] **AC-103** Award blocked by unresolved protest.

### G5 — Contract and remedies

- [ ] **AC-104** Reconsideration envelope hold.
- [ ] **AC-105** Verified protest position paper.
- [ ] **AC-105A** LGU protest finality thresholds.
- [ ] **AC-105B** Protest fee schedule.
- [ ] **AC-105C** Position-paper completeness and oath certifications.
- [ ] **AC-106** Suspension and blacklisting periods.
- [ ] **AC-106A** Sanction-decision finality.
- [ ] **AC-107** Automatic pending suspension.
- [ ] **AC-108** GPPB sanction posting.
- [ ] **AC-108A** Sanction grounds and related-party scope.
- [ ] **AC-108B** Sanction procedure and declaration enforcement.
- [ ] **AC-108C** Dispute pathway and interim-relief restriction.
- [ ] **AC-109** Procurement record closure.

### G6 — Records, migration, and production scale

- [ ] **AC-140** Retention schedule enforcement.
- [ ] **AC-141** Legal hold precedence.
- [ ] **AC-142** Fixity monitoring.
- [ ] **AC-143** Permission-aware search.
- [ ] **AC-144** Duplicate detection is non-destructive.
- [ ] **AC-170** Repeatable import.
- [ ] **AC-171** No fabricated compliance.
- [ ] **AC-172** Legacy chain reconciliation.
- [ ] **AC-173** Unmappable records.
- [ ] **AC-174** Cutover rehearsal.

### Cross-cutting release controls

- [ ] **AC-180** Work-first usability.
- [ ] **AC-181** Control explanation.
- [ ] **AC-182** Accessible critical tasks.
- [ ] **AC-183** Printable evidence.
- [ ] **AC-190** Gate record.
- [ ] **AC-191** Deferred criterion.
- [ ] **AC-200** Synthetic data.
- [ ] **AC-201** Deterministic clock.
- [ ] **AC-202** Legal fixtures.
- [ ] **AC-203** Provider fakes.

---

## 4. Cross-cutting engineering work

### Testing

- [ ] Domain unit tests and property tests for canonical hashing, money, and state machines.
- [ ] Repository and transaction integration tests against PostgreSQL.
- [ ] API contract tests generated from OpenAPI.
- [ ] Browser end-to-end tests for the workbench.
- [ ] Fault-injection tests for duplicate callbacks, outages, retries, and poison messages.
- [ ] Deterministic clock/calendar in every time-sensitive test.
- [ ] Legal-control fixtures with citations and boundary cases.
- [ ] Security tests for tenant isolation, object authorization, and privilege escalation.

### Operations

- [ ] Docker Compose for local PostgreSQL, object storage, and fake providers.
- [ ] CI: formatting, clippy, tests, migrations, frontend checks, dependency audit.
- [ ] Structured logging, correlation IDs, metrics, tracing, and health endpoints.
- [ ] Backup/restore and point-in-time recovery rehearsal.
- [ ] Outbox worker, durable queue, retry policy, dead-letter handling.
- [ ] Deployment topology and infrastructure-as-code decision.

### Security and privacy

- [ ] Threat model for document, workflow, signature, publication, and export boundaries.
- [ ] Secrets management and encryption at rest/in transit.
- [ ] Malware scanning and quarantine state machine.
- [ ] Step-up/re-authentication for signing, delegation, legal-hold release, and disposition.
- [ ] Data inventory and privacy review.

### Migration and compatibility

- [ ] Inventory Laravel entities, routes, documents, and blockchain records.
- [ ] Define import mappings and provenance rules.
- [ ] Reconcile legacy content hashes and legacy chain history.
- [ ] Parallel-run and cutover plan.
- [ ] Preserve historical records without assigning fabricated compliance.

---

## 5. Known gaps and risks to resolve early

- [ ] **Caller-supplied actors are not authentication.** `Platform` trusts any `Actor` passed to it; the API must resolve the principal and must never accept a client-supplied role as authority.
- [ ] **Legal rules are hardcoded.** BAC and signature rules currently live in domain code, contrary to ARC-014. Move them into reviewed rule packs and evaluated controls.
- [ ] **Empty signature policy semantics.** An empty policy blocks signing but also makes `requires_signature` false, so issuance can proceed on approval alone. Decide, with legal review, whether “no configured rule” means “no signature required” or “issuance blocked.”
- [ ] **Acknowledgement artifact binding.** Acknowledgement checks that the hash belongs to the issuance, not that it is the specific artifact delivered to that recipient. Add per-distribution artifact binding before multiple artifacts are supported.
- [ ] **Signature completion trusts provider evidence.** There is no authenticated callback or actor check on `complete_signature`.
- [~] **Audit durability.** The chain and anchors persist with append-only triggers and advisory-lock sequencing; database-role restrictions, integrity alerting, and external timestamping remain.
- [ ] **Rule-pack placeholder.** `RulePackRef` stores only a label and hash; the actual pack, legal citations, and evaluation outcomes are missing.
- [ ] **Repo coexistence.** The Rust crate and Laravel app share the repository root. Decide the target layout and cutover boundary before adding infrastructure.
- [ ] **Toolchain pin.** `rust-toolchain.toml` currently uses `stable` because the pinned `1.99.0` installation was corrupted; restore an exact pin when the toolchain is repaired.
- [ ] **Spec gate defect.** G2 references undefined AC-135 and AC-136.
- [~] **No production object store or backup.** Relational and audit state persist in PostgreSQL; artifacts, backups, and restore drills are still absent.

---

## 6. Decisions needed before the next large increment

- [ ] Web framework: Axum recommended, or document another choice.
- [x] Repository layout: virtual Cargo workspace with bounded-context crates (`shared`, `records`, `workflow`, `signatures`, `issuance`, `procurement`, `evidence`, `app`, and the `procuchain` binary).
- [x] Persistence: PostgreSQL + SQLx with embedded migrations and runtime-checked queries.
- [ ] Local object storage: MinIO/S3-compatible or filesystem abstraction with immutable keys.
- [ ] Frontend delivery: React SPA against REST/OpenAPI versus an Inertia-compatible Rust adapter.
- [ ] Authentication for local development and the target government identity provider.
- [ ] Signature provider and fake-provider contract.
- [ ] PhilGEPS integration contract, credentials, and manual-fallback evidence format.
- [x] Integrity anchoring: local audit hash chain, Merkle roots, inclusion proofs, and a receipt-bearing anchor port. Hyperledger Fabric is the selected optional consortium-ledger adapter; deployment, RFC 3161 provider, and posting channel remain `[L]`.
- [ ] Rule-pack authoring format and schema.
- [ ] Deadline calendar source, holiday handling, and time authority (`Asia/Manila`).
- [ ] Deployment target and backup/restore objectives.
- [ ] Legal/compliance review owner and sign-off workflow.

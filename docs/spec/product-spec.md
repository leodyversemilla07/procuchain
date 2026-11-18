# Product Specification: ProcuChain eProcurement Document Platform

> **Status:** Build target
> **Specification version:** 0.1
> **Depends on:** [Specification index](README.md)

## 1. Product vision

ProcuChain is a secure, auditable platform in which a Procuring Entity creates and manages the complete documentary lifecycle of procurement—from planning and bidding through award, contract implementation, protest, sanctions, closure, and retention.

Every governed document has an authoritative source, an explicit lifecycle, a traceable workflow, and verifiable evidence. Users do not assemble official procurement records across disconnected spreadsheets, chat messages, email attachments, local folders, and separately signed PDFs.

## 2. Problem statement

The current implementation provides useful blockchain-backed procurement workflows, document integrity checks, and dashboards, but it does not yet provide a complete document-management and legal-workflow platform. Important gaps include:

- documents are commonly treated as uploaded attachments rather than governed business records;
- approval, signature, issuance, and receipt are not consistently modeled as separate lifecycle actions;
- document revision, invalidation of prior decisions, and version comparison are incomplete;
- legal requirements are embedded in application logic and summaries rather than a traceable, versioned rule source;
- full-text retrieval, records governance, legal hold, retention, and disposition are absent;
- internal authoring, external submission, public publication, and official records are not one coherent flow;
- an administrator can change workflow behavior without a transparent legal and historical impact analysis.

## 3. Product goals

### PD-001 — Govern the complete document lifecycle

The platform MUST support creation, validation, review, approval, signature, issuance, distribution, acknowledgement, publication, amendment, archival, retention, and disposition of procurement documents.

### PD-002 — Make legal compliance executable

Applicable legal requirements MUST be represented as versioned, citable, testable controls. The platform MUST prevent unauthorized transitions, calculate statutory periods, and preserve the evidence used to reach a decision.

### PD-003 — Keep every decision attached to an exact version

An approval, signature, issuance, distribution, or acknowledgement MUST identify the immutable document version to which it applies. Editing that version MUST create a new version and invalidate affected downstream decisions.

### PD-004 — Provide one operational work queue

Every user MUST be able to identify documents awaiting their action, tasks approaching a legal deadline, overdue work, requested changes, pending signatures, and unacknowledged issuances.

### PD-005 — Preserve an audit-ready record

The platform MUST reconstruct who created, viewed, edited, reviewed, approved, signed, issued, received, superseded, or disposed of a document, including the authority and evidence applied at that time.

### PD-006 — Support internal and external participants

The platform MUST support Procuring Entity staff, observers, and authorized external parties while preserving confidentiality, equal access, segregation of duties, and the legally required publication boundary.

### PD-007 — Preserve mandatory PhilGEPS use

ProcuChain MUST prepare and track procurement publications and preserve their official receipts, but MUST NOT represent itself as a substitute for mandatory PhilGEPS use.

### PD-008 — Remain extensible beyond procurement

The document, workflow, evidence, and records foundations SHOULD be reusable for APP, PPMP, supplier records, contracts, complaints, and other municipal records. The first product release MUST optimize for procurement rather than attempt a generic enterprise DMS.

## 4. Non-goals

The first release will not:

- replace PhilGEPS or implement procurement payment;
- provide a general-purpose office suite or unrestricted collaborative word processor;
- give administrators a control to bypass statutory requirements;
- provide legal advice or decide ambiguous legal questions automatically;
- replace signatures required to remain wet under an applicable instrument or local policy;
- use an anchor provider as a relational database or full-text search engine;
- treat anchoring as proof of workflow truth or legal validity;
- reproduce every legacy Laravel route merely for parity;
- treat an external bidder document as an internally authored official document.

## 5. Product principles

1. **Document first:** procurement processes operate over governed documents and evidence.
2. **Law before configuration:** higher-authority requirements cannot be weakened by workflow configuration.
3. **Structured facts, rendered documents:** calculations, dates, scores, parties, and decisions are data; PDFs and DOCX files are controlled renderings.
4. **Immutable decisions:** approvals and signatures apply to exact versions and hashes.
5. **One clock, explicit events:** deadlines are computed from recorded events and a versioned rule, not from mutable text.
6. **Evidence over claims:** a status such as “posted,” “approved,” or “signed” requires evidence.
7. **Least privilege:** access follows role, matter, classification, and stage.
8. **Historical reproducibility:** historical workflows remain explainable after rules, people, or templates change.
9. **PhilGEPS-aware:** internal preparation never replaces required external publication.
10. **Progressive complexity:** begin with structured templates and controlled text, not a full Word clone.

## 6. Users and primary jobs

| Actor | Primary jobs |
| --- | --- |
| HoPE / Local Chief Executive | Review recommendations, approve plans and awards, decide protests and sanctions, monitor deadlines and delegated authority. |
| BAC Chair | Preside, route documents, record decisions, sign authorized instruments, monitor BAC deadlines and quorum. |
| BAC member | Review evidence, disclose interests, attend meetings, approve/reject within authority, and sign where authorized. |
| BAC Secretariat | Prepare documents, maintain templates and records, administer routing and publication, track milestones, assemble evidence bundles. |
| End-User / Implementing Unit | Prepare PPMP, specifications, terms of reference, estimates, and supporting records; respond to requested changes. |
| Technical Working Group | Review technical criteria and specifications and record findings. |
| Observer | Access permitted records, observe proceedings, and submit required observer reports. |
| Bidder / supplier | Receive official documents, submit and acknowledge records, respond to clarifications, and file permitted requests or protests. |
| Records/compliance officer | Manage classification, retention, legal holds, disposition, export, audits, and rule-pack governance. |
| System administrator | Operate infrastructure, identities, integrations, and monitoring without changing statutory meaning. |
| Oversight/public user | Access records and notices that are lawfully published. |

## 7. Product capability areas

### 7.1 Document authoring

- Versioned templates and document types.
- Structured forms, reusable clauses, controlled rich text, and calculated fields.
- Mandatory-field, consistency, formula, and document-completeness validation.
- Draft preview and deterministic PDF/DOCX rendering.
- Document comparison and correction lineage.
- Mandatory GPPB standard forms where prescribed.

### 7.2 Workflow and routing

- Configurable, versioned workflow definitions.
- Serial, parallel, quorum, majority, conditional, deadline, and return-for-correction steps.
- Role-, office-, committee-, and person-based assignment.
- Delegation, alternate BAC members, authorized signatories, abstention, recusal, and conflict declaration.
- Task inbox, escalation, reminders, and full decision history.

### 7.3 Approval and signature

- Separate workflow approval and electronic-signature actions.
- Version-bound signature requests and evidence packages.
- Pluggable signature/PKI provider integration.
- Signature validity, revocation, and verification reports.
- Document-type signature matrix for each Procuring Entity.

### 7.4 Issuance, publication, and receipt

- Official issuance distinct from approval and signature.
- Distribution to named recipients and controlled public publication.
- Delivery and acknowledgement evidence.
- PhilGEPS and other required-channel publication adapters or controlled manual-publication records.

### 7.5 Tracking and obligations

- Action ownership, blockers, deadlines, reminders, escalations, and tolling.
- Procurement milestone and contract-obligation tracking.
- Cross-document dependency views for required records, approvals, signatures, and publications.

### 7.6 Records and discovery

- Metadata classification, controlled vocabularies, repositories/collections, tags, and relationships.
- Full-text and OCR retrieval, metadata facets, date ranges, and permission-aware results.
- Retention schedules, legal holds, archival, disposition review, and export manifests.
- Duplicate detection and fixity monitoring.

### 7.7 Collaboration and controlled access

- Comments, annotations, mentions, and change requests.
- Per-document and per-collection access controls.
- Segregation of duties and conflict controls.
- Controlled sharing without weakening confidentiality or publication rules.

## 8. Primary journeys

### 8.1 Planning document: PPMP to final APP

1. End-User creates a PPMP from the current template.
2. System validates required structured data and links market-scoping evidence.
3. Secretariat consolidates the item into the Indicative APP.
4. BAC reviews and records a recommendation.
5. HoPE approves or disapproves with written grounds when required.
6. Approved version is signed where required, issued, published, and acknowledged.
7. Amendments create new versions and preserve the prior approval chain.

### 8.2 BAC resolution

1. Secretariat prepares a resolution from a controlled template.
2. System populates cited procurement and meeting facts.
3. Attendance, quorum, conflicts, and recusals are validated.
4. BAC members record individual decisions on the exact draft.
5. The system applies the required majority and abstention rules.
6. Authorized signatories sign the approved version.
7. The signed resolution is issued and linked to the evidence bundle.

### 8.3 Bid bulletin

1. A clarification or authorized amendment is recorded.
2. The system calculates publication and deadline windows from the applicable rule.
3. The authorized approver reviews and signs the bulletin.
4. The bulletin is published through all required channels.
5. Each publication and receipt is evidenced; affected bidders are notified.

### 8.4 Protest and reconsideration

1. Authorized submission is received and time-stamped.
2. The system validates filing period, form, verification, fee, and prior reconsideration requirements.
3. The bid or decision is held as required by the applicable rule.
4. BAC or HoPE review tasks and deadlines are created.
5. The decision, evidence, legal basis, and resulting corrective action are recorded.

### 8.5 Contract implementation

1. The executed contract and securities form the authoritative file.
2. Delivery, acceptance, warranty, variation, payment, and termination obligations are tracked.
3. Amendments, orders, extensions, liquidated damages, and notices are created as governed documents.
4. The final acceptance and closure evidence complete the procurement record.

## 9. Boundaries and relationships

- **PhilGEPS:** mandatory external procurement portal and publication/electronic-bidding channel. ProcuChain manages preparation, traceability, and reconciliation around it.
- **Evidence anchor:** supplementary integrity corroboration for audit roots, normally an RFC 3161 timestamp over a Merkle root, with WORM retention and public posting. A consortium ledger is an optional adapter only when independent institutions operate nodes. No anchor is authoritative for workflow state, document bytes, or personal data.
- **Government identity/PKI:** supplies identity assertions, certificates, and signature evidence where adopted.
- **Supplier systems:** bidder data remains authoritative in its source system; ProcuChain records received copies and their evidence.

## 10. Success measures

The release is successful when the following can be measured from system evidence:

- percentage of governed procurement documents created from an approved template;
- percentage of issued documents with signature and issuance evidence;
- percentage of legal deadlines with a reproducible computed due date and source;
- number of workflow transitions blocked by a documented legal control;
- time required to reconstruct a decision or procurement evidence bundle;
- search success and time-to-first-result for authorized users;
- number of overdue obligations and acknowledgement gaps;
- percentage of active procurements pinned to an immutable rule-pack and template version;
- integrity verification results for stored versions and published artifacts.

Targets for these measures MUST be set by the Procuring Entity before production acceptance.

## 11. First-release scope recommendation

The first release SHOULD prove the platform with one complete, legally meaningful vertical slice:

```text
PPMP
→ Indicative APP
→ BAC recommendation/resolution
→ HoPE approval
→ signed final APP
→ issued and acknowledged
```

The slice MUST exercise authoring, validation, a multi-step approval route, quorum rules where applicable, signature evidence, issuance, publication/acknowledgement, audit reconstruction, and rule-pack pinning. Competitive bidding, award, protest, and contract implementation follow only after this foundation is accepted.

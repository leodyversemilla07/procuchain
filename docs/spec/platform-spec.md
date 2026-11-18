# Platform Specification: Governed Document and Workflow Platform

> **Status:** Build target
> **Specification version:** 0.1
> **Depends on:** [Product Specification](product-spec.md), [Compliance Specification](compliance-spec.md)

## 1. Purpose and scope

This specification defines the reusable document, workflow, approval, signature, issuance, distribution, tracking, collaboration, records-management, and external-participant capabilities of ProcuChain.

The procurement module defined in the [Procurement Lifecycle Specification](procurement-lifecycle-spec.md) composes these capabilities. This platform MUST remain capable of supporting other document classes without changing the legal-control model.

## 2. Normative conventions

Requirements use the RFC 2119 keywords defined in the [Specification Index](README.md). Every requirement has a stable identifier in the form `PLAT-NNN`.

## 3. Core object model

### PLAT-001 — Logical document

The system MUST maintain a `Document` as the stable logical record. A document represents one governed business object—such as a PPMP, APP, BAC resolution, procurement project, contract, or sanction case.

A document MUST NOT be identified solely by a file path or external transaction number.

### PLAT-002 — Immutable document version

Every saveable state MUST be represented by a `DocumentVersion`. A version MUST contain:

- parent document and version number;
- template and schema versions used to author it;
- canonical structured content;
- a content hash computed over canonical content;
- author and authorship timestamp;
- lifecycle state;
- supersession and correction lineage;
- rule-pack and workflow-definition versions;
- generation metadata for every rendered artifact.

A published, approved, signed, or issued version MUST be immutable. Any material change MUST create a new version.

### PLAT-003 — Rendered artifact

A `DocumentArtifact` is a rendering of a document version, such as PDF, DOCX, CSV, or a signed validation report. Every artifact MUST record:

- document version;
- renderer and template version;
- media type;
- byte length;
- cryptographic hash;
- generation time;
- signature or seal metadata, if any;
- fixity-verification status.

Artifacts MUST be reproducible from canonical content and recorded rendering inputs, except where a qualified signature provider returns provider-specific signed material.

### PLAT-004 — Canonical structured content

The canonical content of a document MUST be structured, schema-valid data. It MAY contain controlled rich text blocks, references, tables, reusable clauses, and calculations.

A system MUST NOT parse a PDF as the sole source for facts used in validation, routing, deadlines, or decisions.

### PLAT-005 — Repository and collection

Documents MUST belong to a repository and MAY belong to collections, folders, cases, or projects. Organization MUST support separate legal, operational, and evidentiary views over the same document without duplicating the document.

### PLAT-006 — Document relationship

The system MUST support typed relationships between documents, including but not limited to `supersedes`, `corrects`, `supports`, `responds_to`, `implements`, `references`, `derived_from`, and `signed_by`.

Relationships MUST be version-aware where the relationship applies only to particular document versions.

## 4. Document types and templates

### PLAT-010 — Versioned document type

Every document type MUST have a stable identifier, purpose, owner, default classification, retention class, permitted workflow definitions, and required evidence outputs.

### PLAT-011 — Versioned template

A template MUST define its schema, sections, fields, validations, calculations, rendering instructions, optional clauses, and authority basis.

Templates MUST be immutable after first use. A change MUST create a new template version. A workflow instance MUST retain the template version it used.

### PLAT-012 — GPPB mandatory forms

Where a GPPB standard form is mandatory, the system MUST use the applicable official form and template version. The system MUST preserve the original downloaded artifact, its official source reference, retrieval timestamp, and any completion evidence.

A locally adapted form MUST record deviations from the official form and the approving authority. A legal reviewer MUST classify a deviation as permitted, prohibited, or requiring legal review.

### PLAT-013 — Template validation

Before a document can enter a governed workflow, the system MUST validate:

- required fields and data types;
- allowed controlled-vocabulary values;
- referential integrity;
- arithmetic and formula rules;
- date and chronology constraints;
- cross-document dependencies;
- required attachments and evidence;
- mandatory-template completion rules.

A failed hard validation MUST prevent submission. A warning MUST identify the rule, field, and reviewer action.

### PLAT-014 — Human-readable preview

A draft MUST be renderable as a human-readable preview. The preview MUST clearly identify draft status and MUST NOT be visually indistinguishable from an issued artifact.

## 5. Authoring and collaboration

### PLAT-020 — Structured editor

The editor MUST support structured fields, sections, tables, controlled lists, reusable clauses, inline mentions, references, and controlled rich text.

It MUST NOT initially promise unrestricted collaborative editing of complex DOCX files.

### PLAT-021 — Draft isolation

Only `draft` versions MAY be directly edited. Review, approval, signature, and issuance work MUST use immutable snapshots.

### PLAT-022 — Change request

A reviewer MUST be able to return a document with one or more structured change requests. A change request MUST identify the section, rationale, requester, assignee, and resolution.

### PLAT-023 — Comments and annotations

Comments and annotations MUST be attached to a document version or a specific content location. They MUST be separate from the canonical document content and MUST be retained in the audit record.

### PLAT-024 — Collaborative concurrency

Concurrent editing MUST prevent silent overwrite. The implementation MUST either use optimistic version checks with conflict detection or a CRDT-backed editor with deterministic version creation.

### PLAT-025 — External contribution

External parties MUST submit through controlled intake. The platform MUST preserve:

- submission envelope and timestamp;
- identity or organization assertion;
- exact submitted artifact and hash;
- delivery and authentication evidence;
- malware-scan and content-validation results;
- correspondence and rejection history.

An external submission MUST NOT be confused with an internally authored original.

## 6. Workflow and routing

### PLAT-030 — Versioned workflow definition

A workflow definition MUST be immutable after an instance starts. It MUST identify its owner, purpose, version, applicable document types, steps, conditions, roles, quorum rules, deadlines, evidence requirements, and escalation rules.

### PLAT-031 — Workflow instance

A workflow instance MUST bind one document version to one workflow-definition version and one rule-pack snapshot.

### PLAT-032 — Step types

The workflow engine MUST support:

- manual task;
- approval;
- signature request;
- issuance;
- distribution;
- acknowledgement;
- publication;
- wait or timer;
- condition;
- parallel group;
- quorum decision;
- calculation or validation;
- external callback.

### PLAT-033 — Assignment

A task MUST be assigned by role, organization, committee membership, named person, or a rule that resolves to those actors. The system MUST snapshot the resolved actor set at activation so later roster changes do not rewrite history.

### PLAT-034 — Quorum and voting

A workflow MUST support quorum, majority of all members, majority of those present, unanimity, weighted voting, and abstention rules as separately configured controls.

The system MUST record attendance, eligibility to vote, conflicts, recusals, abstentions, votes, and the rule calculation used to reach the outcome.

The BAC quorum and decision rule MUST conform to IRR §42.2–§42.3. A general workflow quorum MUST NOT be used as evidence that the statutory BAC quorum was met.

### PLAT-035 — Conditional routing

Conditions MUST be evaluated against structured facts and a pinned rule version. A condition MUST record the expression, inputs, result, evaluation time, and evaluator version.

### PLAT-036 — Return for correction

A workflow MUST support return for correction. Returning a document MUST create a new draft version, preserve the rejected version, and make the prior approval and signature inapplicable to the new version.

### PLAT-037 — Delegation and alternate

Delegation and alternate assignment MUST identify the actor, authority, scope, effective period, and whether the delegate may sign. A delegate MUST NOT exercise authority not granted by the underlying appointment or delegation.

### PLAT-038 — Segregation of duties

The workflow engine MUST prevent one actor from performing incompatible actions when a configured rule requires separation. Any statutory separation rule MUST be implemented as a hard gate and cited to its legal source.

### PLAT-039 — Task inbox and escalation

Every actionable task MUST appear in the actor's work queue. The system MUST show due date, blocking dependencies, required evidence, and escalation state.

### PLAT-040 — No administrative bypass

A system administrator MUST NOT have a general action that bypasses a hard legal gate. A transition blocked by a hard gate MUST require a formally defined legal exception or correction path with citation, justification, authorized approver, expiry where applicable, and audit evidence.

## 7. Approval

### PLAT-050 — Approval is distinct from signature

Approval is a recorded decision to accept a document version for a specified purpose. Signature is evidence of a signer's execution or attestation. The platform MUST record and display them separately.

### PLAT-051 — Approval decision

An approval MUST capture the approver, role, document version, decision, timestamp, grounds or comments, applicable rule pack, and evidence snapshot.

### PLAT-052 — Approval quorum

An approval outcome MUST be derived from individual decisions using the workflow's quorum and voting rule. The system MUST distinguish `pending`, `approved`, `rejected`, `returned`, `abstained`, `expired`, and `not_required`.

### PLAT-053 — Withdrawal and revocation

A pending decision MAY be withdrawn by its actor with a recorded reason. An approval MAY be invalidated only by a defined event, such as creation of a superseding version, loss of authority, legal hold conflict, or formal correction. The original decision MUST remain visible.

### PLAT-054 — Material-change invalidation

A new version created after approval MUST mark downstream approvals, signatures, issuance, and acknowledgements as `superseded` or `invalidated` as applicable. It MUST NOT erase them.

## 8. Electronic signatures

### PLAT-060 — Provider-neutral signature adapter

The platform MUST integrate signatures through a provider adapter. The core domain MUST NOT depend on one commercial signing product.

### PLAT-061 — Signature request

A signature request MUST bind:

- exact document version and content hash;
- signer identity and authority;
- signature purpose and requested declaration;
- allowed signature method;
- deadline and reminders;
- evidence package;
- cancellation status.

### PLAT-062 — Signature evidence

A completed signature MUST retain the provider transaction identifier, signed artifact hash, certificate or credential reference, signer assertion, time, validation status, revocation information where available, and verification instructions.

### PLAT-063 — Signature verification

Authorized users MUST be able to verify whether a signature covers the current artifact and whether the underlying document version changed after signing.

### PLAT-064 — Signature method by document class

The Procuring Entity MUST approve a signature-method matrix by document class and signer. The matrix MUST distinguish qualified, advanced, and other accepted methods and identify any wet-signature or notarization requirement.

**Legal review required:** whether a particular document may use the selected method depends on the applicable form, statute, GPPB issuance, local policy, and evidentiary context. The system MUST NOT infer permission solely from GPPB Resolution No. 16-2019.

### PLAT-065 — Multiple signatures

A document requiring multiple signatures MUST record each signer independently. The system MUST support sequential and parallel signing and MUST identify which signatures are outstanding.

### PLAT-066 — Invalid signature

The system MUST support revocation, expiry, provider error, failed verification, and signer mismatch as explicit states. It MUST NOT present an unverified or failed signature as valid.

## 9. Issuance and official status

### PLAT-070 — Distinct issuance action

Issuance is the authorized act of making a specific approved and, where required, signed version official for its intended recipients or channel. It MUST be separate from approval and signature.

### PLAT-071 — Issuance prerequisites

An issuance request MUST evaluate the configured legal gates. The result MUST list every satisfied, failed, or legally-review-required prerequisite.

### PLAT-072 — Official issuance manifest

A successful issuance MUST create a manifest containing the document version, artifact hashes, issue time, issuer, recipients or channels, authority, and required copies.

### PLAT-073 — Withdrawal and reissue

An issued document MUST NOT be silently deleted. Withdrawal, cancellation, or replacement MUST create a separate action with grounds, authority, notice evidence, and linkage to any replacement.

## 10. Distribution, delivery, and acknowledgement

### PLAT-080 — Distribution plan

A distribution MUST identify recipients, channel, purpose, due date, and required acknowledgement behavior. Public publication and named-recipient distribution MUST be separately evidenced.

### PLAT-081 — Delivery evidence

For each recipient and channel, the system MUST record delivery attempt, timestamp, channel reference, receipt or response, and failure reason where applicable.

### PLAT-082 — Acknowledgement

Acknowledgement MUST identify the exact issued version or artifact received, the acknowledging actor, time, method, and optional declaration.

### PLAT-083 — Overdue acknowledgement

The system MUST calculate and report overdue acknowledgements against the applicable rule. It MUST support reminders and escalation without automatically misrepresenting non-delivery as receipt.

### PLAT-084 — External portal

Authorized external users MUST access only explicitly shared matters, documents, and requested actions. They MUST authenticate appropriately, see their own submissions and correspondence, and be unable to browse unrelated procurement information.

## 11. Publication boundary

### PLAT-090 — Publication record

Every publication MUST identify the required channel, exact artifact and hash, publication window, responsible actor, official receipt, timestamps, and reconciliation status.

### PLAT-091 — Unconfirmed publication

A publication MUST remain `pending_confirmation`, `submitted`, `published`, `partially_published`, `failed`, or `rejected` as applicable. It MUST NOT be represented as complete without evidence accepted by the publication control.

### PLAT-092 — Required-channel reconciliation

The system MUST reconcile its publication intent with external-channel evidence and report missing, differing, or expired publications.

### PLAT-093 — PhilGEPS adapter boundary

PhilGEPS publication and electronic bidding MUST use a dedicated adapter or controlled manual-publication workflow. A manual fallback MUST require a reason, evidence, authorized confirmation, and follow-up task.

The platform MUST comply with IRR §20.1.1 and §20.3.1: PhilGEPS is the primary electronic procurement portal and Procuring Entities are mandated to fully use it. The exact API or manual operating procedure is **legal/operations review required** for each deployment.

## 12. Deadlines, obligations, and tracking

### PLAT-100 — Deadline calculation

Every legal or policy deadline MUST be computed from a recorded trigger event, a versioned rule, a timezone or calendar basis, and any tolling or suspension events. The system MUST retain the computation trace.

### PLAT-101 — Deadline clock

A deadline MUST expose its start event, due instant or date, rule citation, clock type, state, and remaining time. A user MUST NOT be able to edit a computed deadline directly.

### PLAT-102 — Tolling

Tolling and suspension events MUST identify the affected interval, legal or policy basis, start, end, authority, and duration. Overlapping intervals MUST NOT be double-counted.

### PLAT-103 — Business-day calendar

Where a rule uses working days, the system MUST use a versioned calendar containing weekends, holidays, local non-working declarations, and exceptional proclamations. Timezone conversion MUST use the Procuring Entity's authoritative timezone.

### PLAT-104 — Obligation

Obligations MUST identify the responsible actor, due date, predecessor, status, evidence requirement, and completion action. A procurement contract may generate multiple obligations for delivery, acceptance, warranty, variation, payment, and reporting.

## 13. Audit and evidence

### PLAT-110 — Append-only event record

The system MUST append an audit event for every material action. Audit events MUST include actor, effective actor, action, subject, timestamp, request correlation, source device or channel, prior and resulting state, rule/version references, and evidence references.

### PLAT-111 — Evidence bundle

The system MUST be able to assemble an evidence bundle containing the document version, artifacts, approvals, signatures, issuance manifest, distribution and publication evidence, relevant audit events, and a manifest with hashes.

### PLAT-112 — Evidence export

An evidence bundle MUST be exportable in a durable, self-describing format. The export MUST preserve hashes, timestamps, provenance, and any signature-validation material.

### PLAT-113 — Audit reconstruction

Given an action or decision, the system MUST reconstruct the exact document version, inputs, workflow state, authority snapshot, rule pack, and evidence available at that time.

## 14. Search and retrieval

### PLAT-120 — Metadata and full-text indexing

The platform MUST index document metadata and extracted text. It MUST support exact phrase, term, fielded, and faceted search, with results constrained by the actor's access rights.

### PLAT-121 — OCR

Scanned or image-based documents MUST be queued for OCR. OCR output MUST be labeled as derived text, linked to its source artifact, and MUST NOT replace the source.

### PLAT-122 — Search ranking and explainability

Search results MUST show enough metadata for the user to distinguish an authoritative issued artifact from a draft, superseded version, or OCR derivative.

### PLAT-123 — Duplicate detection

The platform SHOULD identify likely duplicates by exact hash, normalized hash, metadata similarity, and extracted-text similarity. Duplicate detection MUST NOT delete or merge records automatically.

## 15. Records management

### PLAT-130 — Retention schedule

Every record class MUST map to an approved retention schedule containing the trigger, period, disposition action, authority citation, and review requirements.

### PLAT-131 — Legal hold

A legal hold MUST suspend eligible disposition for affected records. Holds MUST be scoped by matter, record selector, custodians, and date range, and MUST override normal disposition.

### PLAT-132 — Disposition review

Disposition MUST run through a review that checks holds, active obligations, audit significance, unresolved litigation or protest, and approval authority. Destruction MUST leave a tombstone and manifest.

### PLAT-133 — Archival integrity

Retention of records MUST preserve content hashes, metadata, evidence links, and fixity status. Bit rot and missing artifacts MUST be detectable and reportable.

### PLAT-134 — Export and portability

Authorized users MUST be able to export a document, its metadata, audit trail, and evidence manifest in documented formats. The export MUST preserve provenance and identify any omitted confidential material.

## 16. Security and access

### PLAT-140 — Least privilege

Access MUST be deny-by-default and enforced server-side using identity, role, organization, matter, document classification, workflow state, and legal entitlements.

### PLAT-141 — Segregation of privileged data

Bid contents, personal data, confidential technical data, legal-hold material, and credentials MUST have explicit access policies and MUST NOT be exposed through search indexes without equivalent authorization filtering.

### PLAT-142 — Authentication

Users MUST authenticate through an approved mechanism. High-risk actions MUST require appropriate re-authentication or step-up verification, including signing, delegation, legal-hold release, and disposition authorization.

### PLAT-143 — Malware and content safety

Externally supplied files MUST pass malware scanning and content validation before being made available to privileged users. Scanning status MUST be recorded.

### PLAT-144 — Data minimization and privacy

Personal data MUST have a documented purpose, access basis, retention rule, and disclosure history. Privacy obligations under RA 10173 and other applicable law are **legal review required** for each deployment.

### PLAT-145 — Confidentiality

The platform MUST support confidentiality markings, access lists, watermarking or view tracking, and non-disclosure attestations. IRR §48's equal-access and pre-release restrictions MUST be represented by workflow and access controls.

## 17. Notifications

### PLAT-150 — Event-driven notifications

Notifications SHOULD be generated from durable events, not request handlers. Each notification MUST reference the triggering event and deduplication key.

### PLAT-151 — Required notices

The system MUST support legally required notices, including award-recommendation notice, disapproval notice, postponement, eligibility result, post-qualification invitation, notice of award, protest/reconsideration notices, and suspension or blacklisting notice, subject to the applicable rule and template.

### PLAT-152 — Delivery status

Notification delivery MUST distinguish created, queued, sent, delivered, opened where technically available, failed, bounced, and suppressed. Delivery evidence MUST be retained according to retention policy.

### PLAT-153 — Equal-information control

Before official release, the platform MUST prevent unauthorized selective disclosure of bidding documents to interested parties. The control MUST support authorized exceptions and record every release, consistent with IRR §48.1–§48.2.

## 18. Configuration

### PLAT-160 — Configuration as data

Document types, templates, workflow definitions, rule packs, calendars, signature matrices, retention schedules, and notification templates MUST be versioned configuration data rather than hard-coded behavior.

### PLAT-161 — Validation before activation

A configuration MUST pass schema, dependency, legal-basis, and simulation checks before activation. Activation MUST require authorized approval.

### PLAT-162 — Safe rollout

Configuration changes MUST support draft, review, approval, activation, and rollback. A rollback MUST select a prior immutable version; it MUST NOT rewrite completed instances.

### PLAT-163 — No hidden weakening

A configuration validator MUST reject any proposed rule that conflicts with a higher-authority rule, removes a mandatory gate, weakens quorum, changes a legal deadline without an updated legal source, or expands a signature method without approval.

## 19. External APIs and interoperability

### PLAT-170 — Versioned API

External APIs MUST be versioned and documented with schemas, authorization, idempotency, error semantics, and audit behavior.

### PLAT-171 — Event contracts

Domain events MUST have stable identifiers, schema versions, occurrence time, actor, subject, and correlation identifiers. Consumers MUST tolerate additive evolution.

### PLAT-172 — Interoperability

The platform SHOULD expose OpenAPI and event schemas. Exchange with government systems MUST use standard, documented protocols and MUST protect trade secrets, competition, personal data, and procurement confidentiality.

## 20. Target-platform acceptance summary

A release satisfies the platform specification when it can demonstrate:

1. a structured document produces an immutable version and reproducible artifacts;
2. an approval and a signature are independently recorded against that version;
3. editing approved content creates a new version and invalidates downstream decisions;
4. a statutory hard gate blocks an unauthorized transition with a citation and explanation;
5. a deadline is reproducible from its trigger, rule, calendar, and tolling events;
6. an issued document has separate publication, delivery, and acknowledgement evidence;
7. an evidence bundle reconstructs the complete decision;
8. a legal hold prevents disposition;
9. an external recipient sees only the shared matter;
10. historical instances remain reproducible after configuration changes.

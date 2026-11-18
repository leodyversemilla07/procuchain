# Acceptance Criteria: ProcuChain eProcurement Document Platform

> **Status:** Build target
> **Specification version:** 0.1
> **Depends on:** [Specification Index](README.md)

## 1. Purpose

This document defines release gates and testable outcomes for the target platform. It converts normative requirements into acceptance evidence. It does not replace legal approval of the underlying rules.

A release may proceed to the next gate only when all **Must** criteria for that gate pass, every deviation is recorded, and the named approvers sign the gate record.

## 2. Requirement traceability

Every acceptance criterion MUST identify:

- stable criterion ID;
- source requirement IDs;
- test or evidence reference;
- legal provision where applicable;
- responsible owner;
- result: `pass`, `fail`, `not_applicable`, or `deferred`;
- approval record.

### AC-001 — Traceability completeness

The traceability report MUST show every active `PLAT`, `CMP`, `LIFE`, `DOM`, and `ARC` requirement linked to at least one test, inspection, or operational evidence item.

**Verification:** automated report plus maintainer review.
**Release gate:** all gates.

## 3. Legal and compliance gates

### AC-010 — Authoritative source baseline

**Must:** the legal register identifies RA 12009, the officially approved IRR through GPPB Resolution No. 02-2025, GPPB Resolution No. 03-2025, GPPB Resolution No. 12-2025, GPPB Resolution No. 10-2026/Circular No. 06-2026, GPPB Public Advisory No. 09-2026, GPPB Resolution No. 16-2019, RA 8792, EO 810, and the PE's applicable instruments with official source, dates, version, and review status.

**Legal provision:** CMP-010–CMP-014, CMP-030.
**Verification:** legal-register review.
**Owner:** legal/compliance reviewer.

### AC-011 — Withdrawn unofficial IRR rejected

**Must:** attempting to activate a rule sourced only from the withdrawn unofficial compiled IRR MUST fail with a specific source error.

**Legal provision:** CMP-007, CMP-130.
**Verification:** negative integration test.
**Owner:** compliance engineering.

### AC-012 — Source-to-control traceability

**Must:** for every active hard gate, an authorized reviewer can traverse from the exact official provision to requirement, rule, control, workflow operation, UI action, and passing test.

**Verification:** automated coverage report and sample inspection.
**Owner:** compliance and QA.

### AC-013 — No generic override

**Must:** no API, UI, role, database command, or support tool exposes a generic “override IRR,” “skip legal control,” or equivalent action.

**Verification:** route/API inventory, role-permission review, UI review, and negative tests.
**Owner:** product, security, compliance.

### AC-014 — Legal review blocks uncertain interpretation

**Must:** a control marked `legal_review_required` remains blocked until an authorized decision is recorded with rationale, scope, citation, approver, and date.

**Legal provision:** CMP-042–CMP-045.
**Verification:** feature test.
**Owner:** compliance.

### AC-015 — Historical rule snapshot

**Must:** after activation of a new rule pack, an existing procurement continues to show and evaluate its original requirements, rules, forms, calendar, and controls; a new procurement uses the new pack.

**Legal provision:** CMP-033, CMP-036, IRR §112.2.
**Verification:** historical-snapshot integration test.
**Owner:** compliance engineering.

### AC-016 — Legal change impact analysis

**Must:** activating an amended source identifies affected active packs, in-flight procurements, pending controls, and future instances, and records reviewer approvals before activation.

**Verification:** change-workflow feature test and review report.
**Owner:** compliance product owner.

### AC-017 — Exception controls

**Must:** an exception can exist only with a linked permitted legal basis and authorized approval; it records scope, expiry, justification, and compensating controls; expiry removes its authorization while preserving history.

**Verification:** positive, negative, and expiry tests.
**Owner:** compliance and security.

## 4. Document lifecycle

### AC-020 — Structured document creation

**Must:** an authorized user can create a document from a versioned template, enter structured data, pass required validation, and save a draft whose canonical content and hash are reproducible.

**Source:** PLAT-001–PLAT-015, PLAT-020–PLAT-021.
**Verification:** feature test and canonicalization property test.

### AC-021 — Immutable version

**Must:** changing submitted or approved content creates a new version; the prior version, artifacts, decisions, and evidence remain retrievable unchanged.

**Source:** PLAT-002, PLAT-021, DOM-120–DOM-122.
**Verification:** integration test.

### AC-022 — Reproducible artifact

**Must:** the same canonical version and pinned renderer produce a new artifact with the same content hash; artifact metadata identifies renderer and template versions.

**Source:** PLAT-003, ARC-040–ARC-042.
**Verification:** golden-render test.

### AC-023 — Correction lineage

**Must:** a correction identifies the corrected version, reason, author, and content diff; the original is never overwritten.

**Source:** PLAT-006, PLAT-054.
**Verification:** feature test.

### AC-024 — Template deviation control

**Must:** use of an adapted mandatory GPPB form records the official source, local deviation, approving authority, and legal classification.

**Source:** PLAT-012.
**Verification:** compliance feature test.

### AC-025 — External submission distinction

**Must:** an externally uploaded submission is visibly and structurally distinct from an internally authored original, with envelope, submitter, time, hash, and validation evidence.

**Source:** PLAT-025, DOM-029.
**Verification:** external-portal feature test.

### AC-026 — Invalidated downstream decision

**Must:** creating a corrected version after approval marks approval, signature, issuance, distribution, and acknowledgement inapplicable to the new version while preserving their original records.

**Source:** PLAT-054, DOM-122.
**Verification:** state-transition integration test.

## 5. Workflow, approval, and quorum

### AC-030 — Versioned workflow execution

**Must:** a workflow instance executes against one immutable workflow-definition version and rule-pack snapshot; later configuration changes do not alter it.

**Source:** PLAT-030–PLAT-031, ARC-050–ARC-054.
**Verification:** integration test.

### AC-031 — Approval and signature separation

**Must:** a user can approve a version without signing it, sign it without implying approval, and the UI and API represent both states independently.

**Source:** PLAT-050, PLAT-060.
**Verification:** feature and API tests.

### AC-032 — BAC composition

**Must:** a BAC action is blocked when the designated composition, rank/qualification, term, provisional-member, alternate, or prohibited-membership requirement fails.

**Legal provision:** IRR §§41.1–41.2.
**Verification:** table-driven legal-control tests.
**Owner:** compliance QA.

### AC-033 — BAC quorum

**Must:** quorum is calculated from the designated total membership, requires the Chair or Vice-Chairperson, and records the membership snapshot.

**Legal provision:** IRR §42.2.
**Verification:** boundary tests for every composition size and absence combination.
**Owner:** compliance QA.

### AC-034 — BAC decision rule

**Must:** a majority decision of members present at a quorum meeting is recorded as valid, the Chair/Vice casts a deciding vote only on a tie, and the computation is auditable.

**Legal provision:** IRR §42.3.
**Verification:** positive, tie, no-tie, no-quorum, and recusals.
**Owner:** compliance QA.

### AC-035 — Quorum cannot be spoofed by workflow configuration

**Must:** a generic workflow setting cannot override or misstate BAC quorum; statutory quorum is represented by a dedicated reviewed control.

**Source:** PLAT-034, PLAT-163, CMP-023.
**Verification:** configuration attack test.

### AC-036 — Conflict and recusal

**Must:** a disclosed conflict and valid recusal affect eligibility and vote count according to the reviewed rule, with the actor's eligibility status visible in the computation.

**Verification:** feature and audit test.

### AC-037 — Return for correction

**Must:** returning an in-review document creates a new draft version, preserves the rejected version, and invalidates downstream decisions where applicable.

**Source:** PLAT-036.
**Verification:** feature test.

### AC-038 — Delegation limits

**Must:** a delegate can act only within the delegation's scope, period, and signing authority; the audit trail identifies actor and effective actor.

**Source:** PLAT-037, PLAT-110.
**Verification:** positive and expired/out-of-scope tests.

### AC-039 — Task inbox

**Must:** each actionable task appears for its responsible actor with due date, blockers, required evidence, and escalation state; another actor cannot complete it without an authorized reassignment.

**Source:** PLAT-039.
**Verification:** feature and authorization tests.

## 6. Electronic signatures

### AC-040 — Version-bound signature request

**Must:** a signature request binds the exact document version and artifact hash. Any content change after request creation causes the request to be rejected or reissued.

**Source:** PLAT-060–PLAT-063, ARC-080–ARC-084.
**Verification:** integration test with signing fake.

### AC-041 — Provider-neutral adapter

**Must:** the signing provider can be replaced through configuration and contract tests without changing core workflow semantics.

**Source:** PLAT-060, ARC-081.
**Verification:** adapter contract suite.

### AC-042 — Signature evidence

**Must:** a completed signature records provider transaction, signer assertion, certificate/credential reference, signed hash, time, validation state, and revocation information where available.

**Verification:** evidence inspection.

### AC-043 — Signature verification

**Must:** a verifier can determine whether the signature covers the presented artifact and whether the underlying document changed after signing.

**Source:** PLAT-063.
**Verification:** positive, tampered-artifact, and changed-version tests.

### AC-044 — Signature-method matrix

**Must:** a document cannot be signed through a method absent from the approved matrix for that document class and signer.

**Source:** PLAT-064.
**Verification:** matrix-driven negative tests.

### AC-045 — Legal method review

**Must:** before production, a qualified reviewer approves the signature method for each production document class; any wet-signature or notarization requirement is encoded as a gate or documented external prerequisite.

**Legal provision:** RA 8792 §8; EO 810; GPPB Res. 16-2019.
**Verification:** signed legal review.
**Owner:** legal/compliance reviewer.

### AC-046 — Failed verification not shown as valid

**Must:** a provider error, failed verification, expiry, revocation, or signer mismatch is visibly distinct from a valid signature.

**Source:** PLAT-066.
**Verification:** state-transition tests.

## 7. Issuance, distribution, and acknowledgement

### AC-050 — Issuance prerequisites

**Must:** issuance is blocked when a required approval, signature, authority, publication, or evidence control fails, and the response lists each unmet prerequisite.

**Source:** PLAT-070–PLAT-071.
**Verification:** feature test.

### AC-051 — Issue manifest

**Must:** each successful issuance creates a manifest with exact version, artifact hashes, issuer, authority, time, recipients/channels, and required copies.

**Source:** PLAT-072.
**Verification:** evidence inspection.

### AC-052 — Separate publication and distribution

**Must:** a document can be issued to a named recipient without being publicly published, and public publication can be tracked separately; neither status is inferred from the other.

**Source:** PLAT-080, PLAT-090.
**Verification:** feature test.

### AC-053 — Delivery evidence

**Must:** delivery attempts, failures, receipts, and acknowledgements are separate states with recipient, artifact, method, and time.

**Source:** PLAT-081–PLAT-083.
**Verification:** integration test.

### AC-054 — Non-receipt is not receipt

**Must:** failure, expiry, or absence of acknowledgement never produces a `received` or `acknowledged` status.

**Source:** PLAT-083, DOM-132.
**Verification:** negative tests.

### AC-055 — External matter isolation

**Must:** an external user can see only the shared procurement matter, permitted documents, and own submissions; direct object or API references to unrelated records fail.

**Source:** PLAT-084, DOM-134, ARC-112.
**Verification:** tenant and horizontal-authorization security tests.

## 8. PhilGEPS and publication

### AC-060 — Required-channel control

**Must:** a procurement publication requiring PhilGEPS cannot be marked complete without accepted evidence from the required channel.

**Legal provision:** IRR §§20.1.1, 20.3.1.
**Verification:** integration test with contract-faithful PhilGEPS fake.

### AC-061 — Exact artifact reconciliation

**Must:** the publication record binds to the exact published artifact hash; the system detects a remotely visible content difference where evidence permits comparison.

**Source:** ARC-091–ARC-092.
**Verification:** reconciliation test.

### AC-062 — Failure is not success

**Must:** timeout, rejection, malformed receipt, duplicate submission, and channel outage do not create `published`; each creates a retry or operator task and records the actual state.

**Source:** PLAT-091, ARC-095.
**Verification:** failure-matrix test.

### AC-063 — Manual publication fallback

**Must:** manual publication captures operator, timestamp, reference, proof, validation, and required confirmation; missing proof leaves the publication incomplete.

**Source:** PLAT-093, ARC-093.
**Verification:** manual workflow test.

### AC-064 — Bidder notification after bulletin

**Must:** a bulletin records affected content and response deadline, identifies affected bidders, and retains notification evidence. For a bulletin issued after bids were submitted, the system permits the affected bidder to modify or withdraw its bid under IRR §55.

**Legal provision:** IRR §§51.5.2–51.5.3 and §55.
**Verification:** feature and legal-control test.

## 9. Deadlines, time, and tolling

### AC-070 — Reproducible deadline

**Must:** a deadline can be recomputed from trigger event, rule version, calendar version, timezone, parameters, and tolling events and yields the same result.

**Source:** PLAT-100–PLAT-103, CMP-160–CMP-165, ARC-070–ARC-074.
**Verification:** replay test.

### AC-071 — Deadline boundaries

**Must:** tests cover exactly at start, before start, on first and last included day, after last day, deadline at midnight, leap day, year boundary, and daylight-saving irrelevance under the configured timezone.

**Verification:** property and boundary tests.

### AC-072 — Non-working-day adjustment

**Must:** IRR §67.3's adjustment is applied only when the pinned rule uses it and records the calendar version and reason.

**Legal provision:** IRR §67.3.
**Verification:** fixture-based tests.

### AC-073 — Tolling without double count

**Must:** overlapping tolling intervals suspend the clock once; removing or ending an interval resumes the remaining balance without restarting the full period.

**Legal provision:** IRR §67.4.
**Verification:** interval property test.

### AC-074 — Distinct reconsideration clocks

**Must:** BAC reconsideration, HoPE reconsideration after award disapproval, HoPE protest, and sanction reconsideration have separate labeled timers, triggers, and rules.

**Legal provision:** IRR §§66.4, 83.1–83.2, 104.1(c).
**Verification:** timer inventory and feature tests.

### AC-075 — Failed-bidder notice deadline

**Must:** the system computes three calendar days from issuance of the BAC award recommendation and identifies late notice.

**Legal provision:** IRR §66.1.
**Verification:** boundary test.

### AC-076 — HoPE award deadline

**Must:** the system computes ten calendar days from BAC determination and recommendation and identifies overdue action.

**Legal provision:** IRR §66.2.
**Verification:** boundary test.

### AC-077 — Reconsideration deadline

**Must:** a request filed after three calendar days from the applicable trigger is rejected under the reviewed rule, and the decision deadline is seven calendar days.

**Legal provision:** IRR §83.1.
**Verification:** positive, late, and boundary tests.

### AC-078 — Protest deadline and fee

**Must:** a protest filed after seven calendar days from receipt of BAC reconsideration denial is rejected under the reviewed rule; a valid filing uses the official §83.2 fee schedule.

**Legal provision:** IRR §§83.2–83.4.
**Verification:** deadline and fee-table golden tests.

### AC-079 — Sanction decision and posting deadlines

**Must:** the system computes the HoPE's 15-day sanction decision, three-day reconsideration, seven-day reconsideration decision, and seven-day GPPB portal posting from the correct triggers.

**Legal provision:** IRR §104.1.
**Verification:** legal-control tests.

## 10. Procurement lifecycle scenarios

### AC-090 — APP linkage hard gate

**Must:** a covered procurement without a valid approved APP/Indicative APP line cannot enter the applicable lifecycle stage or award.

**Legal provision:** RA 12009 §7; IRR §§7.7–7.8.
**Verification:** positive and negative lifecycle tests.

### AC-091 — EPA restrictions

**Must:** an EPA case cannot begin without approved Indicative APP; its ABC cannot exceed the Indicative APP amount; and award remains blocked until funding approval/effectivity and final HoPE-approved APP.

**Legal provision:** IRR §§12.1–12.5.
**Verification:** legal-control tests.

### AC-091A — APP recommendation, posting, and submission

**Must:** the Indicative APP records BAC recommendation and HoPE approval; the EPA-updated Indicative APP and approved final APP have PE-website posting evidence; the final APP records GPPB submission on or before the end of January of the budget year.

**Legal provision:** IRR §§7.7.2–7.7.5.
**Verification:** APP workflow and publication-evidence tests.

### AC-091B — Procurement-mode allow-list

**Must:** the platform permits only the procurement modes enumerated in IRR §26.1, records Fit-for-Purpose facts, and binds the selected mode to its applicable IRR provisions and GPPB manual version.

**Legal provision:** IRR §§26.1–26.4; GPPB Res. 12-2025.
**Verification:** mode allow-list, rejected-mode, and procedure-version tests.

### AC-091B1 — Detailed engineering, right-of-way, and multi-year authority

**Must:** an Infrastructure case cannot bid or award without the required approved detailed engineering and right-of-way evidence, except that procurement may proceed with pending right-of-way while award remains blocked; a multi-year locally funded case cannot begin before MYCA/Sanggunian-equivalent authority, and its ABC references the authority's total project cost.

**Legal provision:** IRR §§8.1–8.1.1, 9.
**Verification:** design, ROW, commencement-versus-award, and authority/ABC tests.

### AC-091B2 — Typed ABC derivation

**Must:** the system derives ABC from the authority appropriate to the PE/funding type—LGU appropriation, MYCA total project cost, or foreign-approved estimate—and treats it as the bid-price ceiling.

**Legal provision:** IRR §§5(a), 60.1.
**Verification:** funding-type derivation and ceiling tests.

### AC-091B3 — Scope and exclusion classification

**Must:** before workflow activation, the system classifies scope, treats an express treaty conflict according to IRR §4.2, applies the §4.3 exclusions and §4.4 non-procurement activities, and does not open a procurement workflow for excluded activity without the recorded legal basis.

**Legal provision:** IRR §§4.1–4.4.
**Verification:** scope, treaty-conflict, exclusion, and non-procurement classification tests.

### AC-091C — Small Value Procurement controls

**Must:** an SVP case requests at least three quotations; permits one quotation only up to ₱2,000,000; requires three-day posting above ₱200,000; records the applicable versioned LGU threshold; preserves any appropriation-limited extension; and prepares the Abstract of Quotations or Ratings before award recommendation.

**Legal provision:** IRR §34; GPPB Res. 10-2026.
**Verification:** SVP amount, posting, recipient, extension, threshold-version, and abstract tests.

### AC-091C1 — SVP LGU threshold table

**Must:** the system applies the full IRR §34.2 LGU schedule as versioned threshold data — 1st and 2nd Class ₱2,000,000 / ₱2,000,000 / ₱400,000; 3rd Class ₱2,000,000 / ₱1,600,000 / ₱400,000; 4th Class ₱1,600,000 / ₱1,200,000 / ₱200,000; 5th Class ₱1,200,000 / ₱800,000 / ₱200,000; Barangays ₱100,000 — across Goods, Infrastructure Projects, and Consulting Services, and blocks an SVP above the applicable ceiling.

**Legal provision:** IRR §§34.1–34.2.
**Verification:** class/entity-type boundary and over-ceiling legal-control tests.

### AC-091D — Direct Contracting, Direct Acquisition, and Repeat Order controls

**Must:** Direct Contracting requires an applicable IRR §31.2 ground and the prescribed RFQ/pro-forma flow; Direct Acquisition remains within IRR §32 scope, links to the APP, and retains qualifying-source and invoice/delivery evidence; Repeat Order enforces the original/market price comparison, six-month window absent GPPB approval, 25% item-quantity cap, and quantifiable/divisible/four-unit conditions.

**Legal provision:** IRR §§31–33.
**Verification:** mode-eligibility, price, time, quantity, and documentary legal-control tests.

### AC-091D1 — Direct Contracting and Direct Acquisition evidence detail

**Must:** Direct Contracting records the §31.2 ground with competent-authority evidence where required, the RFQ or pro-forma and simplified-negotiation record; Direct Acquisition records the HoPE authorization, PhilGEPS registration or e-commerce availability or off-the-shelf evidence, and BIR-compliant invoice or COA-acceptable delivery and payment evidence, and identifies petty-cash items as Miscellaneous Items in the APP; Repeat Order records the original award mode, the post-qualification result, the Purchase Request, and both price comparisons.

**Legal provision:** IRR §§31.2–31.3, 32.1–32.4, 33.1–33.3.
**Verification:** per-mode evidence-completeness legal-control tests.

### AC-091E — Competitive modes, dialogue, and unsolicited-offer conditions

**Must:** Competitive Bidding records all seven IRR §27.1 stages; Limited Source Bidding records an IRR §28.2 ground, the pre-selection source list or PE self-generated list with attestation, and the four §28.4 criteria, and applies the Competitive Bidding procedure with reduced timelines within the IRR maximum; Competitive Dialogue records an IRR §29.2 ground, stage-one deadlines of 45/65/75 calendar days from the last posting day, pass-or-fail initial technical evaluation, issuance of finalized specifications to all participants within 10 calendar days, and stage-two submission within 20 calendar days; and Unsolicited Offer with Bid Matching records the HoPE new-concept determination, the comparative-bid reference, the IRR §30.2 offer package with indicative ABC, and the IRR §§30.3–30.8 timelines.

**Legal provision:** IRR §§27.1, 28.2–28.5, 29.2–29.4.2, 30.1–30.8.
**Verification:** mode-condition, deadline, and equal-treatment legal-control tests.

### AC-091F — Direct Sales and STI direct procurement

**Must:** Direct Sales requires all IRR §36.1 conditions together, the original agency document transfer, the Purchase Request, and a Direct Sales Request response in writing within five calendar days containing the technical-compliance statement, NFCC computation, same-or-lower unit price, and a valid PhilGEPS Platinum Certificate of Registration; Direct Procurement for STI records the IRR §37.1 scope classification and, unless a written agreement states otherwise, the PE's intellectual-property ownership and the supplier's no-manufacturing/no-sale restriction.

**Legal provision:** IRR §§36.1–36.2, 37.1.
**Verification:** all-conditions, response-window, and IP-clause legal-control tests.

### AC-091G — Negotiated Procurement conditions

**Must:** a Negotiated Procurement case records the applicable IRR §35 instance; for Two Failed Biddings it enforces the mandatory review, the 20% ABC-increase cap relative to the last failed bidding, the invitation of at least three suppliers including previously disqualified ones, equal communication and equal time and opportunity for all negotiating participants, the best-offer request date, and the S/LCRB or MEARQ or S/HRRP recommendation; for emergency cases it records the applicable §35.2 ground and applies the §35.2.1 rule that specified documents are accepted as basis for award subject to submission after award and before payment.

**Legal provision:** IRR §§35.1–35.13.
**Verification:** instance, ABC-cap, invitee-count, equal-treatment, and post-award-submission legal-control tests.

### AC-092 — Pre-procurement conference threshold

**Must:** for each category, the system requires the conference above the IRR threshold and records a valid option-out at or below it.

**Legal provision:** IRR §49.1.
**Verification:** exact-threshold matrix, including equality.

### AC-092A — Conference recording and livestream

**Must:** a Competitive-Bidding procurement-related conference cannot commence without a BAC-initiated recording; the system applies the IRR §38.3 category thresholds, retains recording copies for at least five years, and tracks public livestreaming of preliminary examination and bid opening.

**Legal provision:** IRR §§38.1–38.4.
**Verification:** threshold, start-order, retention, and livestream tests.

### AC-092B — Anti-splitting and CSE-source controls

**Must:** the system records the IRR §39.1 factors for unit/lot/package procurement and the IRR §39.2 phase/design evidence for Infrastructure Projects; a CSE case verifies PS-DBM availability and requires an applicable §40.2 ground before another source may be used.

**Legal provision:** IRR §§39.1–39.2, 40.1–40.2.
**Verification:** scope, phase, catalogue-evidence, and alternative-source legal-control tests.

### AC-093 — Award criterion lock

**Must:** a competitive case cannot publish until the BAC award criterion is recorded and the criterion and weights in the Bidding Documents agree.

**Legal provision:** IRR §§49.1(d), 49.2.
**Verification:** legal-control test.

### AC-094 — Bidding Documents completeness

**Must:** every IRR §47.1 item is present, explicitly not applicable with reason, or pending; mandatory hard failures block publication.

**Legal provision:** IRR §47.1.
**Verification:** checklist matrix.

### AC-095 — Equal access before release

**Must:** no unauthorized external actor can retrieve or infer unreleased bidding-document content before the official release event; authorized release is logged.

**Legal provision:** IRR §48.
**Verification:** security test and release-log inspection.

### AC-096 — Publication window

**Must:** the system calculates the applicable continuous posting period from the pinned rule and does not mark publication complete before its start or after required evidence.

**Legal provision:** IRR §§50–51.
**Verification:** mode-specific legal-control fixtures.

### AC-096C — Publication channels and posting periods

**Must:** the system requires continuous publication on the PE website or social media, or another GPPB-authorized channel, and a newspaper of general nationwide circulation at least once when the PE cannot publish on those channels for justifiable reasons; it enforces seven calendar days of premise posting certified by the BAC Secretariat head plus seven calendar days of continuous PhilGEPS, PE website, and applicable foreign/financing-institution website posting for Competitive Bidding, Competitive Dialogue, and Unsolicited Offer with Bid Matching, and at least three calendar days in PhilGEPS, the PE website, and the PE premises for Limited Source Bidding, Two Failed Biddings, Small Value Procurement above ₱200,000, and NGO Participation.

**Legal provision:** IRR §§50.1–50.1.2, 50.3.1–50.3.2.
**Verification:** channel, fallback, and mode/period-boundary legal-control tests.

### AC-096D — Electronic bidding certification

**Must:** when the PE allows electronic bid submission pending mandatory PhilGEPS electronic bidding, publication of the corresponding Bidding Documents is blocked until the prescribed Certification from the highest official managing the agency's IT system is recorded with GPPB-TSO submission evidence.

**Legal provision:** IRR §50.3.3.
**Verification:** prerequisite-gate legal-control test.

### AC-096E — Invitation to Bid content

**Must:** the Invitation to Bid or Request for Expression of Interest cannot be released without every IRR §50.2 content item, including category-specific project description, lots/items and evaluation basis, eligibility/short-listing/evaluation/post-qualification/award criteria, award criterion, applicable weights and quality-price ratio, consulting evaluation procedure, all deadlines, ABC, source of funding, place/time/website and price of the Bidding Documents, contract duration or delivery schedule, PE contact details, bid security requirement, and the electronic-submission statement.

**Legal provision:** IRR §50.2.
**Verification:** per-field content checklist test.

### AC-096A — Pre-bid conference threshold and timing

**Must:** a competitive-selection procurement with ABC of exactly ₱3,000,000 requires a pre-bid conference; below that threshold it is discretionary. When required, the conference date is at least 12 calendar days before bid deadline and no earlier than 7 calendar days after the applicable posting/shortlist event; minutes are due within 5 calendar days.

**Legal provision:** IRR §§51.1–51.4.
**Verification:** exact-threshold and date-boundary legal-control tests.

### AC-096B — Clarification and bulletin deadlines

**Must:** the system enforces the 10-day clarification-request window, 7-day BAC-response window, 7-day PE-initiated modification window, and the affected bidder's right to modify or withdraw after a bulletin.

**Legal provision:** IRR §§51.5.1–51.5.3 and §55.
**Verification:** boundary and bidder-right scenario tests.

### AC-097 — Late bid handling

**Must:** a bid received after the applicable deadline is classified late under the pinned rule, cannot enter normal evaluation, and its rejection evidence is retained. The minutes record the late bidder's name, representative, and submission time.

**Legal provision:** IRR §54.6 and applicable Bidding Documents.
**Verification:** boundary test.

### AC-097A — Maximum bid-receipt periods

**Must:** the system computes from the last posting day: 45 calendar days for Goods; 50 for Infrastructure Projects with ABC at or below ₱50,000,000 and 65 above it; and 75 for Consulting Services.

**Legal provision:** IRR §54.5.
**Verification:** category/ABC threshold and deadline-boundary tests.

### AC-097B — Bid receipt notice

**Must:** the system creates and tracks the written notice to every bidder whose bid was received, due within seven calendar days from bid opening, with delivery evidence.

**Legal provision:** IRR §54.7.
**Verification:** deadline and recipient-set test.

### AC-097C — Bid security and validity

**Must:** a competitive bid lacking prescribed bid security is automatically disqualified; the system enforces the 2% cash/check and 5% bank/surety minimums and the 120-calendar-day maximum validity from bid opening.

**Legal provision:** IRR §§56.1–56.3, 57.1–57.2.
**Verification:** form/amount/validity legal-control tests.

### AC-097D — Relations and beneficial-ownership disclosure

**Must:** a bid lacking the sworn relation affidavit or required beneficial-ownership disclosure is automatically disqualified; a third-degree relation is disqualifying even if the related official self-inhibits.

**Legal provision:** IRR §§81.1, 82.1–82.3.
**Verification:** missing-document and disclosed-relation legal-control tests.

### AC-097E — Eligibility document set and thresholds

**Must:** the system requires, per category, a valid and updated PhilGEPS Platinum Certificate of Registration, the ongoing-contracts statement, the SLCC, and the NFCC computation—or, for Goods only, a committed line of credit of at least 10% of ABC—and the consulting professional registration with curriculum vitae; it computes the SLCC age and value (50% of ABC, PSA-adjusted, within ten years unless the ITB states a shorter period) and NFCC (at least equal to ABC) and blocks a bid that fails them.

**Legal provision:** IRR §§52.1–52.1(d), 52.4.1.3–52.4.1.5, 52.4.2.4–52.4.2.6, 54.2.
**Verification:** category checklist and SLCC/NFCC/LOC boundary legal-control tests.

### AC-097F — Ownership, joint venture, and foreign-document rules

**Must:** the system computes Filipino ownership and interest (60% standard; the Infrastructure JV exception path with Filipino interest not less than 25%) against JVA contributions; requires the JVA or notarized partner statements for Goods and Consulting Services and a JVA plus PCAB license for Infrastructure Projects; and requires English documents or an English translation authenticated by the appropriate Philippine foreign service establishment, or an apostille for Apostille Convention countries not on the DFA legalization list.

**Legal provision:** IRR §§52.2, 52.3, 52.4.1.1, 52.4.2.1, 52.4.2.3.
**Verification:** ownership-share, JV-documentation, and authentication legal-control tests.

### AC-097G — Qualification reassessment

**Must:** the system supports reassessment of a bidder's qualifications at any stage on reasonable grounds of misrepresentation or changed capability, and disqualifies the bidder from award or contract implementation when false information or changes affecting eligibility are found.

**Legal provision:** IRR §52.6.
**Verification:** trigger, result, and consequence legal-control test.

### AC-097H — Envelope structure and Omnibus Sworn Statement

**Must:** the system requires simultaneous submission of a technical envelope carrying the eligibility documents and a financial envelope carrying the financial documents, and validates every IRR §54.3(a)–(i) Omnibus Sworn Statement declaration together with the notarized authority document.

**Legal provision:** IRR §§54.1–54.4.
**Verification:** envelope-set and declaration checklist tests.

### AC-097I — Modification and withdrawal

**Must:** a modification before the deadline does not retrieve the original bid and is stored as a new, marked, stamped-received submission; a modification after the deadline is not considered and is marked for return unopened; a withdrawal before the deadline is recorded as such; a withdrawal after the deadline is classified as sanctionable; and a withdrawn bidder is blocked from bidding on the same contract directly or indirectly.

**Legal provision:** IRR §§55.1–55.2.
**Verification:** timing, marking, disposition, and re-bid block legal-control tests.

### AC-098 — ABC ceiling

**Must:** a bid above the approved ABC is handled under the pinned rule and cannot produce a normal compliant award result.

**Legal provision:** IRR §60.
**Verification:** legal-control test.

### AC-098A — Award-criterion-specific opening

**Must:** LCRB opens technical and financial proposals on the same day; MEARB/MARB initially open only technical proposals and release financial proposals only for the prescribed qualifying bidders; a justifiable postponement secures bids and issues the required notice through PhilGEPS and the PE website.

**Legal provision:** IRR §§58.1–58.4.
**Verification:** criterion matrix, access-release, postponement, and publication tests.

### AC-098B — Preliminary-examination pass/fail

**Must:** the system uses the prescribed checklist and non-discretionary pass/fail criterion, records the reason for each failure, and automatically fails a second-envelope bid whose total exceeds ABC.

**Legal provision:** IRR §59.
**Verification:** checklist matrix and boundary tests.

### AC-099 — Evaluation reproducibility

**Must:** independent scores, corrections, criterion versions, weights, and result can be reconstructed exactly; rerunning with identical inputs yields the same result.

**Verification:** replay and audit test.

### AC-100 — Post-qualification cascade

**Must:** when the top bidder fails, the system follows the applicable next-bidder post-qualification procedure and preserves each attempt and result. It enforces the five-day submission window and the 12-day process period with the reviewed IRR §63.8 extension caps.

**Legal provision:** IRR §§63.2, 63.6–63.8, 66.4, 69.2.
**Verification:** scenario and boundary tests.

### AC-101 — Disapproval grounds

**Must:** a HoPE disapproval cannot complete without written valid, reasonable, and justifiable grounds and evidence that a copy was furnished to the BAC and bidder.

**Legal provision:** IRR §§66.3, 70.
**Verification:** legal-control test.

### AC-101A — Reconsideration after disapproval or inaction

**Must:** a reconsideration filed after disapproval or inaction is tracked on its own three-day filing timer and seven-day resolution timer; the resolution is furnished to the bidder immediately upon promulgation; the request never stays the process; and award is blocked until the request is resolved.

**Legal provision:** IRR §66.4.
**Verification:** timer, delivery, and pre-award sequencing legal-control tests.

### AC-101B — Failure of bidding and re-bidding

**Must:** a failure determination is produced only under the IRR §64.1 grounds; the mandatory End-User review is recorded; revised documents and any ABC adjustment with approval are captured; re-publishing occurs under IRR §50; previously eligible or short-listed bidders can submit new bids under the same rules and new periods; and a second failure permits the negotiated-procurement transition under IRR §35.

**Legal provision:** IRR §§64.1–64.5.
**Verification:** ground, review, republication, carry-over, and second-failure scenario tests.

### AC-101C — Single responsive bid scenario

**Must:** a single calculated, rated, economically advantageous, or advantageous responsive bid is recognized only under the IRR §65(a)–(c) circumstances, and the system verifies that the ABC reflects the most advantageous prevailing price for the Government.

**Legal provision:** IRR §65.
**Verification:** scenario classifier and ABC prevailing-price tests.

### AC-102 — Award preconditions

**Must:** contract award completion requires the applicable NOA conditions, performance security, and signed contract.

**Legal provision:** IRR §§66.5, 68.
**Verification:** checklist feature test.

### AC-102A — Goods amendment-to-order limits

**Must:** a Goods amendment to order requires an applicable §71.1.1 ground and written contract modification; an increase above 10% of the original contract price is blocked without HoPE approval, and the approved increase cannot exceed 20%.

**Legal provision:** IRR §71.1.1.
**Verification:** amount, authority, and evidence legal-control tests.

### AC-102B — Goods liquidated damages

**Must:** the system computes liquidated damages as one-tenth of one percent of the delayed goods' cost per day through delivery and acceptance, preserves granted extensions, and identifies the 10%-of-contract-price rescission/sanction threshold.

**Legal provision:** IRR §71.1.4.
**Verification:** formula, time-boundary, extension, and threshold tests.

### AC-102B1 — Infrastructure variation-order limits

**Must:** the system caps cumulative positive Infrastructure variation orders at 10% of the original contract price unless the HoPE exceptionally authorizes up to 20%; it permits no payment for unapproved variation work and enforces the 5% unapproved-emergency-work ceiling.

**Legal provision:** IRR §71.2.1.
**Verification:** cumulative percentage, approval, work-start, and payment tests.

### AC-102C — Goods warranty period and security

**Must:** the system applies at least three months' warranty for Expendable Supplies and one year for Non-expendable Supplies after acceptance, and requires the prescribed 1%–5% retention or equivalent guarantee.

**Legal provision:** IRR §90.1.
**Verification:** classification, period, security, and release tests.

### AC-102C1 — Infrastructure warranty obligations and structural-defect periods

**Must:** the system tracks the contractor's full responsibility for works damage (except force majeure) and site safety from construction start to final acceptance, the 90-calendar-day repair obligation after the HoPE's repair order with cost recovery on failure or refusal, the warranty-security form and amount (5% cash or bank letter of credit, 10% bank guarantee, 30% callable-on-demand surety), and the structural-defect liability periods of 15 years for permanent structures, 5 years for semi-permanent structures, and 2 years for other structures.

**Legal provision:** IRR §§90.2.1–90.2.3.
**Verification:** period, class, form/amount, and repair-deadline legal-control tests.

### AC-102C2 — Contract liquidated damages

**Must:** every contract record contains a liquidated-damages provision of at least one-tenth of one percent of the cost of the unperformed portion per day of delay, the system accrues the amount, and it identifies the 10%-of-contract-price point at which the PE may terminate without prejudice to other remedies.

**Legal provision:** IRR §97.
**Verification:** clause-presence, accrual, and threshold tests.

### AC-102C3 — Fixed price and escalation path

**Must:** an awarded bid price is treated as fixed and not subject to escalation; an Infrastructure escalation is blocked without prior GPPB approval, is permitted only on a no-loss, no-gain basis when the computed cost increase exceeds 10% of the unit price of work items against PSA indices, and requires the GPPB-prescribed formula.

**Legal provision:** IRR §89.
**Verification:** fixed-price, threshold, approval, and formula-version tests.

### AC-102D — Performance-security form, amount, and release

**Must:** the system enforces 5% Goods/Consulting and 10% Infrastructure cash/check minimums or 30% bank/surety minimums, requires security before contract signing, maintains it through final acceptance, and blocks release while claims exist.

**Legal provision:** IRR §§68.1, 68.4–68.7.
**Verification:** form/amount/timing/claim-release legal-control tests.

### AC-103 — Award blocked by unresolved protest

**Must:** an eligible protest that must be resolved before award blocks the award transition until a decision is recorded.

**Legal provision:** IRR §86.
**Verification:** scenario test.

### AC-104 — Reconsideration envelope hold

**Must:** when a failed bidder timely signifies intent to request reconsideration, its envelope remains unopened or duly sealed until resolution.

**Legal provision:** IRR §83.1.
**Verification:** state and access test.

### AC-105 — Verified protest position paper

**Must:** an unverified position paper produces the IRR §83.3 no-legal-effect/dismissal outcome; non-curable defects are not presented as curable.

**Legal provision:** IRR §83.3.
**Verification:** negative legal-control test.

### AC-105A — LGU protest finality thresholds

**Must:** for LGUs, the system applies final and executory status at or below ₱1,250,000 for Goods, ₱12,500,000 for Infrastructure Projects, and ₱2,500,000 for Consulting Services; above those thresholds it computes finality 10 calendar days after receipt unless a perfected appeal exists.

**Legal provision:** IRR §§84.3, 85.
**Verification:** exact-threshold and receipt-boundary legal-control tests.

### AC-105B — Protest fee schedule

**Must:** the system computes the non-refundable protest fee from the IRR §83.2 schedule by ABC range — 0.75% up to ₱50,000,000; ₱500,000 above ₱50,000,000 to ₱100,000,000; 0.5% above ₱100,000,000 to ₱500,000,000; ₱2,500,000 above ₱500,000,000 to ₱1,000,000,000; 0.25% above ₱1,000,000,000 to ₱2,000,000,000; ₱5,000,000 above ₱2,000,000,000 — and requires electronic-payment evidence before the protest is treated as filed.

**Legal provision:** IRR §83.2.
**Verification:** range-boundary and payment-evidence legal-control tests.

### AC-105C — Position-paper completeness and oath certifications

**Must:** the system rejects a protest whose position paper omits any IRR §83.3(a)–(h) item or lacks the verifying affidavit, applying the no-legal-effect/outright-dismissal outcome; noncompliance with the oath certifications, including the five-day reporting duty, is recorded as non-curable.

**Legal provision:** IRR §83.3.
**Verification:** omission, missing-affidavit, and non-curable-defect legal-control tests.

### AC-106 — Suspension and blacklisting periods

**Must:** the system applies the verified periods and grounds for suspension, blacklisting, and perpetual disqualification from the pinned source: one year for a first suspension offense and two years for a second; one year for a first blacklisting offense and two years for a second; and perpetual disqualification on the §101 grounds.

**Legal provision:** IRR §§99–101.
**Verification:** ground/period table tests.

### AC-106A — Sanction-decision finality

**Must:** absent a timely request for reconsideration, the system computes a HoPE sanction decision as final after seven days from receipt and preserves the request status that determines finality.

**Legal provision:** IRR §104.1(b).
**Verification:** request/no-request and receipt-boundary tests.

### AC-107 — Automatic pending suspension

**Must:** filing blacklisting proceedings automatically suspends participation in the current procurement, and dismissal automatically lifts it.

**Legal provision:** IRR §102.
**Verification:** state-transition test.

### AC-108 — GPPB sanction posting

**Must:** a sanction order cannot be marked fully completed without evidence of GPPB portal upload within seven calendar days.

**Legal provision:** IRR §104.1(d).
**Verification:** legal-control and publication-evidence test.

### AC-108A — Sanction grounds and related-party scope

**Must:** the system offers the enumerated IRR §§99–101 grounds as a structured selection with the required supporting facts, links suspension and blacklisting to bid or performance security forfeiture, records perpetual disqualification on a third blacklisting offense or three posted violations and on failure to repair within 90 calendar days of the HoPE's repair order, and expands the sanction scope under IRR §§103 and 105 to controlling-interest entities, beneficial owners, and the enumerated related entities including the 20%-share stockholder and corporation officers.

**Legal provision:** IRR §§99–101, 103, 105.
**Verification:** ground selection, forfeiture link, offense-counter, repair-deadline, and ownership-expansion legal-control tests.

### AC-108B — Sanction procedure and declaration enforcement

**Must:** the system records motu proprio or bidder-initiated proceedings under IRR §104, requires immediate written show-cause notice with hearing option, blocks a BAC recommendation without substantial evidence, enforces the HoPE's 15-day decision deadline, allows only one request for reconsideration with a three-day filing and seven-day resolution window and immediate copy delivery, and—on enforcement of a Bid or Performance Securing Declaration—requires the HoPE to issue the order immediately upon the BAC recommendation with a three-day reconsideration window.

**Legal provision:** IRR §§104, 104.1, 105.1.
**Verification:** procedure, deadline, single-request, and immediate-order legal-control tests.

### AC-108C — Dispute pathway and interim-relief restriction

**Must:** a contract dispute record requires an amicable-resolution attempt before any ADR step, supports the selected dispute-resolution mode including CIAC referral where the dispute falls within CIAC's competence, and records the IRR §87 restriction that only the Supreme Court may issue a temporary restraining order or preliminary injunction against procurement, with the extreme-urgency exception and court-fixed bond.

**Legal provision:** IRR §§87, 88.1–88.3.
**Verification:** sequencing, CIAC-routing, and interim-relief legal-control tests.

### AC-109 — Procurement record closure

**Must:** closure requires the required evidence bundle, unresolved-obligation review, retention assignment, and final contract acceptance where applicable.

**Source:** LIFE-150–LIFE-153.
**Verification:** feature test.

## 11. First vertical slice

### AC-120 — End-to-end PPMP to acknowledgement

**Must:** an authorized municipal user can perform:

```text
PPMP → Indicative APP → BAC recommendation/resolution → HoPE approval
→ signed final APP → issued and acknowledged
```

using the applicable official template versions, with every state change, decision, signature, issuance, delivery, and acknowledgement evidenced.

**Verification:** end-to-end browser and API test with audit reconstruction.
**Gate:** first vertical slice.

### AC-121 — Vertical-slice audit reconstruction

**Must:** an auditor can select the final APP and reconstruct its author, content hash, BAC recommendation, BAC roster and quorum, HoPE approval, signature, issuance, recipient, acknowledgement, rule pack, and source citations.

**Verification:** audit report and evidence bundle.
**Gate:** first vertical slice.

### AC-122 — Vertical-slice correction

**Must:** correcting the approved APP creates a new version, invalidates affected approvals and signatures, preserves history, and requires renewed approval before issuance.

**Verification:** scenario test.
**Gate:** first vertical slice.

## 12. Audit, evidence, and integrity

### AC-130 — Append-only audit

**Must:** an application role cannot update or delete audit events, and every material action has one correlated event.

**Source:** PLAT-110, ARC-034.
**Verification:** database-permission test and action inventory.

### AC-131 — Evidence bundle completeness

**Must:** a generated bundle contains the subject version, required artifacts, decisions, signatures, issuance, publication/distribution evidence, relevant audit events, and a signed or hashed manifest.

**Source:** PLAT-111–PLAT-112, DOM-092.
**Verification:** bundle inspection.

### AC-132 — Tamper detection

**Must:** changing artifact bytes or an audit-event chain invalidates the affected verification and raises an integrity alert.

**Verification:** controlled tamper test.

### AC-133 — MultiChain is supplementary

**Must:** with MultiChain unavailable, all core platform functions continue; with an anchor present, verification explains that it corroborates a hash/root and does not establish workflow truth.

**Source:** ARC-100–ARC-105.
**Verification:** outage and verification tests.

### AC-134 — Anchor retry does not duplicate meaning

**Must:** retrying an anchor job does not create semantically duplicate anchors for the same deterministic subject and batch.

**Verification:** retry/fault-injection test.

## 13. Records management and search

### AC-140 — Retention schedule enforcement

**Must:** a disposition request cannot complete without an applicable schedule, trigger calculation, review, authority, and tombstone/manifest.

**Source:** PLAT-130–PLAT-133.
**Verification:** feature test.

### AC-141 — Legal hold precedence

**Must:** an active applicable legal hold blocks disposition even when the retention period has expired, and release restores normal review.

**Source:** PLAT-131, DOM-129.
**Verification:** feature test.

### AC-142 — Fixity monitoring

**Must:** stored artifact fixity can be checked and missing/corrupt artifacts are reported without silently deleting the logical record.

**Source:** PLAT-133.
**Verification:** simulated corruption and missing-object test.

### AC-143 — Permission-aware search

**Must:** search never returns a result the actor cannot open through the corresponding resource endpoint, and OCR derivatives are labeled.

**Source:** PLAT-120–PLAT-122.
**Verification:** cross-role security tests.

### AC-144 — Duplicate detection is non-destructive

**Must:** duplicate candidates are suggestions only; no automatic merge or deletion occurs.

**Source:** PLAT-123.
**Verification:** feature test.

## 14. Security and privacy

### AC-150 — Tenant isolation

**Must:** one tenant cannot access or infer another tenant's metadata, object, search index, task, audit event, export, or job through any API.

**Source:** ARC-162.
**Verification:** automated isolation test suite.

### AC-151 — Object authorization

**Must:** knowledge of an object key or signed URL cannot bypass logical document authorization; expired or wrong-purpose URLs fail.

**Source:** ARC-026, ARC-151.
**Verification:** security test.

### AC-152 — Malware quarantine

**Must:** unscanned or malicious uploads cannot be downloaded, previewed, OCRed, or attached to a workflow; scanning status is visible.

**Source:** PLAT-143, ARC-044.
**Verification:** malware fixture test.

### AC-153 — Privileged action re-authentication

**Must:** signing, delegation, legal-hold release, and disposition authorization require the configured step-up or re-authentication.

**Source:** PLAT-142.
**Verification:** security and feature tests.

### AC-154 — Audit of access and disclosure

**Must:** sensitive views, exports, downloads, and disclosures are auditable with actor, subject, purpose, and time.

**Verification:** audit inspection.

### AC-155 — Legal privacy review

**Must:** before production, a qualified reviewer approves the data inventory, purposes, access, retention, disclosure, and cross-border or external-provider arrangements for personal and confidential procurement data.

**Source:** PLAT-144.
**Verification:** signed legal/privacy review.
**Owner:** legal/privacy reviewer.

## 15. Availability, backup, and operations

### AC-160 — Restore test

**Must:** a production-like environment is restored from backup, and PostgreSQL records, object bytes, hashes, workflow state, and evidence manifests reconcile to the backup point.

**Source:** ARC-160–ARC-162.
**Verification:** timed restore rehearsal.

### AC-161 — Queue recovery

**Must:** worker termination and restart do not lose or duplicate completed work; poisoned jobs are quarantined and replayable.

**Source:** ARC-130–ARC-133.
**Verification:** fault-injection test.

### AC-162 — Integration outage

**Must:** identity, signature, PhilGEPS, notification, and chain outages produce explicit degraded states and safe recovery without false success.

**Verification:** outage matrix.

### AC-163 — Observability and correlation

**Must:** an operator can trace an API command through database change, audit event, outbox, job, and external-call outcome by correlation ID.

**Source:** ARC-140–ARC-144.
**Verification:** operational trace test.

### AC-164 — Monitoring and alerts

**Must:** alerts exist for failed legal-gate evaluation, publication reconciliation mismatch, signature verification failure, overdue critical deadline, audit-ingest lag, backup failure, and repeated job failure.

**Verification:** alert drill.

## 16. Migration acceptance

### AC-170 — Repeatable import

**Must:** the same approved source snapshot produces the same import manifest and target identifiers, with unchanged original artifacts and hashes.

**Source:** ARC-180–ARC-183.
**Verification:** repeated fixture import.

### AC-171 — No fabricated compliance

**Must:** a legacy record lacking approval, signature, receipt, or rule evidence is not assigned a compliant target state without explicit review and provenance.

**Verification:** migration sample and anomaly report.

### AC-172 — Legacy chain reconciliation

**Must:** imported MultiChain references identify network, stream, transaction, key/address, timestamp, and payload hash; missing or inconsistent payloads are reported.

**Source:** ARC-184.
**Verification:** chain reconciliation test.

### AC-173 — Unmappable records

**Must:** unmappable records enter review and are never silently discarded or guessed.

**Verification:** fixture import.

### AC-174 — Cutover rehearsal

**Must:** a full rehearsal completes with reconciliation, rollback, business sign-off, and legacy-history accessibility.

**Source:** ARC-185–ARC-187.
**Verification:** rehearsal report.

## 17. Usability and accessibility

### AC-180 — Work-first usability

**Must:** a user can find, understand, and act on an assigned task without needing to search across documents, spreadsheets, and external inboxes.

**Verification:** moderated task test with procurement staff.
**Owner:** product owner.

### AC-181 — Control explanation

**Must:** a blocked transition explains the unmet requirement, source, owner/next action, and allowed correction or review path without exposing confidential details.

**Source:** ARC-192.
**Verification:** usability and security review.

### AC-182 — Accessible critical tasks

**Must:** core task flows are keyboard operable, have visible focus and programmatic labels, and meet the agreed WCAG 2.2 AA audit criteria.

**Source:** ARC-193.
**Verification:** accessibility audit.

### AC-183 — Printable evidence

**Must:** a user can print or export a human-readable evidence view that identifies the version, status, timestamps, decisions, signatures, and evidence manifest.

**Verification:** print/export inspection.

## 18. Release gates

| Gate | Required criteria | Decision authority |
| --- | --- | --- |
| G0 — Legal baseline approved | AC-010–AC-017; applicable legal reviews | Legal/compliance authority |
| G1 — Platform foundation | AC-020–AC-046, AC-130–AC-134, AC-150–AC-164 | Product and engineering |
| G2 — First vertical slice | AC-090–AC-091 where applicable, AC-120–AC-122, AC-130–AC-136, AC-150–AC-155 | Product, QA, legal/compliance |
| G3 — Planning and readiness | AC-092–AC-096 plus vertical-slice baseline | Product, QA, legal/compliance |
| G4 — Bidding through award | AC-097–AC-103 | Product, QA, legal/compliance |
| G5 — Contract and remedies | AC-104–AC-109 | Product, QA, legal/compliance |
| G6 — Records and migration production | AC-140–AC-144, AC-160–AC-174 | Product, operations, security, records, legal |
| Production | All applicable **Must** criteria, no unapproved legal hard-gate failure, signed deployment and rollback plan | Designated product and PE authority |

### AC-190 — Gate record

**Must:** each gate record lists every criterion and result, deviations, accepted risks, evidence links, legal sign-offs, security/operations sign-offs, and named approvers.

**Verification:** signed gate record.

### AC-191 — Deferred criterion

**Must:** a deferred criterion includes its impact, compensating control, owner, due date, and explicit authority. It MUST NOT defer a requirement needed to make a false legal status possible.

**Verification:** gate review.

## 19. Test data and environment

### AC-200 — Synthetic data

**Must:** automated tests use synthetic data and fake providers. Production personal, bid, or confidential data MUST NOT be copied into test environments unless an approved masking process and authorization exist.

### AC-201 — Deterministic clock

**Must:** automated tests use an injected clock and calendar; tests do not depend on wall-clock timing or network availability.

### AC-202 — Legal fixtures

**Must:** each hard-rule test fixture cites the reviewed requirement and expected legal-rule outcome and includes positive, negative, and boundary cases.

### AC-203 — Provider fakes

**Must:** external-provider fakes implement documented asynchronous, duplicate, expired, rejected, and outage behavior consistent with their contracts.

## 20. Acceptance sign-off template

A release is accepted only when a record equivalent to the following is approved:

```text
Release:
Gate:
Specification version:
Rule-pack versions:
Build/artifact identity:
Database migration:
Test report:
Legal-control report:
Security review:
Operations/DR review:
Records review (if applicable):
Migration reconciliation (if applicable):
Open deviations:
Deferred criteria and compensating controls:
Product owner:
QA owner:
Legal/compliance reviewer:
Security reviewer:
PE authorized representative:
Approval date:
```

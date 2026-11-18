# Compliance Specification: Legal Rules, Gates, Evidence, and Regulatory Boundaries

> **Status:** Build target
> **Specification version:** 0.1
> **Depends on:** [Product Specification](product-spec.md), [Platform Specification](platform-spec.md)

## 1. Purpose

This specification defines how ProcuChain represents, applies, tests, updates, and audits legal requirements. It is a compliance-by-design control model, not legal advice.

The platform MUST preserve the distinction between:

- the text of an authoritative source;
- an interpreted implementation requirement;
- a configured operational control;
- evidence that the control was evaluated;
- a human legal or policy decision.

## 2. Normative conventions

Requirements use `CMP-NNN` identifiers and the RFC 2119 keywords defined in the [Specification Index](README.md).

## 3. Source authority and precedence

### CMP-001 — Controlled legal register

The system MUST maintain a legal register containing each source's identifier, title, issuing authority, authority class, publication or issue date, effective date, retrieval date, official URL, local immutable copy or object hash, supersession relationship, and review status.

### CMP-002 — Precedence

Controls MUST be applied in this order:

1. Constitution and applicable statutes;
2. RA 12009;
3. officially approved IRR and amendments;
4. applicable GPPB resolutions, circulars, guidelines, standard forms, decision trees, and policy matters;
5. other applicable laws and sector rules;
6. approved Procuring Entity policy;
7. lower-level procedure and configuration.

A lower source MUST NOT weaken a higher source. Where a conflict or ambiguity exists, the system MUST block the affected control, preserve the conflicting text, and request legal review.

### CMP-003 — Classification

Each legal source MUST have an authority class and may have multiple subject tags. The platform MUST support at least `constitution`, `statute`, `executive_issuance`, `regulation`, `joint_issuance`, `GPPB_issuance`, `standard_form`, `decision_tree`, `PE_policy`, `technical_guidance`, and `advisory`.

An advisory MUST NOT be treated as a mandatory rule unless its authority class and legal effect are explicitly established by a qualified reviewer.

### CMP-004 — Official source

The legal register MUST link to the authoritative publisher. A local summary, unofficial compilation, blog, vendor guide, or repository working note MUST be labeled as secondary research and MUST NOT establish a settled legal control.

### CMP-005 — Source immutability

A registered source snapshot MUST be immutable. Corrections, replacement PDFs, or later opinions MUST create a new source version linked by `supersedes`, `amends`, `clarifies`, or `repeals`.

### CMP-006 — Source verification

Before a legal rule is activated, a designated reviewer MUST verify the source text, provision, authority, effective date, applicability, and interpretation. Automated extraction MUST be marked `machine_extracted` until reviewed. The current IRR verification record is maintained in the [Verification Ledger](verification-ledger.md).

### CMP-007 — Public Advisory No. 09-2026

The platform MUST reflect that GPPB Public Advisory No. 09-2026 withdrew the unofficial GPPB-TSO compilation and directed stakeholders to use and cite only the IRR approved through GPPB Resolution No. 02-2025. A withdrawn compilation MUST remain in the legal register as withdrawn and MUST NOT be the active baseline.

## 4. Baseline legal register

### CMP-010 — RA 12009

The baseline statute is **Republic Act No. 12009, New Government Procurement Act**, enacted 20 July 2024 and effective 13 August 2024.

The platform MUST support statutory concepts including:

- governing principles and scope (RA §§3–4);
- definitions, including ABC, BAC, Bidding Documents, HoPE, procurement, and Procuring Entity (RA §5);
- mandatory standardized procurement forms and mode decision tree (RA §6);
- strategic planning and APP linkage (RA §7);
- detailed engineering, multi-year contracting, market scoping, specifications, EPA, LCA/LCCA, and related planning controls (RA §§8–19);
- electronic procurement and procurement modes (RA §§20–40);
- BAC, TWG, Secretariat, observers, and procurement unit (RA §§41–46);
- bidding documents, eligibility, submission, opening, evaluation, post-qualification, award, and contract implementation (RA §§47–71);
- sustainable, inclusive, and domestic/foreign procurement rules (RA §§72–80);
- disclosure, protest, dispute resolution, and contract-price/warranty rules (RA §§81–90);
- GPPB powers, public-officer liability, private-entity sanctions, and indemnification (RA §§91–107);
- appropriations, capacity building, oversight, mandatory review, IRR/standard-form authority, transition, reports, repeal, separability, and effectivity (RA §§108–117).

The exact statutory cross-references in the procurement lifecycle specification MUST be verified against the controlled RA 12009 source before implementation.

### CMP-011 — IRR of RA 12009

The baseline IRR is the **IRR of RA 12009 approved through GPPB Resolution No. 02-2025**, published 10 February 2025 and effective 25 February 2025.

The official baseline MUST be used, not the withdrawn unofficial compilation identified in CMP-007.

### CMP-012 — GPPB issuances

The legal register MUST include, at minimum, the following applicable issuances and MUST verify their current status and effect before activation:

| Source | Subject | Status in this specification |
| --- | --- | --- |
| GPPB Resolution No. 02-2025 | IRR of RA 12009 | Baseline IRR; verified against official text. |
| GPPB Resolution No. 03-2025 | Standard Forms for Procurement | Mandatory forms; official source verified. Effective date and deployment applicability must be checked for the relevant procurement. |
| GPPB Resolution No. 12-2025 | Procurement Mode Selection Decision Tree | Adopted as an IRR annex; it guides PE validation and is not a definitive mode determination. |
| GPPB Resolution No. 10-2026 and Circular No. 06-2026 | Documentary requirements for specific modes | Applicable requirements must be activated per mode and procurement stage. |
| GPPB Public Advisory No. 09-2026 | Withdrawal of unofficial compiled IRR | Active notice; unofficial compilation prohibited as baseline. |
| GPPB Resolution No. 16-2019 | Use of digital signature in procurement-related documents | Permits digital signature in enumerated procurement documents; the PE must adopt internal rules. Interaction with RA 8792, EO 810, current forms, and local policy requires legal review. |

The legal register MUST also capture any later GPPB issuance that changes the baseline. A document's rule-pack snapshot determines which issuances applied when it commenced; see CMP-033 and CMP-034.

### CMP-013 — Electronic-signature law

The system MUST support electronic signatures against the legal conditions in RA 8792 §8, the certification scheme established by EO 810, and applicable procurement-specific issuance.

The system MUST NOT claim that an electronic signature is always equivalent to a wet signature. Whether a specific signature satisfies legal formalities is **legal review required** and depends on the instrument, document class, signer authority, signature method, and evidentiary context.

### CMP-014 — PE-specific authority

Before production use, the platform MUST incorporate approved PE instruments, including:

- local delegation and authorization instruments;
- BAC designation and rules;
- signature-method matrix;
- records classification and retention schedule;
- calendar and timezone;
- privacy and confidentiality policy;
- approved forms and document taxonomy;
- authorized publication and acknowledgement procedures.

These may add controls but MUST NOT weaken law, the IRR, or GPPB issuances.

## 5. Legal knowledge model

### CMP-020 — Legal authority

`LegalAuthority` represents the issuing body and authority class.

### CMP-021 — Legal instrument version

`LegalInstrumentVersion` represents one immutable issued version of a source, with official text, metadata, effective date, repeal or supersession relationships, and verification status.

### CMP-022 — Legal provision

`LegalProvision` identifies a provision within an instrument, such as a section and paragraph. It MUST retain an exact excerpt and stable locator.

### CMP-023 — Compliance requirement

`ComplianceRequirement` is the reviewed, citable statement of what the platform or Procuring Entity must do. It MUST link to one or more provisions and record:

- stable requirement ID;
- plain-language requirement;
- applicability predicates;
- actor and action;
- control type;
- required evidence;
- review owner;
- legal-review status;
- effective and sunset dates.

### CMP-024 — Rule

A `Rule` is the executable form of a compliance requirement. It MUST link to its reviewed requirement and record an expression, parameters, test fixtures, and version.

### CMP-025 — Control and gate

A `Control` is a rule or group of rules attached to a workflow transition, document event, or record lifecycle. A hard gate blocks a transition until satisfied. A soft warning permits a transition but creates an evidence or review task.

### CMP-026 — Control outcome

Every evaluation MUST record the control, rule and requirement versions, inputs, result, reason, timestamp, evaluator, and evidence.

### CMP-027 — Traceability chain

The system MUST support the chain:

```text
Legal source/version
→ provision
→ compliance requirement
→ rule
→ control/gate
→ workflow or API operation
→ UI action
→ test case
→ observed control outcome
```

Every activated requirement MUST have an owning reviewer, at least one control or an explicit `guidance_only` classification, and test coverage.

## 6. Rule types

### CMP-030 — Supported rule types

The rule engine MUST support these distinct types:

1. **Hard gate** — transition is prohibited until the requirement is satisfied.
2. **Conditional gate** — applies only when its typed predicate is true.
3. **Deadline** — computes an obligation or transition window from a trigger.
4. **Evidence requirement** — requires a specified artifact or control outcome.
5. **Calculation rule** — computes a regulated value such as a score, fee, or bid comparison.
6. **Composition/quorum rule** — evaluates membership, quorum, voting, or required composition.
7. **Publication rule** — identifies required channels, content, windows, and evidence.
8. **Notification rule** — identifies recipient, timing, channel, and content.
9. **Advisory guidance** — provides context without itself blocking a transition.

A rule MUST NOT be represented as a hard gate unless its reviewed requirement supports that effect.

### CMP-031 — Rule expression safety

Rule evaluation MUST be deterministic, side-effect free, traceable, and isolated from arbitrary code. Expressions MUST use an allow-listed function set.

### CMP-032 — No free-text legal decision

The system MUST NOT infer a hard legal conclusion from free text alone. A qualified reviewer MUST classify any rule whose evaluation depends on legal interpretation.

## 7. Temporal applicability

### CMP-033 — Rule-pack snapshot

A procurement, document, or workflow instance MUST pin an immutable `RulePackSnapshot` containing the applicable legal instruments, provisions, compliance requirements, rules, forms, calendars, and policy versions.

The snapshot MUST be created at the legally relevant commencement event defined by the applicable requirement.

### CMP-034 — IRR amendment boundary

IRR §112.2 states that an IRR amendment applies to procurement activities whose advertisement or invitation was issued after the amendment's effectivity. For competitive modes, the snapshot MUST therefore be fixed no later than the first advertisement or invitation issuance for that procurement unless a reviewed requirement identifies a different event.

The platform MUST support mode-specific commencement events and MUST NOT use one universal pinning point where the law distinguishes modes.

### CMP-035 — Standard-form transition

IRR §113.3 provides a three-year transitory period from approval of the standard forms for full compliance. The platform MUST calculate transition status from the approved form date and the procurement's applicable status. Legal review MUST determine the exact treatment for each transition case.

### CMP-036 — Historical reproduction

A rule-pack update MUST NOT alter an existing snapshot. Historical instances MUST continue to display and evaluate against the pinned snapshot while new instances use the newly activated snapshot.

### CMP-037 — Pending changes

Draft legal changes MUST be separately versioned and MUST NOT affect active gates. Activation MUST require legal review, effective-date metadata, and approval.

## 8. Evidence and legal review

### CMP-040 — Evidence classes

Evidence MUST be typed, for example:

- approved source excerpt;
- signed or certified decision;
- meeting minutes and attendance;
- publication receipt or screenshot;
- delivery and acknowledgement evidence;
- submitted document artifact;
- external-channel verification;
- computation trace;
- identity and authority evidence;
- legal-review record;
- exception record.

### CMP-041 — Evidence integrity

Evidence MUST retain its content hash, origin, acquisition time, custodian, and relationship to the evaluated control. A file name alone MUST NOT be accepted as evidence.

### CMP-042 — Legal-review queue

Ambiguous, conflicting, missing, or context-dependent legal questions MUST create a legal-review item with the exact provision, question, affected rule and documents, options, impact, requested authority, and due date.

### CMP-043 — Unresolved legal review

A `legal_review_required` hard control MUST remain blocked until the designated authority records a decision. The platform MUST support recording a reasoned interpretation with citation, scope, approver, date, and review expiry.

### CMP-044 — Interpretation scope

An interpretation MUST identify the cases it covers. It MUST NOT silently become a general legal rule.

### CMP-045 — No retroactive interpretation

A later interpretation MUST NOT be applied to historical evidence unless the authorized reviewer explicitly classifies it as retroactive and records the reason. Default behavior MUST preserve the original snapshot.

### CMP-046 — Conflict of interest and recusal

Where a legal control depends on an actor's participation, the system MUST require the conflict or interest disclosure and recusal decisions to be captured before counting the actor.

## 9. Exceptions

### CMP-050 — No generic override

The platform MUST NOT provide a generic “override IRR” action.

### CMP-051 — Exception only where law permits

An exception mechanism MUST exist only when an identified authoritative source or approved policy permits the specific exception. It MUST be bound to that source.

### CMP-052 — Exception record

Every exception MUST record:

- exact affected control and document version;
- legal or policy basis and provision;
- facts and justification;
- affected period or scope;
- authorized approver;
- approval and expiry timestamps;
- compensating controls;
- notification or publication requirement;
- linked audit and evidence.

### CMP-053 — Exception expiry

An expired exception MUST cease to authorize the action. The system MUST notify the owner and prevent continued reliance without renewed approval.

### CMP-054 — Exception does not rewrite

An exception MUST remain visible in the history and evidence bundle. It MUST NOT alter the original rule or make the underlying action appear unconditionally compliant.

## 10. Mandatory control matrix

This matrix defines the minimum active controls for the target platform. “Legal review required” means the cited source must be reviewed in the controlled legal register and the exact implementation decision approved before activation.

| Control ID | Requirement | Authority | System behavior | Evidence | Status |
| --- | --- | --- | --- | --- | --- |
| CMP-100 | Procurement must be linked to an approved APP or Indicative APP. | RA 12009 §7; IRR §§7.7–7.8 | Hard gate before procurement initiation and award. | APP version, HoPE approval, APP line linkage, funding evidence. | Verified direction; exact rule activation requires review. |
| CMP-100A | The Indicative APP is recommended by the BAC and approved by the HoPE; EPA uses the updated Indicative APP posted on the PE website; the approved final APP is posted on the PE website and submitted to GPPB by the end of January of the budget year. | IRR §§7.7.2–7.7.5 | APP approval workflow, publication evidence, and GPPB submission deadline. | BAC recommendation, HoPE approval, website posting evidence, GPPB receipt. | Verified. |
| CMP-100B | Procurement modes are those enumerated in IRR §26.1, and the PE must apply the Fit-for-Purpose approach; detailed procedures for each mode come from the applicable IRR provisions and GPPB manual. | IRR §§26.1–26.4; GPPB Res. 12-2025 | Mode allow-list and procedure-version check. | Selected mode, facts, reviewed manual/rule-pack version. | Verified. |
| CMP-100B3 | Except for Competitive Bidding, Limited Source Bidding, Competitive Dialogue, and Unsolicited Offer with Bid Matching, the BAC may delegate the conduct of procurement activities for the other modes to the End-User or Implementing Unit, or the PE's Procurement Unit; delegation MUST be recorded and MUST NOT extend to modes that require BAC conduct. | IRR §26.4 | Delegation eligibility and recording gate. | Mode, delegate, delegation instrument, task allocation. | Verified. |
| CMP-100B4 | Competitive Bidding consists of publication, pre-bid conference, eligibility screening, receipt and opening of bids, bid evaluation, post-qualification, and award of contract, and is subject to IRR Rules VI to XI; a PE may adopt Competitive Bidding notwithstanding the applicability of other modes. | IRR §27.1 | Workflow completeness gate for the competitive path. | Stage completion records, referenced rule range, adoption decision. | Verified. |
| CMP-100K | Direct Sales applies to Non-CSE that a supplier satisfactorily delivered to another government agency under a completed contract, and requires all of the IRR §36.1 conditions: the project is in the approved APP; the original Non-CSE was awarded through Competitive Bidding, Limited Source Bidding, Competitive Dialogue, or Unsolicited Offer with Bid Matching; the original agency inspected and accepted it and issued a certification of satisfactory performance; the PE initiates procurement of the same Non-CSE only once within six months of that acceptance; the new contract amount is within the original contract amount and the supplier offers the same or lower unit price; and the supplier agreed to the PE's terms and remains legally, technically, and financially capable. | IRR §§36.1–36.1(f) | All-conditions gate for Direct Sales. | APP line, original award mode, certification, acceptance date and six-month computation, amount and unit-price comparison, supplier capability attestation. | Verified. |
| CMP-100K1 | Under Direct Sales, the original agency must provide copies of the original procurement documents on request, including the Bidding Documents, Notice of Award, signed contract or Purchase Order, Notice to Proceed, Inspection and Acceptance Report, and Certificate of Satisfactory Performance and Completion; the End-User or Implementing Unit reviews them and prepares the Purchase Request; and the PE issues a Direct Sales Request whose acceptance must be communicated in writing within five calendar days of receipt, containing a statement of compliance with the DSR technical requirements, the NFCC computation, the same-or-lower unit price offer, and a copy of a valid and updated PhilGEPS Platinum Certificate of Registration, or a written non-acceptance within the same period. | IRR §§36.2(a)–(c) | Document-transfer, DSR, and five-day acceptance gate. | Transferred documents, Purchase Request, DSR, supplier response, NFCC computation, unit price, Platinum registration, response date. | Verified. |
| CMP-100L | Direct Procurement for Science, Technology and Innovation covers procurement directly from a legally, technically, and financially capable manufacturer or supplier, or from a qualified startup business, of supplies, materials, equipment, and related services used actually, exclusively, and directly in the conduct of research and development projects or activities; Goods including products of a commissioned task processed, developed, and manufactured to satisfy the PE's needs, including pre-commercial goods and services involving early market engagement and goods manufactured by qualified startup businesses; and other analogous goods. | IRR §37.1 | Mode scope gate with R&D-purpose and supplier-capability checks. | Scope classification, R&D project reference, commissioned-task agreement, startup qualification, capability record. | Verified; GPPB and NIC implementing guidelines pending and require review. |
| CMP-100L1 | Unless otherwise indicated in a written agreement, intellectual property rights and ownership over commissioned goods developed and manufactured under IRR §37.1(b) belong to the Procuring Entity, and the supplier may not perform manufacturing or sale of them. | IRR §37.1(b) | IP-ownership clause check with written-agreement override evidence. | Written agreement, IP clause, manufacturing and sale restriction acknowledgment. | Verified. |
| CMP-100B1 | ABC must follow IRR §5(a) for the funding/PE type, including LGU appropriation, MYCA total project cost, or foreign-approved estimate; it remains a ceiling under IRR §60.1. | IRR §§5(a), 60.1 | Typed ABC derivation and source gate. | Funding type, authority/estimate, amount, derivation, ceiling comparison. | Verified. |
| CMP-100B2 | Scope/exclusion and treaty-precedence checks MUST precede procurement workflow activation. | IRR §§4.1–4.4 | Scope classifier and authority-conflict gate. | Activity, funding, treaty/agreement, exclusion basis, scope decision. | Verified. |
| CMP-100C | Small Value Procurement requests at least three quotations; one is sufficient only up to ₱2,000,000; posting for three calendar days applies above ₱200,000; and LGU thresholds are versioned because GPPB may adjust them. | IRR §34; GPPB Res. 10-2026 | SVP-mode checklist, amount, posting, recipient, and threshold controls. | ABC, LGU class, threshold version, recipients, quotations, posting evidence, appropriation validity. | Verified. |
| CMP-100C1 | Small Value Procurement applies to Goods not available in the PS-DBM, Infrastructure Projects, and Consulting Services, and for LGUs uses the current class-based maximum amounts (1st/2nd Class: ₱2,000,000 Province, ₱2,000,000 City, ₱400,000 Municipality; 3rd Class: ₱2,000,000 / ₱1,600,000 / ₱400,000; 4th Class: ₱1,600,000 / ₱1,200,000 / ₱200,000; 5th Class: ₱1,200,000 / ₱800,000 / ₱200,000; Barangays: ₱100,000), which the GPPB is authorized to adjust based on LGU income classification and which apply across Goods, Infrastructure Projects, and Consulting Services. | IRR §§34.1–34.2 | Versioned threshold table with equality and boundary handling. | LGU class, entity type, applicable maximum, threshold version and effective date, ABC. | Verified; thresholds are versioned because GPPB may adjust them. |
| CMP-100C2 | Under Small Value Procurement, the End-User or Implementing Unit submits a request to the BAC indicating the Technical Specifications, Scope of Work, Terms of Reference, ABC, and other terms and conditions; the BAC sends RFQs or RFPs to at least three suppliers, contractors, or consultants of known qualifications and allows others who responded to required postings to participate; receipt of at least one quotation is sufficient to proceed; where no quotation is received the PE may extend the deadline until receipt of at least one quotation but not beyond the validity of the corresponding appropriations; a pre-bid conference may be held at BAC discretion; and an Abstract of Quotations or Ratings setting out respondent names and their quotations or ratings must be prepared before the BAC recommends the award. | IRR §§34.3(a)–(g) | SVP procedure checklist with recipient-count, posting, extension, and abstract gates. | Request, RFQ/RFP distribution list, posting evidence, quotes, extension record and appropriation validity, abstract, BAC recommendation. | Verified. |
| CMP-100D | Direct Contracting requires an applicable §31.2 ground and the prescribed RFQ/pro-forma-invoice and award flow. | IRR §31 | Conditional mode gate and evidence checklist. | Ground evidence, RFQ/pro-forma, capability/price record, BAC recommendation. | Verified. |
| CMP-100D1 | Direct Contracting applies only under IRR §31.2(a)–(c): the Goods are proprietary and obtainable only from the proprietary source, or patents, trade secrets, and copyrights prohibit others from manufacturing the same item, as supported by documentary evidence issued by a competent authority; procurement of critical components from a specific manufacturer, supplier, or distributor is a condition precedent to hold a service provider to guarantee project performance as determined by the End-User or Implementing Unit; or the Goods are sold by an exclusive dealer or manufacturer with no sub-dealers selling at lower prices and no suitable substitute obtainable at more advantageous terms to the Government. | IRR §31.2 | Structured ground gate with documentary-evidence requirement. | Selected ground, competent-authority evidence, End-User determination, exclusive-dealer/substitute analysis. | Verified. |
| CMP-100D2 | Under Direct Contracting, the BAC prepares the RFQ or pro-forma invoice with terms and conditions of sale and sends it to the identified direct supplier; simplified negotiations may be conducted at the PE's option to ensure the supplier is technically, legally, and financially capable to deliver at the most advantageous price and contract for the Government; and the BAC recommends the award to the HoPE, with award made under IRR §66. | IRR §31.3(a)–(c) | Mode procedure checklist. | RFQ/pro-forma, recipient, negotiation record, capability assessment, price comparison, BAC recommendation. | Verified. |
| CMP-100E1 | Direct Acquisition covers CSE not available in the PS-DBM, Non-CSE, and services with ABC not exceeding ₱200,000, procured without canvass or request for quotations directly from any known and reputable source; the procurement must be reflected in the APP, and Goods paid from petty cash or reimbursement that cannot be itemized must be identified as Miscellaneous Items in the APP. | IRR §§32.1–32.2 | Mode scope, amount, and APP-lineage gate. | ABC, PS-DBM availability, source reputation record, APP line or Miscellaneous Items entry, petty-cash reference. | Verified. |
| CMP-100E2 | The HoPE may authorize the End-User or Implementing Unit to purchase directly from any known and reputable source with legal, technical, and financial capacity where the supplier is registered with PhilGEPS as verified from the PhilGEPS website, the Goods are available on electronic-commerce platforms for delivery or pick-up at the identified date and time, or the Goods are readily available off-the-shelf for immediate pick-up or delivery; the PE must procure only from sources issuing BIR-compliant invoices or other documents evidencing delivery and payment acceptable to COA. | IRR §§32.3–32.4 | Source-eligibility and invoice-compliance gate. | HoPE authorization, PhilGEPS registration evidence, e-commerce availability record, off-the-shelf record, invoice or delivery evidence. | Verified. |
| CMP-100E | Direct Acquisition is limited to the IRR §32 scope, must be reflected in the APP, and requires qualifying source and invoice/delivery evidence. | IRR §32 | Scope/APP/source evidence gates. | ABC, APP line, source capacity, registration where relied upon, invoice/delivery. | Verified; current threshold and PE procedure require review. |
| CMP-100F | Repeat Order is linked to the original bidding award and Notice to Proceed; unit price is no higher than original and market price; it is within six months unless GPPB approved otherwise and no more than 25% of each item's original quantity, which must be quantifiable, divisible, and have at least four units. | IRR §33 | Eligibility, price, time, quantity, and documentary gates. | Original case/contract/NOP, prices, market evidence, item quantity/divisibility, exception. | Verified. |
| CMP-100F1 | Repeat Order applies to replenishment needs for Goods previously awarded through Competitive Bidding, Limited Source Bidding, Competitive Dialogue, or Unsolicited Offer with Bid Matching, is subject to the post-qualification process prescribed in the Bidding Documents, and requires the End-User or Implementing Unit to prepare the Purchase Request confirming the unit price is equal to or lower than the original contract price and lower than the prevailing market price before the BAC recommends the award to the HoPE. | IRR §§33.1–33.3 | Mode linkage, post-qualification, and purchase-request precondition gates. | Original award mode, post-qualification result, Purchase Request, price comparisons, BAC recommendation. | Verified. |
| CMP-100G | Negotiated Procurement is permitted only in the IRR §35 instances: two failed biddings for the second time under §64 (§35.1), emergency cases (§35.2), take-over of contracts (§35.3), adjacent or contiguous contracts (§35.4), agency-to-agency (§35.5), scientific/scholarly/artistic work or exclusive technology (§35.6), highly technical consultants (§35.7), defense cooperation agreements and inventory-based procurement (§35.8), lease of real property and venue (§35.9), NGO participation (§35.10), community participation (§35.11), UN agencies and international organizations (§35.12), and direct retail purchase of petroleum fuel, oil, and lubricants (§35.13). | IRR §§35.1–35.13 | Mode allow-list with per-instance evidence gate. | Selected §35 instance, supporting facts, evidence, BAC/End-User action record. | Verified direction; per-instance document set requires review. |
| CMP-100H | Limited Source Bidding may be resorted to only under IRR §28.2(a)–(c): highly specialized or complex Goods and Consulting Services obtainable only from a limited number of sources; major plant components where limiting bidding to known eligible bidders maintains optimum and uniform quality and performance of the plant as a whole; or highly specialized Infrastructure Projects involving and affecting national security. | IRR §28.2 | Conditional mode gate with structured grounds. | Selected ground, supporting facts, evidence. | Verified. |
| CMP-100I | Competitive Dialogue may be resorted to only under IRR §29.2(a)–(c): the procurement includes innovative design or solutions involving complex purchases requiring a high level of technology, human capital, competencies, or facility; the contract requires prior negotiations because of circumstances related to nature, complexity, legal and financial issues, or risks; or the Technical Specifications cannot be sufficiently established or precisely defined by the PE. | IRR §29.2 | Conditional mode gate with structured grounds. | Selected ground, supporting facts, evidence. | Verified. |
| CMP-100J | Unsolicited Offer with Bid Matching applies only where the HoPE determines the procurement involves a new concept or technology within IRR §30.1(a)(i)–(iv) and the PE has invited comparative or competitive bids through Competitive Bidding referencing the offers; the PE shall consider unsolicited offers from the original offeror on a negotiated basis for Goods and Consulting Services. | IRR §30.1 | Conditional mode gate with new-concept grounds and comparative-bid prerequisite. | HoPE new-concept determination, verifiable data/agency certification, comparative Invitation to Bid reference, offer origin. | Verified. |
| CMP-100J1 | To be considered for evaluation, the proponent must submit the IRR §30.2(a)–(d) package: details of the new concept or technology including purpose, unique features, applications, expected output, and a cost-benefit analysis; the proposed contract cost with supporting computations and justifications serving as the indicative ABC for eligibility checking and bid matching; proof of concept or equivalent document or sample; the IRR §52 eligibility documents; and proposed terms and conditions addressing timelines, deliverables, warranties, and other contractual provisions, including consent for the PE and other bidders to use the entire offer document for bid matching notwithstanding RA 8293. | IRR §30.2 | Offer-package completeness gate with indicative-ABC derivation. | Submitted documents, computed indicative ABC, proof-of-concept artifact, eligibility set, terms and conditions, IP consent. | Verified. |
| CMP-100J2 | The PE must assess the unsolicited offer within 20 calendar days of receipt, complete the entire evaluation process within 60 calendar days unless extended by the HoPE for justifiable reasons by not more than 30 additional calendar days, negotiate with the offeror on scope and terms, and complete negotiation within 15 calendar days from the commencement date specified within 5 calendar days of receipt of the certification conferring original offeror status; the PE must complete the remaining steps within 30 calendar days of that conferment and act within 15 calendar days of determination of the LCCRB. | IRR §§30.3–30.8 | Stage deadline calculators with HoPE extension cap. | Receipt date, assessment result, evaluation completion, extension decision and dates, negotiation start/end, conferment date, LCCRB determination. | Verified. |
| CMP-100I1 | Competitive Dialogue has two stages: in the first stage, Bidding Documents must not exceed maximum periods counted from the last posting day of 45 calendar days for Goods, 65 for Infrastructure Projects, and 75 for Consulting Services; eligibility is determined under IRR §52 and initial technical proposals are evaluated on a pass-or-fail criterion against the PE's minimum technical requirements; eligible bidders meeting the minimum standards are invited to the dialogue, and for Consulting Services the IRR §53 short-listing is conducted in the first stage. | IRR §§29.4.1(a)–(b) | Stage-1 deadline calculator and pass-or-fail gate. | Posting day, category, computed deadline, eligibility result, pass/fail outcomes, invitation list, short-list record. | Verified. |
| CMP-100I2 | In the dialogue, the BAC must ensure all participating bidders are treated equally and fairly, must not discriminate or give undue advantage, and must not disclose confidential information received in the dialogue without the express consent of its owner; any consent or agreement to disclose must be limited to the specific information and must not take the form of a general waiver. | IRR §§29.4.1(c)–(e) | Equal-treatment and confidentiality controls with consent record. | Disclosure log, information shared, consent instrument, equality attestation. | Verified. |
| CMP-100I3 | In the second stage, within 10 calendar days from finalization of the Technical Specifications, Scope of Work, or Terms of Reference, copies and any qualitative rating criteria must be issued to all dialogue participants; bidders must submit revised technical and financial proposals in two sealed envelopes within 20 calendar days of that issuance, and the contract is awarded to the LCRB, MEARB, MARB, or HRRB following IRR §§61–63 evaluation and post-qualification. | IRR §§29.4.2(a)–(b) | Stage-2 deadline calculators and issuance-to-all-participants gate. | Finalization date, issuance date, recipient list, submission deadline, envelope set, award criterion. | Verified. |
| CMP-100H1 | Pre-selected suppliers, contractors, or consultants must come from a periodically updated list maintained by the relevant government authority with expertise in the procurement concerned, submitted to the GPPB-TSO for collation and publication, or, where no such authority exists or the PE is itself the relevant authority, from a PE self-generated list with an established attestation of that fact and the PE's subject-matter expertise; pre-selection must consider capability and resources, personnel, equipment, or manufacturing facilities, financial position, and good standing. | IRR §§28.3–28.4 | Pre-selection provenance and criteria gate. | Source list or PE self-generated list, attestation, expertise basis, criteria scores, list update date, GPPB-TSO submission. | Verified. |
| CMP-100H2 | Except for the IRR §50 publication requirement, the PE must observe the Competitive Bidding procedure for Limited Source Bidding and may adopt reduced timelines within the total maximum period prescribed in the IRR. | IRR §28.5 | Procedure inheritance with reduced-timeline path bounded by the IRR maximum. | Stage checklist, timeline values, IRR maximum comparison. | Verified. |
| CMP-100G1 | In the Two Failed Biddings instance, the BAC must conduct the mandatory review and may revise eligibility requirements, technical specifications, or terms of reference and adjust the ABC subject to required approvals, but the ABC cannot be increased by more than 20% of the ABC for the last failed bidding; the BAC must invite at least three suppliers, contractors, or consultants including those disqualified in previous biddings, must communicate all negotiation information on an equal basis with equal time and opportunity, and must then request best offers on a specified date and recommend the S/LCRB or MEARQ for Goods and Infrastructure Projects or the S/HRRP for Consulting Services. | IRR §§35.1.1(a)–(e) | Review, ABC cap, invitee-count, equal-treatment, best-offer, and recommendation gates. | Review findings, revised documents, ABC before/after and 20% cap check, invitation list, communication log, best-offer date, recommendation. | Verified. |
| CMP-100G2 | For emergency cases, the documented cases are imminent danger to life or property during a state of calamity, time of the essence arising from natural or man-made calamities, and other causes where immediate action is necessary to prevent damage or loss of life or property or to restore vital public services, infrastructure facilities, and other public utilities. | IRR §35.2 | Emergency-ground classifier with immediate-action rationale. | Emergency classification, declaration reference, immediate-action necessity rationale, HoPE delegation if used. | Verified. |
| CMP-100G3 | In emergency cases, documentary requirements such as an expired Mayor's permit with official receipt for renewal and an unnotarized Omnibus Sworn Statement are accepted as basis for award, subject to submission after award of the contract but before payment. | IRR §35.2.1 | Post-award pre-payment remediation gate. | Accepted-for-award basis, post-award submission date, pre-payment confirmation. | Verified. |
| CMP-101 | Planning must include market scoping and the prescribed planning facts. | RA 12009 §§7, 10; IRR §§10.1–10.5 | Evidence gate for PPMP; warning where source says “may.” | Market Scoping Form and source records. | Verified direction. |
| CMP-101A | For Infrastructure Projects, detailed engineering/design and required right-of-way are generally prerequisites to bidding and award; pending right-of-way allows procurement to commence but not award until an authority/permit to enter, deed, or writ exists. | IRR §§8.1–8.1.1 | Infrastructure initiation/award gates. | Design approval, environment assessment, ROW evidence, award condition. | Verified. |
| CMP-101B | A multi-year locally funded contract requires MYCA or the applicable LGU equivalent before procurement activity; the total project cost in that authority is the ABC reference. | IRR §9 | Prior-authority and ABC-reference gate. | MYCA/equivalent, authority date, total project cost, ABC derivation. | Verified. |
| CMP-102 | Early Procurement Activities are limited and award is blocked until funding and final APP approval. | RA 12009 §12; IRR §§12.1–12.5 | Conditional hard gates. | Indicative APP approval, funding instrument, final APP, ROW/site evidence. | Verified. |
| CMP-103 | BAC composition and eligibility of members must be controlled. | IRR §§41.1–41.2 | Hard gate on BAC activation and decision. | HoPE designation, roster, rank/qualification, term and alternate records. | Verified. |
| CMP-104 | BAC quorum and decision must follow IRR §42. | IRR §§42.2–42.3 | Quorum and majority calculation; blocks invalid decision. | Attendance, eligibility, Chair/Vice presence, votes, abstentions, rule trace. | Verified. |
| CMP-105 | Observers must be properly invited and have no voting right. | IRR §43 | Invitation deadline warning/gate per reviewed interpretation; observer tasks are non-voting. | Invitation, qualification, confidentiality, attendance, report. | Verified source; gate effect legal review required. |
| CMP-106 | Procurement Secretariat must maintain custody of records and support monitoring. | IRR §§44.1–44.3 | Role and repository responsibility; assignment and custody tracking. | Designation, custody event, milestone monitor. | Verified. |
| CMP-106B | The Procurement Unit must perform the IRR §44.3 functions in the platform, including arranging BAC and TWG meetings, preparing BAC minutes and resolutions, taking custody of procurement documents and records and ensuring all procurements are properly documented, managing the sale and distribution of Bidding Documents, publishing or posting bidding opportunities and Notices of Award, monitoring procurement activities and milestones for reporting, consolidating PPMPs and preparing the APP, and acting as the BAC's central channel of communication with End-User or Implementing Units, PMOs, other agencies, providers, observers, and the public. | IRR §44.3 | Secretariat function checklist with named-actor assignment and custody ledger. | Function task records, minutes and resolution documents, custody events, distribution records, posting records, milestone monitor, communication log. | Verified. |
| CMP-108A | Before official release, no aspect of the Bidding Documents may be divulged or released to any prospective bidder or person with direct or indirect interest, or to any party, except those officially authorized in handling; in national-security procurements the disclosure of specific components such as technical specifications, requirements, and components depends on the HoPE's determination. | IRR §§48.1–48.3 | Pre-release confidentiality gate with HoPE-controlled component disclosure. | Access list, release log, confidentiality attestation, HoPE national-security determination, component disclosure decision. | Verified. |
| CMP-106A | The BAC must prepare a procurement monitoring report in the GPPB-prescribed form and coverage, approved by the HoPE and submitted to GPPB on a semestral basis. | IRR §42.1(k) | Semestral reporting workflow with HoPE approval and GPPB submission evidence. | Monitoring report version, period, HoPE approval, submission receipt. | Verified. |
| CMP-107 | Bidding Documents must follow GPPB standard forms and include prescribed contents. | RA 12009 §6; IRR §47; GPPB Res. 03-2025 | Template hard gate and content checklist. | Official form version, completed document, checklist, any approval of deviation. | Verified direction; mandatory applicability requires legal review. |
| CMP-107A | The Invitation to Bid or Request for Expression of Interest must contain the IRR §50.2 contents, including category-specific project description, lots/items and evaluation basis, eligibility/short-listing/evaluation/post-qualification/award criteria, award criterion (LCRB/MEARB/MARB/HRRB), weights and quality-price ratio where applicable, evaluation procedure for Consulting Services, all submission/opening deadlines, ABC, source of funding, place/time/website and price of Bidding Documents, contract duration or delivery schedule, PE contact details, bid security requirement, and whether electronic submission is allowed. | IRR §50.2 | Structured content checklist with per-field validation before release. | ITB/REOI version, checklist result, per-field values, deviations. | Verified. |
| CMP-107B | The Bidding Documents must contain the IRR §47.1(a)–(m) components: ABC; Invitation to Bid or Request for Expression of Interest; Instructions to Bidders including bid documents, eligibility criteria, evaluation methodology or criteria, post-qualification, criterion weights and quality-price ratio for MEARB or HRRB, or the LCRB/MARB specification and quality allocation, and the pre-bid conference, submission, and opening dates, times, and places; Technical Specifications, Terms of Reference, or Scope of Work; Eligibility Requirements; environmental criteria and specifications under §73; plans or drawings; Bid Form, Price Schedule, and List of Goods or Bill of Quantities; delivery time or completion schedule; form and amount of bid security; form and amount of performance security and warranty; form of contract with general and special conditions; and additional documentary requirements. | IRR §47.1 | Document-assembly checklist tied to the release gate. | Component list, present/absent per component, form versions, amounts. | Verified. |
| CMP-107C | The BAC must make the Bidding Documents available from the time the Invitation to Bid or Request for Expression of Interest is first published until the bid deadline, and must post the complete Bidding Documents on the PE website and PhilGEPS from publication; where a fee is required under the GPPB guidelines, it is paid upon bid submission or on securing the documents from the BAC Secretariat, and it may be refunded under the GPPB guidelines on the IRR §70 grounds. | IRR §§47.3–47.5 | Availability-window and fee-lifecycle checks. | Publication timestamp, availability window, download evidence, fee basis, payment record, refund record and grounds. | Verified; GPPB fee and refund guidelines require review. |
| CMP-107D | Specifications and other terms in the Bidding Documents must reflect the necessary requirements in clear and unambiguous terms; in mixed procurements the PE must specify the requirements, criteria, and conditions applicable to each component; and the PE must ensure compliance with existing laws, rules, and regulations, especially licenses and permits required for the project. | IRR §47.2 | Clarity attestation and per-component applicability check. | Clarity attestation, component mapping, license/permit checklist. | Verified. |
| CMP-108 | Equal access and confidentiality apply before bidding-document release. | IRR §48 | Access gate and selective-release prevention. | Access list, release log, confidentiality attestation. | Verified. |
| CMP-109 | Pre-procurement conference is mandatory above stated thresholds and optional at or below them. | IRR §49.1 | Threshold rule with complete evidence for mandatory case and recorded option-out for optional case. | Conference minutes, attendees, readiness checklist, threshold calculation. | Verified. |
| CMP-109A | In Competitive Bidding, procurement-related conferences must not commence without a video recording initiated by the BAC; recording is mandatory above the IRR §38.3 category thresholds, recording copies are retained at least five years, and preliminary examination/opening are livestreamed. | IRR §§38.1–38.4 | Recording-start and livestream gates plus retention. | Recording reference, initiation time, category/ABC threshold, livestream reference, retention. | Verified; current livestream guideline review required. |
| CMP-109B | Procurement scope must not be split to evade requirements; unit/lot/package procurement requires the IRR §39.1 factors, and phased Infrastructure work must be delineated, usable, structurally sound, and consistent with detailed design. | IRR §§39.1–39.2 | Planning evidence and reviewed-scope check. | Scope/lots/phases, budget, quantity, availability, delivery, geography, market capacity, design approval. | Verified. |
| CMP-109C | CSE must be procured from PS-DBM when available; procurement from another source requires an IRR §40.2 ground and its prescribed catalogue evidence or BAC/End-User justification and PS-DBM notification. | IRR §§40.1–40.2 | CSE source and evidence gate. | Catalogue checks, certificate/report, BAC resolution, End-User recommendation, notification. | Verified. |
| CMP-109D | The GPPB maintains a registry of entities in the IRR §76 sectors—farmers (DA), fisherfolk (BFAR), persons with disabilities (NCDA), solo parents (DSWD), microenterprises and social enterprises (MSMED Council), startups and STI entities (DTI, DICT, NIC, DOST), cooperatives (CDA), and other sectors the GPPB may determine. | IRR §76.1 | Sector-registry reference and certification lookup. | Sector classification, certifying agency, registry reference. | Verified; registry availability and GPPB-issued exemptions under §77 require review. |
| CMP-110 | BAC must determine the award criterion before publication. | IRR §§49.1(d), 49.2 | Hard gate before publication for the competitive workflow. | BAC resolution and locked criterion. | Verified. |
| CMP-111 | Publication and Bidding Documents must follow prescribed periods and channels. | IRR §§50–51; IRR §20 | Publication rule, deadline calculator, required-channel evidence. | Channel receipts/screenshots, exact artifact hashes, timestamps. | Verified direction; detailed durations and channels require per-mode review. |
| CMP-111A | A competitive procurement with ABC of ₱3,000,000 or more requires a pre-bid conference; below that amount it is discretionary, subject to BAC approval or a bidder's written request. | IRR §51.1 | Threshold rule and written-request path. | ABC, conference record, BAC approval or written request. | Verified. |
| CMP-111B | The pre-bid conference is at least 12 calendar days before bid deadline and no earlier than 7 calendar days after the applicable posting/shortlist event; minutes are due within 5 calendar days; amendments are issued at least 7 calendar days before bid deadline. | IRR §§51.2–51.4 | Deadline calculator and bulletin gate. | Posting/shortlist event, conference and minutes, deadline, bulletin. | Verified. |
| CMP-111C | Clarification requests are due at least 10 calendar days before bid deadline and the response bulletin at least 7 calendar days before; PE-initiated modifications and bulletins follow the applicable 7-day rule and bidder notice/right to modify or withdraw. | IRR §§51.5.1–51.5.3; IRR §55 | Conditional deadline rules and bidder-right task. | Request, response, bulletin, affected-bidder notice. | Verified. |
| CMP-111D | Invitations to bid are published continuously on the PE website or social media, or other GPPB-authorized channel; a PE unable to publish on those channels publishes at least once in a newspaper of general nationwide circulation. | IRR §§50.1–50.1.2 | Channel activation and fallback rule. | Channel, continuous-period start/end, reason, newspaper issue and evidence. | Verified. |
| CMP-111E | Competitive Bidding, Competitive Dialogue, and Unsolicited Offer with Bid Matching must be posted at a conspicuous place in the PE premises for seven calendar days certified by the BAC Secretariat head and posted continuously in PhilGEPS, the PE website, and the foreign/financing-institution website when applicable, for seven calendar days from advertisement. | IRR §50.3.1 | Per-mode posting-period and multi-channel evidence gate. | Premise posting certification, channel start/end, advertisement date. | Verified. |
| CMP-111F | Limited Source Bidding, Two Failed Biddings, Small Value Procurement above ₱200,000, and NGO Participation must be posted in PhilGEPS, the PE website, and the PE premises for at least three calendar days; other modes are not required to publish under §50.3. | IRR §50.3.2 | Mode-scoped three-day posting rule. | Mode, channels, start/end, amount where applicable. | Verified. |
| CMP-111G | Where the PE allows electronic bid submission pending mandatory PhilGEPS electronic bidding, the highest official managing the agency's IT system must issue the prescribed Certification and submit it to the GPPB-TSO before posting the Bidding Documents that allow electronic submission and receipt. | IRR §50.3.3 | Prerequisite certification gate before electronic-submission posting. | IT certification, GPPB-TSO submission receipt, posting date. | Verified. |
| CMP-112 | Eligibility and bid submission must meet prescribed requirements and time limits. | IRR §§52, 54–58; applicable GPPB documentary requirements | Conditional gates, checklist, deadline and receipt logic. | Bid envelopes/artifacts, receipt timestamps, checklist, deferment evidence. | Verified direction; document set requires per-mode review. |
| CMP-112F | A bid must include the sworn relation affidavit and ultimate beneficial-ownership disclosure; noncompliance is automatically disqualifying, and a third-degree relation disqualifies notwithstanding official self-inhibition. | IRR §§81.1, 82.1–82.3; RA 12009 §§81–82 | Conditional hard gate and evidence check. | Affidavit, ownership declaration/registry evidence, related-party determination. | Verified. |
| CMP-112G | Eligibility documents are prescribed by category: valid and updated PhilGEPS Platinum Certificate of Registration; statement of all ongoing government and private contracts; SLCC; NFCC computation (or a committed Line of Credit of at least 10% of ABC for Goods, confirmed or authenticated by a local bank when issued by a foreign bank); and, for Consulting Services, nationality and registration of the professionals who will actually perform the services with curriculum vitae. | IRR §§52.1–52.1(d), 54.2 | Category-scoped eligibility checklist with NFCC/LOC calculation. | Registration, ongoing-contracts statement, SLCC, NFCC inputs, LOC instrument, professional registration and CVs. | Verified. |
| CMP-112H | Goods and Infrastructure eligibility requires an SLCC similar to the project, adjusted to current prices using PSA consumer price indices, of at least 50% of ABC, completed within ten years from bid submission unless the ITB states a shorter period; the PE may accept a combination of contracts totaling at least 50% of ABC with at least one similar contract of at least 25% and other contracts of at least 25% of ABC. | IRR §§52.4.1.3, 52.4.2.4 | SLCC value/age calculation and combination-schedule check. | SLCC contracts, values, index basis, completion dates, combination allocation, PE clarification. | Verified. |
| CMP-112I | NFCC must be at least equal to the ABC using the IRR formula, with domestic current assets and liabilities from the latest AFS submitted to BIR and foreign values from AFS prepared under IFRS; for Infrastructure Projects any different formula requires PE recommendation submitted to GPPB for consideration. | IRR §§52.4.1.4, 52.4.2.6 | Typed NFCC formula and comparison gate. | AFS source and period, current assets/liabilities, ongoing-contract deductions, computed NFCC, ABC comparison, GPPB submission where used. | Verified. |
| CMP-112J | Filipino ownership or interest of at least 60% applies to partnerships, corporations, and joint ventures for Goods and Infrastructure Projects; Infrastructure JVs below 60% may be eligible only under the IRR §52.4.2.1(e) conditions with Filipino interest not less than 25%. | IRR §§52.4.1.1, 52.4.2.1 | Ownership-share calculation and exception gate. | Ownership schedule, JVA contributions, primary-purpose statement, exception basis. | Verified. |
| CMP-112K | Joint-venture bidders must submit a valid JVA for Goods and Consulting Services or notarized partner statements, failing which failure to enter into a JV on award is ground for forfeiture of bid security; Infrastructure JV bidders must submit a JVA per RA 4566 and its IRR and hold a PCAB license. | IRR §§52.2, 52.4.2.3 | JV documentation gate and forfeiture link. | JVA or notarized statements, notarization, PCAB license, partner registrations. | Verified. |
| CMP-112L | Foreign-bidder documents must be in English or accompanied by an English translation by the foreign government agency, an authorized translator, or a registered translator in the bidder's country, authenticated by the appropriate Philippine foreign service establishment or equivalent office; Apostille Convention countries use an apostille except those identified by the DFA as still requiring legalization. | IRR §52.3 | Language/authentication checklist. | Language, translation, translator authority, authentication/apostille, DFA legalization list reference. | Verified. |
| CMP-112M | The PE may reassess a bidder's qualifications at any stage on reasonable grounds of misrepresentation or changed capability; false information or changes affecting eligibility disqualify the bidder from award or contract implementation. | IRR §52.6 | Standing reassessment trigger and disqualification consequence. | Trigger, grounds, reassessment result, disqualification record. | Verified. |
| CMP-112A | Maximum posting-to-bid periods are 45 calendar days for Goods; 50 for Infrastructure Projects with ABC at or below ₱50,000,000 and 65 above it; and 75 for Consulting Services. | IRR §54.5 | Category/ABC deadline calculator. | Last posting day, category, ABC, computed deadline. | Verified. |
| CMP-112B | A bid or eligibility requirement submitted after the deadline must not be accepted, and the late bidder's name, representative, and time must be recorded in the minutes. | IRR §54.6 | Hard rejection and minutes-entry rule. | Late receipt, minute entry, rejection evidence. | Verified. |
| CMP-112C | The BAC Secretariat must notify all bidders whose bids were received within seven calendar days from bid opening. | IRR §54.7 | Deadline and delivery-evidence task. | Bidder list, opening date/time, notice artifact, delivery evidence. | Verified. |
| CMP-112D | For competitive modes, a bid without the required bid security in the prescribed form and amount is automatically disqualified; minimum security is 2% of ABC for cash/check and 5% for bank instrument or callable-on-demand surety bond. | IRR §§56.1–56.3 | Conditional hard gate and amount calculator. | ABC, security artifact, form, issuer, amount, validity, disqualification record. | Verified. |
| CMP-112E | Bid and bid-security validity must be stated in the Bidding Documents and may not exceed 120 calendar days from bid opening. | IRR §§57.1–57.2 | Validity calculator and extension-request task. | Opening date, stated validity, expiry, extension request/response. | Verified. |
| CMP-113 | ABC is a ceiling for bid prices. | IRR §60 | Calculation rule; disqualify or block inconsistent bid price. | ABC version, bid amount, comparison trace. | Verified. |
| CMP-113A | Opening occurs immediately after the deadline; opening mode follows the award criterion; postponement requires securing bids and a Notice of Postponement through PhilGEPS and the PE website. | IRR §§58.1–58.4 | Opening-state and postponement/publication gate. | Deadline, opening event, criterion, envelope state, postponement notice and channels. | Verified. |
| CMP-113B | Preliminary examination uses the prescribed checklist and non-discretionary pass/fail criterion; missing, incomplete, or patently insufficient requirements fail, as does a second-envelope total bid price above ABC. | IRR §59 | Checklist and deterministic outcome rule. | Checklist version, artifacts, pass/fail reason, price comparison. | Verified. |
| CMP-114 | Evaluation and post-qualification must use the approved method and records. | IRR §§59–64 | Scoring and post-qualification gates; independent evaluator records. | Criteria, scores, corrections, post-qualification result, BAC resolution. | Verified direction; exact formulas require approved forms/manual review. |
| CMP-114A | Post-qualification documents are due within five calendar days from receipt of notice, and the process completes within 12 calendar days from the relevant determination/negotiation trigger unless the reviewed HoPE extension applies within the IRR §63.8 aggregate caps. | IRR §§63.2, 63.8 | Deadline and extension gate. | Notice/receipt, submission, determination/negotiation trigger, extension decision. | Verified. |
| CMP-114B | Bids are submitted in two sealed envelopes (or equivalent electronic form) submitted simultaneously: the technical envelope carries eligibility documents and technical components, and the financial envelope carries financial documents. | IRR §§54.1–54.2, 54.4 | Envelope-structure validation at receipt and opening. | Envelope set, simultaneous-submission attestation, envelope contents. | Verified. |
| CMP-114C | The Omnibus Sworn Statement executed by the bidder or its duly authorized representative must contain the IRR §54.3(a)–(i) declarations, including the notarized Special Power of Attorney, Board or Partnership Resolution, or Secretary's Certificate as applicable, and the disclosure compliance with IRR §§81–82. | IRR §54.3 | Structured declaration checklist with authority-document link. | Omnibus Sworn Statement, authority document and notarization, item-by-item attestation. | Verified. |
| CMP-114D | A modification must be made before the deadline, must not retrieve the original bid, and must be submitted as a new equally sealed and identified bid marked "modification" and stamped received; modifications after the deadline are not considered and are returned unopened. | IRR §55.1 | Modification handling rule with original retention. | Original bid, modification bid, marking, receipt stamp, disposition if late. | Verified. |
| CMP-114E | A bidder may withdraw by letter before the deadline; withdrawal after the deadline is subject to sanctions under the IRR, and a bidder that withdraws may no longer bid on the same contract directly or indirectly. | IRR §55.2 | Withdrawal classification and re-bid block. | Withdrawal letter, receipt stamp, timing, disqualification from same contract, sanction record. | Verified. |
| CMP-114F | Failure of bidding in Competitive Bidding, Limited Source Bidding, or Competitive Dialogue occurs only when no bids are received, all bidders are declared ineligible, no bid qualifies as SCRB, MARB, LCRB, HRRB, or MEARB, or the winning bidder refuses the award without justifiable cause; for other modes the BAC may also declare failure of procurement or failed negotiations. | IRR §64.1 | Deterministic failure-determination rule with the other-modes BAC option. | Bid counts, eligibility results, qualifying-bid results, refusal record, BAC declaration. | Verified. |
| CMP-114G | After a failure, the End-User or Implementing Unit conducts a mandatory review of the Bidding Documents terms, conditions, specifications, and cost estimates; the BAC revises them, adjusts the ABC where necessary subject to required approvals, and conducts a re-bidding with re-publishing under IRR §50. | IRR §§64.2–64.3 | Mandatory review and re-bid workflow with republication. | Review record, revised documents, ABC adjustment approval, re-publication evidence. | Verified. |
| CMP-114H | All bidders who initially responded and were declared eligible or short-listed in the previous bidding must be allowed to submit new bids, and the BAC must observe the same process and set new periods under the same rules. | IRR §64.4 | Re-bid eligibility carry-over and period reset. | Prior bidder list, eligibility/short-list status, new invitation, new periods. | Verified. |
| CMP-114I | A second failure of bidding permits the PE to resort to negotiated procurement under IRR §35. | IRR §64.5 | Failure-count gate for negotiated procurement. | Failure history, negotiated-procurement decision. | Verified. |
| CMP-114J | A single calculated, rated, economically advantageous, or advantageous responsive bid may be considered for award only under the IRR §65(a)–(c) circumstances, and in all instances the ABC must reflect the most advantageous prevailing price for the Government. | IRR §65 | Single-bid scenario classifier and ABC prevailing-price check. | Applicability scenario, eligibility counts, responsive result, ABC/market comparison. | Verified. |
| CMP-115 | Failed bidder notice is due within three calendar days of award recommendation. | IRR §66.1 | Deadline from issuance of recommendation resolution. | Resolution, recipient list, notice artifacts, delivery evidence. | Verified. |
| CMP-116 | HoPE award decision is due within ten calendar days from BAC determination and recommendation. | IRR §66.2 | Deadline calculator and decision gate. | BAC resolution, notice/receipt, HoPE decision. | Verified. |
| CMP-117 | Disapproval must be on valid, reasonable, and justifiable grounds expressed in writing. | IRR §66.3; IRR §70 | Written-grounds hard gate for disapproval. | Disapproval document, grounds, copy to BAC and bidder. | Verified. |
| CMP-117C | The HoPE reserves the right to reject any and all bids, declare a failure of bidding, or not award the contract where there is prima facie evidence of collusion involving PE officers, the BAC, bidders, or a third party; where the BAC failed to comply with applicable law or prescribed bidding procedures; or, for justifiable and reasonable grounds where award would not redound to the benefit of the Government, because physical and economic conditions have significantly changed so the project is no longer economically, financially, or technically feasible, the project is no longer necessary, or the source of funds has been withheld or reduced through no fault of the PE. | IRR §70 | Structured reservation ground with evidence requirement. | Ground code, prima facie/collusion findings, BAC compliance finding, feasibility/necessity/funding determination, HoPE determination. | Verified. |
| CMP-117A | A request for reconsideration after disapproval or inaction is filed with the HoPE within three calendar days of receipt of the notice of disapproval or the explanation for inaction, and the HoPE resolves it with finality within seven calendar days from filing and furnishes the bidder a copy immediately upon promulgation; it never stays or delays the bidding process and must be resolved before any award. | IRR §66.4 | Separate timer, decision deadline, copy delivery, and pre-award resolution gate. | Disapproval/inaction notice, filing receipt, resolution, promulgation and delivery timestamps, award-sequence check. | Verified. |
| CMP-117B | On approval, the HoPE or duly authorized representative shall immediately issue the Notice of Award to the bidder with the LCRB, MEARB, MARB, HRRB, SCRB, SEARB, SARB, or SRRB. | IRR §66.3 | Award-issuance action with recipient verification. | Approval record, NOA artifact, recipient, issuance timestamp. | Verified. |
| CMP-118 | Winning bidder conditions and contract execution are tracked before award completion. | IRR §§66.5, 68–71 | Condition checklist and contract/performance-security evidence gate. | NOA, JVA/license where applicable, performance security, executed contract. | Verified direction; document-specific requirements require review. |
| CMP-118A | For Goods, an amendment to order must stay within the applicable IRR §71.1.1 conditions and written agreement; an increase must not exceed 10% of the original contract price unless the HoPE approves an increase up to 20%. | IRR §71.1.1 | Conditional amount/authority gate and written contract-modification requirement. | Order, grounds, price/time impact, HoPE approval, amended contract. | Verified. |
| CMP-118B | Goods liquidated damages are one-tenth of one percent of the cost of delayed goods per day until delivery and acceptance; at 10% of total contract price, the PE may rescind and impose sanctions. | IRR §71.1.4 | Formula and threshold gate. | Contract price, delayed amount, delivery/extension dates, daily calculation, cumulative amount. | Verified. |
| CMP-118B1 | For Infrastructure Projects, cumulative positive Variation Orders are capped at 10% of original contract price except exceptional HoPE authorization up to 20%; unapproved emergency work is capped at 5% of adjusted original contract price and payment requires approval. | IRR §71.2.1 | Cumulative percentage, authority, and payment gate. | Original/adjusted price, variation history, approval, work-start and payment events. | Verified. |
| CMP-118C | Goods warranty is at least three months for Expendable Supplies and one year for Non-expendable Supplies after acceptance, with the prescribed 1%–5% retention or equivalent guarantee. | IRR §90.1 | Warranty-period and security gate. | Acceptance, classification, warranty period, retention/guarantee, release condition. | Verified. |
| CMP-118D | Performance security is at least 5% of total contract price for Goods/Consulting Services and 10% for Infrastructure Projects in cash/check form, or 30% in bank-draft/guarantee/LC/callable-surety form; it must precede contract signing and remain effective through final acceptance, with release blocked by claims. | IRR §§68.1, 68.4–68.7 | Form/amount/timing/validity/release gates. | Contract price, category, instrument, amount, dates, final acceptance, claims. | Verified. |
| CMP-118E | Sectors registered under IRR §76 may post a Performance Securing Declaration in lieu of performance security; the PSD must state blacklisting for one year on first offense or two years on a prior similar offense, and an unnotarized PSD may be accepted only subject to a notarized PSD before any payment unless replaced by a performance security. | IRR §§68.2–68.3 | PSD substitution path with notarization-before-payment gate. | Sector registration, PSD instrument, notarization, blacklisting undertaking, payment linkage. | Verified. |
| CMP-118F | For Infrastructure Projects, the winning bidder must post additional performance security to cover any cumulative increase of more than 10% over the original contract value from amendments to order, change orders, extra work orders, or supplemental agreements, and must extend the validity of the performance security to cover approved contract time extensions. | IRR §68.7 | Cumulative-increase trigger and validity-extension gate. | Original value, cumulative increases, additional security, extension approvals, security validity. | Verified. |
| CMP-118G | The PE may proportionally reduce the original performance security for a reduction in contract value, or for usable and accepted partially completed Infrastructure works that will not affect structural integrity; any single reduction more than 10% is permitted only while the aggregate of reductions does not exceed 50% of the performance security. | IRR §68.8 | Reduction authorization with aggregate cap. | Reduction grounds, accepted works, structural assessment, per-reduction percentage, aggregate percentage. | Verified. |
| CMP-118H | For Infrastructure Projects, the contractor assumes full responsibility for damage or destruction of the works (except force majeure) and for the safety, protection, security, and convenience of its personnel, third parties, and the public from construction start to final acceptance, and must repair defects at its own expense within 90 calendar days of the HoPE's repair order; on failure or refusal the PE may repair and recover the full cost. | IRR §§90.2.1–90.2.2 | Warranty-obligation tracker with repair-order deadline. | Commencement, final acceptance, defect notices, HoPE order, repair completion, cost recovery. | Verified. |
| CMP-118I | Warranty security for Infrastructure Projects must be in the PE-chosen form at not less than 5% of the total contract price for cash or bank Letter of Credit (confirmed or authenticated by a local bank when issued by a foreign bank), 10% for a bank guarantee confirmed by a bank, or 30% for a surety bond callable upon demand issued by GSIS or an Insurance Commission–certified surety. | IRR §90.2.3(c) | Warranty-security form and amount gate. | Total contract price, form, issuer certification, amount, issuance date. | Verified. |
| CMP-118J | Liability for structural defects or failures runs from final acceptance for 15 years for permanent structures (including National Building Code types 4 and 5, steel, iron, or concrete structures compliant with structural codes), 5 years for semi-permanent structures (NBC types 1–3, concrete or asphalt roads, river control, drainage, irrigation canals, river landings, deep wells, rock causeways, pedestrian overpasses), and 2 years for other structures (Bailey and wooden bridges, shallow wells, spring developments). | IRR §90.2.3(b) | Structure-class warranty-period calculator. | Structure classification, acceptance date, computed expiry, liability party. | Verified. |
| CMP-118K | Where structural defects or failures occur during the applicable warranty period, the PE may undertake restoration or reconstruction and recover the full cost from the parties found liable, without prejudice to administrative, civil, or criminal charges and forfeiture of posted warranty securities. | IRR §90.2.3(d) | Recovery and forfeiture workflow. | Defect finding, restoration cost, demand, liability allocation, charge referral, forfeiture. | Verified. |
| CMP-118L | Bid prices are fixed and not subject to price escalation during contract implementation; price escalation for extraordinary increases in Infrastructure component costs requires prior GPPB approval, is authorized only on a no-loss, no-gain basis when the cost increase exceeds 10% of the unit price of work items measured against PSA prevailing price indices, and is governed by the GPPB-prescribed formula. | IRR §89 | Fixed-price rule with GPPB-approved escalation path. | Awarded price, PSA index values and dates, computed increase percentage, GPPB approval, formula version. | Verified; GPPB escalation formula requires review. |
| CMP-119 | Opening-to-award period and non-working-day adjustment are controlled. | IRR §67 | Calendar deadline calculator with traceability. | Trigger event, calendar version, computed due date, adjustment/tolling trace. | Verified. |
| CMP-119A | The period from bid opening to award must not exceed 60 calendar days, and may be adjusted by GPPB up to a maximum of 90 calendar days; procurement personnel remain on jury-duty assignment until the HoPE issues the Notice of Award. | IRR §§67.1–67.2 | Aggregate deadline calculator with GPPB-adjusted ceiling. | Opening date, award date, applied period, GPPB adjustment evidence, assignment record. | Verified. |
| CMP-119B | A deadline falling on a Saturday, Sunday, legal or special non-working holiday, or other non-working day duly declared moves to the next working day; the BAC may instead hold an activity on such a day for expediency with BAC, Secretariat, and TWG availability and the §51.5.3 supplemental or bid bulletin issued and posted. | IRR §67.3 | Working-day roll-forward with documented alternative-date path. | Declared non-working calendar, original due date, rolled due date, BAC determination, availability, bulletin. | Verified. |
| CMP-119C | The period of action is tolled for Early Procurement Activities pending approval and effectivity of the GAA, corporate budget, appropriations ordinance, or loan agreement, and during a BAC-determined suspension of procurement activities; tolling resumes when the basis ceases to exist and grants only the balance of the remaining period, which does not restart. | IRR §67.4 | Tolling ledger with remaining-balance computation. | Toll start/end basis, effective instrument, elapsed and remaining days, resumption computation. | Verified. |
| CMP-120 | Request for reconsideration is due within three calendar days and decided by BAC within seven. | IRR §83.1 | Deadline calculator; rejection of late filing under the rule. | Notice/notification, filing receipt, BAC decision, envelope hold. | Verified. |
| CMP-121 | Protest follows reconsideration, verified position paper, electronic fee, and seven-day HoPE decision. | IRR §§83.2–83.4 | Hard gate on filing completeness and decision deadline. | Prior MR, verified paper, fee receipt, filing, decision. | Verified; detailed GPPB electronic-filing procedure requires review. |
| CMP-121A | The non-refundable protest fee is computed from the IRR §83.2 schedule by ABC: 0.75% of ABC up to ₱50,000,000; ₱500,000 above ₱50,000,000 up to ₱100,000,000; 0.5% of ABC above ₱100,000,000 up to ₱500,000,000; ₱2,500,000 above ₱500,000,000 up to ₱1,000,000,000; 0.25% of ABC above ₱1,000,000,000 up to ₱2,000,000,000; ₱5,000,000 above ₱2,000,000,000. | IRR §83.2 | Deterministic fee calculator with range-boundary tests and electronic-payment evidence. | ABC, applicable range, computed fee, electronic payment receipt. | Verified. |
| CMP-121B | The verified position paper must contain the IRR §83.3(a)–(h) information and be verified by affidavit; an unverified position paper is treated as unsigned, has no legal effect, and results in outright dismissal. The bidder must also certify under oath the no-other-proceedings statements and the five-day duty to report a later-filed similar action or claim, and noncompliance is not curable by mere amendment. | IRR §83.3 | Completeness checklist with dismissal consequence and non-curable validation failures. | Position paper contents, affidavit, oath certifications, other-proceedings disclosure, five-day report record. | Verified. |
| CMP-122 | A protest does not stay the process but must be resolved before award. | IRR §86 | Conditional award gate and process-status control. | Protest status, decision, award event. | Verified. |
| CMP-122A | LGU protest decisions are final and executory at or below ₱1,250,000 for Goods, ₱12,500,000 for Infrastructure Projects, and ₱2,500,000 for Consulting Services; above those thresholds, finality runs 10 calendar days after receipt unless perfected appeal is made. | IRR §§84.3, 85 | Category/ABC finality calculator and court-action deadline. | Category, ABC, decision, receipt, appeal record. | Verified. |
| CMP-123 | Suspension, blacklisting, and perpetual disqualification follow their grounds and decision procedures. | IRR §§98–105 | Case workflow, deadlines, evidence, scope, and portal-posting task. | Notice, hearing record, BAC resolution, HoPE decision, MR, posting receipt. | Verified direction; exact penalty and deadline logic requires per-case review. |
| CMP-123A | Suspension is one year for a first offense and two years for a second; blacklisting is one year for a first offense and two years for a second; perpetual disqualification applies on the IRR §101 grounds. | IRR §§99–101 | Ground and penalty-period table. | Offense history, ground, penalty class, decision. | Verified. |
| CMP-123B | A HoPE sanction decision becomes final after seven days from receipt if no request for reconsideration is filed. | IRR §104.1(b) | Finality deadline. | Decision, receipt, MR status. | Verified. |
| CMP-123C | The §99 suspension grounds are enumerated: unjustified delay of eligibility screening, opening, evaluation, post-evaluation, or award; refusal to clarify or validate its bid in writing within seven calendar days of a post-qualification clarification request; withdrawing a bid or refusing the award or contract without justifiable cause after HoPE approval; failure to post performance security on time; refusal to accept a renewal notice or failure to submit updated renewal documents for regular and recurring services; delivery of unsatisfactory or inferior goods; poor consultant performance as defined in §99.2(b)(i)–(iv); and poor winning-bidder performance including negative slippage of 15% and above within the critical path caused entirely by the bidder's fault or negligence, or non-compliance with approved specifications from the bidder's fault or negligence. Bid or performance security is also forfeited. | IRR §99.1–99.2 | Ground-classification and metric checks with forfeiture link. | Ground code, computed slippage, clarification request/response dates, security forfeiture record. | Verified. |
| CMP-123D | The §100 blacklisting grounds are enumerated: commission of three or more acts punishable by suspension; use of force, fraudulent machinations, coercion, undue influence, or pressure on BAC members or PE officers; prima facie corrupt, fraudulent, collusive, or coercive practices, use of forged documents, or use of adulterated materials or methods contrary to science or trade; collusion and malicious multiple submission creating false competition; agreement with other bidders to refrain or withdraw; failure to faithfully disclose relationships under IRR §81; false beneficial-ownership entries; use of another's name; false or falsified eligibility requirements and bids; unauthorized access to bid contents before opening; documented unsolicited attempts to unduly influence the outcome; and schemes that stifle or suppress procurement activity; plus assignment, subcontracting, or key-personnel substitution without prior written approval, deliberate abandonment or non-performance, and termination for default. Bid or performance security is also forfeited. | IRR §100.1–100.3 | Ground-classification checklist with forfeiture link. | Ground code, supporting facts, evidence links, security forfeiture record. | Verified. |
| CMP-123E | Perpetual disqualification applies on a third blacklisting offense imposed by the same PE, or a combination of three blacklisting violations imposed by the PE and other PEs as posted on the GPPB portal; or failure to repair noted defects or damage to an Infrastructure Project due to inferior materials within 90 calendar days of the HoPE's repair order. Bid or performance security is also forfeited. | IRR §101 | Repeat-offense counter with GPPB portal cross-check and repair-order deadline. | Offense history, GPPB portal entries, repair order date, computed 90-day expiry. | Verified. |
| CMP-123F | During the pendency of blacklisting proceedings, the bidder is automatically suspended from the current procurement activity of the PE concerned; the notice of commencement serves as the suspension order, and dismissal of the case automatically lifts the suspension. | IRR §102 | Automatic pending-suspension state with linked notice. | Commencement notice, suspension status, case outcome, automatic lifting. | Verified. |
| CMP-123G | Before a blacklisting order issues, the erring bidder may participate in other government procurements except in the PE where it is suspended; a blacklisting order issued before the Notice of Award disqualifies the bidder from award and the contract must be awarded to another bidder; a suspension or blacklisting order does not prejudice contracts awarded earlier unless the offenses are connected to that contract; and a penalty imposed during the pendency of a previous penalty subsumes the previous one. | IRR §103 | Cross-PE participation state, award-disqualification gate, and subsumption rule. | PE scope, award event date, order date, prior contracts, subsumption record. | Verified. |
| CMP-123H | Suspension and blacklisting extend to any entity in which the suspended or blacklisted person holds a controlling interest and to any beneficial owner of the suspended entity or blacklisted person, and to the related persons and entities enumerated in IRR §105(a)–(e), including a stockholder holding at least 20% of shares together with relatives up to the third civil degree and the chairperson and president of corporations determined to hold the same controlling interest in previously blacklisted corporations. | IRR §105 | Related-party expansion with ownership threshold calculation. | Ownership records, controlling-interest findings, third-degree relationship records, expanded entity list. | Verified. |
| CMP-123I | Upon a determination of grounds for enforcement and forfeiture of a Bid Securing Declaration Form or Performance Securing Declaration Form, the HoPE shall immediately issue the blacklisting or suspension order upon the BAC recommendation, and a request for reconsideration may be filed with the HoPE within three calendar days from receipt of the order. | IRR §105.1 | Immediate-order path with three-day reconsideration window. | BSDF/PSDF enforcement finding, BAC recommendation, order, receipt, MR. | Verified. |
| CMP-124 | A blacklisting order must be uploaded to the GPPB portal within seven calendar days. | IRR §104.1(d) | Deadline and publication evidence gate. | Order, portal receipt, reconciliation. | Verified. |
| CMP-124A | The BAC or the HoPE may commence corrective-measure, suspension, or blacklisting proceedings motu proprio at any stage of procurement or contract implementation upon a prima facie determination of a §99, §100, or §101 ground, and any bidder or prospective bidder may initiate the proceedings; the PE may require a reasonable fee for initiating them. | IRR §104 preamble | Case-initiation source record and fee capture. | Initiator, initiation basis, prima facie finding, fee assessment and receipt. | Verified. |
| CMP-124B | On initiation, the BAC must immediately notify the bidder in writing of the opportunity to show cause and of the option to request a hearing before the BAC, and may issue a resolution recommending penalties to the HoPE only on substantial evidence; the HoPE must decide within 15 days from receipt of the BAC resolution and otherwise dismiss the action. | IRR §104.1(a)–(b) | Notice-and-hearing workflow with substantial-evidence and 15-day decision gates. | Notice, hearing request, record, BAC resolution, receipt, HoPE decision. | Verified. |
| CMP-124C | A request for reconsideration of a sanction decision may be filed within three calendar days from receipt of the decision notice, and the HoPE must resolve it within seven calendar days from filing and furnish a copy immediately upon promulgation; only one request for reconsideration is allowed. | IRR §104.1(c) | Single-request reconsideration tracker with resolution deadline. | Decision notice, receipt, MR count, filing, resolution, promulgation and delivery timestamps. | Verified. |
| CMP-124D | Contract disputes must first be resolved amicably by mutual consultation or agreement; parties are encouraged to select the most expeditious mode of dispute resolution and may contractually provide for arbitration or agree in writing to alternative modes; unresolved disputes may be submitted to arbitration or ADR under RA 9285, except disputes within the competence and jurisdiction of CIAC, which must be referred there. | IRR §§88.1–88.3 | Dispute pathway with amicable-settlement stage and CIAC routing. | Dispute record, amicable attempt, agreed mode, ADR referral, CIAC referral. | Verified. |
| CMP-124E | No court other than the Supreme Court may issue a temporary restraining order, preliminary injunction, or preliminary mandatory injunction to restrain, prohibit, or compel procurement, bidding, awarding, contract commencement, execution, implementation, operation, termination, or rescission, or any other lawful activity necessary for the contract; the prohibition applies to all cases including bidder claims, except in cases of extreme urgency involving a constitutional issue where the applicant must file a bond fixed by the court accruing to the government if relief is denied. | IRR §87 | Legal-notice flag and urgency exception record. | Court notice, order type, issuing court, urgency basis, bond order. | Verified. |
| CMP-124F | All contracts executed under the Act and IRR must contain a liquidated-damages provision of at least one-tenth of one percent of the cost of the unperformed portion per day of delay; once the cumulative liquidated damages reach 10% of the contract price, the PE may terminate the contract without prejudice to other remedies. | IRR §97 | Contract-clause presence check and cumulative threshold gate. | Contract clause, unperformed portion value, daily accrual, cumulative amount, termination notice. | Verified. |
| CMP-125 | PhilGEPS is the primary portal and its full use is mandatory. | IRR §§20.1.1, 20.3.1 | Required-channel publication adapter/manual control. | PhilGEPS receipt/reference, timestamp, exact artifact reconciliation. | Verified. |
| CMP-125A | The platform MUST support the IRR §20.2 PhilGEPS feature boundary used by the procurement workflow: Electronic Bulletin Board, Electronic Catalogue, Virtual Store, Electronic Marketplace, Electronic Bidding, Electronic Small Value Procurement, Electronic Reverse Auction, Electronic Payment, and the Registry of Suppliers, Manufacturers, Distributors, Contractors, and Consultants. | IRR §20.2.1–20.2.9 | Feature-to-workflow mapping with a recorded capability status per feature. | Feature identifier, platform interaction type (automated/manual/out-of-scope), evidence reference. | Verified; per-feature implementation status is a deployment decision. |
| CMP-125B | The platform MUST support the IRR §23 interoperability targets as reference lookups where the PE relies on them: GoP-OMR, DTI business registration and ongoing projects, CIAP CPES and PCAB licensing, LGU permits and licenses, BIR tax returns and clearance, SEC and CDA registrations and beneficial-ownership information, the Insurance Commission list of callable-on-demand surety companies, and PE-published project, contract, and bidder-performance status. | IRR §23(a)–(i) | External-system reference catalogue with provenance and failure handling. | Source system, record reference, retrieval timestamp, staleness/failure flag. | Verified. |
| CMP-125C | The platform MUST provide an audit trail for electronic transactions and permit verification of the security and integrity of the system at any time, and MUST track the performance of suppliers, contractors, and consultants against delivery schedules and other performance indicators as well as the settlement of PE obligations to them. | IRR §§22.1, 22.2.1–22.2.2 | Immutable transaction audit trail plus performance-tracking records. | Audit events, verification report, supplier performance indicators, PE settlement records. | Verified. |
| CMP-126 | Documents, transactions, security, integrity, and confidentiality require auditability. | IRR §22; RA 12009 §3 | Immutable audit events, evidence, access controls. | Audit log, hash chain/anchoring, access reports. | Verified direction. |
| CMP-127 | Electronic signatures must meet legal recognition and certification conditions. | RA 8792 §8; EO 810; GPPB Res. 16-2019 | Signature-method matrix, provider validation, evidence bundle. | Certificate/credential, signature, hash, validation result, PE internal rule. | Legal review required for each document class. |
| CMP-128 | IRR amendments apply according to their effectivity boundary. | IRR §112.2 | Immutable snapshot at reviewed commencement event. | Snapshot, advertisement/invitation, effective-date evidence. | Verified. |
| CMP-129 | The three-year standard-form transition must be represented. | IRR §113.3 | Applicability calculator and policy decision. | Form approval date, procurement date, transition status. | Verified; per-case interpretation review required. |
| CMP-129A | Standard forms for procurement must be formulated and approved by the GPPB not later than 90 days upon approval of the IRR; until the GPPB publishes them, the platform must not treat an unpublished form as a controlled standard form. | IRR §112.3 | Standard-form publication status gate. | IRR approval date, 90-day computation, GPPB publication evidence, form version list. | Verified. |
| CMP-129B | Prior to the IRR's effectivity, RA 9184 and its IRR remain in force for continued implementation; the platform must support a verified pre-effectivity rule pack for cases whose advertisement or invitation was issued before the IRR took effect. | IRR §§113.1–113.2 | Pre-effectivity rule-pack branch. | Advertisement/invitation date, effectivity date, selected rule pack, Fit-for-Purpose and Proportionality application. | Verified. |
| CMP-129C | RA 9184 and Commonwealth Act No. 138 are repealed, and inconsistent laws, ordinances, executive orders, proclamations, issuances, rules, and regulations are repealed, amended, or modified accordingly; the IRR takes effect 15 calendar days after publication in the Official Gazette or in two newspapers of general nationwide circulation and upon filing of three certified copies with the University of the Philippines Law Center. | IRR §§115–117 | Baseline effective-date control and repeal record. | Publication evidence, filing evidence, computed effectivity date, superseded-instrument register. | Verified. |
| CMP-130 | The unofficial compiled IRR is withdrawn. | GPPB Public Advisory No. 09-2026 | Source selection hard gate for legal-source activation. | Official IRR source and advisory evidence. | Verified. |

## 11. PhilGEPS interoperability

### CMP-140 — Adapter boundary

PhilGEPS integration MUST be isolated behind an adapter with capability discovery, explicit error mapping, idempotent publication intents, receipt reconciliation, and manual fallback.

### CMP-141 — No false confirmation

A successful API call without a verifiable official receipt or reference MUST NOT by itself prove legal publication. The control's evidence standard is **operations/legal review required**.

### CMP-142 — Identity and credentials

PhilGEPS credentials MUST be stored outside application source, rotated under dual control, and never embedded in document metadata or audit exports.

### CMP-143 — Electronic bidding

If the platform receives bids through PhilGEPS, it MUST treat the portal receipt and submitted artifacts as the authoritative external evidence. It MUST not claim that its internal copy proves portal receipt without reconciliation.

### CMP-144 — mPhilGEPS and current channels

The deployment MUST use the officially prescribed current electronic channels. “PhilGEPS,” “mPhilGEPS,” and related transition instructions MUST be versioned as operational source requirements. The exact deployment channel is **legal/operations review required**.

## 12. Calculations

### CMP-150 — Typed inputs and units

Every regulated calculation MUST use typed values, explicit units, precision, and rounding mode. Currency MUST use ISO 4217 code `PHP` and decimal arithmetic.

### CMP-151 — Formula provenance

A calculation formula MUST link to the statutory or issued source, the controlled parameter set, and test examples. Formula text embedded only in a UI or document is insufficient.

### CMP-152 — Reproducibility

Given the same inputs, rule version, calendar version, and parameter set, a calculation MUST produce the same result. The system MUST retain an input-to-result trace.

### CMP-153 — Human override prohibited

A user MUST NOT directly replace a regulated calculated result with an arbitrary value. Correction MUST occur by changing a governed input, recording a legal exception where authorized, or creating a new version through the governed correction process.

### CMP-154 — Fee schedules

Protest and other fee calculations MUST use the schedule and amount in the applicable pinned source. The platform MUST retain the cited schedule snapshot with the computation.

## 13. Deadlines and tolling

### CMP-160 — Trigger event registry

Each deadline rule MUST define a canonical trigger event, including start and end semantics and evidentiary prerequisites.

### CMP-161 — Inclusive/exclusive semantics

A rule MUST explicitly state whether the first day and last day are included and whether the period is measured from receipt, publication, issuance, or another event.

### CMP-162 — Calendar rule

A deadline MUST identify whether it uses calendar days, working days, or another basis. Non-working-day adjustment MUST follow the pinned rule and a versioned holiday calendar.

### CMP-163 — Tolling

Only a reviewed rule may suspend or toll a clock. The event record MUST include authority and effective interval. A generic “pause” action MUST NOT be available.

### CMP-164 — Protest and reconsideration

The system MUST distinguish the IRR §83 reconsideration deadline from the IRR §66.4 award-recommendation reconsideration deadline. They MUST NOT share an unlabeled “reconsideration” timer.

### CMP-165 — Deadline reproduction

A compliance report MUST show the trigger, source, rule, parameters, calendar, adjustments, final due date, and any resulting state change.

## 14. Compliance reporting

### CMP-170 — Requirement coverage report

The system MUST report every active requirement, its rule and controls, owning reviewer, test coverage, and instances evaluated.

### CMP-171 — Control exception report

The system MUST report blocked transitions, legal-review items, expired exceptions, late evidence, failed publications, overdue acknowledgements, and configuration conflicts.

### CMP-172 — Provenance report

For every report assertion, the system MUST expose the source and evidence chain. A dashboard summary MUST be linkable to underlying records.

### CMP-173 — Reproducible report snapshot

A generated compliance report MUST record the time, subject population, rule-pack versions, query/filter parameters, and artifact hash.

## 15. Legal-change workflow

### CMP-180 — Change intake

A legal change MUST be recorded as a proposal identifying the new or amended source, affected provisions, proposed requirement changes, affected workflows/templates/calculations, and urgent implementation needs.

### CMP-181 — Impact analysis

The platform MUST identify affected active rule packs, procurements, documents, and pending decisions. It MUST distinguish historical, in-flight, and future cases.

### CMP-182 — Review and activation

Legal review, product review, security/operations review, test evidence, approval, effective date, and activation MUST be separately recorded. Only activated versions may affect new instances.

### CMP-183 — Retirement

A superseded rule MUST remain available for historical reproduction. It MUST be marked inactive for new instances and MUST NOT be deleted.

### CMP-184 — Emergency correction

If an authoritative source is urgently corrected, the normal change workflow MUST still be used, with expedited approval and an explicit affected-scope note. An administrator MUST NOT silently edit a live rule.

## 16. Compliance acceptance summary

A release satisfies the compliance specification when it can demonstrate:

1. every active hard gate cites a reviewed source and requirement;
2. a blocked action explains the exact unmet control;
3. no generic override exists;
4. a legal-review item cannot be silently treated as satisfied;
5. a historical procurement retains its original rule-pack snapshot;
6. an IRR amendment is applied only to the legally reviewed in-scope population;
7. a protest fee is computed from the pinned statutory schedule;
8. a PhilGEPS publication is not complete without accepted evidence;
9. every decision report reconstructs its source-to-evidence chain;
10. the withdrawn unofficial IRR compilation cannot be selected as the active baseline.

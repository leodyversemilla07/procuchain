# Procurement Lifecycle Specification: RA 12009 / IRR of RA 12009

> **Status:** Build target
> **Specification version:** 0.1
> **Depends on:** [Compliance Specification](compliance-spec.md), [Platform Specification](platform-spec.md)

## 1. Purpose and scope

This specification defines the governed procurement lifecycle for the target platform. It reconciles the earlier [Lifecycle Spec](lifecycle-spec.md) against the official RA 12009 and officially approved IRR and separates:

- authoritative legal requirements;
- workflow and evidence requirements;
- platform behavior;
- guidance or points requiring legal review.

It is not a substitute for the applicable GPPB standard forms, procurement manuals, guidelines, or PE policies.

## 2. Normative conventions

Requirements use `LIFE-NNN` identifiers and the RFC 2119 keywords defined in the [Specification Index](README.md).

A phase MUST NOT be implemented as an unconditional linear wizard. Applicability, entry, and exit MUST depend on procurement category, mode, value, funding type, project characteristics, and the pinned rule pack.

## 3. Source basis and verified baseline

### LIFE-001 — Governing sources

This specification is grounded in:

- RA 12009, New Government Procurement Act;
- IRR of RA 12009 approved through GPPB Resolution No. 02-2025;
- GPPB Resolution No. 03-2025, Standard Forms for Procurement;
- GPPB Resolution No. 12-2025, Procurement Mode Selection Decision Tree;
- GPPB Resolution No. 10-2026 and Circular No. 06-2026, documentary requirements for specific modes;
- other issuances activated through the legal register.

The baseline MUST be verified through the [Compliance Specification](compliance-spec.md). The earlier lifecycle draft's summary of protest fees, suspension/blacklisting periods, Rule numbering, and other details MUST NOT be carried into implementation unless re-verified against the official source.

### LIFE-002 — Municipal-first scope

The first implementation targets LGU procurement and shall support province, city, and municipality organizational structures, LCE as HoPE, BAC roster, Secretariat, End-User, TWG, observers, and Sanggunian-related authorities.

National-government and other PE variants are future scope unless explicitly activated.

### LIFE-003 — Procurement categories and modes

The platform MUST determine scope before enabling a procurement workflow. Under IRR §§4.1–4.4, the Act and IRR generally apply to government procurement, treaties and international agreements observed with treaty precedence in an express conflict, and specified foreign-grant, real-property, and PPP activities are excluded or only partly covered. Direct assistance, scholarships/training/conferences/similar activities, COS/JO workers, and ordinary government-property disposal are not procurement activities under the IRR unless the applicable provision says otherwise.

The platform MUST support the RA 12009/IRR procurement categories and modes, including:

- Goods;
- Infrastructure Projects;
- Consulting Services;
- Competitive Bidding;
- Limited Source Bidding;
- Competitive Dialogue;
- Unsolicited Offer with Bid Matching;
- Direct Contracting;
- Direct Acquisition;
- Repeat Order;
- Small Value Procurement;
- Negotiated Procurement;
- Direct Sales;
- Direct Procurement for Science, Technology and Innovation;
- other modes activated by the rule pack.

Mode selection MUST follow the reviewed decision-tree and PE validation. GPPB Resolution No. 12-2025 states that its result is guidance and does not constitute a definitive determination.

### LIFE-004 — Fit-for-purpose and proportionality

Every procurement MUST record the business need, scope, supply positioning, market context, risks, lifecycle considerations, value-for-money rationale, and selected mode, subject to the applicable requirements.

## 4. Actors and decision rights

### LIFE-010 — HoPE / LCE

The HoPE or LCE MUST have the authority and responsibility defined by applicable law and PE delegation. At minimum, the system MUST support actions for:

- approving the APP or Indicative APP and amendments;
- approving or disapproving BAC award recommendations;
- resolving protests;
- deciding suspension, blacklisting, and other sanctions;
- authorizing procurement methods, delegations, variations, termination, and other acts requiring HoPE authority.

Authority MUST be evaluated at action time from the applicable delegation instrument and rule pack.

### LIFE-011 — BAC

The BAC MUST be constituted and act under IRR §§41–42. The platform MUST support:

- designation and composition checks;
- chairperson and vice-chairperson designation;
- provisional members for relevant procurements;
- alternates;
- one-year terms and the consecutive-term rule, including authorized exceptions;
- meeting quorum and decision calculation;
- conflict disclosure, recusal, abstention, and voting;
- recommendation of mode, award, and sanctions;
- procurement functions enumerated in IRR §42.1.

### LIFE-012 — BAC Secretariat / Procurement Unit

The Secretariat MUST support the functions in IRR §44, including administration, minutes and resolutions, custody of procurement records, document distribution, publication, milestone monitoring, PPMP consolidation, and communications.

### LIFE-013 — End-User / Implementing Unit

The End-User MUST support planning, specifications or terms of reference, estimates, market scoping, delivery and completion evidence, and technical participation. It MUST NOT approve its own conflicting decision where segregation rules apply.

### LIFE-014 — TWG

The TWG MUST support task assignment, confidentiality, technical review, recommendations, evaluation support where assigned, and evidence.

### LIFE-015 — Observers

Observers MUST have access limited to the proceedings and documents authorized by IRR §43. They MUST NOT vote. The system MUST record invitation, qualifications, conflicts, confidentiality, attendance, and report status.

### LIFE-016 — Bidders, suppliers, contractors, and consultants

External parties MUST be able to receive or access permitted documents, submit required records, respond to clarification, acknowledge receipt, and use a permitted protest or reconsideration mechanism.

### LIFE-017 — Records and compliance officers

These officers MUST administer classification, retention, legal holds, disposition review, evidence export, and compliance reporting.

## 5. Common procurement record

### LIFE-020 — Procurement case

Every procurement MUST have a `ProcurementCase` with a stable identifier, PE, category, mode, title, scope, funding source, ABC, APP linkage, timeline, participants, rule-pack snapshot, template set, and current lifecycle state.

### LIFE-021 — Procurement mode justification

The case MUST contain the Fit-for-Purpose assessment and approved mode rationale. The decision-tree result MAY support the assessment but MUST NOT replace PE validation.

### LIFE-022 — ABC

The case MUST contain a versioned ABC with amount, currency, basis, components, approver, date, funding evidence, and calculation/source references. For an LGU, the ABC is the contract budget approved through the Sanggunian appropriation ordinance; for multi-year work it is the total project cost in the MYCA or equivalent document; and for foreign-funded work it is the cost estimate approved by the foreign government or international financing institution as specified in the applicable treaty or agreement, consistent with IRR §5(a). The ABC is a ceiling under IRR §60.1 and is not a bid value.

### LIFE-023 — APP linkage

The case MUST link to the approved APP or Indicative APP version and line item. The system MUST enforce the IRR §7.8 linkage as a hard gate for procurement covered by the rule.

### LIFE-024 — Timeline

The case MUST contain a computed event-based timeline, including planned and actual events, legal deadlines, dependencies, tolling, and evidence.

## 6. Lifecycle state model

### LIFE-030 — State model

A procurement case MUST use a controlled state machine. The baseline states are:

```text
draft
→ planned
→ app_linked
→ mode_selected
→ readiness_review
→ documents_prepared
→ published
→ open
→ received
→ opened
→ evaluated
→ post_qualified
→ award_recommended
→ award_decided
→ awarded
→ contracted
→ implementing
→ closed
```

Additional states MUST support `on_hold`, `returned`, `cancelled`, `failed`, `protesting`, `reconsidering`, `rebidding`, `sanctioned`, and `archived` where applicable.

### LIFE-031 — State events

Every state change MUST be an event with actor, time, prior state, next state, reason, evidence, rule-pack snapshot, and resulting tasks or deadlines.

### LIFE-032 — Parallel lifecycle

The state model MUST represent parallel tracks for procurement, contract implementation, protest/reconsideration, sanctions, publication, and acknowledgement without conflating their outcomes.

### LIFE-033 — Reopening

A closed procurement MUST NOT be silently reopened. Correction, late evidence, audit finding, protest, contract dispute, or sanction MUST create a linked case event and controlled state transition.

## 7. Phase A — Strategic procurement planning

### LIFE-040 — Entry

Planning MUST be available when a need, requirement, or program is identified. It MUST NOT require an already-created procurement case.

### LIFE-041 — Market scoping

The End-User MUST perform and record market scoping before preparing the PPMP, subject to the requirements in IRR §10. The record MUST cover cost estimation, design/specifications, technical and selection criteria, delivery lead time, storage or warehousing, market information, and sources.

### LIFE-042 — PPMP

The PPMP MUST use the applicable GPPB standard form and template version. It MUST capture at least the prescribed project facts, scope, estimate/ABC basis, schedule, risk, method considerations, and links to supporting records.

### LIFE-043 — Technical specifications

Technical specifications, scope of work, or terms of reference MUST be structured where facts are regulated or reused. A brand-name reference MUST include the justification and evidence required by IRR §11.2 and the applicable form.

For Infrastructure Projects, the case MUST also track detailed engineering under IRR §8, including the approved studies/designs and required right-of-way. Unless the project qualifies for the design-and-build exception, bidding and award are blocked without sufficiently completed and duly approved detailed engineering and required right-of-way. Where right-of-way is pending, procurement may proceed, but award remains blocked until an authority or permit to enter, notarized deed of sale or donation, or writ of possession exists under IRR §8.1.1.

For a multi-year locally funded contract, the platform MUST require the applicable MYCA or Sanggunian-equivalent authority before procurement activity begins, and MUST use the total project cost in that authority as the ABC reference under IRR §9.

### LIFE-044 — APP consolidation

The Secretariat MUST consolidate PPMPs and support the Indicative APP and final APP. Each APP version MUST retain line items, fund sources, schedules, modes, criteria, ABCs, dependencies, and revision history.

### LIFE-045 — APP approval

The APP or Indicative APP MUST be approved by the HoPE through the governed approval workflow. Approval MUST bind the exact APP version.

### LIFE-046 — APP amendment

An amendment MUST create a new APP version, identify affected procurements, and rerun affected gates. Existing cases MUST NOT silently inherit an amended line without an explicit linkage update and review.

### LIFE-047 — APP publication and submission

The platform MUST track the PE website posting and any submission required to GPPB, including the required deadline from the applicable source. Under IRR §7.7.4, the updated Indicative APP for EPA must be posted on the PE website. Under IRR §7.7.5, the approved final APP must be posted on the PE website and submitted to GPPB on or before the end of January of the budget year. GPPB receipt MUST be evidenced. The current submission procedure requires legal/operations review before activation.

### LIFE-048 — Planning exit

Planning exits when the required planning, APP, and funding evidence is available for the next applicable phase. For EPA, IRR §12 requires Indicative APP approval before EPA, the ABC not to exceed the Indicative APP amount, and no award until funding approval/effectivity and final HoPE-approved APP.

## 8. Phase B — Procurement initiation and mode selection

### LIFE-050 — Initiation

A procurement case MUST be created only against an eligible APP line unless the reviewed rule pack explicitly allows another state. It MUST capture category, mode, scope, ABC, schedule, funding, End-User, and required documents.

### LIFE-051 — Mode decision

The mode MUST be selected using the reviewed rule set and decision-tree process. The case MUST contain the facts supporting the decision and authorized approval.

The platform MUST support only the modes enumerated in IRR §26.1, and MUST record the applicable mode conditions and evidence:

- Competitive Bidding — publication, pre-bid conference, eligibility screening, receipt and opening, evaluation, post-qualification, and award, subject to IRR Rules VI to XI (IRR §27.1).
- Limited Source Bidding — only under IRR §28.2(a)–(c), with pre-selection from a relevant government authority's periodically updated list or, where none exists or the PE is itself the relevant authority, a PE self-generated list with an established attestation of that fact and the PE's subject-matter expertise, and pre-selection based on capability and resources, personnel, equipment, or manufacturing facilities, financial position, and good standing (IRR §§28.3–28.4). Except for IRR §50 publication, the Competitive Bidding procedure applies, with reduced timelines allowed within the IRR maximum (IRR §28.5).
- Competitive Dialogue — only under IRR §29.2(a)–(c), in two stages: stage one with maximum Bidding Documents periods from the last posting day of 45 calendar days for Goods, 65 for Infrastructure Projects, and 75 for Consulting Services, eligibility under IRR §52, and pass-or-fail evaluation of initial technical proposals; stage two with issuance of finalized specifications to all dialogue participants within 10 calendar days and submission of revised technical and financial proposals in two sealed envelopes within 20 calendar days of that issuance (IRR §§29.4.1–29.4.2).
- Unsolicited Offer with Bid Matching — only where the HoPE determines a new concept or technology under IRR §30.1(a)(i)–(iv) and comparative or competitive bids have been invited through Competitive Bidding, with the IRR §30.2 offer package and the IRR §§30.3–30.8 timelines (IRR §30).
- Negotiated Procurement — only under the IRR §§35.1–35.13 instances, each with its own conditions and procedure.
- Direct Contracting, Direct Acquisition, Repeat Order, Small Value Procurement, Direct Sales, and Direct Procurement for Science, Technology and Innovation — each under IRR §§31–37 with its own conditions.

Except for Competitive Bidding, Limited Source Bidding, Competitive Dialogue, and Unsolicited Offer with Bid Matching, the BAC MAY delegate conduct of procurement activities to the End-User or Implementing Unit or the PE's Procurement Unit under IRR §26.4; the platform MUST record the delegation and MUST NOT extend it to the four modes that require BAC conduct.

### LIFE-052 — Eligibility of method

The system MUST verify mode-specific eligibility, thresholds, prior-procurement history, conditions, and documentary requirements. GPPB Resolution No. 10-2026 and Circular No. 06-2026 requirements MUST be activated per mode and stage.

### LIFE-053 — Rejected or unsupported mode

If a mode is unsupported or its evidence is incomplete, the platform MUST block initiation or publication and create a review task. It MUST NOT silently substitute another mode.

## 9. Phase C — Pre-procurement conference and readiness

### LIFE-060 — Applicability

IRR §49.1 makes the pre-procurement conference mandatory for each procurement except:

- Goods costing ₱5,000,000 and below;
- Infrastructure Projects costing ₱10,000,000 and below;
- Consulting Services costing ₱2,000,000 and below.

The system MUST apply “and below” exactly. Threshold and ABC source must be pinned and evidenced.

### LIFE-061 — Readiness checklist

The conference MUST support and evidence the IRR §49.1 review:

- description and scope;
- ABC and contract duration;
- PPMP/APP conformity;
- availability of appropriations or funding source;
- completeness of Bidding Documents;
- infrastructure detailed engineering, where applicable;
- right-of-way/site/affected-property possession, where applicable;
- eligibility, evaluation, and post-qualification criteria;
- award criterion;
- procurement schedule;
- confidentiality measures and applicable sanctions.

### LIFE-062 — Award criterion lock

The BAC MUST finally determine whether the contract will be awarded using LCRB, MEARB, MARB, or HRRB before publication, consistent with IRR §49.2. The selected criterion and version MUST be locked to the procurement's Bidding Documents.

### LIFE-063 — Optional conference evidence

Where the conference is optional under IRR §49.1, the system MUST either record that it occurred or record a reviewed option-out. It MUST NOT infer that a missing conference was mandatory without evaluating the threshold.

### LIFE-064 — Exit

Readiness exits only when all mandatory gates are satisfied, deficiencies are resolved, and the Bidding Documents and schedule are ready for the applicable publication step.

### LIFE-064A — Competitive-bidding conference recording and livestream

For Competitive Bidding, the system MUST track whether each procurement-related conference—pre-procurement conference, pre-bid conference, bid opening, and other BAC meetings—has a video recording initiated before commencement. Under IRR §38.3, the mandatory video-recording rule applies only above ₱10,000,000 for Goods, ₱20,000,000 for Infrastructure Projects, and ₱5,000,000 for Consulting Services.

The system MUST also track the public livestream requirement for preliminary examination and bid opening under IRR §38.2, while leaving the current livestream operating guideline as **legal/operations review required**. Recordings must be retained for at least five years and be available to the public on request and payment of the PE's fee, subject to budgeting, accounting, and auditing rules and any national-security determination by the HoPE under IRR §38.4.

### LIFE-064B — Contract-splitting control

The system MUST record the basis for dividing procurement into units, lots, packages, or Infrastructure Project phases and MUST NOT permit a configuration that artificially divides scope to evade a mode, threshold, publication, or approval requirement. Per-unit, lot, or package procurement is permitted subject to budget, required quantity, product availability, delivery sites, geographical location, and market capacity under IRR §39.1. Phased Infrastructure work must be clearly delineated, usable, structurally sound, and consistent with the detailed architectural and engineering design under IRR §39.2.

### LIFE-064C — Common-Use Supplies control

For a procurement involving CSE, the system MUST first verify PS-DBM availability and record the electronic-catalogue evidence. Procurement from another source is permitted only under an applicable IRR §40.2 ground, including zero inventory with a Certificate of Non-Availability, insufficient specifications, insufficient stock, or a BAC resolution on the End-User's recommendation for efficiency, practicality, or economic viability with the prescribed justification and PS-DBM notification.

## 10. Phase D — Bidding Documents and publication

### LIFE-070 — Mandatory standard forms

The Bidding Documents MUST use the GPPB-prescribed standard forms and manuals, subject to reviewed applicability. The case MUST preserve the source and version of each official form used.

### LIFE-071 — Content checklist

The system MUST validate the IRR §47.1 content set:

a. ABC;

b. Invitation to Bid or Request for Expression of Interest;

c. Instructions to Bidders;

d. technical specifications, terms of reference, or scope of work;

e. eligibility requirements;

f. environmental criteria and specifications;

g. plans or drawings;

h. bid form, price schedule, and list of goods or bill of quantities;

i. delivery or completion schedule;

j. bid security form and amount;

k. performance security and warranty form and amount;

l. contract form and general/special conditions;

m. other necessary documentary requirements or specifications.

Applicability of each item MUST be recorded; `not_applicable` requires a reason.

### LIFE-072 — Equal access and confidentiality

Before official release, no bidding-document aspect may be divulged to an interested party outside authorized handling, consistent with IRR §48. The system MUST enforce selective-release controls and record authorized exceptions.

### LIFE-073 — Publication requirements

For a competitive procurement, the platform MUST calculate and apply the IRR §50 and §51 publication requirements, including prescribed channels, continuous posting period, Bidding Documents availability, and pre-bid conference/bulletin rules.

Detailed duration and channel rules MUST be activated per mode from the official source; they MUST NOT be copied from the superseded draft without verification.

### LIFE-074 — Publication transaction

A publication MUST be created from the exact approved Bidding Documents version and artifact hashes. All required channels MUST be recorded. A channel that cannot be automated MUST use the controlled manual-publication workflow.

### LIFE-075 — Publication evidence

The case MUST retain start/end evidence, posting receipt or reference, captured content where allowed, responsible actor, and any reconciliation result.

### LIFE-076 — Pre-bid conference

For competitive-selection procurements with ABC of ₱3,000,000 or more, the BAC MUST conduct at least one pre-bid conference under IRR §51.1. Below ₱3,000,000 it is discretionary, subject to BAC approval or a prospective bidder's written request.

Where applicable, the system MUST calculate the conference date from the pinned rule, require attendance and minutes, link clarifications, and enforce the IRR §51.2–§51.4 deadlines: at least 12 calendar days before bid deadline, no earlier than 7 calendar days after the applicable posting or shortlist event, and minutes not later than 5 calendar days after the conference.

### LIFE-077 — Supplemental or bid bulletin

A bulletin MUST identify the affected documents and clauses, reason, authority, issue date, response deadline, and bidder-notification evidence. It MUST not alter unrelated content. The system MUST apply IRR §§51.4–51.5: amendment bulletins at least 7 calendar days before bid deadline; clarification requests at least 10 calendar days before; BAC responses at least 7 calendar days before; PE-initiated modifications at least 7 calendar days before. A bidder that submitted before a bulletin MUST be informed and allowed to modify or withdraw its bid under IRR §55.

### LIFE-078 — Rule-pack commencement

For an IRR amendment, the applicable snapshot MUST be fixed no later than the first advertisement or invitation issuance for that procurement, consistent with IRR §112.2, unless a reviewed mode-specific rule identifies another event.

## 11. Phase E — Eligibility, submission, and opening

### LIFE-080 — Eligibility stage

The system MUST support the IRR §52 and applicable GPPB documentary requirements as a mode- and stage-aware checklist. It MUST distinguish machine-verifiable facts from reviewer judgment and preserve both evidence and decision.

The eligibility workflow MUST also support IRR §81 disclosure of relations: every bid must be accompanied by the bidder's sworn affidavit that it is not related by consanguinity or affinity up to the third civil degree to the HoPE, Procurement Agent if engaged, BAC members, TWG, BAC Secretariat, PMO/End-User/Implementing Unit head, and project consultants, and must disclose ultimate beneficial ownership. Failure to comply is a ground for automatic disqualification under IRR §59, and a disclosed relationship within the third degree disqualifies the bidder notwithstanding self-inhibition by the related official.

For competitive modes covered by IRR §82.1, beneficial-ownership information is a participation requirement. The system MUST preserve the affidavit, ownership declaration, source, verification status, and update date.

### LIFE-081 — Eligibility decision

An eligibility or ineligibility notice MUST identify the bidder, exact checklist/rule pack, result, reasons, evidence, issuer, date, delivery, and appeal or remedy information where applicable.

### LIFE-082 — Submission

The platform MUST preserve submission time, actor, channel, envelope or artifact identity, hash, receipt, and validation result. Where bids are submitted through PhilGEPS, the official portal receipt MUST be reconciled.

### LIFE-083 — Deadline and lateness

The system MUST compute submission windows from the pinned rule and trigger. For IRR §54.5, the period from the last day of posting of the Invitation to Bid or Request for Expression of Interest to submission and receipt of bids MUST be calculated as follows:

- Goods: maximum 45 calendar days;
- Infrastructure Projects with ABC of ₱50,000,000 and below: maximum 50 calendar days;
- Infrastructure Projects with ABC above ₱50,000,000: maximum 65 calendar days;
- Consulting Services: maximum 75 calendar days.

The system MUST classify timely, late, withdrawn, modified, or technically failed submission and retain the reason. Under IRR §54.6, bids and eligibility requirements submitted after the deadline must not be accepted, and the late bidder's name, representative, and submission time must be recorded in the minutes.

### LIFE-084 — Modification and withdrawal

The system MUST preserve each modification or withdrawal as a new submission event. The previous content MUST remain available as evidence according to the pinned rule.

Under IRR §55.1, a modification MUST be made before the deadline, MUST NOT retrieve or replace the original bid, and MUST be submitted as a new equally sealed and identified bid marked "modification" and stamped received; modifications received after the deadline are not considered and MUST be returned to the bidder unopened. Under IRR §55.2, a bidder may withdraw by letter before the deadline, a withdrawal after the deadline is subject to sanctions under the IRR, and a bidder that withdraws MUST be blocked from bidding on the same contract directly or indirectly.

### LIFE-085 — Bid security

The system MUST verify bid-security form, amount, validity, issuer, and evidence against the applicable rule and Bidding Documents. For competitive modes, a bid lacking the required security in the prescribed form and amount MUST be automatically disqualified under IRR §56.1. The minimum percentage of ABC is 2% for cash or cashier's/manager's check and 5% for bank draft/guarantee, irrevocable letter of credit, or callable-on-demand surety bond. The Bidding Documents MUST indicate at least three acceptable forms, including a Bid Securing Declaration where required by the pinned rule.

### LIFE-086 — Opening

Opening MUST preserve the attendance, envelopes or electronic records, timestamps, deferment decision, abstract, and opening evidence. The BAC MUST open bids immediately after the submission deadline. For LCRB, technical and financial proposals are opened on the same day; for MEARB or MARB, only technical proposals are opened initially and financial proposals remain secured until the specified opening, limited to bidders meeting the minimum technical score for MEARB or the highest technical score for MARB.

If opening cannot occur as scheduled for justifiable reasons, the BAC MUST secure the bids and issue a Notice of Postponement through PhilGEPS and the PE website, rescheduling for the next working day or the soonest possible time.

The failed bidder's unopened or duly sealed envelope MUST be controlled during a timely reconsideration under IRR §83.1.

### LIFE-087 — Short-listing

For consulting services subject to the IRR short-listing procedure, the system MUST preserve the short-list criteria, applications, evaluation, decision, notice, and opportunity for timely reconsideration.

Under IRR §53.1.2, the number of short-listed consultants MUST be three to seven with five preferred, determined in the pre-procurement conference, and short-listed bidders MUST pay the Bidding Documents fee where applicable. Under IRR §§53.1.3–53.1.4, the BAC MUST specify the criteria and rating system in the Request for Expression of Interest, recommend the short-list to the HoPE for approval, and complete eligibility checking and short-listing within 20 calendar days.

## 12. Phase F — Evaluation and post-qualification

### LIFE-090 — Evaluation basis

Evaluation MUST use the approved criterion, weights, criteria, instructions, and Bidding Documents version. A changed evaluation input MUST create a new evaluation run and preserve the prior run.

### LIFE-091 — Independent evaluation

Each evaluator's scores, observations, corrections, and conflicts MUST be separately recorded. The system MUST support BAC or TWG review of individual records without exposing them improperly to bidders.

### LIFE-092 — Financial opening

If the approved method requires staged opening of financial data, the system MUST enforce the release condition and preserve access logs.

### LIFE-093 — Arithmetic correction

Arithmetical corrections MUST be recorded as structured adjustments with original value, corrected value, rule basis, and responsible reviewer. They MUST be reflected consistently in the abstract and result.

### LIFE-094 — ABC ceiling

A bid exceeding the approved ABC MUST be handled under IRR §60 and the applicable instructions. The exact consequence must come from the pinned rule, not a generic platform setting.

### LIFE-095 — Post-qualification

The system MUST implement the applicable IRR §63 and GPPB documentary requirements. It MUST support the required notice and five-calendar-day submission window, validity checks, legal/technical/financial verification, findings, communication, and ranking or cascade.

The post-qualification process MUST complete within 12 calendar days from determination of the LCB, MEAB, or MAB; for Consulting Services, the 12-day period reckons from completion of negotiation with the bidder that submitted the HRB. An HoPE extension MUST NOT cause the aggregate period to exceed 45 calendar days for Goods/Infrastructure Projects or 30 calendar days for Consulting Services, subject to the reviewed exception logic in IRR §63.8.

### LIFE-096 — Responsive determination

The system MUST produce a traceable determination of responsiveness and financial ranking. It MUST preserve the evaluated bid data and criteria version.

### LIFE-097 — Failure of bidding

A failure determination MUST capture the IRR §64 grounds, evidence, BAC resolution, required review or next-step record, and re-publication or negotiation transition as applicable.

Failure of bidding applies only under IRR §64.1: no bids received, all bidders declared ineligible, no bid qualifies as SCRB, MARB, LCRB, HRRB, or MEARB, or the winning bidder refuses the award without justifiable cause. For modes that do not require competitive selection, the BAC may also declare a failure of procurement or failed negotiations.

After a failure, the End-User or Implementing Unit MUST conduct the mandatory review of the Bidding Documents terms, conditions, specifications, and cost estimates under IRR §64.2; the BAC MUST revise them, adjust the ABC where necessary subject to the required approvals, and conduct a re-bidding with re-publishing under IRR §50 per IRR §64.3. All bidders who initially responded and were declared eligible or short-listed MUST be allowed to submit new bids, and the BAC MUST observe the same process and set new periods under the same rules per IRR §64.4. A second failure of bidding permits the PE to resort to negotiated procurement under IRR §35 per IRR §64.5.

### LIFE-097A — Single responsive bid

Where only one prospective bidder applies, only one meets the eligibility requirements, or only one submits a bid that is found responsive, the system MUST record the applicable IRR §65(a)–(c) scenario and MUST verify that the ABC reflects the most advantageous prevailing price for the Government.

## 13. Phase G — Award recommendation and decision

### LIFE-100 — Recommendation package

The BAC recommendation MUST be supported by at least the IRR §66.1 package:

- BAC resolution recommending award;
- abstract of bids;
- approved program of work or delivery schedule and cost estimates;
- instrument authorizing obligation for the specified amount;
- other pertinent documents required by law, rules, or the PE.

### LIFE-101 — Failed-bidder notice

Within three calendar days from issuance of the award-recommendation resolution, the BAC MUST notify all other bidders in writing. The system MUST calculate and evidence this deadline.

### LIFE-102 — HoPE decision

The HoPE or duly authorized representative MUST approve or disapprove within ten calendar days from BAC determination and recommendation, consistent with IRR §66.2. Authority and delegation MUST be checked.

### LIFE-103 — Disapproval

A disapproval MUST be in writing and state valid, reasonable, and justifiable grounds under IRR §66.3 and §70. The system MUST require the grounds and record furnishing to the BAC and bidder.

### LIFE-104 — Reconsideration after disapproval or inaction

A bidder may request reconsideration to the HoPE within three calendar days from receipt of the disapproval or explanation for inaction, and the HoPE must resolve it within seven calendar days under IRR §66.4. This MUST be distinct from the BAC reconsideration timer in IRR §83.1.

Under IRR §66.4 the HoPE resolves the request with finality, furnishes the bidder a copy of the resolution immediately from promulgation, and the request never stays or delays the bidding process; it must, however, be resolved before any award is made.

### LIFE-105 — Award and NOA

On approval, the HoPE or authorized representative MUST immediately issue the Notice of Award. The NOA MUST bind the winning bidder, approved amount, documents, conditions, and issuance evidence.

### LIFE-106 — Winning-bidder conditions

Before contract award, the system MUST track IRR §66.5 conditions: applicable JVA or foreign-license documents, performance security, and signing of the contract. Non-performance MUST be handled under IRR §69.

### LIFE-107 — Award gate

Award completion MUST be blocked while a timely protest that must be resolved before award remains unresolved, consistent with IRR §86.

## 14. Phase H — Contract formation and implementation

### LIFE-110 — Contract file

The contract file MUST include the executed contract, NOA, performance security, bid and winning-bidder documents, approvals, disclosure records, and required supporting evidence.

### LIFE-111 — Contract implementation records

The platform MUST support contract-implementation documents and obligations, including delivery, inspection, acceptance, warranty, variation or amendment to order, payment support, extension, termination, and final acceptance.

### LIFE-112 — Variations and amendments

Each variation, change order, supplemental agreement, extension, or amendment MUST be a governed document with authority, amount/time impact, approval, security adjustment where applicable, publication or notice requirement, and linkage to the contract.

For Infrastructure Projects, cumulative positive Variation Orders must not exceed 10% of the original contract price without the HoPE's exceptional authorization, which may allow a cumulative value up to but not more than 20%. Work must not start before the required approval, except under the IRR §71.2.1 emergency/time-is-of-the-essence path where unapproved work may not exceed 5% of the adjusted original contract price; payment remains contingent on approval.

### LIFE-113 — Performance security

The system MUST track amount, form, validity, issuer, release conditions, renewal, reduction, forfeiture, and final acceptance linkage under IRR §68 and the contract. Minimum performance security is 5% of the total contract price for Goods and Consulting Services and 10% for Infrastructure Projects when paid in cash or by cashier's/manager's check; it is 30% when paid by bank draft/guarantee, irrevocable letter of credit, or callable-on-demand surety bond.

Performance security must be posted before contract signing and remain effective through the Certificate of Final Acceptance. Release after final acceptance is conditional on there being no claims filed against the security. For Infrastructure Projects, an additional performance security is required for cumulative contract increases above 10%, and approved contract-time extensions must be covered by renewed validity.

Entities registered under IRR §76 may post a Performance Securing Declaration in lieu of performance security under IRR §§68.2–68.3; the PSD must state blacklisting for one year on first offense or two years on a prior similar offense, and an unnotarized PSD may be accepted only subject to submission of a notarized PSD before any payment unless it is replaced with a performance security in the prescribed form.

The PE may proportionally reduce the original performance security under IRR §68.8 for a reduction in contract value, or for usable and accepted partially completed Infrastructure works that will not affect structural integrity. Any single reduction above 10% is permitted only while the aggregate of reductions does not exceed 50% of the performance security.

### LIFE-114 — Warranty

Goods warranty and security obligations MUST be tracked under IRR §90 and the contract, including period, retention or guarantee, defect action, release, and unresolved claims. For Expendable Supplies, the minimum warranty period is three months; for Non-expendable Supplies, one year, measured from acceptance. The warranty obligation must be covered by retention money or a special bank guarantee of at least 1% and not more than 5% of each progress payment, or an equivalent amount of the total contract price where the IRR applies.

### LIFE-115 — Liquidated damages and termination

The system MUST support calculations and evidence for the applicable liquidated damages and termination rules. Every legal or contractual formula MUST cite its source.

Every contract executed under the Act and IRR MUST contain a liquidated-damages provision of at least one-tenth of one percent of the cost of the unperformed portion per day of delay under IRR §97; once cumulative liquidated damages reach 10% of the contract price, the PE may terminate the contract without prejudice to other remedies.

For Infrastructure Projects, the contractor assumes full responsibility for damage or destruction of the works (except force majeure) and for the safety, protection, security, and convenience of its personnel, third parties, and the public from construction start to final acceptance, and must repair defects at its own expense within 90 calendar days of the HoPE's repair order; on failure or refusal the PE may repair and recover the full cost. Warranty security for Infrastructure Projects must be in the PE-chosen form at not less than 5% of the total contract price for cash or bank letter of credit (confirmed or authenticated by a local bank when issued by a foreign bank), 10% for a bank guarantee confirmed by a bank, or 30% for a callable-on-demand surety bond issued by GSIS or an Insurance Commission–certified surety.

Liability for structural defects or failures runs from final acceptance for 15 years for permanent structures (including National Building Code types 4 and 5 and steel, iron, or concrete structures compliant with structural codes), 5 years for semi-permanent structures (NBC types 1–3, concrete or asphalt roads, river control, drainage, irrigation canals, river landings, deep wells, rock causeways, pedestrian overpasses), and 2 years for other structures (Bailey and wooden bridges, shallow wells, spring developments). Where a structural defect or failure occurs during the applicable warranty period, the PE may undertake restoration or reconstruction and recover the full cost from the parties found liable, without prejudice to administrative, civil, or criminal charges and forfeiture of posted warranty securities.

### LIFE-116 — Closure

Closure MUST require final acceptance, security disposition, unresolved-obligation review, retention assignment, and a complete contract evidence package.

## 15. Parallel track — Protest and reconsideration

### LIFE-120 — BAC request for reconsideration

A bidder may request reconsideration to the BAC within three calendar days after receipt of written notice or verbal notification of the BAC decision. The BAC must decide within seven calendar days. Additional documents MUST NOT be submitted to cure bid defects under IRR §83.1.

### LIFE-121 — Envelope hold

When a failed bidder timely signifies intent to request reconsideration, the BAC MUST keep that bidder's bid envelopes unopened or duly sealed until the request is resolved. The system MUST enforce and evidence this state.

### LIFE-122 — HoPE protest

After denial of reconsideration, a bidder may protest to the HoPE within seven calendar days from receipt of the denial resolution. The protest MUST be an electronically filed verified position paper with the non-refundable fee paid through the prescribed electronic channel under IRR §83.2.

### LIFE-123 — Verified-position-paper completeness

The system MUST validate the IRR §83.3 information and oath/certification requirements. An unverified position paper has no legal effect and results in dismissal under IRR §83.3. Non-curable defects MUST not be presented as curable.

### LIFE-124 — Fee schedule

The fee MUST be calculated using the IRR §83.2 schedule, not the superseded draft's generic formula. The pinned schedule and inputs MUST be retained.

The schedule is: ABC up to ₱50,000,000 — 0.75% of ABC; above ₱50,000,000 to ₱100,000,000 — ₱500,000; above ₱100,000,000 to ₱500,000,000 — 0.5% of ABC; above ₱500,000,000 to ₱1,000,000,000 — ₱2,500,000; above ₱1,000,000,000 to ₱2,000,000,000 — 0.25% of ABC; above ₱2,000,000,000 — ₱5,000,000. The fee is non-refundable and must be paid through an electronic payment channel.

### LIFE-124A — Verified position paper completeness

A protest MUST be filed with a verified position paper containing the IRR §83.3(a)–(h) information: bidder name and office address, project or contract name, implementing office or agency or PE, brief statement of facts, issue to be resolved, factual basis and legal arguments, and any other pertinent information. The paper MUST be verified by an affidavit attesting that the affiant has read and understood its contents and that the allegations are true and correct based on personal knowledge or authentic records. An unverified position paper is treated as unsigned, produces no legal effect, and results in outright dismissal of the protest.

The bidder MUST also certify under oath that no other action or claim involving the same issues has been commenced or filed in any court, tribunal, or quasi-judicial agency; that any such pending action or claim is fully stated; and that a later-learned similar action or claim will be reported to the HoPE within five days. Failure to comply is not curable by mere amendment.

### LIFE-125 — HoPE protest decision

The HoPE must resolve the protest within seven calendar days from receipt, strictly on the BAC record, with clear factual and legal bases and citations to relevant documents under IRR §84.

The decision must also state the HoPE's authority basis and finality: it is final up to the limit of the HoPE's contract-approving authority, and for LGUs the decision of the local chief executive is final, subject to the IRR §84.2–§84.3 executory thresholds.

### LIFE-125A — Contract disputes and interim-relief restriction

Contract disputes MUST first be resolved amicably by mutual consultation or agreement under IRR §88.1. The platform MUST support the most expeditious dispute-resolution mode, contractual arbitration, or other alternative modes agreed in writing, and ADR under RA 9285, while routing disputes within the competence and jurisdiction of CIAC to CIAC under IRR §88.3.

The platform MUST record that no court other than the Supreme Court may issue a temporary restraining order, preliminary injunction, or preliminary mandatory injunction to restrain, prohibit, or compel procurement, bidding, awarding, contract commencement, execution, implementation, operation, termination, or rescission under IRR §87, except in cases of extreme urgency involving a constitutional issue where the applicant must file a bond fixed by the court accruing to the government if relief is denied.

### LIFE-125B — Contract price and escalation

Awarded bid prices are fixed and not subject to price escalation during contract implementation under IRR §89. Price escalation for extraordinary increases in Infrastructure component costs requires prior GPPB approval, is authorized only on a no-loss, no-gain basis when the cost increase exceeds 10% of the unit price of work items measured against PSA prevailing price indices, and is governed by the GPPB-prescribed formula, which remains **legal review required** until pinned.

### LIFE-126 — Finality and court action

The system MUST support the IRR §84.2–§85 finality categories and the LGU thresholds in IRR §84.3: Goods ₱1,250,000 and below; Infrastructure Projects ₱12,500,000 and below; and Consulting Services ₱2,500,000 and below. Above those thresholds, the decision becomes final and executory 10 calendar days after receipt by the parties unless an appeal to the Regional Trial Court is perfected within the same period, consistent with IRR §85.

Court action may be resorted to only after the protest process is completed.

### LIFE-127 — No process stay

The protest MUST not automatically stay or delay the process, but must be resolved before award under IRR §86.

### LIFE-128 — Corrective action

A protest outcome MUST record any required corrective action, such as re-evaluation, re-bidding, re-award, correction, or no further action, and the authorized decision that caused it.

## 16. Parallel track — Suspension, blacklisting, and sanctions

### LIFE-130 — Initiation

The BAC or HoPE may initiate administrative proceedings motu proprio, or an affected bidder may initiate, upon the applicable prima facie determination under IRR §104.

### LIFE-131 — Notice and hearing

The bidder MUST receive written notice of the opportunity to show cause and may request a hearing. The system MUST preserve notice, response, hearing record, and evidence.

### LIFE-132 — BAC recommendation

The BAC MUST issue a resolution recommending penalty where substantial evidence supports fault. The recommendation MUST identify the grounds and evidence.

### LIFE-133 — HoPE decision

The HoPE must decide within fifteen days from receipt of the BAC resolution under IRR §104.1(b). The decision MUST state whether substantial evidence exists; otherwise the action is dismissed. If no request for reconsideration is filed, the decision becomes final after seven days from receipt by the affected bidder.

### LIFE-134 — Reconsideration

A request for reconsideration may be filed within three calendar days from receipt of the decision and must be resolved by the HoPE within seven calendar days. Only one request is allowed.

### LIFE-135 — GPPB posting

The PE MUST upload the suspension or blacklisting order to the GPPB portal within seven calendar days from issuance under IRR §104.1(d). The system MUST calculate the deadline and retain posting evidence.

### LIFE-136 — Penalty classification and period

The system MUST implement the grounds and periods in IRR §§99–101, including one-/two-year suspension, one-/two-year blacklisting, and perpetual disqualification grounds. The earlier draft's differing periods MUST NOT be used without verified activation of the official rule.

The system MUST support the enumerated grounds as structured checklists rather than free text. Suspension grounds under IRR §99.1–§99.2 include unjustified delay of eligibility screening, opening, evaluation, post-evaluation, or award; refusal to clarify or validate its bid in writing within seven calendar days of a post-qualification clarification request; withdrawing a bid or refusing the award or contract without justifiable cause after HoPE approval; failure to post performance security on time; refusal to accept a renewal notice or failure to submit updated renewal documents for regular and recurring services; delivery of unsatisfactory or inferior goods; poor consultant performance under §99.2(b)(i)–(iv); and poor winning-bidder performance including negative slippage of 15% and above within the critical path caused entirely by the bidder's fault or negligence, or non-compliance with approved specifications from the bidder's fault or negligence. Bid or performance security is forfeited with a qualifying suspension.

Blacklisting grounds under IRR §100.1–§100.3 include the commission of three or more acts punishable by suspension; use of force, fraudulent machinations, coercion, undue influence, or pressure on BAC members or PE officers; prima facie corrupt, fraudulent, collusive, or coercive practices, use of forged documents, or use of adulterated materials or methods contrary to science or trade; collusion and malicious multiple submission creating false competition; agreement with other bidders to refrain or withdraw; failure to faithfully disclose relationships under IRR §81; false beneficial-ownership entries; use of another's name; false or falsified eligibility requirements and bids; unauthorized access to bid contents before opening; documented unsolicited attempts to unduly influence the outcome; and schemes that stifle or suppress procurement activity; plus assignment, subcontracting, or key-personnel substitution without prior written approval, deliberate abandonment or non-performance, and termination for default. Bid or performance security is forfeited with a qualifying blacklisting.

Perpetual disqualification under IRR §101 applies on a third blacklisting offense imposed by the same PE, or a combination of three blacklisting violations imposed by the PE and other PEs as posted on the GPPB portal; or failure to repair noted defects or damage to an Infrastructure Project due to inferior materials within 90 calendar days of the HoPE's repair order.

### LIFE-136A — Bid or performance securing declaration enforcement

Upon determination of grounds for enforcement and forfeiture of a Bid Securing Declaration Form or Performance Securing Declaration Form, the HoPE must immediately issue the blacklisting or suspension order upon the BAC recommendation under IRR §105.1, and a request for reconsideration may be filed within three calendar days from receipt of the order.

### LIFE-137 — Automatic pending suspension

The bidder is automatically suspended from the current procurement while blacklisting proceedings are pending under IRR §102. Dismissal lifts it automatically.

### LIFE-138 — Scope and related entities

The system MUST support IRR §103 status and §105 extent of sanctions, including controlling interests, beneficial owners, and related entities. The platform MUST NOT infer a relationship without evidence and review.

Under IRR §103, before a blacklisting order issues, the erring bidder may participate in other government procurements except in the PE where it is suspended; a blacklisting order issued before the Notice of Award disqualifies the bidder from award and the contract must be awarded to another bidder; a suspension or blacklisting order does not prejudice contracts awarded earlier unless the offenses are connected to that contract; and a penalty imposed during the pendency of a previous penalty subsumes the previous one.

Under IRR §105, sanctions extend to any entity in which the suspended or blacklisted person holds a controlling interest and to any beneficial owner of the suspended entity or blacklisted person, and to the related persons and entities enumerated in §105(a)–(e), including a stockholder holding at least 20% of shares together with relatives up to the third civil degree and the chairperson and president of corporations determined to hold the same controlling interest in previously blacklisted corporations.

### LIFE-139 — Portal and pre-bid check

The platform MUST support blacklist/suspension status checks before award and other stages where required by the pinned rule. External portal status MUST be recorded with source and time.

## 17. Parallel track — Publication, distribution, and acknowledgement

### LIFE-140 — Required notices

The system MUST support the applicable requirement, template, timing, audience, and evidence for:

- invitation and Bidding Documents;
- eligibility/ineligibility and short-list notices;
- pre-bid and procurement notices;
- postponement;
- bid opening;
- award recommendation and failed-bidder notice;
- award/disapproval and NOA;
- post-qualification and post-disqualification;
- protest/reconsideration and decisions;
- suspension/blacklisting and GPPB posting;
- contract, variation, termination, and acceptance notices.

### LIFE-141 — PhilGEPS publication

PhilGEPS publication MUST be tracked through the required-channel boundary in the [Compliance Specification](compliance-spec.md). Internal completion MUST NOT substitute for required PhilGEPS publication.

### LIFE-142 — Distribution

Distribution MUST preserve recipient, channel, artifact hash, delivery attempt, and acknowledgment where applicable.

### LIFE-143 — Acknowledgement

Acknowledgement MUST bind to the issued version or artifact and identify the actor and time. Non-acknowledgement MUST remain visible and MUST NOT be treated as receipt.

## 18. Procurement closure and records

### LIFE-150 — Evidence bundle

Closure MUST create or finalize an evidence bundle containing the procurement file, key approvals, decisions, publications, notices, bid and award records, contract file, and audit manifest.

### LIFE-151 — Retention

The procurement record MUST map to an approved retention schedule. Legal holds override disposition.

### LIFE-152 — Reporting

The system MUST support applicable procurement monitoring, contract implementation, annual-plan, and other reporting from governed records while preserving the underlying evidence and source version.

### LIFE-153 — Integrity anchoring

The system MAY anchor document hashes, workflow evidence, or periodic roots to MultiChain. The anchor MUST be supplementary and MUST NOT be presented as the canonical record or proof of workflow state.

## 19. Build order

### LIFE-160 — First vertical slice

The first complete vertical slice MUST be:

```text
PPMP
→ Indicative APP
→ BAC recommendation/resolution
→ HoPE approval
→ signed final APP
→ issued and acknowledged
```

It MUST exercise structured authoring, official GPPB form handling, APP linkage, approval and signature separation, issuance, acknowledgement, audit reconstruction, and rule-pack pinning.

### LIFE-161 — Following increments

The recommended sequence after the vertical slice is:

1. procurement initiation and mode selection;
2. pre-procurement conference and readiness;
3. publication, pre-bid conference, and bid bulletins;
4. eligibility and submission intake;
5. opening and evaluation;
6. post-qualification and award;
7. contract implementation;
8. protest and reconsideration;
9. suspension and blacklisting;
10. records, search, retention, and reporting depth.

### LIFE-162 — Parallel procurement modes

SVP and direct-contract workflows SHOULD be implemented after the common foundation because GPPB Resolution No. 10-2026 provides mode-specific documentary requirements. Each mode MUST have its own verified rule pack and acceptance suite.

### LIFE-163A — Direct Contracting, Direct Acquisition, and Repeat Order controls

The platform MUST implement these modes as distinct, rule-pack-driven workflows:

- **Direct Contracting (IRR §31):** the BAC sends an RFQ or pro-forma invoice with terms of sale to the identified supplier, may conduct simplified negotiations to establish technical, legal, and financial capability at the most advantageous price, and recommends award under IRR §66. The case MUST retain the documentary basis for the applicable §31.2 ground.
- **Direct Acquisition (IRR §32):** the case MUST remain within the IRR §32.1 scope and the currently effective ABC threshold, be reflected in the APP, and use a source with legal, technical, and financial capacity. The workflow MUST retain evidence of PhilGEPS registration where the HoPE relies on that basis, and compliant invoice/delivery evidence.
- **Repeat Order (IRR §33):** the prior procurement, award, Notice to Proceed, original unit price, current market price, and quantities MUST be linked. Unit price must be equal to or lower than both the original contract price and prevailing market price; the order must be within six months from the original Notice to Proceed unless GPPB approved an exception; and it must not exceed 25% of each original item's quantity, whose goods must be quantifiable, divisible, and have at least four units per item. The case MUST include the End-User Purchase Request and BAC recommendation.

The effective thresholds and any later GPPB adjustments MUST be activated from reviewed data, not embedded as permanent constants.

### LIFE-163 — Small Value Procurement controls

For SVP, the platform MUST implement IRR §34 as a separate mode workflow:

- the End-User requests SVP from the BAC with technical specifications, scope of work or terms of reference, ABC, and other terms;
- for ABC at or below ₱200,000, no posting is required; above ₱200,000, the RFQ/RFP is posted for three calendar days on PhilGEPS, the PE website if available, and a conspicuous place on PE premises;
- the BAC sends the RFQ/RFP to at least three suppliers, contractors, or consultants of known qualifications, while allowing any respondent to the required posting to participate;
- one quotation is sufficient for evaluation only where the amount does not exceed ₱2,000,000;
- where no quotation is received, any extension to obtain at least one quotation must not extend beyond the validity of the appropriation;
- a pre-bid conference is discretionary;
- the Abstract of Quotations or Ratings is prepared after the deadline; and
- the BAC recommends award to the HoPE using LCRB, MEARB, MARB, or HRRB as applicable.

The municipality SVP ceiling of ₱400,000 and the ₱200,000 ceiling for fourth- and fifth-class municipalities in IRR §34.2 MUST be represented as versioned, reviewable threshold data. The GPPB is authorized to adjust these thresholds, so activation MUST verify the currently effective official schedule rather than hard-code an unchangeable value.

The full IRR §34.2 LGU schedule MUST be represented as versioned threshold data: 1st and 2nd Class — ₱2,000,000 Province, ₱2,000,000 City, ₱400,000 Municipality; 3rd Class — ₱2,000,000 / ₱1,600,000 / ₱400,000; 4th Class — ₱1,600,000 / ₱1,200,000 / ₱200,000; 5th Class — ₱1,200,000 / ₱800,000 / ₱200,000; Barangays — ₱100,000. These ceilings apply to Goods, Infrastructure Projects, and Consulting Services.

### LIFE-163B — Negotiated Procurement conditions

Where Negotiated Procurement is selected, the platform MUST record the applicable IRR §35 instance and enforce its conditions, including for the Two Failed Biddings instance under IRR §35.1.1: the mandatory BAC review of terms, conditions, specifications, and cost estimates; revision of eligibility requirements, technical specifications, or terms of reference and any ABC adjustment subject to required approvals, with the ABC not increased by more than 20% of the ABC for the last failed bidding; invitation of at least three suppliers, contractors, or consultants including those disqualified in previous biddings, while allowing respondents to required postings to participate; equal communication of all negotiation information with equal time and opportunity; request of best offers on a specified date after negotiations; and recommendation of the S/LCRB or MEARQ for Goods and Infrastructure Projects or the S/HRRP for Consulting Services, with award made under IRR §66.

For emergency cases under IRR §35.2, the platform MUST record the applicable ground—imminent danger to life or property during a state of calamity, time of the essence arising from natural or man-made calamities, or other causes where immediate action is necessary to prevent damage or loss of life or property or to restore vital public services, infrastructure facilities, and other public utilities—and MUST apply the IRR §35.2.1 rule that documentary requirements such as an expired Mayor's permit with official receipt for renewal and an unnotarized Omnibus Sworn Statement are accepted as basis for award subject to submission after award of the contract but before payment.

## 20. Lifecycle acceptance summary

A release satisfies the lifecycle specification when it can demonstrate:

1. a procurement cannot proceed without the required APP linkage;
2. the correct pre-procurement conference requirement is selected from the pinned ABC and category;
3. the award criterion is locked before publication;
4. every publication has exact artifact and channel evidence;
5. a deadline calculation is reproducible;
6. a failed bidder's envelope is held during timely reconsideration;
7. the protest fee uses the official IRR §83.2 schedule;
8. award is blocked until a protest requiring pre-award resolution is decided;
9. a sanction decision, reconsideration, and GPPB posting are tracked separately;
10. an amendment creates a new version without rewriting historical decisions.

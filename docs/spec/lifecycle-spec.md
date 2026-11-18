# ProcuChain Lifecycle Spec (RA 12009 IRR, Municipal Scope)

> **Status:** Superseded working draft — retained for provenance and comparison only.
> **Canonical specification:** [Procurement Lifecycle Specification](procurement-lifecycle-spec.md)
> **Canonical entry point:** [Specification Index](README.md)
>
> This draft was reconciled against the official IRR in November 2026. Its summaries of protest fees, suspension/blacklisting periods, Rule numbering, and several other legal details are not authoritative. Do not implement from this file.

Foundation spec for the overhaul. Every procurement phase from planning to reporting, derived from `docs/ngpa/` Rules I–XXIII, with entry/exit gates, timers, calculators, data model, and acceptance criteria. Municipal-first: LGU roles, Sanggunian budgets, SVP-priority build order.

Design principles:

1. **No phase starts unless entry gates pass.** Gates are enforced in code, not policy memos.
2. **Structured data over scans.** Anything the IRR computes on (prices, dates, scores) is a field, not a PDF.
3. **Every clock is a timer.** All IRR deadlines run in the clock engine with tolling on protest/suspension/EPA.
4. **Rule-version pinning.** Each procurement freezes `applicable_irr_version` at commencement (ITB publication / REOI issuance / RFQ sending). Amendments never rewrite history.

## Actors and roles

| Role | IRR source | System powers |
|---|---|---|
| LCE (HoPE for LGU) | Rule I Sec. 4, Rule V | Approves APP/amendments, awards, NOA, sanctions, suspensions; 10-day award decision, 7-day protest decision |
| BAC (5 or 7, LCE-designated; Chair + Vice + members + alternates; 1-yr terms, max 3 consecutive) | Rule V Sec. 41–42 | Recommends mode/award, publishes ITB/REI, conducts conferences, evaluates, post-qualifies, resolves MRs |
| BAC Secretariat / Procurement Unit | Rule V Sec. 44 | Consolidates PPMP→APP, custody of records, publishes opportunities and NOAs, milestone monitoring |
| TWG | Rule V | Technical evaluation support |
| End-User / Implementing Unit | Rule I–II | Prepares PPMP, specs/TOR, market scoping record; mandatory review on failure of bidding |
| Observers (COA + ≥2: private group + CSO/PO) | Rule V Sec. 43 | Invited ≥5 days before activity; reports within 7 days after; confidentiality-bound |
| Bidders / suppliers | Rules III, VIII | Submit bids, disclosures, post-qual packets, protests; bound by validity/security rules |
| GPPB / PhilGEPS / PS-DBM | Rules III, XIX, XXIII | Forms versions, blacklist registry, APP submission target, annual report recipient |

Existing `UserRole` (`admin`, `bac_secretariat`, `bac_chairman`, `hope`) maps with `hope` = LCE.

## Master data (seeded, versioned)

* BAC roster with rank, seat type (regular/provisional/alternate), term start/end, term count; block actions by ineligible/term-expired members.
* Supplier registry mirror: PhilGEPS Platinum status + expiry, 6 eligibility files, contact email, blacklist flags (PE scope vs national).
* GPPB forms/manuals versions; sector→certifier table (Rule XII Sec. 76); Tatak Pinoy sector flags; SVP cap matrix (municipality 1st–3rd ₱400K, 4th–5th ₱200K).
* APP year record; rule-version catalog for pinning.

## Phase 1 — Planning (PPMP → APP)

**Entry:** budget preparation cycle opens. **Exit:** LCE-approved APP; procurements may initiate only against APP line items (Sec. 7.8 hard block).

Steps: End-User prepares PPMP (project, quantity, specs/TOR, mode, schedule, ABC, fund source) → offices evaluate merit → Secretariat consolidates Indicative APP (11 required fields a–k) → BAC recommends mode → LCE approves Indicative → revision after NEP/expenditure program → post updated Indicative on PE website (EPA) → final PPMP on appropriation finality → final APP → BAC recommends → LCE approves → post on PE website + submit to GPPB **on or before Jan-31**.
Amendments follow the same recommend→approve path. Multi-year items require MYCA/board/Sanggunian authority **before commencement**. EPA items capped at Indicative APP amount, award blocked until funding + final APP.

**Gates:** APP-line linkage on initiation; ABC ≤ APP amount; MYCA check for multi-year.
**Artifacts:** PPMP versions, Indicative/final APP versions, approval records, posting proofs, GPPB submission receipt, market scoping record, brand-name justifications.
**Acceptance:** initiation without an APP line is rejected; amendment history is complete; Jan-31 deadline tracked with reminders.

## Phase 2 — Pre-procurement conference

**Entry:** APP line item released. **Exit:** locked award criterion + schedule + readiness checklist.

Mandatory for every procurement; optional only ≤₱5M goods / ≤₱10M infra / ≤₱2M consulting. Attendees: BAC + TWG + Secretariat + End-User + consultants. Confirms scope/ABC/duration, APP compliance, appropriations + Bidding Docs completeness (+ detailed engineering + ROW for infra), eligibility/eval/post-qual criteria, schedule; locks **LCRB / MEARB / MARB / HRRB** before publication.

**Acceptance:** no publication without a completed pre-procurement record (or recorded option-out with threshold proof).

## Phase 3 — Publication and bulletins

**Entry:** locked criterion + complete Bidding Docs (ABC, ITB/REI, instructions, specs, eligibility, securities, contract form, 13 content items a–m).
**Exit:** submission deadline reached.

* 7 calendar days posting (competitive/dialogue/unsolicited) or 3 days minimum (limited source, two-failed, SVP >₱200K, NGO) across conspicuous premises + PhilGEPS + PE website; e-submission requires prior IT-certification gate.
* Pre-bid conference required if ABC ≥₱3M (≥12 days before deadline, not earlier than 7 days after posting); minutes ≤5 days; clarifications in writing ≥10 days before deadline with bulletin response ≥7 days before; initiative bulletins ≥7 days before; pre-bulletin bidders notified and allowed to modify/withdraw.
* SVP: RFQ posted 3 days (except ≤₱200K), sent to ≥3 suppliers; optional pre-bid at BAC discretion.

**Acceptance:** posting proofs stored per channel; deadline math validated; bulletin windows enforced; bidder notifications logged.

## Phase 4 — Eligibility and receipt

**Entry:** deadline open. **Exit:** abstract of bids + 7-day bidder notifications.

Two sealed envelopes (technical+eligibility / financial); Platinum + category packets (SLCC ≥50% ABC within 10 years, NFCC `[(CA−CL)×15] − outstanding ≥ ABC` or LoC ≥10%, JVA/PCAB as applicable, OSS attestations incl. sworn disclosure of relations to 3rd degree). Max submission windows: goods 45 / infra ≤₱50M 50 / >₱50M 65 / consulting 75 days. Late bids rejected and logged. Bid security: 2% cash/check, 5% bank/surety; validity ≤120 days. Consulting short-list 3–7 within 20 days. Mandatory pre-acceptance **PhilGEPS blacklist verify + log**.

**Acceptance:** NFCC/SLCC/validity/security calculators; no acceptance of blacklisted or disclosure-less bids; mid-process DQ flaggable.

## Phase 5 — Evaluation

**Entry:** opening (immediately after deadline; postponement = sealed + rescheduled with notice).
**Exit:** ranked bidders + HoPE approvals where required.

* LCB: completeness → arithmetical corrections (figures→words, unit×qty, actual sums, BOQ, bid form precedence) → tax-inclusive equal footing → rank ascending, >ABC disqualified. ≤7 days.
* MEAB: quality first (catalogued criteria, bias elimination unless collegial), price opened only above minimum score, weighted 15–40% price; HoPE approves within 2 days. ≤14 days.
* MARB: highest technical's financial opened; accept if ≤ABC. Consulting QBE/QCBE with technical ranking → HoPE 2-day approval → financial opening (3 days) → ≤10-day negotiations; no key-personnel replacement pre-award except certified exceptions. ≤21 days.
* Comms ban BAC/Secretariat/TWG/observers ↔ bidders until NOA; only logged BAC-initiated written clarifications. Green weight overlays per Rule XII; domestic preference per Rule XIII (Tatak mandatory in-sector within 10 years, else 25% margin with certification gate).

**Acceptance:** scoring sheets per evaluator per criterion; weight-range validation; approval timers; results posted ≥7 days (MEAB/consulting).

## Phase 6 — Post-qualification and award

Post-qual: LCB/MAB/MEAB or HRB notified → 5 days to submit (Platinum support, tax returns preceding quarter, licenses) → 12-day verification (legal/technical/financial) → declare responsive → award at min(submitted, calculated/negotiated). Failure cascades to 2nd/3rd preserving order; reconsideration interplay handled. Failure of bidding → mandatory End-User review → revise → re-bid with re-publication → after 2nd failure, negotiated track.
Award: BAC resolution + 5-item packet → loser notices within 3 days → HoPE decides within 10 (written grounds if disapproved; 3-day reconsideration, 7-day finality) → NOA → winner complies within 10 (JVA/PCAB/SEC docs, performance security 5%/10%/30%, signed contract). Overall opening→award ≤60 days (GPPB-adjustable to 90) with tolling. Single-bid failure → disqual + forfeit + rebid; multi-bid → cascade.
Performance security valid to final acceptance; variations capped (goods ≤10%, HoPE to 20%; infra cumulative +10%, exceptional 20%, else new procurement; consulting +20%). LD 1/10-of-1%/day capped at 10% with auto rescind/terminate option; infra retention 10%→5%; advances capped with guarantees; termination file per procedure (7-day verification, 7-day show cause, 10-day decision).

**Acceptance:** SLA tracker with tolling; cascade queue; LD/slippage/retention ledgers; termination checklist.

## Phase 7 — Protests, sanctions, closure

* Protests: MR within 3 days of notice → BAC resolves in 7 (filing tolls downstream clocks) → escalation within 7 of denial/inaction → HoPE decides in 7 (final, executory; corrective action: re-evaluate/re-bid/re-award). Fee = max(1% ABC, ₱50,000) escrowed, refunded within 15 days if meritorious. Filing does not suspend unless HoPE orders (prima facie irregularity / government interest).
* Sanctions/blacklisting: notice → ≥7-day explanation window → BAC recommendation within 15 → HoPE decision within 7 → MR 7 → GPPB appeal 15. Suspension ≤90 days (bidding only, contracts continue, auto-lift). Blacklist: mandatory 2yr/perpetual, discretionary 1/2yr/perpetual from finality; PE vs national scope; terminate ongoing + forfeit; propagate to related entities; mandatory pre-bid verify.
* Closure: final acceptance, security release (≤15 days post-expiry, blocked on open claims), Annual Procurement Report auto-drafted and submitted within 90 days of FY-end (non-submission is itself sanctionable).

## Data model (new tables; existing mirror untouched)

* `annual_procurement_plans` (year, type indicative/final, status, approved_by/at, gppb_submitted_at) + `ppmp_items` (end_user, description, category, mode, quantity, abc, fund_source, specs_ref, epa_flag, status, versions) + `app_line_items` (plan, ppmp ref, ABC ceiling, mode, inclusion proof). `procurements.app_line_item_id` FK (nullable during migration, mandatory for new).
* `pre_procurement_conferences` (procurement, attendees, readiness checklist JSON, award_criterion, option_out_reason) ; `publications` (channel, posted_at, proof, period_days) ; `bulletins` + `bidder_notifications`.
* `bid_submissions` (bidder, envelopes checklist JSON, security instrument, validity_until, disclosure_ref, blacklist_check_at) ; `evaluations` (scores JSON per evaluator, corrections log, ranking, approvals) ; `post_qual_attempts` (rank, notice_at, due_at, verdict, grounds).
* `award_trackers` (resolution_at, loser_notice_at, hope_decision_at, noa_at, compliance_due_at, sla_deadline, tolling_events JSON) ; `contract_securities` (type, amount, validity, status) ; `ld_ledger`, `retention_ledger`, `variation_orders`.
* `protests` (stage MR/HoPE, filed_at, deadlines, fee_escrow, tolling flag, decision, corrective_action) ; `sanction_cases` (track, SLA timestamps, scope, related entities JSON, registry sync) ; `annual_procurement_reports` (FY, payload JSON, submitted_at).
* Catalogs: `bac_terms`, `supplier_registry_cache`, `gppb_form_versions`, `sector_certifiers`, `tatak_sectors`, `svp_caps`, `rule_versions` (+ `procurements.applicable_irr_version`).
* Ledger: every phase transition publishes to existing streams (`procurement.status/events`) with actor + timestamp + inputs hash; OTS-anchored daily roots per trust plan.

## Build order (municipal-first)

1. APP module + initiation linkage. 2. Pre-procurement lock + SVP/RFQ track. 3. Publication/bulletin tracking + blacklist pre-check. 4. Evaluation scoring (LCB first). 5. Post-qual cascade + award SLA tracker. 6. Securities/LD/retention ledgers. 7. Protest machine. 8. Sanctions registry. 9. Annual report generator. 10. Competitive-dialogue/unsolicited edge tracks.

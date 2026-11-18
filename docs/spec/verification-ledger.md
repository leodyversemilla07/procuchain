# Verification Ledger: Official IRR Traceability

> **Status:** Build target
> **Verification date:** 2026-11-17
> **Companion documents:** [Specification Index](README.md), [Compliance Specification](compliance-spec.md)

## 1. Purpose

This ledger records the traceability between the cited sections of the official IRR of RA 12009 and the requirements in the [Compliance Specification](compliance-spec.md) (`CMP`), [Procurement Lifecycle Specification](procurement-lifecycle-spec.md) (`LIFE`), [Platform Specification](platform-spec.md) (`PLAT`), and [Acceptance Criteria](acceptance-criteria.md) (`AC`).

It answers two questions:

1. Which official provision supports each legal control?
2. Which official text was read to verify that provision?

It is a verification record, not a source of law and not a legal opinion. The authoritative sources and their precedence are defined in the [Compliance Specification](compliance-spec.md).

## 2. Source of record

| Field | Value |
| --- | --- |
| Instrument | IRR of RA 12009 |
| Approval | GPPB Resolution No. 02-2025 |
| Publisher | Government Procurement Policy Board |
| Official URL | `https://www.gppb.gov.ph/wp-content/uploads/2025/02/IRR-of-RA-No.-12009.pdf` |
| Retrieved | 2026-11-16 |
| Effective date | 25 February 2025 |
| PDF SHA-256 | `3ffcf5ef9de73d2b3642a8840a105ab4591b0443220b7a3894e4b5909fb5c041` |
| Extracted text | `pdftotext -layout irr.pdf irr.txt`; 8,968 lines |

The SHA-256 value identifies the exact PDF that was read. The working extraction is intentionally untracked; this ledger and the source hash are the durable record. A replacement PDF, a later GPPB correction, or a different extraction tool MUST be treated as a new source version under the legal-change workflow in the Compliance Specification.

## 3. Reproduction procedure

1. Download the PDF from the official URL in section 2.
2. Compute its SHA-256 and confirm it matches the value in section 2.
3. Extract the text with `pdftotext -layout` (Poppler). The line ranges in section 4 refer to that extraction; a different tool, page size, or line-ending conversion may shift them.
4. Locate the section by heading and line range, then read the full section text rather than only the first line.
5. Confirm that each requirement ID in the row still matches the provision it cites. Requirements are normative in the linked specification; this ledger is evidence of what was checked.
6. If a provision, requirement, or section mapping changes, update this ledger in the same change set. The change-governance rules in the [Specification Index](README.md) apply.

## 4. Section traceability

The table lists every IRR section cited by at least one active `CMP`, `LIFE`, `PLAT`, or `AC` requirement on the verification date. An em dash (—) means no requirement in that family cites the section directly. A requirement may cite several sections, so the same ID can appear in more than one row.

| IRR § | Section | Extracted lines | CMP controls | LIFE requirements | PLAT requirements | AC criteria |
| --- | --- | --- | --- | --- | --- | --- |
| §4 | Scope and Application | 95–160 | CMP-100B2 | LIFE-003 | — | AC-091B3 |
| §5 | Definition of Terms | 161–376 | CMP-100B1 | LIFE-022 | — | AC-091B2 |
| §7 | Strategic Procurement Planning and Budgeting Linkage | 397–643 | CMP-100, CMP-100A | LIFE-023, LIFE-047 | — | AC-090, AC-091A |
| §8 | Detailed Engineering for the Procurement of Infrastructure Projects | 644–879 | CMP-101A | LIFE-043 | — | AC-091B1 |
| §9 | Multi-Year Contracting | 880–911 | CMP-101B | LIFE-043 | — | AC-091B1 |
| §10 | Market Scoping | 912–1004 | CMP-101 | LIFE-041 | — | — |
| §11 | Specifications | 1005–1025 | — | LIFE-043 | — | — |
| §12 | Early Procurement Activities | 1026–1055 | CMP-102 | LIFE-048 | — | AC-091 |
| §20 | Procurement by Electronic Means | 1396–1691 | CMP-111, CMP-125, CMP-125A | — | PLAT-093 | AC-060 |
| §22 | Security, Integrity, and Confidentiality | 1700–1724 | CMP-125C, CMP-126 | — | — | — |
| §23 | Interconnectivity Systems between Government Agencies | 1725–1767 | CMP-125B | — | — | — |
| §26 | Modes of Procurement | 1838–1881 | CMP-100B, CMP-100B3 | LIFE-051 | — | AC-091B |
| §27 | Competitive Bidding | 1882–1909 | CMP-100B4 | LIFE-051 | — | AC-091E |
| §28 | Limited Source Bidding | 1910–1975 | CMP-100H, CMP-100H1, CMP-100H2 | LIFE-051 | — | AC-091E |
| §29 | Competitive Dialogue | 1976–2101 | CMP-100I, CMP-100I1, CMP-100I2, CMP-100I3 | LIFE-051 | — | AC-091E |
| §30 | Unsolicited Offer with Bid Matching | 2102–2281 | CMP-100J, CMP-100J1, CMP-100J2 | LIFE-051 | — | AC-091E |
| §31 | Direct Contracting | 2282–2327 | CMP-100D, CMP-100D1, CMP-100D2 | LIFE-051, LIFE-163A | — | AC-091D, AC-091D1 |
| §32 | Direct Acquisition | 2328–2374 | CMP-100E, CMP-100E1, CMP-100E2 | LIFE-163A | — | AC-091D, AC-091D1 |
| §33 | Repeat Order | 2375–2424 | CMP-100F, CMP-100F1 | LIFE-163A | — | AC-091D, AC-091D1 |
| §34 | Small Value Procurement | 2425–2506 | CMP-100C, CMP-100C1, CMP-100C2 | LIFE-163 | — | AC-091C, AC-091C1 |
| §35 | Negotiated Procurement | 2507–3435 | CMP-100G, CMP-100G1, CMP-100G2, CMP-100G3, CMP-114I | LIFE-051, LIFE-097, LIFE-163B | — | AC-091G, AC-101B |
| §36 | Direct Sales | 3436–3507 | CMP-100K, CMP-100K1 | — | — | AC-091F |
| §37 | Direct Procurement for Science, Technology, and Innovation | 3508–3546 | CMP-100L, CMP-100L1 | LIFE-051 | — | AC-091F |
| §38 | Video Recording of All Procurement-Related Conferences for Competitive Bidding | 3547–3584 | CMP-109A | LIFE-064A | — | AC-092A |
| §39 | Prohibition on Splitting of Government Contracts | 3585–3603 | CMP-109B | LIFE-064B | — | AC-092B |
| §40 | Procurement of CSE | 3604–3751 | CMP-109C | LIFE-064C | — | AC-092B |
| §41 | The BAC and its Composition | 3752–3899 | CMP-103 | LIFE-011 | — | AC-032 |
| §42 | Functions of the BAC | 3900–3981 | CMP-104, CMP-106A | LIFE-011 | PLAT-034 | AC-033, AC-034 |
| §43 | Observers | 3982–4083 | CMP-105 | LIFE-015 | — | — |
| §44 | Procurement Unit | 4084–4137 | CMP-106, CMP-106B | LIFE-012 | — | — |
| §47 | Form and Content of Bidding Documents | 4195–4279 | CMP-107, CMP-107B, CMP-107C, CMP-107D | LIFE-071 | — | AC-094 |
| §48 | Access to Information | 4280–4305 | CMP-108, CMP-108A | LIFE-072 | PLAT-145, PLAT-153 | AC-095 |
| §49 | Pre-Procurement Conference | 4306–4394 | CMP-109, CMP-110 | LIFE-060, LIFE-061, LIFE-062, LIFE-063 | — | AC-092, AC-093 |
| §50 | Publication and Contents of the Invitation to Bid | 4395–4535 | CMP-100H2, CMP-107A, CMP-111, CMP-111D, CMP-111E, CMP-111F, CMP-111G, CMP-114G | LIFE-051, LIFE-073, LIFE-097 | — | AC-096, AC-096C, AC-096D, AC-096E, AC-101B |
| §51 | Pre-bid Conference | 4536–4632 | CMP-111, CMP-111A, CMP-111B, CMP-111C | LIFE-076, LIFE-077 | — | AC-064, AC-096, AC-096A, AC-096B |
| §52 | Eligibility Requirements for the Procurement of Goods, Infrastructure Projects, and Consulting Services | 4633–5005 | CMP-100I1, CMP-100J1, CMP-112, CMP-112G, CMP-112H, CMP-112I, CMP-112J, CMP-112K, CMP-112L, CMP-112M | LIFE-051, LIFE-080 | — | AC-097E, AC-097F, AC-097G |
| §53 | Short-Listing for Consulting Services | 5006–5052 | CMP-100I1 | LIFE-087 | — | — |
| §54 | Submission and Receipt of Bids | 5053–5260 | CMP-112, CMP-112A, CMP-112B, CMP-112C, CMP-114B, CMP-114C | LIFE-083 | — | AC-097, AC-097A, AC-097B, AC-097H |
| §55 | Modification and Withdrawal of Bids | 5261–5290 | CMP-111C, CMP-114D, CMP-114E | LIFE-077, LIFE-084 | — | AC-064, AC-097I |
| §56 | Bid Security | 5291–5379 | CMP-112D | LIFE-085 | — | AC-097C |
| §57 | Bid Validity | 5380–5393 | CMP-112E | — | — | AC-097C |
| §58 | Bid Opening | 5394–5433 | CMP-112, CMP-113A | — | — | AC-098A |
| §59 | Preliminary Examination of Bids | 5434–5493 | CMP-113B, CMP-114 | LIFE-080 | — | AC-098B |
| §60 | Ceiling for Bid Prices | 5494–5534 | CMP-100B1, CMP-113 | LIFE-022, LIFE-094 | — | AC-098 |
| §61 | Bid Evaluation for the Procurement of Goods and Infrastructure Projects | 5535–5699 | CMP-100I3 | — | — | — |
| §63 | Objective and Process of Post-Qualification | 5888–5984 | CMP-100I3, CMP-114A | LIFE-095 | — | AC-100 |
| §64 | Failure of Bidding | 5985–6027 | CMP-114, CMP-114F, CMP-114G, CMP-114H, CMP-114I | LIFE-097 | — | AC-101B |
| §65 | Single Calculated/Rated/Economically Advantageous and Responsive Bid Submission | 6028–6057 | CMP-114J | LIFE-097A | — | AC-101C |
| §66 | Notice and Execution of Award | 6058–6145 | CMP-100D2, CMP-115, CMP-116, CMP-117, CMP-117A, CMP-117B, CMP-118 | LIFE-100, LIFE-102, LIFE-103, LIFE-104, LIFE-106, LIFE-163A, LIFE-163B | — | AC-074, AC-075, AC-076, AC-100, AC-101, AC-101A, AC-102 |
| §67 | Period of Action on Procurement Activities | 6146–6197 | CMP-119, CMP-119A, CMP-119B, CMP-119C | — | — | AC-072, AC-073 |
| §68 | Performance Security | 6198–6294 | CMP-118, CMP-118D, CMP-118E, CMP-118F, CMP-118G | LIFE-113 | — | AC-102, AC-102D |
| §69 | Failure to Enter into Contract and Post Performance Security | 6295–6332 | — | LIFE-106 | — | AC-100 |
| §70 | Reservation Clause | 6333–6359 | CMP-107C, CMP-117, CMP-117C | — | — | AC-101 |
| §71 | Contract Implementation and Termination | 6360–7334 | CMP-118, CMP-118A, CMP-118B, CMP-118B1 | LIFE-112 | — | AC-102A, AC-102B, AC-102B1 |
| §76 | Registration of Certain Sectors | 7595–7627 | CMP-109D, CMP-118E | LIFE-113 | — | — |
| §81 | Disclosure of Relations | 7714–7823 | CMP-112F, CMP-114C, CMP-123D | LIFE-080, LIFE-136 | — | AC-097D |
| §82 | Declaration of Beneficial Ownership | 7824–7847 | CMP-112F, CMP-114C | LIFE-080 | — | AC-097D |
| §83 | Protests on Decisions of the BAC | 7848–7923 | CMP-120, CMP-121, CMP-121A, CMP-121B | LIFE-086, LIFE-104, LIFE-120, LIFE-122, LIFE-123, LIFE-124, LIFE-124A | — | AC-074, AC-077, AC-078, AC-104, AC-105, AC-105B, AC-105C |
| §84 | Resolution of Protests | 7924–7965 | CMP-122A | LIFE-125, LIFE-126 | — | AC-105A |
| §85 | Resort to Regular Courts | 7966–7988 | CMP-122A | LIFE-126 | — | AC-105A |
| §86 | Non-interruption of the Bidding Process | 7989–7994 | CMP-122 | LIFE-107, LIFE-127 | — | AC-103 |
| §87 | Prohibition on the Issuance of Temporary Restraining Orders, Preliminary Mandatory Injunctions | 7995–8020 | CMP-124E | LIFE-125A | — | AC-108C |
| §88 | Dispute Resolution | 8021–8047 | CMP-124D | LIFE-125A | — | AC-108C |
| §89 | Contract Prices | 8048–8063 | CMP-118L | LIFE-125B | — | AC-102C3 |
| §90 | Warranty | 8064–8265 | CMP-118C, CMP-118H, CMP-118I, CMP-118J, CMP-118K | LIFE-114 | — | AC-102C, AC-102C1 |
| §97 | Liquidated Damages | 8436–8452 | CMP-124F | LIFE-115 | — | AC-102C2 |
| §98 | Administrative Liability of Private Entities | 8453–8498 | CMP-123 | — | — | — |
| §99 | Imposition of Suspension | 8499–8562 | CMP-123A, CMP-123C | LIFE-136 | — | AC-106, AC-108A |
| §100 | Imposition of Blacklisting | 8563–8651 | CMP-123D | LIFE-136 | — | — |
| §101 | Imposition of Blacklisting with Perpetual Disqualification | 8652–8669 | CMP-123A, CMP-123E | LIFE-136 | — | AC-106, AC-108A |
| §102 | Suspension of Bidder Pending Blacklisting Proceedings | 8670–8682 | CMP-123F | LIFE-137 | — | AC-107 |
| §103 | Status of Suspended or Blacklisted Bidder | 8683–8698 | CMP-123G | LIFE-138 | — | AC-108A |
| §104 | Initiation of Action; Subsequent Procedure | 8699–8749 | CMP-123B, CMP-124, CMP-124A, CMP-124B, CMP-124C | LIFE-130, LIFE-133, LIFE-135 | — | AC-074, AC-079, AC-106A, AC-108, AC-108B |
| §105 | Extent of Administrative Sanctions | 8750–8810 | CMP-123, CMP-123H, CMP-123I | LIFE-136A, LIFE-138 | — | AC-108A, AC-108B |
| §112 | Implementing Rules and Regulations and Standard Forms | 8907–8921 | CMP-128, CMP-129A | LIFE-078 | — | AC-015 |
| §113 | Transitory Provision | 8922–8935 | CMP-129, CMP-129B | — | — | — |
| §115 | Repealing Clause | 8947–8956 | CMP-129C | — | — | — |
| §117 | Effectivity | 8961–8968 | CMP-129C | — | — | — |

## 5. Sections not used as direct control sources

The following sections are not cited by an active `CMP`, `LIFE`, `PLAT`, or `AC` requirement as of the verification date:

`1, 2, 3, 6, 13, 14, 15, 16, 17, 18, 19, 21, 24, 25, 45, 46, 62, 72, 73, 74, 75, 77, 78, 79, 80, 91, 92, 93, 94, 95, 96, 106, 107, 108, 109, 110, 111, 114, 116`

These sections are not automatically gaps. They include baseline and policy statements, planning and organizational provisions, future-scope topics, and final provisions that the first release does not implement as controls. If a future requirement depends on one of them, add the citation to the requirement and to this table in the same change.

## 6. Review caveats

This ledger records traceability, not legal approval. The [Compliance Specification](compliance-spec.md) control matrix records 22 controls with a status other than plain **Verified**; those caveats remain in force. The most significant are:

- **CMP-127** — electronic signatures remain **legal review required** for each document class.
- **CMP-100, CMP-100C1, CMP-100E, CMP-100G, CMP-107, CMP-107C, CMP-111, CMP-112, CMP-114, CMP-118, CMP-118L, CMP-129** — activation depends on an approved GPPB form, manual, threshold schedule, or PE procedure.
- **CMP-100L, CMP-105, CMP-109A, CMP-109D, CMP-125A** — implementation depends on pending guidance, registry availability, or a per-instance determination.

No row in this ledger may be used to represent an unresolved question as settled law.

## 7. Maintenance

Update this ledger when:

- a new `CMP`, `LIFE`, `PLAT`, or `AC` requirement cites an IRR section that is not yet listed;
- the legal register activates a new IRR version or a GPPB issuance that changes a cited provision;
- a requirement's citation changes;
- a reviewer finds a mismatch between a requirement and the official text.

The ledger version moves with the specification suite. The PDF hash stays fixed unless the source of record is formally replaced.

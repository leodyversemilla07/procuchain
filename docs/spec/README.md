# ProcuChain eProcurement Document Platform Specification

> **Status:** Build target
> **Specification version:** 0.1
> **Date:** 2026-11-16
> **Audience:** Product owners, developers, QA, legal/GPPB reviewers, records officers, security reviewers, and future maintainers

## Purpose

This directory defines the target ProcuChain platform: a **municipal-first eProcurement document and workflow system** in which procurement documents are created, completed, reviewed, approved, routed, signed, issued, tracked, acknowledged, and retained within a governed digital process.

The target is not a line-by-line port of the current Laravel application. The current application remains useful as an implementation reference and migration source, but these specifications define a new platform centered on documents, legal controls, and durable evidence.

## Normative language

The key words **MUST**, **MUST NOT**, **REQUIRED**, **SHALL**, **SHALL NOT**, **SHOULD**, **SHOULD NOT**, **RECOMMENDED**, **MAY**, and **OPTIONAL** are to be interpreted as described in RFC 2119 and RFC 8174.

These specifications do **not** replace law, regulation, GPPB issuances, or the authority of the Procuring Entity. If this specification conflicts with an applicable authoritative source, the authoritative source controls and the conflict MUST be recorded and corrected.

## Specification map

| Document | Purpose |
| --- | --- |
| [Product Specification](product-spec.md) | Defines the vision, users, scope, outcomes, and product boundaries. |
| [Platform Specification](platform-spec.md) | Defines document authoring, versioning, workflow, approval, signature, issuance, tracking, records management, and collaboration. |
| [Compliance Specification](compliance-spec.md) | Defines legal-source governance, rule packs, hard gates, deadlines, evidence, PhilGEPS boundaries, and e-signature controls. |
| [Procurement Lifecycle Specification](procurement-lifecycle-spec.md) | Defines the RA 12009/IRR procurement lifecycle and its entry, exit, and evidence requirements. |
| [Domain Model](domain-model.md) | Defines the conceptual entities, relationships, state machines, and invariants of the target platform. |
| [Architecture Specification](architecture-spec.md) | Defines the target Rust architecture, persistence, integrations, security, migration, and deployment boundaries. |
| [Acceptance Criteria](acceptance-criteria.md) | Defines testable launch gates and traceability from requirements to verification. |
| [Verification Ledger](verification-ledger.md) | Records the official IRR sections cited, their extracted line ranges, and the CMP/LIFE/PLAT/AC requirements they support. |

## Recommended reading order

### Product and compliance reviewers

1. [Product Specification](product-spec.md)
2. [Compliance Specification](compliance-spec.md)
3. [Procurement Lifecycle Specification](procurement-lifecycle-spec.md)
4. [Acceptance Criteria](acceptance-criteria.md)
5. [Verification Ledger](verification-ledger.md)

### Implementers

1. [Product Specification](product-spec.md)
2. [Platform Specification](platform-spec.md)
3. [Domain Model](domain-model.md)
4. [Architecture Specification](architecture-spec.md)
5. [Compliance Specification](compliance-spec.md)
6. [Procurement Lifecycle Specification](procurement-lifecycle-spec.md)
7. [Acceptance Criteria](acceptance-criteria.md)
8. [Verification Ledger](verification-ledger.md)

## Authority and precedence

The target platform MUST apply requirements in this order:

1. Applicable Philippine statutes and the Constitution.
2. RA 12009 and its officially published IRR.
3. Applicable and effective GPPB resolutions, circulars, standard forms, and guidelines.
4. Other applicable laws and sector-specific rules.
5. Approved Procuring Entity policies, which may add controls but MUST NOT weaken higher authority.
6. Versioned platform configuration and workflows, which may operationalize but MUST NOT override higher authority.

The [Compliance Specification](compliance-spec.md) defines how this hierarchy is represented and enforced.

## Current authoritative baseline

As of the specification date, the minimum baseline is:

- **RA 12009:** New Government Procurement Act, effective 13 August 2024.
- **IRR of RA 12009:** approved through GPPB Resolution No. 02-2025, published 10 February 2025, and effective 25 February 2025.
- **GPPB Resolution No. 03-2025:** Standard Forms for Procurement.
- **GPPB Resolution No. 12-2025:** Procurement Mode Selection Decision Tree.
- **GPPB Resolution No. 10-2026:** Documentary Requirements and Relevant Guidelines for Specific Modes of Procurement.
- **GPPB Public Advisory No. 09-2026:** notice that an unofficial compiled IRR was withdrawn; only the officially published IRR may be treated as the IRR baseline.

The platform MUST NOT automatically treat an arbitrary website, local summary, or unofficial compilation as authoritative. Legal-source ingestion MUST use the controlled process in the [Compliance Specification](compliance-spec.md).

## Target versus current implementation

These documents specify the **target platform**.

- `app/`, `routes/`, `database/`, and the current frontend describe the present Laravel implementation.
- Existing code MAY be mined for business knowledge, test cases, terminology, and historical data.
- Existing implementation behavior MUST NOT become authoritative merely because it already exists.
- Current blockchain records MAY be imported as legacy evidence, subject to validation and manifest generation.
- A feature is complete only when it satisfies the traceability and acceptance requirements in this specification suite.

## Change governance

A proposed change to any normative specification MUST:

1. Identify affected specification IDs and linked acceptance criteria.
2. Identify affected legal provisions, templates, workflows, data entities, or integrations.
3. Be reviewed by the product owner and the designated legal/compliance owner.
4. Preserve historical rule packs, workflow versions, templates, and evidence. A change MUST NOT rewrite history.
5. Update the relevant documents and the specification version in the same change set.

Legal interpretation remains the responsibility of the Procuring Entity and its qualified legal/compliance reviewers. The platform MUST present the authority, computation, and evidence supporting each blocked or completed control so a reviewer can reconstruct the decision.

## Current decision set

The following are fixed product decisions unless this specification is formally changed:

- The target backend is written in Rust.
- The target frontend remains React and TypeScript, reusing the existing design system where useful.
- The core product is a document and workflow platform; procurement is its first major business module.
- Internally governed procurement documents originate in ProcuChain; external submissions are captured through controlled intake rather than treated as internal originals.
- Approval, signature, issuance, distribution, and acknowledgement are separate recorded actions.
- Approved or signed content is immutable; a correction creates a new version.
- ProcuChain complements mandatory PhilGEPS use; it does not replace it.
- MultiChain provides integrity anchoring and corroborating evidence; it is not the document database, workflow database, or search index.
- The first complete vertical slice is `PPMP → Indicative APP → BAC recommendation/resolution → HoPE approval → signed final APP → issued and acknowledged`.

## Specification status terminology

| Label | Meaning |
| --- | --- |
| **Verified** | Checked directly against the cited authoritative source. |
| **Implementation requirement** | Normative target-platform behavior defined by this suite. |
| **Legal review required** | Depends on interpretation, local policy, unavailable guidance, or a pending GPPB issuance. It MUST NOT be represented as settled law. |
| **Future scope** | Intended capability not required for the first release. |

## Related documentation

- [Current runtime architecture](../explanation/architecture.md)
- [Current route reference](../reference/routes.md)
- [Current database schema](../reference/database-schema.md)
- [Current blockchain schema](../reference/blockchain-schema.md)
- [Local NGPA working summaries](../ngpa/README.md) — secondary research aids, not authoritative legal sources.

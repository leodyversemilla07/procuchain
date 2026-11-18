# ProcuChain Documentation

Documentation follows the [Diátaxis](https://diataxis.fr/) structure: each page serves one user need — learning, doing, looking up, or understanding.

## Tutorials (learning)

Start here. Lessons that guide a newcomer to a working setup.

- [Quick Start](tutorials/quick-start.md) — install, configure, migrate, and run ProcuChain locally.

## How-to guides (doing)

Recipes for specific tasks. Assumes a working setup.

- [Verification Checklist](how-to/verification-checklist.md) — merge/release gate: setup check, lint, types, tests, and smoke checks.

## Reference (looking up)

Technical descriptions. Facts about the machinery.

- [Route Reference](reference/routes.md) — named route surface per role.
- [Blockchain Schema](reference/blockchain-schema.md) — MultiChain streams, keys, and address model.
- [Database Schema](reference/database-schema.md) — MySQL tables.
- [Data Dictionary](reference/data-dictionary.md) — data elements mapped to code, DB, chain, and NGPA sections.
- [Testing Guide](reference/testing.md) — test setup, commands, and CI policy.
- [NGPA Rules](ngpa/README.md) — RA 12009 IRR Rules I–XXIII, the legal reference behind workflow rules.

## Explanation (understanding)

Discussions that clarify why the system is built this way.

- [Architecture](explanation/architecture.md) — runtime layers, persistence split, workflow resolution, integrity model.
- [Reporting Subsystem](explanation/reporting-architecture.md) — reports and filtered keyword search.

## Archive (historical)

Superseded or snapshot documents. Kept for context, not guidance.

- [Implementation Summary](archive/IMPLEMENTATION_SUMMARY.md) and [Implementation Complete](archive/IMPLEMENTATION_COMPLETE.txt) — rollout tombstones.
- [Database Redesign Analysis](archive/DATABASE_REDESIGN_ANALYSIS.md) — executed migration plan (blob → normalized tables).
- [Semantic Search Shim](archive/SEMANTIC_SEARCH_README.md) — legacy filename note (search is keyword, not vector).
- [Source Dump](archive/PROCUCHAIN_SOURCE_DOCS.md) — frozen code snapshot, not a reference.

## Specification (build target)

The canonical entry point is the [ProcuChain Specification Index](spec/README.md).

- [Product Specification](spec/product-spec.md) — product vision, users, scope, outcomes, and boundaries.
- [Platform Specification](spec/platform-spec.md) — document authoring, versioning, workflow, approval, signature, issuance, tracking, records, and collaboration.
- [Compliance Specification](spec/compliance-spec.md) — legal-source governance, rule packs, hard gates, deadlines, evidence, PhilGEPS, and e-signature controls.
- [Procurement Lifecycle Specification](spec/procurement-lifecycle-spec.md) — reconciled RA 12009/IRR procurement lifecycle with gates, evidence, and legal citations.
- [Domain Model](spec/domain-model.md) — target entities, relationships, state machines, and invariants.
- [Architecture Specification](spec/architecture-spec.md) — target Rust architecture, persistence, integrations, security, migration, and deployment.
- [Acceptance Criteria](spec/acceptance-criteria.md) — testable launch gates and requirement traceability.
- [Verification Ledger](spec/verification-ledger.md) — official IRR sections cited, extracted line ranges, and requirement traceability.
- [Superseded Lifecycle Draft](spec/lifecycle-spec.md) — earlier working draft retained for provenance; not authoritative.

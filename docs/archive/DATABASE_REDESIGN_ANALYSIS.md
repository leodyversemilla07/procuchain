# ProcuChain Codebase Analysis

## Current Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CURRENT FLOW                                       │
└─────────────────────────────────────────────────────────────────────────────┘

User Action
    │
    ▼
┌──────────────────┐
│ BlockchainWriteJob│
└────────┬─────────┘
         │
         ├──▶ Publish to MultiChain Stream
         │
         └──▶ BlockchainRecordSyncService.upstream()
                    │
                    └──▶ procurement_records (single table with data_json)
                              │
                              ▼
                    DataIntegrityVerifier
                    IntegrityVerificationService
                              │
                              └──▶ integrity_audit_logs
```

## What Exists Now

### Backend Services
| Service | Purpose | Status |
|---------|---------|--------|
| `BlockchainRecordSyncService` | Sync blockchain → DB | ✅ Works with procurement_records |
| `DataIntegrityVerifier` | Hash verification | ✅ Works with procurement_records |
| `IntegrityVerificationService` | Full verification + repair | ✅ Works with procurement_records |
| `BlockchainAuditTrailService` | Audit trail | ✅ Works |

### Frontend Pages
| Page | Purpose | Status |
|------|---------|--------|
| `integrity-breaches.tsx` | List breaches | ✅ Uses procurement_records |
| `breach-detail.tsx` | View breach details | ✅ Shows tamper comparison |
| `integrity-audit-logs.tsx` | View audit logs | ✅ Uses integrity_audit_logs |
| `verification-report.tsx` | Verification reports | ✅ Works |

### Database Tables
| Table | Purpose | Status |
|-------|---------|--------|
| `procurement_records` | Blockchain mirror (data_json) | ✅ Current source |
| `integrity_audit_logs` | Violation tracking | ✅ Append-only |
| `procurements` | NEW: Normalized PR data | ✅ Created |
| `procurement_stages` | NEW: Stage history | ✅ Created |
| `procurement_documents` | NEW: Documents | ✅ Created |
| `procurement_events` | NEW: Events | ✅ Created |
| `procurement_corrections` | NEW: Corrections | ✅ Created |
| `files` | NEW: File metadata | ✅ Created |

## Requirements Coverage

| # | Requirement | Current Implementation | Gap |
|---|-------------|----------------------|-----|
| 1 | Detect unauthorized modifications | ✅ Hash comparison in DataIntegrityVerifier | None |
| 2 | Detect deleted records | ✅ Chain-vs-mirror check in IntegrityVerificationService | None |
| 3 | Compare DB vs blockchain | ✅ fetchChainData() method | None |
| 4 | Generate violation reports | ✅ generateReport() in IntegrityVerificationService | None |
| 5 | Restore from blockchain | ✅ restoreViolation() + repairFromChain() | None |
| 6 | Audit trail of recovery | ✅ IntegrityAuditLog (append-only) | None |

## The Problem

The current system works with `procurement_records` table containing `data_json` blob. We've created new normalized tables but they're **empty** and **not connected** to the existing flow.

## What Needs to Change

### Phase 1: Data Sync Service (Backend)
Create a new service that:
1. Listens to `BlockchainRecordSyncService::upstream()`
2. Extracts structured data from `data_json`
3. Populates normalized tables (procurements, procurement_stages, etc.)
4. Computes and stores `data_hash` and `blockchain_hash`

### Phase 2: Updated Verification (Backend)
Update `IntegrityVerificationService` to:
1. Verify normalized tables (not just procurement_records)
2. Compare normalized data against blockchain
3. Support restoration to normalized tables

### Phase 3: Updated Frontend
Update frontend pages to:
1. Query `procurements` table instead of `procurement_records.data_json`
2. Show normalized data in lists and dashboards
3. Keep integrity verification UI working

### Phase 4: Migration
1. Migrate existing data from `procurement_records` to normalized tables
2. Verify data integrity after migration
3. Switch frontend to use new tables

## Recommended Implementation Order

```
1. Create SyncService for normalized tables
   └── Extract data from data_json → populate procurements, stages, etc.

2. Update IntegrityVerificationService
   └── Add verification for normalized tables

3. Update frontend pages
   └── Query procurements instead of procurement_records

4. Run data migration
   └── Migrate existing procurement_records → normalized tables

5. Verify everything works
   └── Test integrity verification with new tables
```

## Key Files to Modify

### Backend
- `app/Services/BlockchainRecordSyncService.php` - Add normalized table sync
- `app/Services/IntegrityVerificationService.php` - Add normalized table verification
- `app/Models/Procurement.php` - NEW: Eloquent model for procurements table
- `app/Models/ProcurementStage.php` - NEW: Eloquent model
- `app/Models/ProcurementDocument.php` - NEW: Eloquent model
- `app/Models/ProcurementEvent.php` - NEW: Eloquent model

### Frontend
- `resources/js/pages/admin/integrity-breaches.tsx` - Update to use procurements
- `resources/js/pages/admin/breach-detail.tsx` - Update to use procurements
- `resources/js/pages/admin/integrity-audit-logs.tsx` - Keep as-is (uses integrity_audit_logs)

## Data Flow After Migration

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NEW FLOW                                            │
└─────────────────────────────────────────────────────────────────────────────┘

User Action
    │
    ▼
BlockchainWriteJob
    │
    ├──▶ Publish to MultiChain Stream
    │
    └──▶ BlockchainRecordSyncService.upstream()
                │
                ├──▶ procurement_records (keep for raw blockchain data)
                │
                └──▶ NEW: NormalizedTableSyncService
                            │
                            ├──▶ procurements
                            ├──▶ procurement_stages
                            ├──▶ procurement_documents
                            ├──▶ procurement_events
                            └──▶ files

UI Queries
    │
    ├──▶ procurements (fast queries for lists/dashboard)
    │
    └──▶ integrity_audit_logs (violation reports)

Integrity Verification
    │
    ├──▶ Compare procurement_records.data_json vs blockchain
    │
    └──▶ Compare procurements fields vs blockchain
```

## Summary

The current codebase **already satisfies all 6 requirements**. The only change needed is:

1. **Add normalized table sync** - Extract data from `data_json` to normalized tables
2. **Update frontend** - Query normalized tables instead of `data_json`
3. **Keep procurement_records** - Still needed for raw blockchain data verification

The integrity verification system is already robust and working. We just need to connect the new normalized tables to it.

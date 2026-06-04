# Integrity Breach Verification & Recovery Demo Guide

This guide explains how to demonstrate that ProcuChain satisfies the six data integrity requirements using the admin UI and controlled database tampering.

## Requirements Covered

1. Detect unauthorized modifications of database records.
2. Detect deleted records.
3. Compare current database records with blockchain records.
4. Generate integrity violation reports.
5. Restore original records from trusted blockchain data.
6. Maintain a permanent audit trail of all recovery operations.

## Latest Production Edge-Case Test Result

A controlled production edge-case test was executed against `PR-2026-001-0001`.

The test intentionally:

- modified a procurement title directly in the database;
- deleted one blockchain-backed procurement event row;
- inserted one fake DB-only procurement event row.

Then **Verify & Repair All** was executed.

Result:

```json
{
  "run_id": "ba3ee1d3-c4b7-4f50-9da0-20ff3223180d",
  "verified": 1834,
  "violation_count": 3,
  "restored": 4,
  "failed": 0,
  "title_restored": true,
  "deleted_event_restored": true,
  "fake_event_remaining": 0
}
```

Audit logs created:

| Violation Type | Stream | Status |
| --- | --- | --- |
| `hash_mismatch` | `procurement.metadata` | `restored` |
| `hash_mismatch` | `procurement.events` | `restored` |
| `row_deleted` | `procurement.events` | `restored` |
| `unauthorized_record` | `procurement.events` | `restored` |

Blockchain audit evidence was also confirmed:

- each violation had one entry on `integrity.violations`;
- each recovery had one recovery entry on `integrity.violations`.

## Recommended Demo Flow

### 1. Show the Clean Baseline

Open:

```text
Admin → Integrity Breaches
```

Click:

```text
Verify
```

Expected result:

```text
1834 records checked
0 breaches found
```

This proves the current database mirror is clean and aligned with blockchain.

---

### 2. Demo Unauthorized Database Modification

Using a DB client, modify a procurement directly:

```sql
UPDATE procurements
SET title = 'DEMO TAMPERED TITLE'
WHERE pr_number = 'PR-2026-001-0001';
```

Then open:

```text
Admin → Integrity Breaches
```

Click:

```text
Verify
```

Expected result:

```text
Hash Mismatch
PR-2026-001-0001
procurement.metadata
pending
```

Open the breach detail page and show:

- database/mirror snapshot contains the tampered value;
- blockchain/chain snapshot contains the original trusted value.

This demonstrates:

- requirement 1: unauthorized modification detection;
- requirement 3: DB vs blockchain comparison;
- requirement 4: violation report generation.

---

### 3. Demo Restoration from Blockchain

On the detected breach, click:

```text
Repair
```

or click:

```text
Verify & Repair All
```

Then verify the DB value:

```sql
SELECT title
FROM procurements
WHERE pr_number = 'PR-2026-001-0001';
```

Expected result:

```text
Procurement of Water System Repair
```

This demonstrates:

- requirement 5: restore original data from trusted blockchain data.

---

### 4. Demo Deleted Record Detection and Recovery

First, select a real blockchain-backed event:

```sql
SELECT id, txid
FROM procurement_events
WHERE procurement_id = 1
AND txid IS NOT NULL
LIMIT 1;
```

Save the selected `txid`, then delete the row:

```sql
DELETE FROM procurement_events
WHERE id = <selected_id>;
```

Open:

```text
Admin → Integrity Breaches
```

Click:

```text
Verify & Repair All
```

Expected result:

```text
row_deleted
restored
```

Confirm the event was restored:

```sql
SELECT id, txid
FROM procurement_events
WHERE txid = '<deleted_txid>';
```

Expected result: the row exists again.

This demonstrates:

- requirement 2: deleted record detection;
- requirement 5: restoration from blockchain.

---

### 5. Demo Fake DB-Only Record Removal

Insert a fake event that does not exist on blockchain:

```sql
INSERT INTO procurement_events (
  procurement_id,
  event_type,
  category,
  severity,
  details,
  stage,
  document_count,
  user_address,
  user_name,
  txid,
  data_hash,
  is_blockchain_verified,
  has_breach,
  metadata,
  occurred_at,
  created_at,
  updated_at
) VALUES (
  1,
  'demo_fake_event',
  'integrity_demo',
  'info',
  'Fake DB-only event for demo',
  'demo_stage',
  0,
  'demo-user',
  'Demo User',
  'demo-fake-txid-001',
  'fake-hash',
  0,
  0,
  JSON_OBJECT('demo', true),
  NOW(),
  NOW(),
  NOW()
);
```

Open:

```text
Admin → Integrity Breaches
```

Click:

```text
Verify & Repair All
```

Expected result:

```text
unauthorized_record
restored
```

Confirm the fake row was removed:

```sql
SELECT COUNT(*)
FROM procurement_events
WHERE txid = 'demo-fake-txid-001';
```

Expected result:

```text
0
```

This demonstrates:

- requirement 1: unauthorized DB-only record detection;
- requirement 5: fake record removal using blockchain as source of truth.

---

### 6. Demo Permanent Audit Trail

Open:

```text
Admin → Integrity Audit Logs
```

Search or filter by the latest verification run ID.

Show entries such as:

```text
hash_mismatch
row_deleted
unauthorized_record
restored
```

Then open:

```text
Admin → Blockchain Explorer
```

Inspect stream:

```text
integrity.violations
```

You should see both:

- violation entries;
- recovery entries.

This demonstrates:

- requirement 6: permanent audit trail of recovery operations.

## Best Presentation Sequence

Use this order during the demo:

1. Show clean state: `Verify = 0 breaches`.
2. Tamper procurement title in DB.
3. Run `Verify`.
4. Show `hash_mismatch` in Integrity Breaches.
5. Open breach detail and show DB snapshot vs blockchain snapshot.
6. Click `Repair` or `Verify & Repair All`.
7. Show DB value restored.
8. Delete one event row.
9. Run `Verify & Repair All`.
10. Show `row_deleted` restored.
11. Insert fake DB-only event.
12. Run `Verify & Repair All`.
13. Show `unauthorized_record` restored/removed.
14. Open Integrity Audit Logs.
15. Open Blockchain Explorer and show `integrity.violations` entries.

## Notes

- Do not delete audit logs during the demo. They prove the permanent audit trail requirement.
- `Integrity Breaches` is the active remediation queue.
- `Integrity Audit Logs` is the permanent historical record.
- Blockchain is the source of truth for repair and recovery.
- If old false-positive rows appear, confirm they are not `pending`. Stale false positives should be marked `skipped`, not deleted.

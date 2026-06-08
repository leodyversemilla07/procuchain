# ProcuChain Purge/Delete/Resync Workflow — End-to-End Audit Report

**Date:** 2026-05-24  
**Scope:** Full cross-layer audit of purge, delete (file-level), and resync operations  
**Layers traced:** Frontend → Routes → Controller → Service (facade) → NodeOperationsService → On-chain → AuditLogger → LedgerEntryData → SharedLedgerService → HealthCheck

---

## 1. Per-Operation Field Trace Tables

### 1A. PURGE ALL FROM NODE (`purgeAllFromNode`)

| Layer | Fields | Notes |
|---|---|---|
| **Frontend POST** | `node_id: string`, `reason: string` | Defaults reason to `"Demo: full node purge — all data removed from single node"` |
| **Route** | `POST /admin/recoverable-data/purge-all-from-node` → `RecoverableDataController@purgeAllFromNode` | Named `admin.recoverable-data.purge-all-from-node` |
| **Controller validates** | `node_id: required\|string`, `reason: nullable\|string\|max:500` | ✅ Matches frontend |
| **Service facade receives** | `purgeAllFromNode(nodeId, reason)` | Direct pass-through |
| **NodeOperationsService** | `purgeAllFromNode(nodeId, reason, skipAudit=false)` | Extra `$skipAudit` param (default false) |
| **On-chain publish** (stream: `file.metadata`, key: `node_{nodeId}_full_purge`) | `action: 'full_node_purge'`, `node_id`, `node_name`, `items_purged`, `method: 'ssm_physical_delete'`, `reason`, `purged_at` (ISO 8601), `performed_by` | 8 fields total |
| **AuditLogger** (when skipAudit=false) | action=`'node.full_purge'`, subjectType=`'node'`, subjectId=`nodeId`, **oldValues**: `action`, `node_id`, `node_name`, `method`, `items_purged`, `reason`, `performed_by` | 7 oldValues fields; newValues is empty `[]` |
| **LedgerEntryData extraction** | Key pattern: `str_starts_with($key, 'node_') && str_ends_with($key, '_full_purge')` → action=`'node_purged'`, newValues: `node_id`, `node_name`, `items_purged`, `reason`; oldValues: `[]`; summary from sprintf | 4 newValues fields extracted |
| **SharedLedgerService** (`checkPurgeStateFromPrimary`) | Key: `node_{nodeId}_full_purge` → checks blocktime; also checks `node_{nodeId}_resync` to compare | Compares `$purgeBlock >= $resyncBlock` |
| **HealthCheck** (`isNodePurged`) | Same keys: `node_{nodeId}_full_purge` and `node_{nodeId}_resync` | Same blocktime comparison |

### 1B. DELETE FROM NODE (`deleteFromNode` — file-level purge)

| Layer | Fields | Notes |
|---|---|---|
| **Frontend POST** | `file_key: string`, `node_id: string`, `reason: string` | Controller validates these; but **no frontend UI for this operation** |
| **Route** | `POST /admin/recoverable-data/delete-from-node` → `RecoverableDataController@deleteFromNode` | Named `admin.recoverable-data.delete-from-node` |
| **Controller validates** | `file_key: required\|string`, `node_id: required\|string`, `reason: nullable\|string\|max:500` | ✅ Consistent |
| **Service facade receives** | `deleteFromNode(fileKey, nodeId, reason)` | Direct pass-through |
| **NodeOperationsService** | Internally calls `purgeAllFromNode(nodeId, reason, skipAudit: true)` then publishes its own on-chain + audit | ⚠️ **CRITICAL: File-level delete actually purges ALL node data** |
| **On-chain publish #1** (from purgeAllFromNode) | Same as purge: key `node_{nodeId}_full_purge` | Written even though skipAudit=true — skipAudit only skips DB audit |
| **On-chain publish #2** (from deleteFromNode) (stream: `file.metadata`, key: `{fileKey_slash_to_underscore}_node_purge`) | `file_key`, `data_key`, `action: 'file_node_purge'`, `node_id`, `node_name`, `items_purged`, `method: 'ssm_physical_delete'`, `reason`, `purged_at`, `performed_by` | 10 fields |
| **AuditLogger** (single combined entry) | action=`'node.file_purge'`, subjectType=`'file'`, subjectId=`fileKey`, **oldValues**: `action: 'file_node_purge'`, `file_key`, `node_id`, `node_name`, `items_purged`, `method`, `reason`, `performed_by` | 8 oldValues fields; newValues empty |
| **LedgerEntryData extraction** | Key pattern: `str_ends_with($key, '_node_purge') && !isNodePurgeEvent` → action=`'file_node_purged'`, newValues: `file_key`, `node_id`, `items_purged`, `reason`; oldValues: `[]` | 4 newValues fields; **missing `node_name`** |
| **SharedLedgerService** | Does NOT specifically check for file_node_purge keys — only checks `node_{id}_full_purge` | File-level purges not detected independently |
| **HealthCheck** | Same — only checks `node_{id}_full_purge` | File-level purge events invisible to health monitoring |

### 1C. RESYNC NODE (`resyncNode`)

| Layer | Fields | Notes |
|---|---|---|
| **Frontend POST** | `node_id: string`, `reason: string` | Defaults reason to `"Manual resync — data restored from peers"` |
| **Route** | `POST /admin/recoverable-data/resync-node` → `RecoverableDataController@resyncNode` | Named `admin.recoverable-data.resync-node` |
| **Controller validates** | `node_id: required\|string`, `reason: nullable\|string\|max:500` | ✅ Matches frontend |
| **Service facade receives** | `resyncNode(nodeId, reason)` | Direct pass-through |
| **NodeOperationsService** | `resyncNode(nodeId, reason)` | Full method |
| **On-chain publish** (stream: `file.metadata`, key: `node_{nodeId}_resync`) | `action: 'node_resync'`, `node_id`, `node_name`, `items_resynced`, `method: 'ssm_subscribe_all'`, `reason`, `resynced_at` (ISO 8601), `performed_by` | 8 fields |
| **AuditLogger** | action=`'node.resync'`, subjectType=`'node'`, subjectId=`nodeId`, **newValues**: `action: 'node_resync'`, `node_id`, `node_name`, `method`, `items_resynced`, `reason`, `performed_by` | 7 newValues fields; oldValues empty |
| **LedgerEntryData extraction** | Key pattern: `str_starts_with($key, 'node_') && str_ends_with($key, '_resync')` → action=`'node_resynced'`, newValues: `node_id`, `node_name`, `items_resynced`, `reason`; oldValues: `[]` | 4 newValues fields |
| **SharedLedgerService** | Key: `node_{nodeId}_resync` → checks blocktime vs purge blocktime | Used to determine if node is still purged |
| **HealthCheck** | Same key pattern comparison | Used to skip auto-repair for intentionally purged nodes |

---

## 2. Issues Found

### ISSUE 1 — CRITICAL: `deleteFromNode` purges ALL node data, not just one file

**Severity:** CRITICAL  
**Location:** `NodeOperationsService::deleteFromNode()` (line 58)  
**Detail:** The method comment says "Purge a single file's data from a specific node" but the implementation calls `purgeAllFromNode()` — which physically deletes ALL chain data from the target node via SSM. The comment admits "For MultiChain CE, this performs a full node purge (same as purgeAllFromNode) since per-key deletion is not available." However:  
- The controller's docblock says "Purge a file's data from a single node's local storage. The data remains on all other nodes."  
- The route is `delete-from-node`, implying file-level granularity  
- There is **no frontend UI** exposing this endpoint, so the only callers would be API consumers who read the route list  
- **Two on-chain events** are written: `node_{id}_full_purge` (from the inner purgeAllFromNode) AND `{fileKey}_node_purge` (from deleteFromNode itself) — creating a confusing dual record  
**Impact:** Anyone calling `deleteFromNode` expecting surgical file removal will wipe an entire node. The on-chain record will show both a full_purge AND a file_node_purge event, which is contradictory.  
**Fix:** Either (a) remove the `deleteFromNode` endpoint if it's never truly file-level, or (b) clearly document that it's an alias for full purge and update all docblocks accordingly, or (c) if file-level purge is desired, implement a different strategy (e.g., just unsubscribe from specific streams containing the file).

---

### ISSUE 2 — MEDIUM: `skipAudit` in `purgeAllFromNode` skips DB audit but NOT on-chain publish — semantic confusion

**Severity:** MEDIUM  
**Location:** `NodeOperationsService::purgeAllFromNode()` (line 262)  
**Detail:** The `$skipAudit` parameter name implies it skips all auditing. In reality, it only skips the `AuditLogger::log()` call (MySQL + blockchain audit trail via EventPublisher). The direct on-chain `publish()` call (line 237) always executes regardless of `$skipAudit`.  
- When `deleteFromNode()` calls `purgeAllFromNode(skipAudit: true)`, the full_purge event IS published on-chain (correct for data survival), but NO AuditLog DB entry is created for the full_purge action  
- `deleteFromNode()` then creates its own combined `node.file_purge` audit entry, which goes through AuditLogger (MySQL + EventPublisher blockchain publish)  
- **Result:** There is NO MySQL audit_log row for the `node.full_purge` action when called via `deleteFromNode`, even though the on-chain full_purge event exists  
**Impact:** Audit trail is inconsistent — MySQL lacks the full_purge record but blockchain has it. Any audit compliance queries against MySQL will miss the full_purge event.  
**Fix:** Rename `$skipAudit` to `$skipDbAudit` for clarity, or always write the MySQL AuditLog entry and only skip the EventPublisher (blockchain) double-publish to avoid duplicate events.

---

### ISSUE 3 — MEDIUM: Dual on-chain events when `deleteFromNode` is called

**Severity:** MEDIUM  
**Location:** `NodeOperationsService::deleteFromNode()` lines 58 + 64  
**Detail:** When `deleteFromNode` is called, two separate on-chain events are published to `file.metadata`:  
1. Key `node_{nodeId}_full_purge` — from the inner `purgeAllFromNode()` call  
2. Key `{fileKey}_node_purge` — from `deleteFromNode()` itself  

Both are on the same stream but with different keys and different action strings (`full_node_purge` vs `file_node_purge`). The SharedLedgerService and HealthCheck only check for `node_{id}_full_purge`, so they WILL correctly detect the node as purged. However, the `file_node_purge` key is never checked by any purge-detection logic — it only surfaces in LedgerEntryData for display.  
**Impact:** The `file_node_purge` on-chain event is essentially write-only — it's never used for state detection, only for display. If someone resyncs the node, the `file_node_purge` event's purge state is never cleared.  
**Fix:** Either (a) remove the second on-chain publish since `deleteFromNode` is already recording a full purge, or (b) add `file_node_purge` detection logic to SharedLedgerService/HealthCheck, or (c) as recommended in Issue 1, deprecate `deleteFromNode`.

---

### ISSUE 4 — MEDIUM: AuditLogger `oldValues` vs `newValues` inconsistency across operations

**Severity:** MEDIUM  
**Location:** NodeOperationsService + FileLifecycleManager audit calls  
**Detail:**  
- **Purge:** Uses `oldValues` (7 fields) with empty `newValues` — semantically, purge IS a destructive change (data goes from existing to gone), so `oldValues` is arguably correct, but it's inconsistent with...  
- **Resync:** Uses `newValues` (7 fields) with empty `oldValues` — semantically, resync is a restorative change  
- **File delete** (FileLifecycleManager): Uses `oldValues` with action='deleted'  
- **File restore** (FileLifecycleManager): Uses `newValues` with action='restored'  

The pattern is: destructive = oldValues, restorative = newValues. However, this means:  
- The purge AuditLogger entry publishes to blockchain via EventPublisher with metadata containing `old_values` (populated) and `new_values` (empty)  
- The resync AuditLogger entry publishes with `new_values` (populated) and `old_values` (empty)  
- `LedgerEntryData` always maps these to its own `oldValues`/`newValues` arrays for the purge/resync special cases, overriding the default extraction  
**Impact:** Low functional impact but semantically confusing. Audit reports querying old_values vs new_values need to understand this convention.  
**Fix:** For consistency, consider putting the full payload in `newValues` for all cases (the event IS the new state), or consistently use both old and new for state transitions.

---

### ISSUE 5 — MEDIUM: AuditLogger `categorizeAction()` doesn't handle `node.*` prefix

**Severity:** MEDIUM  
**Location:** `AuditLogger::categorizeAction()` (line 252)  
**Detail:** The method handles prefixes: `procurement.`, `document.`, `auth.`, `admin.`, `user.`, `account.`, `settings.`, `security.` — but **NOT** `node.`. The three node actions (`node.full_purge`, `node.file_purge`, `node.resync`) all fall through to `default => 'system'`.  
**Impact:** When AuditLogger publishes to blockchain via EventPublisher, the category is `'system'` instead of something meaningful like `'node_operations'`. This affects blockchain event metadata quality.  
**Fix:** Add `str_starts_with($action, 'node.') => 'node_operations'` to the match expression.

---

### ISSUE 6 — LOW: LedgerEntryData extracts fewer fields than on-chain payload

**Severity:** LOW  
**Location:** `LedgerEntryData::fromStreamItem()` lines 47-97  
**Detail:**  
- **Full purge on-chain:** 8 fields (action, node_id, node_name, items_purged, method, reason, purged_at, performed_by). **LedgerEntryData newValues:** 4 fields (node_id, node_name, items_purged, reason). Missing: `method`, `purged_at`, `performed_by` (though `performed_by` is mapped to `actorAddress`).  
- **File purge on-chain:** 10 fields. **LedgerEntryData newValues:** 4 fields (file_key, node_id, items_purged, reason). Missing: `data_key`, `action`, `node_name`, `method`, `purged_at`, `performed_by`. Note: `node_name` IS in the on-chain payload but NOT extracted to newValues (it IS used in the summary text).  
- **Resync on-chain:** 8 fields. **LedgerEntryData newValues:** 4 fields (node_id, node_name, items_resynced, reason). Missing: `method`, `resynced_at`, `performed_by`.  

The `rawJson` property contains ALL on-chain fields, so no data is truly lost. However, the structured `newValues`/`oldValues` arrays shown in the Shared Ledger UI will be incomplete.  
**Impact:** Users viewing the Shared Ledger detail panel will see only 4 of 8+ fields in the structured values view, though rawJson is available.  
**Fix:** Add `method`, `performed_by`, and timestamp fields to the LedgerEntryData newValues extraction for completeness, or document that `rawJson` is the canonical source.

---

### ISSUE 7 — LOW: `deleteFromNode` route has no frontend UI

**Severity:** LOW  
**Location:** Route exists at `POST /admin/recoverable-data/delete-from-node`, Wayfinder routes are generated, but no frontend component calls it  
**Detail:** The frontend `recoverable-data.tsx` only implements `handleFullPurgeFromNode` (calling `purgeAllFromNode`) and `handleResyncNode` (calling `resyncNode`). There is no UI for `handleDeleteFromNode`. The route, controller method, and service method all exist but are unreachable from the UI.  
**Impact:** Dead code surface — increases attack surface (the endpoint still works if called directly) and maintenance burden.  
**Fix:** Either add a UI for it (with clear warning that it's actually a full purge), or remove the route/controller/service method and keep only `purgeAllFromNode`.

---

### ISSUE 8 — LOW: LedgerEntryData action strings differ from on-chain `action` field

**Severity:** LOW  
**Location:** `LedgerEntryData::fromStreamItem()`  
**Detail:**  
| On-chain `action` | LedgerEntryData `$action` | AuditLogger action |
|---|---|---|
| `full_node_purge` | `node_purged` | `node.full_purge` |
| `file_node_purge` | `file_node_purged` | `node.file_purge` |
| `node_resync` | `node_resynced` | `node.resync` |

Three different naming conventions for the same logical action. The LedgerEntryData action is what appears in the Shared Ledger UI. The AuditLogger action is what appears in the audit_log MySQL table. The on-chain `action` is what's in the blockchain JSON payload.  
**Impact:** Confusing for developers and auditors trying to correlate events across systems. No functional breakage.  
**Fix:** Standardize on one naming convention. Recommended: use the AuditLogger dotted format (`node.full_purge`, `node.file_purge`, `node.resync`) everywhere, or at minimum document the mapping.

---

### ISSUE 9 — LOW: SharedLedgerService only fetches from `LEDGER_STREAMS`, not `FILE_METADATA`

**Severity:** LOW  
**Location:** `SharedLedgerService::LEDGER_STREAMS` (line 27) vs `PURGE_CHECK_STREAM` (line 42)  
**Detail:** The `FILE_METADATA` stream (where all purge/resync events are published) is NOT included in `LEDGER_STREAMS`. It's only used for `PURGE_CHECK_STREAM` (purge detection). This means:  
- Purge/resync events published to `file.metadata` are **never shown** as ledger entries in the Shared Ledger page  
- The `LedgerEntryData` extraction logic for node purge/resync events (lines 43-97) exists but is **dead code** in normal operation — those items are never fetched  
- The only way purge/resync events appear in the Shared Ledger is indirectly, through the `nodePurgeState` banner  
**Impact:** Users cannot see purge/resync events in the Shared Ledger timeline. The blockchain explorer would show them, but the primary UI doesn't.  
**Fix:** Add `StreamEnums::FILE_METADATA->value` to `LEDGER_STREAMS` (but be cautious — `file.metadata` also contains file upload metadata entries which could be very large). Alternatively, filter `file.metadata` items to only those with node-purge/resync keys.

---

### ISSUE 10 — LOW: Potential `purgeBlock >= resyncBlock` edge case with equal blocktimes

**Severity:** LOW  
**Location:** `SharedLedgerService::checkPurgeStateFromPrimary()` (line 403), `NodeOperationsService::getAvailableNodes()` (line 509), `MultichainNodeHealthCheck::isNodePurged()` (line 202)  
**Detail:** All three implementations use `$purgeBlock >= $resyncBlock` to determine if a node is purged. If purge and resync happen in the same block (same `blocktime`), the node is considered still purged. In practice, MultiChain block times are typically seconds apart, but the edge case exists.  
**Impact:** If purge and resync land in the same block, the node is incorrectly shown as purged.  
**Fix:** Use `>` instead of `>=`, or compare `confirm` field / tx index within the block for more precise ordering.

---

### ISSUE 11 — LOW: `LedgerEntryData::fromStreamItem()` file_node_purge pr_number extraction may be wrong

**Severity:** LOW  
**Location:** `LedgerEntryData::fromStreamItem()` line 62  
**Detail:** For `isFileNodePurgeEvent`, prNumber is extracted as: `$data['pr_number'] ?? explode('_', $key)[0] ?? 'system'`. However, the on-chain payload for `file_node_purge` does NOT include a `pr_number` field. The key format is `{fileKey_with_underscores}_node_purge` (e.g., `PR-2024-001-001_document.pdf_node_purge`). Using `explode('_', $key)[0]` would extract just `PR`, not the full PR number, since PR numbers contain hyphens but the file key's slashes were converted to underscores.  
**Impact:** The prNumber shown in the Shared Ledger for file_node_purge events would be `PR` instead of `PR-2024-001-001`. However, since file.metadata isn't in LEDGER_STREAMS (Issue 9), this is currently dead code.  
**Fix:** Extract prNumber from `$data['file_key']` by splitting on `/` and taking the first segment, matching the pattern used in `getAvailableNodes()`.

---

## 3. Recommended Fixes Summary

| # | Severity | Issue | Recommended Fix |
|---|---|---|---|
| 1 | CRITICAL | `deleteFromNode` purges ALL data, not one file | Deprecate or clearly document as full-purge alias; update controller docblock; add warning in route response |
| 2 | MEDIUM | `skipAudit` skips DB audit but not on-chain publish | Rename to `$skipDbAudit`; always write MySQL AuditLog entry; skip only EventPublisher double-publish |
| 3 | MEDIUM | Dual on-chain events from `deleteFromNode` | Remove the second `file_node_purge` on-chain publish; or add file-purge detection to SharedLedgerService/HealthCheck |
| 4 | MEDIUM | Inconsistent oldValues/newValues convention across operations | Standardize: put event payload in `newValues` for all; or document the destructive=old, restorative=new convention |
| 5 | MEDIUM | `categorizeAction()` missing `node.*` prefix | Add `str_starts_with($action, 'node.') => 'node_operations'` |
| 6 | LOW | LedgerEntryData extracts 4/8 fields | Add `method`, `performed_by` to newValues; or document rawJson as canonical |
| 7 | LOW | `deleteFromNode` has no frontend UI | Remove dead endpoint or add UI with full-purge warning |
| 8 | LOW | Three different action naming conventions | Standardize on one; document mapping table |
| 9 | LOW | Purge/resync events invisible in Shared Ledger | Add `file.metadata` to LEDGER_STREAMS with key-based filtering, or create a dedicated `node.events` stream |
| 10 | LOW | `purgeBlock >= resyncBlock` equal blocktime edge case | Change to `>` and add tiebreaker logic |
| 11 | LOW | File_node_purge pr_number extraction broken | Use `$data['file_key']` with `/` split instead of key-based extraction |

---

## 4. Data Flow Diagram

```
Frontend (recoverable-data.tsx)
  │
  ├── POST /purge-all-from-node  {node_id, reason}
  │     → RecoverableDataController@purgeAllFromNode
  │       → BlockchainStorageService@purgeAllFromNode(nodeId, reason)
  │         → NodeOperationsService@purgeAllFromNode(nodeId, reason, skipAudit=false)
  │           ├── [1] AWS SSM: stop daemon → rm chain data → restart
  │           ├── [2] On-chain publish: stream=file.metadata, key=node_{id}_full_purge
  │           │     payload: {action:full_node_purge, node_id, node_name, items_purged,
  │           │              method:ssm_physical_delete, reason, purged_at, performed_by}
  │           └── [3] AuditLogger::log(action=node.full_purge, subjectType=node,
  │                     subjectId=nodeId, oldValues={7 fields})
  │                       ├── MySQL: audit_log INSERT
  │                       ├── EventPublisher: blockchain publish to procurement.events
  │                       └── Notification: if CRITICAL (yes, node.full_purge is critical)
  │
  ├── POST /delete-from-node  {file_key, node_id, reason}  [NO UI CALLER]
  │     → RecoverableDataController@deleteFromNode
  │       → BlockchainStorageService@deleteFromNode(fileKey, nodeId, reason)
  │         → NodeOperationsService@deleteFromNode(fileKey, nodeId, reason)
  │           ├── [1] purgeAllFromNode(nodeId, reason, skipAudit=true)
  │           │     ├── AWS SSM: same full purge
  │           │     └── On-chain: node_{id}_full_purge (ALWAYS published)
  │           │         ⚠ NO AuditLogger call (skipped)
  │           ├── [2] On-chain publish: stream=file.metadata, key={fileKey}_node_purge
  │           │     payload: {file_key, data_key, action:file_node_purge, node_id,
  │           │              node_name, items_purged, method:ssm_physical_delete,
  │           │              reason, purged_at, performed_by}
  │           └── [3] AuditLogger::log(action=node.file_purge, subjectType=file,
  │                     subjectId=fileKey, oldValues={8 fields})
  │
  └── POST /resync-node  {node_id, reason}
        → RecoverableDataController@resyncNode
          → BlockchainStorageService@resyncNode(nodeId, reason)
            → NodeOperationsService@resyncNode(nodeId, reason)
              ├── [1] AWS SSM: subscribe to all streams with rescan
              ├── [2] On-chain publish: stream=file.metadata, key=node_{id}_resync
              │     payload: {action:node_resync, node_id, node_name, items_resynced,
              │              method:ssm_subscribe_all, reason, resynced_at, performed_by}
              ├── [3] If primary node was purged → resetByResync()
              └── [4] AuditLogger::log(action=node.resync, subjectType=node,
                        subjectId=nodeId, newValues={7 fields})

DISPLAY LAYER:
  SharedLedgerService
    ├── checkPurgeStateFromPrimary(): queries node_{id}_full_purge + node_{id}_resync
    ├── isNodePurged(): same key pattern check
    └── LEDGER_STREAMS: does NOT include file.metadata → purge/resync events NOT shown

  LedgerEntryData::fromStreamItem()
    ├── node_*_full_purge → action='node_purged', newValues={node_id, node_name, items_purged, reason}
    ├── *_node_purge → action='file_node_purged', newValues={file_key, node_id, items_purged, reason}
    └── node_*_resync → action='node_resynced', newValues={node_id, node_name, items_resynced, reason}

  MultichainNodeHealthCheck
    └── isNodePurged(): queries node_{id}_full_purge + node_{id}_resync → skips auto-repair
```

---

## 5. Key Pattern Reference

| Event | Stream | Key Pattern | Used By |
|---|---|---|---|
| Full node purge | `file.metadata` | `node_{nodeId}_full_purge` | NodeOperationsService, SharedLedgerService, HealthCheck, LedgerEntryData |
| File node purge | `file.metadata` | `{fileKey_underscored}_node_purge` | NodeOperationsService (publish), LedgerEntryData (extract) |
| Node resync | `file.metadata` | `node_{nodeId}_resync` | NodeOperationsService, SharedLedgerService, HealthCheck, LedgerEntryData |
| File deletion | `file.metadata` | `{fileKey_underscored}_deleted` | FileLifecycleManager |
| File restoration | `file.metadata` | `{fileKey_underscored}_deleted` | FileLifecycleManager (same key, action='restored') |

All purge/resync/delete/restore events are published to the **same stream** (`file.metadata`), differentiated only by key suffix.

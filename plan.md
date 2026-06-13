# Refactor: Extract IntegrityViolationRecorder

## Problem

`IntegrityVerificationService.php` is 1,682 lines. The violation recording and resolution logic (11 methods) should be extracted into `app/Services/Integrity/IntegrityViolationRecorder.php` to improve separation of concerns.

## Key Analysis: Shared Helper Dependencies

The 11 extracted methods depend on several helper methods that are also called by remaining service methods:

| Helper | Used by Extracted | Used by Remaining |
|--------|------------------|-------------------|
| `modelClassForStream()` | `violationIsResolved` | - |
| `tableNameForStream()` | `contentViolationIsResolved` | `procurementStatusDiffersFromLatestStatusStream` |
| `fetchChainData()` | `contentViolationIsResolved` | `verifyRecord`, `detectUnauthorizedInDb` |
| `recordReferencesLatestChainRevision()` | `contentViolationIsResolved` | `recordReferencesSupersededChainRevision` |
| `latestChainItemForRecord()` | via above | `recordReferencesSupersededChainRevision` |
| `computeFieldDifferences()` | `contentViolationIsResolved` | `verifyRecord`, `detectUnauthorizedInDb` |
| `recordToArray()` | `recordViolation`, `contentViolationIsResolved` | `verifyRecord` |
| `computeRecordHash()` | `contentViolationIsResolved` | `refreshHashesAfterRepair` |
| `normaliseHashValue()` | via `recordToArray`/`computeRecordHash` | via same |
| `procurementStatusDifferencesFromLatestStatusStream()` | `contentViolationIsResolved` | `procurementStatusDiffersFromLatestStatusStream` |
| `latestStatusItemForPrNumber()` | via above | via same |
| `valuesMatch()` | via above | via same |
| `notifyBreach()` | `recordViolation` | - |

**Decision:** Move ALL shared helpers into the recorder. The service delegates to the recorder for both extracted methods AND shared helpers. This makes the recorder the "verification intelligence" layer and the service the orchestration layer. The public API of the service stays identical.

## Implementation

### Step 1: Create `app/Services/Integrity/IntegrityVerificationRunState.php`

Simple value object with public properties:

```php
class IntegrityVerificationRunState
{
    public string $runId = '';
    public string $source = 'scheduled';
    public array $violationCounts = [];
    public int $verifiedCount = 0;
    public int $restoredCount = 0;
    public int $failedCount = 0;
}
```

### Step 2: Create `app/Services/Integrity/IntegrityViolationRecorder.php`

**Constructor dependencies** (via constructor injection):
- `BlockchainVerificationIndex $blockchainIndex` — chain lookups
- `IntegrityComparator $comparator` — field diffs
- `BlockchainAuditTrailService $auditTrail` — audit logging
- `BlockchainPayloadProjector $payloadProjector` — chain data projection
- `BlockchainRpcClient $blockchainRpcClient` — RPC fallback in fetchChainData

**State management:**
- Receives `IntegrityVerificationRunState` via `setState()` method (called by service's `reset()`)
- Mutates `$this->state->violationCounts`, `$this->state->restoredCount`, `$this->state->failedCount` directly

**11 extracted methods** (from user's list):
1. `recordViolation()` — lines 1540-1618
2. `violationIsResolved()` — lines 1100-1112
3. `rowDeletedViolationIsResolved()` — lines 1114-1127
4. `unauthorizedRecordViolationIsResolved()` — lines 1129-1144
5. `contentViolationIsResolved()` — lines 1146-1186
6. `findMirrorRecordForViolation()` — lines 1188-1213
7. `resolveStalePendingViolationsAfterCleanRun()` — lines 1083-1098
8. `resolvePendingStaleHashViolations()` — lines 710-729
9. `resolvePendingFalsePositiveViolations()` — lines 731-760
10. `hasPendingViolationsForRecord()` — lines 762-774
11. `refreshTrustedRecordHash()` — lines 700-708

**Shared helper methods** (also moved from service):
- `modelClassForStream()` — stream→model map
- `tableNameForStream()` — stream→table map
- `fetchChainData()` — chain data lookup with RPC fallback
- `recordReferencesLatestChainRevision()` — txid check
- `latestChainItemForRecord()` — latest chain item lookup
- `computeFieldDifferences()` — delegates to `$this->comparator->diff()`
- `recordToArray()` — record→array for hash/diff
- `computeRecordHash()` — SHA-256 hash of record fields
- `normaliseHashValue()` — value normalization
- `procurementStatusDifferencesFromLatestStatusStream()` — status diff check
- `latestStatusItemForPrNumber()` — latest status item lookup
- `valuesMatch()` — numeric/string comparison
- `notifyBreach()` — breach notification dispatch

**Also need the TABLE_STREAM_MAP constant** (duplicated on recorder for `recordViolation`).

### Step 3: Update `app/Services/IntegrityVerificationService.php`

**Constructor changes:**
- Add `IntegrityViolationRecorder $recorder` parameter
- Remove `$runId`, `$source`, `$violationCounts`, `$verifiedCount`, `$restoredCount`, `$failedCount` properties (moved to state object)
- Add `$state` property (IntegrityVerificationRunState)
- Remove `$blockchainIndex` reassignment in `reset()` — use recorder's blockchainIndex

**`reset()` method changes:**
```php
private function reset(string $source): void
{
    $this->state = new IntegrityVerificationRunState();
    $this->state->runId = IntegrityViolationLog::newRunId();
    $this->state->source = $source;
    $this->recorder->setState($this->state);
    $this->verifyPublishers = false;
    $this->blockchainIndex = app(BlockchainVerificationIndex::class);
    $this->recorder->setBlockchainIndex($this->blockchainIndex);
}
```

**Thin wrapper methods** (delegate to recorder):
- All 11 extracted methods become one-line delegations
- Shared helpers become one-line delegations

**Remaining service methods** updated to call recorder:
- `verifyRecord()` → `$this->recorder->computeRecordHash()`, `$this->recorder->fetchChainData()`, etc.
- `verifyAndRepair()` → state references become `$this->state->runId`, `$this->state->verifiedCount++`
- `autoRepair()` → `$this->recorder->violationIsResolved()`
- `restoreViolation()` → `$this->recorder->violationIsResolved()`
- Detection methods → `$this->recorder->recordViolation()`

**Public API preserved:**
- `verifyAndRepair()` — signature and return unchanged
- `verifyPr()` — signature and return unchanged
- `restoreViolation()` — signature and return unchanged
- `generateReport()` — unchanged
- `computeFieldDifferences()` — public, delegates to recorder

### Step 4: Verification

1. `php artisan test --compact` — all 1458 tests must pass
2. `vendor/bin/pint --dirty --format agent` — formatting check
3. Report line counts

## Dependency Flow

```
IntegrityVerificationService (orchestrator)
    ├── IntegrityViolationRecorder (verification intelligence)
    │   ├── BlockchainVerificationIndex
    │   ├── IntegrityComparator
    │   ├── BlockchainAuditTrailService
    │   ├── BlockchainPayloadProjector
    │   ├── BlockchainRpcClient
    │   └── IntegrityVerificationRunState (shared mutable state)
    └── IntegrityVerificationRunState
```

## Files Modified/Created

| File | Action |
|------|--------|
| `app/Services/Integrity/IntegrityVerificationRunState.php` | **CREATE** |
| `app/Services/Integrity/IntegrityViolationRecorder.php` | **CREATE** |
| `app/Services/IntegrityVerificationService.php` | **MODIFY** |
| `tests/Feature/IntegrityVerificationServiceTest.php` | **NO CHANGE** |

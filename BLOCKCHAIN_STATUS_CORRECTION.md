# Blockchain Status Correction Guide

**Date**: December 15, 2025  
**Issue**: 3 procurements have incorrect status-stage combinations in blockchain  
**Cause**: Created before mode-aware status fix (commit 36060d0)

---

## Problem Overview

Three procurements have **mismatched stage-status pairs** in the blockchain:

| PR Number | Stage | Current Status (❌ Wrong) | Correct Status (✅) |
|-----------|-------|---------------------------|---------------------|
| PR-2025-0011-1496 | Procurement Initiation | Procurement Submitted | Procurement Initiated |
| PR-2025-0001-0043 | Abstract Of Quotations | Quotations Received | Abstract Prepared |
| PR-2025-0001-0001 | Procurement Initiation | Procurement Submitted | Procurement Initiated |

### Why This Happened

These procurements were created **before the mode-aware status determination fix** was implemented. The old code hardcoded incorrect statuses during stage transitions (see [ROOT_CAUSE_ANALYSIS.md](ROOT_CAUSE_ANALYSIS.md)).

### Why We Can't Delete Blockchain Records

MultiChain blockchain data is **immutable** - records cannot be edited or deleted. This is a core feature that ensures:
- Audit trail integrity
- Tamper-proof procurement records
- Compliance with government transparency requirements

---

## How Blockchain Status Queries Work

### Current Query Pattern (StatusRepository.php)

```php
// Get latest status for a procurement
public function getLatest(string $prNumber): ?StatusData
{
    $statuses = $this->findByProcurement($prNumber);
    
    if (empty($statuses)) {
        return null;
    }
    
    // Sort by timestamp descending (most recent first)
    usort($statuses, fn ($a, $b) => $b->timestamp->timestamp - $a->timestamp->timestamp);
    
    return $statuses[0];  // Returns the NEWEST record
}
```

**Key Insight**: The application uses **"latest wins" pattern** - it always uses the most recent status record for each PR number.

### Blockchain Stream Structure

```
procurement.status stream:
┌─────────────────────────────────────────────────────────────┐
│ Key: PR-2025-0011-1496                                      │
├─────────────────────────────────────────────────────────────┤
│ ❌ Record 1 (2025-01-10 08:30):                            │
│    stage: procurement_initiation                            │
│    status: procurement_submitted (WRONG)                    │
├─────────────────────────────────────────────────────────────┤
│ ✅ Record 2 (2025-12-15 14:00):                            │
│    stage: procurement_initiation                            │
│    status: procurement_initiated (CORRECT)                  │
│    metadata: { correction_type: "status_mismatch_fix" }     │
└─────────────────────────────────────────────────────────────┘
```

**Solution**: Publish a **new corrected status record** with a newer timestamp. The application will automatically use it.

---

## Three Approaches to Fix

### **Option 1: Artisan Command (RECOMMENDED) ✅**

**Best for**: Production environments, batch corrections, audit trail

**Advantages**:
- ✅ Can run with `--dry-run` to preview changes
- ✅ Includes confirmation prompts (safety)
- ✅ Detailed logging and transaction IDs
- ✅ Can target specific PR or all at once
- ✅ Reusable for future corrections

**Usage**:

```bash
# Preview changes without applying
php artisan status:correct --dry-run

# Apply all corrections (with confirmation)
php artisan status:correct

# Skip confirmation prompt
php artisan status:correct --force

# Correct specific procurement
php artisan status:correct PR-2025-0011-1496
```

**Output Example**:

```
🔍 Procurement Status Correction Tool

🔧 Corrections to Apply:

+-------------------+------------------------+---------------------------+------------------------+
| PR Number         | Stage                  | Current Status (Wrong)    | Correct Status         |
+-------------------+------------------------+---------------------------+------------------------+
| PR-2025-0011-1496 | PROCUREMENT INITIATION | PROCUREMENT SUBMITTED     | PROCUREMENT INITIATED  |
| PR-2025-0001-0043 | ABSTRACT OF QUOTATIONS | QUOTATIONS RECEIVED       | ABSTRACT PREPARED      |
| PR-2025-0001-0001 | PROCUREMENT INITIATION | PROCUREMENT SUBMITTED     | PROCUREMENT INITIATED  |
+-------------------+------------------------+---------------------------+------------------------+

Do you want to proceed with these corrections? (yes/no) [no]:
> yes

📝 Publishing corrected statuses to blockchain...

Processing PR-2025-0011-1496...
  ✅ Corrected: PR-2025-0011-1496
     TXID: 7a9e8f6d5c4b3a2e1f0d9c8b7a6e5d4c3b2a1f0e9d8c7b6a5e4d3c2b1a0f9e8d
     Stage: procurement_initiation
     Status: procurement_submitted → procurement_initiated

📊 Summary:
  ✅ Successful: 3

🎉 All corrections applied successfully!
The procurement list should now display correct statuses.
```

**Implementation**: See [app/Console/Commands/CorrectProcurementStatuses.php](../app/Console/Commands/CorrectProcurementStatuses.php)

---

### **Option 2: Tinker Script (Quick Fix)**

**Best for**: Development, one-time fixes, testing

**Advantages**:
- ✅ Quick and interactive
- ✅ No need to create command file
- ✅ Good for testing approach

**Usage**:

```bash
php artisan tinker
```

```php
// Get required services
$statusPublisher = app(\App\Services\Publishers\StatusPublisher::class);
$procurementRepo = app(\App\Repositories\ProcurementRepository::class);

// Define corrections
$corrections = [
    'PR-2025-0011-1496' => [
        'stage' => \App\Enums\StageEnums::PROCUREMENT_INITIATION,
        'correct_status' => \App\Enums\StatusEnums::PROCUREMENT_INITIATED,
        'incorrect_status' => \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
    ],
    'PR-2025-0001-0043' => [
        'stage' => \App\Enums\StageEnums::ABSTRACT_OF_QUOTATIONS,
        'correct_status' => \App\Enums\StatusEnums::ABSTRACT_PREPARED,
        'incorrect_status' => \App\Enums\StatusEnums::QUOTATIONS_RECEIVED,
    ],
    'PR-2025-0001-0001' => [
        'stage' => \App\Enums\StageEnums::PROCUREMENT_INITIATION,
        'correct_status' => \App\Enums\StatusEnums::PROCUREMENT_INITIATED,
        'incorrect_status' => \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
    ],
];

// Apply corrections
foreach ($corrections as $prNumber => $correction) {
    $procurement = $procurementRepo->getByPrNumber($prNumber);
    
    if (!$procurement) {
        echo "❌ Procurement not found: {$prNumber}\n";
        continue;
    }
    
    $result = $statusPublisher->publish(
        prNumber: $prNumber,
        procurementTitle: $procurement->title,
        stage: $correction['stage'],
        currentStatus: $correction['correct_status'],
        userAddress: $procurement->userAddress,
        previousStatus: $correction['incorrect_status'],
        metadata: [
            'correction_type' => 'status_mismatch_fix',
            'correction_reason' => 'Fixed stage-status mismatch from before mode-aware fix',
            'corrected_at' => now()->toIso8601String(),
            'corrected_by' => 'admin',
        ]
    );
    
    if ($result['success']) {
        echo "✅ Corrected {$prNumber}: {$result['status_txid']}\n";
    } else {
        echo "❌ Failed to correct {$prNumber}\n";
    }
}

echo "\n✅ All corrections published!\n";
```

---

### **Option 3: Direct MultiChain RPC (Advanced)**

**Best for**: Direct blockchain manipulation, debugging

**Advantages**:
- ✅ Bypasses application layer
- ✅ Direct control over blockchain
- ✅ Useful for understanding blockchain structure

**Disadvantages**:
- ❌ No validation or safety checks
- ❌ Requires manual data formatting
- ❌ More error-prone

**Usage**:

```bash
php artisan tinker
```

```php
// Get MultiChain manager
$multichain = app(\App\Services\Manager::class);

// Publish corrected status for PR-2025-0011-1496
$txid = $multichain->publish(
    'procurement.status',
    'PR-2025-0011-1496',
    ['json' => [
        'pr_number' => 'PR-2025-0011-1496',
        'procurement_title' => 'Sample Procurement Title',
        'stage' => 'procurement_initiation',
        'current_status' => 'procurement_initiated',  // CORRECTED
        'previous_status' => 'procurement_submitted',  // OLD INCORRECT VALUE
        'user_address' => '1ABC123xyz',
        'timestamp' => now()->toIso8601String(),
        'metadata' => [
            'correction_type' => 'status_mismatch_fix',
            'correction_reason' => 'Fixed stage-status mismatch',
            'corrected_at' => now()->toIso8601String(),
        ],
    ]]
);

echo "✅ Published correction: {$txid}\n";
```

---

## Verification Steps

After applying corrections, verify they worked:

### 1. Check Latest Status via Tinker

```bash
php artisan tinker
```

```php
$statusRepo = app(\App\Repositories\StatusRepository::class);

$status = $statusRepo->getLatest('PR-2025-0011-1496');

// Should show CORRECT status now
echo "Stage: {$status->stage}\n";
echo "Status: {$status->currentStatus}\n";
echo "Timestamp: {$status->timestamp}\n";
```

### 2. Check Procurement List Page

Navigate to `/admin/procurements-list` or `/bac-secretariat/procurements-list` and verify:
- ✅ Stage and status now match correctly
- ✅ Status badge shows correct color/text
- ✅ No warning messages about mismatched pairs

### 3. Check Blockchain Stream Directly

```bash
php artisan tinker
```

```php
$multichain = app(\App\Services\Manager::class);

// Get all status records for PR
$items = $multichain->liststreamkeyitems('procurement.status', 'PR-2025-0011-1496', false, 100);

// Should show 2 records: old incorrect + new correct
foreach ($items as $item) {
    $data = json_decode($item['data']['json'], true);
    echo "Time: {$data['timestamp']} - Status: {$data['current_status']}\n";
}
```

Expected output:
```
Time: 2025-01-10T08:30:00Z - Status: procurement_submitted (old/wrong)
Time: 2025-12-15T14:00:00Z - Status: procurement_initiated (new/correct) ✅
```

---

## Implementation Details

### How StatusPublisher Works

```php
// Located in: app/Services/Publishers/StatusPublisher.php

public function publish(
    string $prNumber,
    string $procurementTitle,
    StageEnums $stage,
    StatusEnums $currentStatus,
    string $userAddress,
    ?StatusEnums $previousStatus = null,
    ?array $metadata = null
): array {
    // Creates StatusData DTO
    $status = new StatusData(
        prNumber: $prNumber,
        procurementTitle: $procurementTitle,
        stage: $stage->value,
        currentStatus: $currentStatus->value,
        userAddress: $userAddress,
        timestamp: now(),
        previousStatus: $previousStatus?->value,
        metadata: $metadata,
    );
    
    // Publishes to blockchain via StatusRepository
    $txid = $this->statuses->create($status);
    
    // Invalidates cache
    Cache::forget('procurements:list:all');
    
    return [
        'success' => true,
        'status_txid' => $txid,
        'stage' => $stage->value,
        'current_status' => $currentStatus->value,
    ];
}
```

### Why Metadata is Important

The `metadata` field in the corrected status records serves several purposes:

1. **Audit Trail**: Documents why the correction was made
2. **Traceability**: Shows when and by whom the correction was performed
3. **Debugging**: Helps identify corrected vs original records
4. **Compliance**: Meets government transparency requirements

Example metadata:
```json
{
    "correction_type": "status_mismatch_fix",
    "correction_reason": "Status mismatch: Stage is PROCUREMENT_INITIATION but status was PROCUREMENT_SUBMITTED",
    "corrected_at": "2025-12-15T14:00:00+08:00",
    "corrected_by": "system_admin",
    "original_incorrect_status": "procurement_submitted"
}
```

---

## Correct Stage-Status Mappings

Reference for future corrections:

| Stage | Correct Initial Status |
|-------|------------------------|
| `procurement_initiation` | `procurement_initiated` |
| `pre_procurement_conference` | `pre_procurement_conference_held` |
| `invitation_to_bid` | `invitation_published` |
| `pre_bid_conference` | `pre_bid_conference_held` |
| `bid_submission` | `bids_received` |
| `bid_opening` | `bids_opened` |
| `bid_evaluation` | `bids_evaluated` |
| `post_qualification` | `post_qualification_completed` |
| `notice_of_award` | `noa_issued` |
| `contract_signing` | `contract_signed` |
| `notice_to_proceed` | `ntp_issued` |
| `delivery_acceptance` | `delivery_accepted` |
| `payment_processing` | `payment_processed` |
| `abstract_of_quotations` | `abstract_prepared` |
| `performance_bond_contract_and_po` | `performance_bond_contract_and_po_recorded` |

**Source**: [app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php](../app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php) - `getInitialStatusForStage()` method

---

## Testing Recommendations

### Unit Test

Create test to verify status correction works:

```php
// tests/Feature/StatusCorrectionTest.php

it('corrects mismatched status records', function () {
    // Create procurement with incorrect status
    $pr = createProcurement([
        'stage' => 'procurement_initiation',
        'status' => 'procurement_submitted', // Wrong
    ]);
    
    // Apply correction
    $result = app(\App\Services\Publishers\StatusPublisher::class)->publish(
        prNumber: $pr['pr_number'],
        procurementTitle: $pr['title'],
        stage: \App\Enums\StageEnums::PROCUREMENT_INITIATION,
        currentStatus: \App\Enums\StatusEnums::PROCUREMENT_INITIATED, // Correct
        userAddress: $pr['user_address'],
    );
    
    // Verify correction was published
    expect($result['success'])->toBeTrue();
    expect($result['current_status'])->toBe('procurement_initiated');
    
    // Verify latest status is now correct
    $status = app(\App\Repositories\StatusRepository::class)
        ->getLatest($pr['pr_number']);
        
    expect($status->currentStatus)->toBe('procurement_initiated');
});
```

---

## FAQ

### Q: Will this break anything?

**A**: No. The correction adds a new record without modifying existing ones. The application's "latest wins" pattern ensures the new correct status is used.

### Q: What if I make a mistake in the correction?

**A**: You can publish another correction with the right values. The blockchain will have all records (incorrect → wrong correction → right correction), and the application will use the latest.

### Q: Can users see the old incorrect statuses?

**A**: No. The application only shows the latest status. The old records remain in the blockchain for audit purposes but are not displayed.

### Q: Do I need to restart the application?

**A**: No. The corrections take effect immediately because:
1. Cache is automatically invalidated when status is published
2. Next query will fetch the new correct status

### Q: What about the dashboard and list pages?

**A**: They will automatically show the correct status on next page load. The cache TTL is 5 minutes, so worst case is a 5-minute delay.

### Q: How do I verify the correction worked?

**A**: See [Verification Steps](#verification-steps) above.

---

## Related Documentation

- [ROOT_CAUSE_ANALYSIS.md](ROOT_CAUSE_ANALYSIS.md) - Why the mismatch happened
- [MODE_AWARE_STATUS_FIX.md](MODE_AWARE_STATUS_FIX.md) - The fix that prevents this in future
- [BATCH_PUBLISHING_GUIDE.md](BATCH_PUBLISHING_GUIDE.md) - How batch publishing works
- [AGENTS.md](AGENTS.md) - Codebase guide for AI agents

---

## Recommended Next Steps

1. ✅ **Apply corrections** using Option 1 (Artisan command)
   ```bash
   php artisan status:correct --dry-run  # Preview first
   php artisan status:correct            # Apply corrections
   ```

2. ✅ **Verify corrections** worked
   - Check procurement list page
   - Run tinker verification commands

3. ✅ **Monitor for similar issues**
   - Check if there are other procurements with mismatched statuses
   - Run status consistency check periodically

4. ✅ **Test the fix**
   - Create new procurements
   - Transition through stages
   - Verify statuses match stages correctly

---

**Command Created**: [app/Console/Commands/CorrectProcurementStatuses.php](../app/Console/Commands/CorrectProcurementStatuses.php)

Ready to fix? Run:
```bash
php artisan status:correct --dry-run
```

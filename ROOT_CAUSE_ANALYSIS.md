# Root Cause Analysis: Stage-Status Mismatch in Blockchain

**Date**: December 15, 2025  
**Analyzed By**: AI Agent  
**Status**: ✅ Issues Identified and Corrected

---

## Executive Summary

4 procurements had mismatched stage-status pairs in the blockchain due to **incorrect status parameter** being passed to `publishTransition()` method. The bug exists in two different code patterns across multiple controllers.

---

## Root Cause #1: Hardcoded `PROCUREMENT_SUBMITTED` Status

**Location**: Document upload methods when auto-transitioning stages

**Affected Files**:
- `app/Http/Controllers/Procurement/ProcurementController.php` (Line ~199)
- `app/Http/Controllers/Procurement/PreProcurementController.php` (Line ~203)
- `app/Http/Controllers/Procurement/PostProcurementController.php` (Line ~209)

**The Bug**:
```php
// When documents are uploaded and stage auto-completes:
if ($completionCheck['can_complete']) {
    $nextStage = $this->getNextStage($stage);
    
    if ($nextStage) {
        $this->statusPublisher->publishTransition(
            $pr_number,
            $procurement['title'] ?? 'Unknown',
            $stage,
            $nextStage,
            \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,  // ❌ WRONG! Hardcoded status
            $userAddress
        );
    }
}
```

**Why This Is Wrong**:
- When transitioning FROM `procurement_initiation` TO `pre_procurement_conference`, the status should be `pre_procurement_conference_held` (or appropriate status for the NEW stage)
- Instead, it hardcodes `PROCUREMENT_SUBMITTED` which is the status for the `procurement_initiation` stage
- This causes the blockchain to record: `stage=pre_procurement_conference` but `status=procurement_submitted` ❌

**Affected Procurements**:
- PR-2025-012-0124: Transitioned to `pre_procurement_conference` with wrong status `procurement_submitted`

**The Fix Should Be**:
```php
// Calculate the appropriate status for the NEXT stage
$nextStageStatus = $this->getInitialStatusForStage($nextStage);

$this->statusPublisher->publishTransition(
    $pr_number,
    $procurement['title'] ?? 'Unknown',
    $stage,
    $nextStage,
    $nextStageStatus,  // ✓ Correct status for the next stage
    $userAddress
);
```

---

## Root Cause #2: Using Completion Status of CURRENT Stage

**Location**: Mark complete endpoint when auto-transitioning

**Affected Files**:
- `app/Http/Controllers/Procurement/ProcurementController.php` (Line ~472)
- `app/Http/Controllers/Procurement/PreProcurementController.php` (Line ~476)

**The Bug**:
```php
// In markComplete() method:
// 1. Determine completion status based on CURRENT stage
$completionStatus = $this->getCompletionStatusForStage($stage);

// 2. Publish status update for CURRENT stage ✓ This is correct
$this->statusPublisher->publish(
    prNumber: $pr_number,
    procurementTitle: $procurement->title,
    stage: $stage,
    currentStatus: $completionStatus,  // ✓ Correct for current stage
    userAddress: $userAddress
);

// 3. Get next stage
$nextStage = $this->getNextStageForProcurement($pr_number, $stage);

if ($nextStage) {
    // 4. Publish transition using CURRENT stage's completion status
    $this->statusPublisher->publishTransition(
        prNumber: $pr_number,
        procurementTitle: $procurement->title,
        fromStage: $stage,
        toStage: $nextStage,
        currentStatus: $completionStatus,  // ❌ WRONG! This is completion status of OLD stage
        userAddress: $userAddress
    );
}
```

**Why This Is Wrong**:
- `$completionStatus` is calculated for the CURRENT/OLD stage
- When transitioning to a NEW stage, we need the status for the NEW stage, not the completion status of the old stage
- Example: When completing `post_qualification` (status=`post_qualification_verified`) and transitioning to `bac_resolution`, the blockchain records `stage=bac_resolution` but `status=post_qualification_verified` ❌

**Affected Procurements**:
- PR-2025-0002-0001: Transitioned to `bac_resolution` with `post_qualification_verified` (completion status from previous stage)
- PR-2025-019-0124: Transitioned to `pre_bid_conference` with `bidding_documents_published` (completion status from previous stage)

**The Fix Should Be**:
```php
$nextStage = $this->getNextStageForProcurement($pr_number, $stage);

if ($nextStage) {
    // Calculate the appropriate INITIAL status for the NEXT stage
    $nextStageStatus = $this->getInitialStatusForStage($nextStage);
    
    $this->statusPublisher->publishTransition(
        prNumber: $pr_number,
        procurementTitle: $procurement->title,
        fromStage: $stage,
        toStage: $nextStage,
        currentStatus: $nextStageStatus,  // ✓ Correct status for next stage
        userAddress: $userAddress
    );
}
```

---

## Root Cause #3: Similar Pattern in Stage Skip Logic

**Location**: `HasProcurementSupport` trait and skip decision methods

**Affected Files**:
- `app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php` (Line ~388)
- `app/Http/Controllers/Procurement/PreProcurementController.php` (Lines 711, 839, 934)

**The Bug**:
```php
// When skipping a stage:
$this->statusPublisher->publishTransition(
    prNumber: $prNumber,
    procurementTitle: $procurement->title,
    fromStage: $stage,
    toStage: $nextStage,
    currentStatus: \App\Enums\StatusEnums::STAGE_SKIPPED,  // ❌ Generic skip status, not stage-specific
    userAddress: $userAddress
);
```

**Why This Can Be Problematic**:
- Using `STAGE_SKIPPED` is semantically correct for skipped stages
- However, it's inconsistent with the pattern where the status should represent the state of the NEW stage
- Some skip methods correctly use stage-specific statuses (like `PRE_PROCUREMENT_CONFERENCE_SKIPPED`)

**Mixed Correctness Example**:
```php
// ✓ This one is correct - uses stage-specific skip status
$this->statusPublisher->publishTransition(
    $pr_number,
    $procurement['title'],
    StageEnums::PRE_PROCUREMENT_CONFERENCE,
    StageEnums::BIDDING_DOCUMENTS,
    StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED,  // ✓ Stage-specific
    $userAddress
);

// ✓ This is also correct
$this->statusPublisher->publishTransition(
    $pr_number,
    $procurement['title'],
    StageEnums::SUPPLEMENTAL_BID_BULLETIN,
    StageEnums::BID_OPENING,
    StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,  // ✓ Correct for that stage
    $userAddress
);
```

---

## Special Case: Alternative Procurement Methods

**Affected Procurement**: PR-2025-001-0001

**The Issue**:
- This procurement uses alternative procurement method (Shopping, SVP, Direct Contracting, etc.)
- When transitioning to `bac_resolution`, it used status `quotations_received` which belongs to `request_for_quotation` stage
- This suggests the same Root Cause #2 pattern occurred

---

## Impact Analysis

| PR Number | Wrong Combination | Root Cause | Severity |
|-----------|-------------------|------------|----------|
| PR-2025-0002-0001 | `bac_resolution` + `post_qualification_verified` | RC#2 | High |
| PR-2025-001-0001 | `bac_resolution` + `quotations_received` | RC#2 | High |
| PR-2025-012-0124 | `pre_procurement_conference` + `procurement_submitted` | RC#1 | High |
| PR-2025-019-0124 | `pre_bid_conference` + `bidding_documents_published` | RC#2 | High |

**Severity Justification**: High - These mismatches cause:
1. Incorrect blockchain records (data integrity issue)
2. Potential confusion in audit trails
3. Dashboard/reporting showing wrong statuses
4. Possible workflow logic errors if status is used for routing decisions

---

## The Proper Solution

### Step 1: Create Helper Method

Add a new method to determine the correct initial status for a stage when transitioning TO it:

```php
/**
 * Get the initial/default status when entering a new stage
 * This is used when transitioning FROM one stage TO another
 */
protected function getInitialStatusForStage(StageEnums $stage): StatusEnums
{
    return match ($stage) {
        // Pre-Procurement Phase
        StageEnums::PROCUREMENT_INITIATION => StatusEnums::PROCUREMENT_INITIATED,
        StageEnums::PRE_PROCUREMENT_CONFERENCE => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
        StageEnums::BIDDING_DOCUMENTS => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        StageEnums::REQUEST_FOR_QUOTATION => StatusEnums::QUOTATIONS_RECEIVED,
        
        // Procurement/Bidding Phase
        StageEnums::PRE_BID_CONFERENCE => StatusEnums::PRE_BID_CONFERENCE_HELD,
        StageEnums::SUPPLEMENTAL_BID_BULLETIN => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
        StageEnums::BID_OPENING => StatusEnums::BIDS_OPENED,
        StageEnums::ABSTRACT_OF_QUOTATIONS => StatusEnums::ABSTRACT_PREPARED,
        StageEnums::BID_EVALUATION => StatusEnums::BIDS_EVALUATED,
        StageEnums::POST_QUALIFICATION => StatusEnums::POST_QUALIFICATION_VERIFIED,
        StageEnums::BAC_RESOLUTION => StatusEnums::RESOLUTION_RECORDED,
        
        // Post-Procurement Phase
        StageEnums::NOTICE_OF_AWARD => StatusEnums::AWARDED,
        StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        StageEnums::NOTICE_TO_PROCEED => StatusEnums::NTP_RECORDED,
        StageEnums::MONITORING => StatusEnums::MONITORING_COMPLETED,
        StageEnums::COMPLETION => StatusEnums::COMPLETION_DOCUMENTS_UPLOADED,
        StageEnums::COMPLETED => StatusEnums::COMPLETED,
        
        default => StatusEnums::PROCUREMENT_SUBMITTED,
    };
}
```

### Step 2: Update All publishTransition Calls

Replace all instances where wrong status is passed:

**Before**:
```php
$this->statusPublisher->publishTransition(
    $pr_number,
    $procurement['title'],
    $stage,
    $nextStage,
    StatusEnums::PROCUREMENT_SUBMITTED,  // ❌ Wrong
    $userAddress
);
```

**After**:
```php
$nextStageStatus = $this->getInitialStatusForStage($nextStage);

$this->statusPublisher->publishTransition(
    $pr_number,
    $procurement['title'],
    $stage,
    $nextStage,
    $nextStageStatus,  // ✓ Correct
    $userAddress
);
```

### Step 3: Update Trait Method

In `HasProcurementSupport` trait, ensure the `skipStage()` method also uses proper status.

---

## Files That Need Updates

1. ✅ **app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php**
   - Add `getInitialStatusForStage()` method
   - Update `skipStage()` method (line ~388)

2. ✅ **app/Http/Controllers/Procurement/ProcurementController.php**
   - Update line ~199 (uploadDocuments method)
   - Update line ~472 (markComplete method)

3. ✅ **app/Http/Controllers/Procurement/PreProcurementController.php**
   - Update line ~203 (uploadDocuments method)
   - Update line ~476 (markComplete method)

4. ✅ **app/Http/Controllers/Procurement/PostProcurementController.php**
   - Update line ~209 (uploadDocuments method)
   - Update line ~495 (markComplete method)

5. ✅ **app/Http/Controllers/Procurement/ProcurementInitiationController.php**
   - Check if similar pattern exists

---

## Prevention Measures

1. **Add Validation**: Create a validation method in `StatusPublisher` that checks if the status is valid for the stage
2. **Add Logging**: Log warnings when mismatched stage-status combinations are detected
3. **Add Tests**: Create tests that verify correct status is used when transitioning stages
4. **Code Review**: Add checklist item for stage transition code reviews

---

## Testing Recommendations

After implementing fixes:

1. **Unit Tests**: Test `getInitialStatusForStage()` returns correct status for each stage
2. **Integration Tests**: Test full stage transition workflows
3. **Blockchain Verification**: Verify new transitions have correct stage-status alignment
4. **Regression Tests**: Ensure existing procurements continue working correctly

---

## Status

- ✅ Root causes identified
- ✅ Affected procurements corrected in blockchain
- ⚠️ **Code fixes pending implementation**
- ⚠️ **Tests pending**

---

## Recommendations

**Immediate Actions**:
1. Implement the `getInitialStatusForStage()` helper method
2. Update all 10+ instances of `publishTransition()` calls
3. Add validation in `StatusPublisher::publish()` to detect mismatches
4. Add comprehensive tests

**Long-term**:
1. Consider refactoring stage transition logic into a dedicated service
2. Add stronger typing/validation at the interface level
3. Consider using a state machine pattern for workflow management

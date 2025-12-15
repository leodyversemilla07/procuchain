# Mode-Aware Status Fix Documentation

## Overview

This document describes the implementation of **mode-aware status determination** for the procurement system. The system now correctly assigns statuses based on the procurement mode being used, ensuring stage-status alignment across all procurement workflows defined in the NGPA IRR (RA 12009).

---

## Problem Statement

The previous implementation of `getInitialStatusForStage()` was **mode-agnostic**, meaning it returned the same status for a stage regardless of the procurement mode. This caused issues because:

1. **Different procurement modes have different workflows** (e.g., Competitive Bidding vs Small Value Procurement)
2. **Different modes use different stages** (e.g., Competitive Bidding uses `BID_OPENING`, while SVP uses `REQUEST_FOR_QUOTATION`)
3. **Some stages only exist in specific modes** (e.g., `PRE_BID_CONFERENCE` only exists in competitive modes)

### Example of the Problem

**Before Fix:**
- When transitioning from `PROCUREMENT_INITIATION` to `REQUEST_FOR_QUOTATION` in **Small Value Procurement** mode:
  - System would try to use status from Competitive Bidding workflow
  - This could assign wrong statuses like `BIDDING_DOCUMENTS_PUBLISHED` instead of `QUOTATIONS_RECEIVED`

**After Fix:**
- System now checks the procurement mode and validates that the stage exists in that mode's workflow
- Returns the correct status for the stage within the context of the procurement mode

---

## Solution Implemented

### 1. Updated `getInitialStatusForStage()` Method

**Location:** `app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php`

**Changes:**
- Added `$prNumber` parameter to determine the procurement mode
- Added mode validation to check if stage exists in the mode's workflow
- Returns generic `PROCUREMENT_SUBMITTED` status if stage doesn't exist in mode

```php
protected function getInitialStatusForStage(string $prNumber, StageEnums $stage): \App\Enums\StatusEnums
{
    // Get procurement mode for mode-aware status determination
    $mode = $this->getProcurementMode($prNumber);

    // Validate that stage exists in the procurement's mode workflow
    if ($mode && !$stage->existsInModeWorkflow($mode)) {
        // Stage doesn't exist in this mode's workflow
        return \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED;
    }

    return match ($stage) {
        // ... stage-to-status mappings
    };
}
```

### 2. Updated All Method Calls

Updated **8 call sites** across 5 files to pass `$pr_number`:

#### HasProcurementSupport.php
- `skipStage()` method

#### ProcurementController.php
- `uploadDocuments()` method
- `markComplete()` method

#### PreProcurementController.php
- `uploadDocuments()` method
- `markComplete()` method

#### PostProcurementController.php
- `uploadDocuments()` method
- `markComplete()` method

#### ProcurementInitiationController.php
- `markComplete()` method

---

## Procurement Modes & Their Workflows

### Competitive Modes (Full/Near-Full Bidding Process)

#### 1. Competitive Bidding (Section 27)
**Stages:** 15 total
```
Procurement Initiation → Pre-Procurement Conference → Bidding Documents → 
Pre-Bid Conference → Supplemental Bid Bulletin → Bid Opening → 
Bid Evaluation → Post-Qualification → BAC Resolution → 
Notice of Award → Performance Bond/Contract/PO → Notice to Proceed → 
Monitoring → Completion → Completed
```

#### 2. Limited Source Bidding (Section 28)
**Stages:** Same as Competitive Bidding
- Direct invitation to pre-selected suppliers
- Observes full Competitive Bidding procedure per Section 28.5

#### 3. Competitive Dialogue (Section 29)
**Stages:** 15 total (two-stage process)
```
Procurement Initiation → Pre-Procurement Conference → 
Bidding Documents (First Stage) → Pre-Bid Conference (Dialogue) → 
Supplemental Bid Bulletin → Bid Opening (Second Stage) → 
[Same as CB from here]
```

#### 4. Unsolicited Offer with Bid Matching (Section 30)
**Stages:** 13 total
```
Procurement Initiation → Pre-Procurement Conference (Negotiation) → 
Bidding Documents (Publication for bid matching) → Bid Opening → 
[Same as CB from here]
```

---

### Alternative Modes (Simplified Procedures)

#### 5. Direct Contracting (Section 31)
**Stages:** 9 total - **RFQ-based, no elaborate bidding**
```
Procurement Initiation → Request for Quotation → BAC Resolution → 
Notice of Award → Performance Bond/Contract/PO → Notice to Proceed → 
Monitoring → Completion → Completed
```

#### 6. Direct Acquisition (Section 32)
**Stages:** 7 total - **Simplest workflow (≤₱200,000)**
```
Procurement Initiation → Notice of Award → Performance Bond/Contract/PO → 
Notice to Proceed → Monitoring → Completion → Completed
```

#### 7. Repeat Order (Section 33)
**Stages:** 9 total - **Purchase from previous winning bidder**
```
Procurement Initiation → Request for Quotation → BAC Resolution → 
Notice of Award → Performance Bond/Contract/PO → Notice to Proceed → 
Monitoring → Completion → Completed
```

#### 8. Small Value Procurement (Section 34)
**Stages:** 10 total - **RFQ with 3 quotations (up to ₱2M)**
```
Procurement Initiation → Request for Quotation → Abstract of Quotations → 
BAC Resolution → Notice of Award → Performance Bond/Contract/PO → 
Notice to Proceed → Monitoring → Completion → Completed
```

#### 9. Negotiated Procurement (Section 35)
**Stages:** 9 total - **Negotiation-based**
```
Procurement Initiation → Pre-Procurement Conference (Negotiation) → 
BAC Resolution → Notice of Award → Performance Bond/Contract/PO → 
Notice to Proceed → Monitoring → Completion → Completed
```

#### 10. Direct Sales (Section 36)
**Stages:** 9 total - **From supplier with completed contract**
```
Procurement Initiation → Request for Quotation → BAC Resolution → 
Notice of Award → Performance Bond/Contract/PO → Notice to Proceed → 
Monitoring → Completion → Completed
```

#### 11. Direct Procurement for STI (Section 37)
**Stages:** 9 total - **For science, technology, innovation, R&D**
```
Procurement Initiation → Request for Quotation → BAC Resolution → 
Notice of Award → Performance Bond/Contract/PO → Notice to Proceed → 
Monitoring → Completion → Completed
```

---

## Stage-to-Status Mappings

### Universal Stages (All Modes)
| Stage | Initial Status |
|-------|---------------|
| `procurement_initiation` | `procurement_initiated` |
| `notice_of_award` | `awarded` |
| `performance_bond_contract_and_po` | `performance_bond_contract_and_po_recorded` |
| `notice_to_proceed` | `ntp_recorded` |
| `monitoring` | `monitoring_completed` |
| `completion` | `completion_documents_uploaded` |
| `completed` | `completed` |

### Competitive Mode Stages
| Stage | Initial Status |
|-------|---------------|
| `pre_procurement_conference` | `pre_procurement_conference_held` |
| `bidding_documents` | `bidding_documents_published` |
| `pre_bid_conference` | `pre_bid_conference_held` |
| `supplemental_bid_bulletin` | `supplemental_bulletins_ongoing` |
| `bid_opening` | `bids_opened` |
| `bid_evaluation` | `bids_evaluated` |
| `post_qualification` | `post_qualification_verified` |

### Alternative Mode Stages
| Stage | Initial Status |
|-------|---------------|
| `request_for_quotation` | `quotations_received` |
| `abstract_of_quotations` | `abstract_prepared` |

### Common to All
| Stage | Initial Status |
|-------|---------------|
| `bac_resolution` | `resolution_recorded` |

---

## Mode-Aware Validation Flow

```
User initiates stage transition
         ↓
getNextStageForProcurement($prNumber, $currentStage)
         ↓
Retrieves procurement mode from DB
         ↓
Gets mode-specific next stages using StageEnums::getNextStagesForMode($mode)
         ↓
Returns appropriate next stage for that mode
         ↓
getInitialStatusForStage($prNumber, $nextStage)
         ↓
Validates stage exists in mode workflow
         ↓
Returns correct initial status for that stage
         ↓
Publishes to blockchain with correct stage-status pair
```

---

## Key Validation Methods

### `existsInModeWorkflow(ProcurementModeEnums $mode): bool`
**Location:** `StageEnums.php`

Checks if a stage exists in a specific mode's workflow.

**Example:**
```php
StageEnums::BID_OPENING->existsInModeWorkflow(
    ProcurementModeEnums::COMPETITIVE_BIDDING
); // true

StageEnums::BID_OPENING->existsInModeWorkflow(
    ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
); // false
```

### `getStagesForMode(ProcurementModeEnums $mode): array`
**Location:** `StageEnums.php`

Returns all stages in a specific mode's workflow.

**Example:**
```php
$stages = StageEnums::getStagesForMode(
    ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
);
// Returns: [PROCUREMENT_INITIATION, REQUEST_FOR_QUOTATION, 
//           ABSTRACT_OF_QUOTATIONS, BAC_RESOLUTION, ...]
```

### `getNextStagesForMode(ProcurementModeEnums $mode): array`
**Location:** `StageEnums.php`

Returns the next valid stages from the current stage for a specific mode.

**Example:**
```php
$nextStages = StageEnums::REQUEST_FOR_QUOTATION->getNextStagesForMode(
    ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
);
// Returns: [ABSTRACT_OF_QUOTATIONS]
```

---

## Testing Recommendations

### 1. Unit Tests
Create tests for `getInitialStatusForStage()` with different modes:

```php
it('returns correct status for SVP mode stages', function () {
    $procurement = Procurement::factory()->create([
        'procurement_mode' => ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
    ]);
    
    $controller = new TestController();
    $status = $controller->getInitialStatusForStage(
        $procurement->pr_number,
        StageEnums::REQUEST_FOR_QUOTATION
    );
    
    expect($status)->toBe(StatusEnums::QUOTATIONS_RECEIVED);
});

it('returns generic status for invalid stage-mode combination', function () {
    $procurement = Procurement::factory()->create([
        'procurement_mode' => ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
    ]);
    
    $controller = new TestController();
    $status = $controller->getInitialStatusForStage(
        $procurement->pr_number,
        StageEnums::BID_OPENING // Doesn't exist in SVP
    );
    
    expect($status)->toBe(StatusEnums::PROCUREMENT_SUBMITTED);
});
```

### 2. Integration Tests
Test complete workflows for each procurement mode:

```php
it('completes SVP workflow with correct statuses', function () {
    $procurement = Procurement::factory()->create([
        'procurement_mode' => ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
    ]);
    
    // Test each stage transition
    // Verify correct status is published to blockchain
});
```

### 3. Browser Tests
Test UI workflow for different modes:

```php
it('displays correct stages for SVP mode', function () {
    $page = visit('/bac-secretariat/dashboard');
    
    $page->assertSee('Request for Quotation')
         ->assertDontSee('Bid Opening');
});
```

---

## Files Modified

1. **app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php**
   - Updated `getInitialStatusForStage()` signature and logic
   - Updated `skipStage()` to pass `$prNumber`

2. **app/Http/Controllers/Procurement/ProcurementController.php**
   - Updated `uploadDocuments()` to pass `$pr_number`
   - Updated `markComplete()` to pass `$pr_number`

3. **app/Http/Controllers/Procurement/PreProcurementController.php**
   - Updated `uploadDocuments()` to pass `$pr_number`
   - Updated `markComplete()` to pass `$pr_number`

4. **app/Http/Controllers/Procurement/PostProcurementController.php**
   - Updated `uploadDocuments()` to pass `$pr_number`
   - Updated `markComplete()` to pass `$pr_number`

5. **app/Http/Controllers/Procurement/ProcurementInitiationController.php**
   - Updated `markComplete()` to pass `$pr_number`

---

## Benefits

### 1. **Correct Stage-Status Alignment**
- Ensures blockchain records always have valid stage-status pairs
- Prevents mismatches like "bid_opening stage with quotations_received status"

### 2. **Mode-Specific Validation**
- System validates stages exist in the procurement mode's workflow
- Prevents invalid stage transitions

### 3. **Compliance with NGPA IRR**
- Each procurement mode follows its defined workflow per RA 12009
- Maintains regulatory compliance

### 4. **Improved Auditability**
- Blockchain records accurately reflect the procurement process
- Easier to track and verify procurement activities

### 5. **Future-Proof**
- Easy to add new procurement modes
- Status determination logic is centralized and maintainable

---

## Related Documentation

- [ROOT_CAUSE_ANALYSIS.md](ROOT_CAUSE_ANALYSIS.md) - Original bug analysis
- [StageEnums.php](app/Enums/StageEnums.php) - Stage definitions and mode workflows
- [StatusEnums.php](app/Enums/StatusEnums.php) - Status definitions
- [ProcurementModeEnums.php](app/Enums/ProcurementModeEnums.php) - Procurement mode definitions
- [AGENTS.md](AGENTS.md) - AI agent guidelines including mode-aware requirements

---

## Maintenance Notes

### Adding a New Procurement Mode
1. Add mode case to `ProcurementModeEnums.php`
2. Define workflow in `StageEnums::getStagesForMode()`
3. Define optional stages in `StageEnums::getOptionalStagesForMode()`
4. Update stage-to-status mapping in `getInitialStatusForStage()` if needed
5. Test the new mode's complete workflow

### Adding a New Stage
1. Add stage case to `StageEnums.php`
2. Add to relevant mode workflows in `getStagesForMode()`
3. Add status mapping in `getInitialStatusForStage()`
4. Add status case to `StatusEnums.php` if needed
5. Update blockchain filters if needed

---

## Conclusion

The mode-aware status determination system ensures that all procurement workflows follow their defined processes per NGPA IRR (RA 12009), with correct stage-status alignment in blockchain records. This fix prevents future mismatches and maintains regulatory compliance across all procurement modes.

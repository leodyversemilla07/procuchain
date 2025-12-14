# Procurement Not Found Error - Fix Documentation

**Date:** December 14, 2025  
**Issues Fixed:** 
1. Document upload failing with "Upload failed - Procurement not found" error
2. Procurement corrections failing with "Failed to submit correction" error

**PR Numbers Affected:** PR-2024-001, PR-2025-0002-0001 (and potentially others)  
**Affected URLs:** All document upload pages and procurement corrections across workflow stages

## Problem Analysis

### Root Cause
The application uses **two different blockchain streams** to fetch procurement data:

1. **STATUS Stream** (`procurement.status`) - Used by the `show()` method to display pages
2. **METADATA Stream** (`procurement.metadata`) - Used by `uploadSingleDocument()` and `correctProcurement()` methods

This created a critical inconsistency where:
- ✅ Page loads successfully (data from STATUS stream)
- ❌ Upload fails (data missing/unavailable from METADATA stream)
- ❌ Correction submission fails (data missing/unavailable from METADATA stream)

### Technical Details

**Before the Fix:**
```php
// In show() method - Uses STATUS stream via findProcurementById()
$procurement = $this->findProcurementById($pr_number);

// In uploadSingleDocument() method - Uses METADATA stream only
$procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
if (! $procurement) {
    return back()->withErrors(['message' => 'Procurement not found']);
}
```

**The Problem:**
- `findProcurementById()` fetches from STATUS stream via `ProcurementDataService::fetchStatusItems()`
- `ProcurementRepository::findByProcurement()` fetches from METADATA stream via `liststreamkeyitems()`
- If METADATA stream is temporarily unavailable, out of sync, or has blockchain connectivity issues, uploads fail

## Solution Implemented

### Fallback Mechanism
Implemented a resilient two-tier lookup system:
1. **Primary:** Try METADATA stream first (original behavior)
2. **Fallback:** If METADATA fails, use STATUS stream (same as page load)
3. **Create DTO:** Build a temporary `ProcurementData` DTO from STATUS stream data

### Code Changes

**Files Modified:**
2. `app/Http/Controllers/Procurement/ProcurementController.php`
3. `app/Http/Controllers/Procurement/PostProcurementController.php`
4. `app/Http/Controllers/Procurement/ProcurementInitiationController.php`
5. `app/Http/Controllers/ProcurementCorrectionController.php` ⭐ NEW
1. `app/Http/Controllers/Procurement/PreProcurementController.php`
2. `app/Http/Controllers/Procurement/ProcurementController.php`
3. `app/Http/Controllers/Procurement/PostProcurementController.php`
4. `app/Http/Controllers/Procurement/ProcurementInitiationController.php`

**After the Fix:**
```php
// Get procurement details - Try METADATA stream first, fallback to STATUS stream
$procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);

// Fallback to STATUS stream if METADATA stream fails (provides resilience)
if (! $procurement) {
    \Log::warning('Procurement not found in METADATA stream, attempting fallback to STATUS stream', [
        'pr_number' => $pr_number,
        'stage' => $stage->value,
        'user' => $user->email,
    ]);
    
    $statusData = $this->findProcurementById($pr_number);
    if (! $statusData) {
        \Log::error('Procurement not found in both METADATA and STATUS streams', [
            'pr_number' => $pr_number,
            'stage' => $stage->value,
            'user' => $user->email,
        ]);
        return back()->withErrors(['message' => 'Procurement not found. Please ensure the procurement has been properly initiated.']);
    }
    
    // Create a temporary ProcurementData DTO from STATUS stream data
    $procurement = new \App\DataTransferObjects\ProcurementData(
        prNumber: $pr_number,
        title: $statusData['procurement_title'] ?? 'N/A',
        status: \App\Enums\StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
        stage: \App\Enums\StageEnums::tryFrom($statusData['stage'] ?? '') ?? $stage,
        procurementMode: $this->getProcurementMode($pr_number) ?? \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
        timestamp: $statusData['timestamp'] ?? now()->toIso8601String(),
        userAddress: $statusData['user_address'] ?? $userAddress,
    );
    
    \Log::info('Using STATUS stream fallback for procurement data', [
        'pr_number' => $pr_number,
        'title' => $procurement->title,
    ]);
}
```

## Benefits of This Fix

### 1. **Improved Resilience**
- Document uploads no longer fail if one blockchain stream is temporarily unavailable
- Graceful degradation: system continues working with available data

### 2. **Better Diagnostics**
- Enhanced logging shows exactly which stream failed
- Easier troubleshooting of blockchain connectivity issues
- Clear audit trail in logs

### 3. **Data Consistency**
- Uses the same data source (STATUS stream) that successfully loaded the page
- If users can see the page, they can now upload documents

### 4. **No Breaking Changes**
- Maintains backward compatibility
- Primary behavior unchanged (METADATA stream first)
- Only activates fallback when needed

## Logging & Monitoring

The fix adds comprehensive logging at three levels:

1. **Warning Level** - METADATA stream lookup failed, attempting fallback
   ```
   'Procurement not found in METADATA stream, attempting fallback to STATUS stream'
   ```

2. **Error Level** - Both streams failed (critical issue)
   ```
   'Procurement not found in both METADATA and STATUS streams'
   ```

3. **Info Level** - Fallback successful
   ```
   'Using STATUS stream fallback for procurement data'
   ```

## Testing

### Tests Run & Passed
✅ All Progressive Document Upload tests (2/2)  
✅ All Document Validation tests (36/36)  
✅ Code formatting (Laravel Pint)  
✅ No syntax errors

### Test Coverage
- Authorization tests
- Document validation workflow
- Mode-aware document requirements
- Progressive upload workflow

## Deployment Recommendations

### Pre-Deployment Checklist
- [x] Code changes implemented
- [x] Code formatted with Pint
- [x] Tests passing
- [x] No syntax errors
- [ ] Review server logs for METADATA stream issues
- [ ] Monitor blockchain node connectivity

### Post-Deployment Monitoring

**Watch for these log messages:**
1. **Warning messages** - Indicates METADATA stream reliability issues
2. **Error messages** - Both streams failing (critical blockchain issue)
3. **Info messages** - Fallback being used successfully

**Metrics to Monitor:**
- Frequency of fallback usage
- Document upload success rate
- Blockchain node health
- Stream synchronization status

### If Issues Persist

If users still encounter "Procurement not found" errors after this fix:

1. **Check blockchain node status:**
   ```bash
   multichain-cli procuchain getinfo
   ```

2. **Verify stream availability:**
   ```bash
   multichain-cli procuchain liststreamkeys procurement.metadata
   multichain-cli procuchain liststreamkeys procurement.status
   ```

3. **Check application logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "Procurement not found"
   ```

4. **Verify procurement exists:**
   ```bash
   multichain-cli procuchain liststreamkeyitems procurement.status PR-2024-001
   ```

## Preventive Measures

To prevent this issue from recurring:

### 1. **Improve Stream Synchronization**
- Ensure both METADATA and STATUS streams are updated atomically
- Consider using transactions or batch publishing

### 2. **Add Health Checks**
- Monitor METADATA stream availability
- Alert when stream sync drift is detected

### 3. **Consistent Data Access Pattern**
- Standardize which stream is the "source of truth" for each operation
- Document when to use METADATA vs STATUS stream

### 4. **Database Caching Layer** (Future Enhancement)
- Cache procurement data in MySQL for faster access
- Use blockchain as authoritative source, database as performance layer
- Reduce dependency on direct blockchain queries

## Related Files

### Related Files

### Controllers Modified
- `app/Http/Controllers/Procurement/PreProcurementController.php`
- `app/Http/Controllers/Procurement/ProcurementController.php`
- `app/Http/Controllers/Procurement/PostProcurementController.php`
- `app/Http/Controllers/Procurement/ProcurementInitiationController.php`
- `app/Http/Controllers/ProcurementCorrectionController.php` ⭐ NEW

### Supporting Classes
- `app/Repositories/ProcurementRepository.php` - METADATA stream access
- `app/Services/ProcurementDataService.php` - STATUS stream access
- `app/Http/Controllers/Procurement/Concerns/HasProcurementSupport.php` - Shared helper methods
- `app/DataTransferObjects/ProcurementData.php` - DTO structure

### Tests
- `tests/Feature/ProgressiveDocumentUploadTest.php`
- `tests/Feature/DocumentValidationWorkflowTest.php`
- `tests/Feature/ModeAwareDocumentRequirementsTest.php`

## Conclusion

This fix addresses the immediate "Procurement not found" error by implementing a robust fallback mechanism. The solution:
- ✅ Fixes the production error
- ✅ Maintains existing functionality
- ✅ Improves system resilience
- ✅ Adds better diagnostics
- ✅ All tests passing

The root cause (stream inconsistency) should be further investigated to determine if there are underlying blockchain synchronization issues that need addressing.

---

**For questions or issues, check:**
- Application logs: `storage/logs/laravel.log`
- Blockchain logs: MultiChain data directory
- This documentation: `PROCUREMENT_NOT_FOUND_FIX.md`

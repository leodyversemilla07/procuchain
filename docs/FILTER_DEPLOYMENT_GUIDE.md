# Stream Filter Deployment Guide

## Updated Filters v2.0/v3.0

All ProcuChain filters have been updated to support the new `pr_number` identifier while maintaining backward compatibility with `pr_number`.

### Updated Filters:
- **procuchain_status_v3** (Status Filter v3.0)
- **procuchain_documents_v2** (Documents Filter v2.0)
- **procuchain_events_v2** (Events Filter v2.0)
- **procuchain_corrections_v2** (Corrections Filter v2.0)

### Changes in v2/v3:
- **Primary identifier**: `pr_number` (format: PR-YYYY-####-####)
- **Backward compatible**: Still accepts `pr_number` (UUID format)
- **Validation**: PR number format validation added
- **Required fields**: Either `pr_number` OR `pr_number` must be provided (not both required)
- **DTO Alignment**: All filters now match the DTO field expectations

### Filter File Locations
```
resources/blockchain/filters/status_filter_v3_standalone.js
resources/blockchain/filters/documents_filter_v1_standalone.js (updated to v2.0)
resources/blockchain/filters/events_filter_v1_standalone.js (updated to v2.0)
resources/blockchain/filters/corrections_filter_v1_standalone.js (updated to v2.0)
```

**Note**: The file names still reference v1, but the internal version is now v2.0 for documents, events, and corrections filters.

### Manual Deployment Steps

Since the Laravel command doesn't have full filter API support, deploy manually using MultiChain CLI:

#### 1. Create the filters

```bash
# Navigate to MultiChain data directory
cd ~/.multichain/procuchain

# Create the status filter
multichain-cli procuchain create streamfilter procuchain_status_v3 '{"for":"procurement.status"}' "$(cat /path/to/status_filter_v3_standalone.js)"

# Create the documents filter v2
multichain-cli procuchain create streamfilter procuchain_documents_v2 '{"for":"procurement.documents"}' "$(cat /path/to/documents_filter_v1_standalone.js)"

# Create the events filter v2
multichain-cli procuchain create streamfilter procuchain_events_v2 '{"for":"procurement.events"}' "$(cat /path/to/events_filter_v1_standalone.js)"

# Create the corrections filter v2
multichain-cli procuchain create streamfilter procuchain_corrections_v2 '{"for":"procurement.corrections"}' "$(cat /path/to/corrections_filter_v1_standalone.js)"
```

#### 2. Test the filters

Each filter is automatically in test mode after creation. Test each one:

```bash
# Test status filter
multichain-cli procuchain publish procurement.status "PR-2025-TEST-0001" '{"json":{"pr_number":"PR-2025-TEST-0001","procurement_title":"Test Procurement","current_status":"procurement_submitted","stage":"procurement_initiation","timestamp":"2025-11-15T00:00:00Z","user_address":"<valid-address>"}}'

# Test documents filter
multichain-cli procuchain publish procurement.documents "PR-2025-TEST-0001" '{"json":{"pr_number":"PR-2025-TEST-0001","procurement_title":"Test Procurement","hash":"a".repeat(64),"file_key":"test-key","document_type":"procurement_initiation","file_size":1024,"stage":"procurement_initiation","timestamp":"2025-11-15T00:00:00Z","user_address":"<valid-address>"}}'

# Test events filter
multichain-cli procuchain publish procurement.events "PR-2025-TEST-0001" '{"json":{"pr_number":"PR-2025-TEST-0001","procurement_title":"Test Procurement","event_type":"procurement_created","stage":"procurement_initiation","category":"procurement","severity":"info","details":"Test event","document_count":0,"timestamp":"2025-11-15T00:00:00Z","user_address":"<valid-address>"}}'

# Test corrections filter
multichain-cli procuchain publish procurement.corrections "PR-2025-TEST-0001" '{"json":{"pr_number":"PR-2025-TEST-0001","procurement_title":"Test Procurement","correction_type":"document_correction","original_txid":"a".repeat(64),"original_document_hash":"b".repeat(64),"reason":"Test correction reason for validation","corrected_by":"Test User","action":"invalidate","timestamp":"2025-11-15T00:00:00Z","user_address":"<valid-address>"}}'
```

If a filter works, you'll see the transaction ID. If it fails, you'll see the validation error from the filter.

#### 3. Approve the filters (Production deployment)

After testing, approve all filters for production use:

```bash
# Get your admin address
multichain-cli procuchain getaddresses

# Approve each filter (replace <admin-address> with actual address)
multichain-cli procuchain approvefrom <admin-address> procuchain_status_v3 true
multichain-cli procuchain approvefrom <admin-address> procuchain_documents_v2 true
multichain-cli procuchain approvefrom <admin-address> procuchain_events_v2 true
multichain-cli procuchain approvefrom <admin-address> procuchain_corrections_v2 true
```

### Verification

Check deployed filters:

```bash
# List all stream filters
multichain-cli procuchain liststreamfilters

# Check specific filters
multichain-cli procuchain liststreamfilters procuchain_status_v3
multichain-cli procuchain liststreamfilters procuchain_documents_v2
multichain-cli procuchain liststreamfilters procuchain_events_v2
multichain-cli procuchain liststreamfilters procuchain_corrections_v2
```

### Current Implementation Status

All filters are now aligned with DTOs:

1. **All filters**: Accept both `pr_number` (primary) and `pr_number` (legacy) for backward compatibility
2. **All DTOs**: Use `prNumber` as the property name, output as `pr_number` in `toBlockchainArray()`
3. **Publishing services**: Should include both `pr_number` and `pr_number` fields during transition
4. **Format validation**: PR numbers must match pattern `PR-YYYY-####-####` (e.g., PR-2025-0001-0042)

### Testing the Updated System

```php
// Test with tinker
php artisan tinker

$multichain = app(\App\Services\FileStorageService::class)->multichain;

// Test publishing with pr_number
$statusData = [
    'pr_number' => 'PR-2025-TEST-9999',
    'pr_number' => 'PR-2025-TEST-9999', // Include for compatibility
    'procurement_title' => 'Test Procurement',
    'stage' => 'procurement_initiation',
    'current_status' => 'procurement_submitted',
    'timestamp' => now()->toIso8601String(),
    'user_address' => '1G58VnnbEYuJwMdfuxS44hTytKUrRNY2FvLmq',
    'metadata' => [],
];

$txid = $multichain->publish('procurement.status', 'PR-2025-TEST-9999', ['json' => $statusData]);
echo "Published: $txid\n";
```

## Notes

- All filters (status v3, documents v2, events v2, corrections v2) are now **aligned with DTOs** and compatible with both identifiers
- The blockchain may still have old filter versions active (e.g., `procuchain_status_validator`, `procuchain_documents_v1`)
- Deployment of new filters requires MultiChain admin permissions
- Filter approval is a blockchain-level operation that requires consensus if multiple admins
- Filter filenames still show v1 for some, but internal versions are updated to v2.0

## Future Enhancement

To fully automate filter deployment, add these methods to `app/Libraries/MultiChain/Manager.php`:

```php
public function createStreamFilter(string $name, object $restrictions, string $code): string
{
    return $this->request('create', ['streamfilter', $name, $restrictions, $code]);
}

public function approveFilter(string $fromAddress, string $filterName, bool $approve): string
{
    return $this->request('approvefrom', [$fromAddress, $filterName, $approve]);
}

public function listStreamFilters(?string $filterName = null): array
{
    $params = $filterName ? [$filterName] : [];
    return $this->request('liststreamfilters', $params);
}
```

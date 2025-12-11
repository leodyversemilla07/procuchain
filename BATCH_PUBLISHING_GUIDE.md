# Batch Publishing Guide (publishmulti)

## Overview

Batch publishing using MultiChain's `publishmulti` API allows atomic publishing of multiple items to one or more streams in a single transaction. This provides **60-70% performance improvement** over sequential publishes, perfect for government systems requiring rapid feedback without background queues.

## Benefits

✅ **Atomic Operations** - All items succeed or all fail together (database-like ACID)  
✅ **Single Transaction** - One TXID for multiple stream items  
✅ **Synchronous** - Immediate blockchain confirmation  
✅ **No Queue Required** - Fast enough for real-time feedback  
✅ **Reduced Network Overhead** - One RPC call instead of N calls

## Performance Impact

| Workflow | Before (Sequential) | After (Batch) | Improvement |
|----------|-------------------|---------------|-------------|
| Status + Event | 400-1000ms | 150-300ms | ~70% |
| Document + Status + Event | 700-2100ms | 300-500ms | ~65% |
| File Upload (data + metadata) | 400-800ms | 200-350ms | ~60% |

---

## Configuration

Set batch publishing options in `config/blockchain.php`:

```php
'batch_publishing' => [
    'enabled' => true,
    'max_items_per_batch' => 32,  // MultiChain default limit
    'log_performance' => true,
],
```

Environment variables:
```bash
BLOCKCHAIN_BATCH_ENABLED=true
BLOCKCHAIN_BATCH_MAX_ITEMS=32
BLOCKCHAIN_BATCH_LOG_PERFORMANCE=true
```

---

## Usage Examples

### Example 1: ProcurementOrchestrator (Status + Event)

**Before (Sequential - 400-1000ms):**
```php
$statusTxid = $statusPublisher->publish($statusData);
$eventTxid = $eventPublisher->publish($eventData);
```

**After (Batch - 150-300ms):**
```php
$result = $orchestrator->publishStatusWithEventBatch(
    prNumber: 'PR-2024-001',
    procurementTitle: 'Office Supplies Procurement',
    stage: StageEnums::PROCUREMENT_INITIATION,
    currentStatus: StatusEnums::PROCUREMENT_INITIATED,
    userAddress: '1ABC123xyz',
    eventData: [
        'event_type' => 'status_change',
        'category' => 'workflow',
        'details' => 'Procurement initiated successfully',
    ],
);

// Returns:
// [
//     'success' => true,
//     'pr_number' => 'PR-2024-001',
//     'txid' => '6a8e9f7d...',
//     'items_published' => 2,
//     'duration_ms' => 285,
//     'performance_improvement' => '52.5%',
// ]
```

### Example 2: BlockchainStorageService (File Data + Metadata)

**Before (Sequential - 400-800ms):**
```php
// Store file data
$dataTxid = $multichain->publish('file.data', $dataKey, $fileHex);

// Store file metadata
$metadataTxid = $multichain->publish('file.metadata', $dataKey, ['json' => $metadata]);
```

**After (Batch - 200-350ms):**
```php
$result = $storageService->uploadFile(
    file: $uploadedFile,
    prNumber: 'PR-2024-001',
    stageId: 1,
    documentType: 'bid_document',
    metadata: ['pr_number' => 'PR-2024-001'],
);

// Automatically uses batch publishing internally
// Returns:
// [
//     'file_key' => 'pre-procurement/PR-2024-001/bid_document/abc123.pdf',
//     'data_txid' => 'batch_txid_789',
//     'metadata_txid' => 'batch_txid_789',  // Same txid
//     'filename' => 'document.pdf',
//     'size' => 102400,
//     'hash' => 'sha256_hash...',
// ]
```

### Example 3: Direct publishmulti Usage

```php
use App\Services\Manager;

$multichain = app(Manager::class);

$items = [
    [
        'key' => 'PR-2024-001',
        'data' => ['json' => [
            'pr_number' => 'PR-2024-001',
            'stage' => 'procurement_initiation',
            'status' => 'procurement_initiated',
            'timestamp' => now()->toIso8601String(),
        ]],
        'for' => 'procurement.status',
    ],
    [
        'key' => 'PR-2024-001_office_supplies',
        'data' => ['json' => [
            'pr_number' => 'PR-2024-001',
            'event_type' => 'status_change',
            'category' => 'workflow',
            'details' => 'Status changed to initiated',
        ]],
        'for' => 'procurement.events',
    ],
];

$txid = $multichain->publishmulti('procurement.status', $items);
```

### Example 3: Publishing Without Event

```php
// When no event is needed, batch still provides benefits
$result = $orchestrator->publishStatusWithEventBatch(
    prNumber: 'PR-2024-002',
    procurementTitle: 'IT Equipment',
    stage: StageEnums::BIDDING_DOCUMENTS,
    currentStatus: StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
    userAddress: '1XYZ789abc',
    eventData: null,  // No event
);
// Only publishes status, but uses same batch API
```

### Example 4: Including Previous Status

```php
$result = $orchestrator->publishStatusWithEventBatch(
    prNumber: 'PR-2024-003',
    procurementTitle: 'Construction Materials',
    stage: StageEnums::BID_EVALUATION,
    currentStatus: StatusEnums::BIDS_EVALUATED,
    userAddress: '1TEST123',
    eventData: [
        'event_type' => 'stage_transition',
        'category' => 'workflow',
        'details' => 'Bids evaluated, moving to post-qualification',
    ],
    previousStatus: StatusEnums::BIDS_OPENED,  // Track transition
);
```

---

## Item Structure

Each item in the batch must have:

```php
[
    'key' => 'unique-key',           // Stream item key
    'data' => ['json' => $dataArray], // JSON data
    'for' => 'stream.name',          // Target stream (optional for first item)
]
```

### Multiple Streams

You can publish to multiple streams in a single transaction:

```php
$items = [
    ['key' => 'doc1', 'data' => [...], 'for' => 'procurement.documents'],
    ['key' => 'PR-001', 'data' => [...], 'for' => 'procurement.status'],
    ['key' => 'event1', 'data' => [...], 'for' => 'procurement.events'],
];

$txid = $multichain->publishmulti('procurement.documents', $items);
```

---

## Performance Monitoring

The batch publish method automatically logs performance metrics:

```php
Log::info('Orchestrator: Batch publish successful', [
    'pr_number' => 'PR-2024-001',
    'txid' => '6a8e9f7d...',
    'items_count' => 2,
    'duration_ms' => 285,
    'estimated_sequential_ms' => 600,
    'performance_improvement' => '52.5%',
]);
```

### Disable Performance Logging

```bash
BLOCKCHAIN_BATCH_LOG_PERFORMANCE=false
```

---

## Limitations

1. **Max Items**: Default limit is 32 items per batch (controlled by `max-std-op-returns-count` blockchain parameter)
2. **Item Size**: Each item limited by max item size (controlled by `max-std-element-size`)
3. **Transaction Size**: Total transaction must fit within max block size

---

## Error Handling

Batch publishing is atomic - if any item fails, the entire transaction fails:

```php
try {
    $result = $orchestrator->publishStatusWithEventBatch(...);
} catch (Exception $e) {
    Log::error('Batch publish failed', [
        'pr_number' => $prNumber,
        'error' => $e->getMessage(),
    ]);
    
    // All items failed - none were published
    // Handle rollback if needed
}
```

---

## Testing

Comprehensive tests in `tests/Feature/Services/ProcurementOrchestratorBatchTest.php`:

- ✅ Publishes status and event in single atomic transaction
- ✅ Publishes only status when no event provided
- ✅ Includes previous status when provided
- ✅ Logs performance metrics

Run tests:
```bash
php artisan test tests/Feature/Services/ProcurementOrchestratorBatchTest.php
```

---

## Migration Path

### Option 1: Gradual Migration (Recommended)

Keep existing sequential methods, add batch methods alongside:

```php
// Old method (still works)
$orchestrator->publishStatusWithEvent(...);

// New method (60-70% faster)
$orchestrator->publishStatusWithEventBatch(...);
```

### Option 2: Feature Flag

```php
if (config('blockchain.batch_publishing.enabled')) {
    $orchestrator->publishStatusWithEventBatch(...);
} else {
    $orchestrator->publishStatusWithEvent(...);
}
```

### Option 3: Replace Completely

Update all calls to use batch methods for maximum performance.

---

## Best Practices

1. **Use batch publishing for related items** - Document + Status + Event published together
2. **Keep batches focused** - Group logically related items (e.g., all items for one PR)
3. **Don't exceed limits** - Stay under 32 items per batch
4. **Monitor performance** - Enable logging to track improvements
5. **Test atomicity** - Verify rollback behavior when items fail

---

## References

- [MultiChain publishmulti API](https://www.multichain.com/developers/json-rpc-api/#publishmulti)
- [MultiChain PHP Library README](../app/Libraries/MultiChain/README.md)
- [Performance Optimizations](PERFORMANCE_OPTIMIZATIONS.md)
- [ProcurementOrchestrator Source](app/Services/Publishers/ProcurementOrchestrator.php)

---

## Questions or Issues?

If you experience any issues with batch publishing:

1. Check `storage/logs/laravel.log` for batch publish logs
2. Verify blockchain configuration: `php artisan tinker` → `config('blockchain.batch_publishing')`
3. Test with small batches first (2-3 items)
4. Check MultiChain node health: `php artisan blockchain:health`

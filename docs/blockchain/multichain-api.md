# MultiChain JSON-RPC API Integration

This document provides comprehensive documentation for ProcuChain's integration with the MultiChain blockchain via JSON-RPC API. The integration is implemented through the `MultichainService` class which provides a type-safe, Laravel-friendly interface to MultiChain operations.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    Laravel Application                   │
├─────────────────────────────────────────────────────────┤
│  Controllers, Jobs, Services (Business Logic)           │
│             ↓                                            │
│  MultichainService (API Wrapper)                        │
│             ↓                                            │
│  MultichainConnectionService (Connection Manager)       │
│             ↓                                            │
│  denpa/php-multichain (JSON-RPC Client)                 │
├─────────────────────────────────────────────────────────┤
│              HTTP/JSON-RPC Communication                 │
├─────────────────────────────────────────────────────────┤
│              MultiChain Node (Port 4266)                │
└─────────────────────────────────────────────────────────┘
```

### Key Components

1. **MultichainService** (`app/Services/MultichainService.php`)
   - Primary service class with 90+ blockchain operations
   - Type-safe method signatures with proper return types
   - Comprehensive PHPDoc documentation
   - Exception handling and logging

2. **MultichainConnectionService** (`app/Services/MultichainConnectionService.php`)
   - Connection management and retry logic
   - Exponential backoff for failed requests
   - Configuration management
   - Circuit breaker pattern implementation

3. **Configuration** (`config/multichain.php`)
   - Host, port, credentials
   - Retry settings
   - Stream names
   - Admin addresses

## API Categories

The MultiChain API is organized into logical categories:

### 1. General Utilities
Node information, blockchain parameters, and system status.

**Methods:**
- `getInfo()` - Get node and blockchain information
- `getBlockchainParams()` - Get immutable blockchain parameters
- `getRuntimeParams()` - Get mutable runtime parameters
- `setRuntimeParam($param, $value)` - Set a runtime parameter
- `getInitStatus()` - Get blockchain initialization status

**Example:**
```php
$info = $multichainService->getInfo();
// Returns: chainname, version, blocks, balance, walletversion, etc.

$params = $multichainService->getBlockchainParams();
// Returns: chain-protocol, target-block-time, maximum-block-size, etc.
```

### 2. Address Management
Create, import, and manage blockchain addresses.

**Methods:**
- `getAddresses()` - List all addresses in wallet
- `getNewAddress()` - Create a new blockchain address
- `importAddress($address, ?$label)` - Import an address (watch-only)
- `listAddresses($address, $verbose)` - Get detailed address information
- `createKeyPairs()` - Generate public/private key pairs
- `createMultiSig($required, $publicKeys)` - Create multisig address
- `validateAddress($address)` - Validate address and get info

**Example:**
```php
// Create new address for a user
$address = $multichainService->getNewAddress();

// Validate and get info
$info = $multichainService->validateAddress($address);
// Returns: isvalid, address, ismine, pubkey, etc.

// Import address for monitoring
$multichainService->importAddress($externalAddress, 'External User');
```

### 3. Permissions Management
Grant, revoke, and list blockchain permissions (RBAC).

**Methods:**
- `grant($addresses, $permissions)` - Grant permissions to address(es)
- `grantWithData($addresses, $permissions, $data)` - Grant with metadata
- `revoke($addresses, $permissions)` - Revoke permissions
- `listPermissions($permissions, $address, $verbose)` - List permissions

**Supported Permissions:**
- `connect` - Connect to blockchain
- `send` - Send transactions
- `receive` - Receive transactions
- `create` - Create streams/assets/filters
- `issue` - Issue assets
- `mine` - Mine blocks
- `activate` - Activate features
- `admin` - Administrative permissions

**Example:**
```php
// Grant permissions to BAC Secretariat
$multichainService->grant(
    $bacSecretariatAddress,
    'send,receive,create'
);

// Grant stream-specific write permission
$multichainService->grant(
    $userAddress,
    'procurement.documents.write'
);

// List all permissions
$permissions = $multichainService->listPermissions('*', '*', true);
```

### 4. Asset Management
Create and manage native blockchain assets (not used in ProcuChain currently).

**Methods:**
- `createAsset($address, $assetParams, $quantity, $units)` - Create asset
- `issueMore($address, $asset, $quantity, $details)` - Issue additional units
- `getAssetInfo($assetName)` - Get asset information
- `listAssets($asset, $verbose, $count, $start)` - List assets
- `getAddressBalances($address, $minconf)` - Get address asset balances
- `getMultiBalances($addresses, $assets, $minconf)` - Get multiple balances
- `getTotalBalances($minconf, $includeWatchOnly)` - Get total balances

**Example:**
```php
// Create a custom asset
$assetParams = [
    'name' => 'ProcuChainToken',
    'open' => true
];
$txid = $multichainService->createAsset(
    $issuerAddress,
    $assetParams,
    1000000, // quantity
    0.01     // smallest unit
);

// Check balances
$balances = $multichainService->getAddressBalances($userAddress);
```

### 5. Stream Management ⭐ **Core Feature**
Create and manage data streams (primary data storage mechanism).

**Methods:**
- `createStream($streamName, $options, $details)` - Create new stream
- `createStreamFrom($fromAddress, $streamName, $options, $details)` - Create with specific address
- `getStreamInfo($streamName, $verbose)` - Get stream details
- `listStreams($streams, $verbose, $count, $start)` - List streams

**ProcuChain Streams:**
```php
// Core streams defined in App\Enums\StreamEnums
StreamEnums::DOCUMENTS->value;    // 'procurement.documents'
StreamEnums::STATUS->value;       // 'procurement.status'
StreamEnums::EVENTS->value;       // 'procurement.events'
StreamEnums::CORRECTIONS->value;  // 'procurement.corrections'
```

**Example:**
```php
// Create procurement documents stream
$streamName = 'procurement.documents';
$options = [
    'restrict' => 'write',  // Restrict write access
];
$details = [
    'description' => 'Procurement document hashes and metadata',
    'version' => '1.0'
];

$txid = $multichainService->createStream($streamName, $options, $details);

// Get stream information
$info = $multichainService->getStreamInfo($streamName, true);
// Returns: name, createtxid, streamref, open, details, subscribed, etc.
```

### 6. Stream Publishing ⭐ **Most Used**
Publish data to streams with keys and metadata.

**Methods:**
- `publish($streamName, $key, $data, ?$options)` - Publish single item
- `publishFrom($fromAddress, $streamName, $key, $data)` - Publish from specific address
- `publishMulti($streamName, $items)` - Publish multiple items in one transaction
- `publishMultiFrom($fromAddress, $streamName, $items)` - Publish multiple with specific address

**Data Formats:**
- Raw hexadecimal: `"a1b2c3d4"`
- Text: `["text" => "hello world"]`
- JSON: `["json" => ["key" => "value"]]`

**Example:**
```php
// Publish document metadata (used by PublishProcurementDocumentsJob)
$streamName = 'procurement.documents';
$key = 'procurement-' . $procurementId;
$data = [
    'json' => [
        'procurement_id' => $procurementId,
        'procurement_title' => $title,
        'hash' => $documentHash,
        'file_key' => $s3Key,
        'document_type' => 'bidding_documents',
        'file_size' => 2048576,
        'stage' => 'bidding_documents',
        'timestamp' => now()->toIso8601String(),
        'user_address' => $userAddress,
    ]
];

$txid = $multichainService->publishFrom($fromAddress, $streamName, $key, $data);

// Publish multiple items (batch operation)
$items = [
    [
        'key' => 'doc-1',
        'data' => ['json' => ['hash' => 'abc123...']]
    ],
    [
        'key' => 'doc-2',
        'data' => ['json' => ['hash' => 'def456...']]
    ],
];
$txid = $multichainService->publishMulti($streamName, $items);
```

### 7. Stream Subscription
Subscribe to streams to index and query data.

**Methods:**
- `subscribe($streams, $rescan)` - Subscribe to stream(s)
- `unsubscribe($streams, $purge)` - Unsubscribe from stream(s)

**Example:**
```php
// Subscribe to all ProcuChain streams
$streams = [
    'procurement.documents',
    'procurement.status',
    'procurement.events',
    'procurement.corrections'
];
$multichainService->subscribe($streams, true); // rescan=true to index history

// Unsubscribe (rarely needed)
$multichainService->unsubscribe('procurement.events', false);
```

### 8. Stream Querying ⭐ **Critical for Reading**
Query stream data by various criteria.

**Methods:**
- `listStreamItems($streamName, $verbose, $count, $start, $localOrdering)` - List all items
- `listStreamKeyItems($streamName, $key, ...)` - List items with specific key
- `listStreamPublisherItems($streamName, $address, ...)` - List items by publisher
- `listStreamKeys($streamName, $keys, ...)` - List all keys in stream
- `listStreamPublishers($streamName, $addresses, ...)` - List publishers
- `listStreamBlockItems($streamName, $blocks, ...)` - List items in specific block(s)
- `listStreamQueryItems($streamName, $query, $verbose)` - Complex queries
- `getStreamItem($streamName, $txid, $verbose)` - Get single item by txid
- `listStreamTxItems($streamName, $txid, $verbose)` - Get all items in transaction
- `getStreamKeySummary($streamName, $key, $mode)` - Aggregate JSON data by key
- `getStreamPublisherSummary($streamName, $publisher, $mode)` - Aggregate by publisher
- `getTxOutData($txid, $vout, $countBytes, $startByte)` - Get raw transaction data

**Query Parameters:**
- `$verbose` - Include full transaction details
- `$count` - Number of items to return (default: 1000)
- `$start` - Starting position (negative = from end)
- `$localOrdering` - Order by local receipt vs blockchain order

**Example:**
```php
// Get last 10 documents for a procurement
$items = $multichainService->listStreamKeyItems(
    'procurement.documents',
    'procurement-123',
    verbose: true,
    count: 10,
    start: -10
);

// Get all status transitions for a procurement
$statusHistory = $multichainService->listStreamKeyItems(
    'procurement.status',
    'procurement-123',
    verbose: false,
    count: PHP_INT_MAX,
    start: -1000
);

// Complex query: items by key AND publisher
$query = [
    'key' => 'procurement-123',
    'publishers' => [$bacSecretariatAddress]
];
$items = $multichainService->listStreamQueryItems(
    'procurement.documents',
    $query,
    verbose: true
);

// Get aggregated JSON summary (merge all JSON items)
$summary = $multichainService->getStreamKeySummary(
    'procurement.status',
    'procurement-123',
    'jsonobjectmerge,recursive'
);
```

### 9. Wallet & UTXO Management
Manage unspent transaction outputs (rarely used in ProcuChain).

**Methods:**
- `listUnspent($minconf, $maxconf, $addresses)` - List unspent outputs
- `listLockUnspent()` - List locked outputs
- `lockUnspent($unlock, $outputs)` - Lock/unlock outputs
- `prepareLockUnspent($assets, $lock)` - Prepare locked output
- `prepareLockUnspentFrom($fromAddress, $assets, $lock)` - Prepare with address
- `combineUnspent($params)` - Combine UTXOs for efficiency

### 10. Raw Transactions
Low-level transaction construction (advanced use cases).

**Methods:**
- `createRawTransaction($inputs, $outputs, $data, $action)` - Create raw tx
- `signRawTransaction($hexString)` - Sign raw transaction
- `sendRawTransaction($hexString)` - Broadcast raw transaction
- `getRawTransaction($txid, $verbose)` - Get raw transaction
- `decodeRawTransaction($txHex)` - Decode raw transaction
- `appendRawData($txHex, $data)` - Add metadata to tx
- `appendRawTransaction($txHex, $inputs, $outputs, $data, $action)` - Append to tx
- `createRawSendFrom($fromAddress, $outputs, $data, $action)` - Create from address
- `appendRawChange($txHex, $address)` - Add change output

### 11. Network Management
Manage peer-to-peer connections.

**Methods:**
- `addNode($node, $command)` - Add/remove network node
- `getNetworkInfo()` - Get network information
- `getPeerInfo()` - Get connected peers information

**Example:**
```php
// Add a new node
$multichainService->addNode('192.168.1.100:4266', 'add');

// Get peer information
$peers = $multichainService->getPeerInfo();
foreach ($peers as $peer) {
    echo "Connected to: {$peer['addr']}\n";
}
```

### 12. Message Signing
Sign and verify messages with blockchain addresses.

**Methods:**
- `signMessage($address, $message)` - Sign message with address
- `verifyMessage($address, $signature, $message)` - Verify signature

**Example:**
```php
// Sign a message
$signature = $multichainService->signMessage(
    $userAddress,
    'I approve this procurement'
);

// Verify signature
$isValid = $multichainService->verifyMessage(
    $userAddress,
    $signature,
    'I approve this procurement'
);
```

### 13. Blockchain Queries
Query blocks and blockchain state.

**Methods:**
- `getBlock($hashOrHeight, $verbose)` - Get block by hash or height
- `getBlockchainInfo()` - Get blockchain state
- `getBlockHash($height)` - Get block hash at height
- `listBlocks($blocks, $verbose)` - List multiple blocks
- `getLastBlockInfo($skip)` - Get most recent block
- `getTxOut($txid, $vout)` - Get transaction output info

**Example:**
```php
// Get latest block
$latestBlock = $multichainService->getLastBlockInfo(0);

// Get specific block
$block = $multichainService->getBlock(1000, verbose: 2);

// Get blockchain info
$info = $multichainService->getBlockchainInfo();
echo "Current height: {$info['blocks']}\n";
```

### 14. Binary Cache
Temporary storage for large binary data (advanced).

**Methods:**
- `createBinaryCache()` - Create cache item
- `appendBinaryCache($id, $data)` - Append data to cache

### 15. Wallet Backup
Backup and restore wallet data.

**Methods:**
- `backupWallet($path)` - Backup wallet.dat
- `getWalletInfo()` - Get wallet information
- `importPrivKey($privkey)` - Import private key
- `dumpWallet($filename)` - Export wallet to file
- `importWallet($filename)` - Import wallet from file

**Example:**
```php
// Backup wallet
$multichainService->backupWallet('/backup/wallet-2025-10-31.dat');

// Get wallet stats
$info = $multichainService->getWalletInfo();
echo "Transactions: {$info['txcount']}\n";
```

### 16. Smart Filters ⭐ **Phase 2 Feature**
Create and manage JavaScript validation filters.

**Methods:**
- `createTxFilter($name, $params, $code)` - Create transaction filter
- `createStreamFilter($name, $params, $code)` - Create stream filter
- `approveFrom($fromAddress, $entityName, $approve)` - Approve filter/library
- `getFilterCode($filterName)` - Get filter JavaScript code

**Example:**
```php
// Create stream filter for documents
$filterName = 'procuchain_documents_validator';
$params = new stdClass(); // Empty for stream filter
$code = File::get(resource_path('blockchain/filters/documents_filter.js'));

$txid = $multichainService->createStreamFilter($filterName, $params, $code);

// Approve filter (requires admin)
$multichainService->approveFrom(
    $adminAddress,
    $filterName,
    ['for' => 'procurement.documents', 'approve' => true]
);
```

### 17. Variables & Libraries ⭐ **Phase 2 Feature**
On-chain configuration and shared code.

**Methods:**
- `createVariable($name, $createUpgrade, $value)` - Create on-chain variable
- `getVariableValue($name)` - Read variable value
- `setVariableValue($name, $value)` - Update variable value
- `listVariables()` - List all variables
- `createLibrary($name, $params, $code)` - Create JavaScript library
- `getLibraryCode($libraryName, $updateName)` - Get library code

**Example:**
```php
// Create configuration variable
$configName = 'document_validation_config';
$config = [
    'max_file_size' => 10485760, // 10MB
    'allowed_types' => ['pdf', 'docx', 'xlsx'],
    'version' => 1
];
$multichainService->createVariable($configName, false, $config);

// Read configuration
$config = $multichainService->getVariableValue($configName);

// Create validation library
$libraryName = 'procuchain_validation_helpers';
$params = ['updatemode' => 'none']; // Immutable
$code = File::get(resource_path('blockchain/libraries/validation_helpers.js'));

$txid = $multichainService->createLibrary($libraryName, $params, $code);
```

### 18. Advanced Node Control
Pause, resume, and manage node operations.

**Methods:**
- `pause($tasks)` - Pause mining/incoming/offchain
- `resume($tasks)` - Resume paused tasks
- `clearMemPool()` - Clear transaction memory pool
- `getChunkQueueInfo()` - Get off-chain chunk queue status
- `getChunkQueueTotals()` - Get cumulative chunk statistics

**Example:**
```php
// Pause mining to perform maintenance
$multichainService->pause('mining,incoming');

// Perform maintenance...

// Resume operations
$multichainService->resume('mining,incoming');
```

## Common Usage Patterns

### Pattern 1: Publishing Document Metadata
**Used by:** `PublishProcurementDocumentsJob`

```php
public function publishDocument(Procurement $procurement, array $documentData): string
{
    $streamName = StreamEnums::DOCUMENTS->value;
    $key = 'procurement-' . $procurement->id;
    
    $data = [
        'json' => [
            'procurement_id' => $procurement->id,
            'procurement_title' => $procurement->title,
            'hash' => $documentData['hash'],
            'file_key' => $documentData['file_key'],
            'document_type' => $documentData['type'],
            'file_size' => $documentData['size'],
            'stage' => $procurement->stage,
            'timestamp' => now()->toIso8601String(),
            'user_address' => auth()->user()->blockchain_address,
        ]
    ];
    
    return $this->multichainService->publishFrom(
        config('multichain.addresses.admin'),
        $streamName,
        $key,
        $data
    );
}
```

### Pattern 2: Recording Status Changes
**Used by:** `HandleStageTransitionJob`

```php
public function recordStatusChange(Procurement $procurement): string
{
    $streamName = StreamEnums::STATUS->value;
    $key = 'procurement-' . $procurement->id;
    
    $data = [
        'json' => [
            'procurement_id' => $procurement->id,
            'procurement_title' => $procurement->title,
            'current_status' => $procurement->status,
            'previous_status' => $procurement->getOriginal('status'),
            'stage' => $procurement->stage,
            'timestamp' => now()->toIso8601String(),
            'user_address' => auth()->user()->blockchain_address,
            'remarks' => $procurement->remarks,
        ]
    ];
    
    return $this->multichainService->publish($streamName, $key, $data);
}
```

### Pattern 3: Logging Events
**Used by:** `LogBlockchainEventJob`

```php
public function logEvent(string $type, array $eventData): string
{
    $streamName = StreamEnums::EVENTS->value;
    $key = 'event-' . Str::uuid();
    
    $data = [
        'json' => [
            'event_type' => $type,
            'category' => $eventData['category'],
            'severity' => $eventData['severity'],
            'procurement_id' => $eventData['procurement_id'] ?? null,
            'user_address' => $eventData['user_address'],
            'description' => $eventData['description'],
            'metadata' => $eventData['metadata'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ]
    ];
    
    return $this->multichainService->publish($streamName, $key, $data);
}
```

### Pattern 4: Querying Procurement History
**Used by:** `BlockchainVerificationService`

```php
public function getDocumentHistory(int $procurementId): array
{
    $streamName = StreamEnums::DOCUMENTS->value;
    $key = 'procurement-' . $procurementId;
    
    $items = $this->multichainService->listStreamKeyItems(
        $streamName,
        $key,
        verbose: true,
        count: PHP_INT_MAX,
        start: -1000
    );
    
    return collect($items)->map(function ($item) {
        return [
            'txid' => $item['txid'],
            'hash' => $item['data']['json']['hash'],
            'document_type' => $item['data']['json']['document_type'],
            'timestamp' => $item['data']['json']['timestamp'],
            'publisher' => $item['publishers'][0],
            'confirmed' => $item['confirmations'] > 0,
        ];
    })->toArray();
}
```

### Pattern 5: Verifying Document Integrity
**Used by:** `DocumentVerificationService`

```php
public function verifyDocument(string $hash, int $procurementId): bool
{
    $streamName = StreamEnums::DOCUMENTS->value;
    $key = 'procurement-' . $procurementId;
    
    // Get all documents for this procurement
    $items = $this->multichainService->listStreamKeyItems(
        $streamName,
        $key,
        verbose: false
    );
    
    // Check if hash exists in blockchain
    foreach ($items as $item) {
        if ($item['data']['json']['hash'] === $hash) {
            // Document hash exists on blockchain
            return $item['confirmations'] >= 1; // Wait for confirmation
        }
    }
    
    return false; // Hash not found
}
```

## Error Handling

The `MultichainService` uses `MultichainConnectionService` for robust error handling:

```php
// Connection service handles retries automatically
try {
    $txid = $multichainService->publish($stream, $key, $data);
} catch (Exception $e) {
    Log::error('Failed to publish to blockchain', [
        'stream' => $stream,
        'key' => $key,
        'error' => $e->getMessage()
    ]);
    
    // Handle error appropriately
    // - Queue for retry
    // - Notify user
    // - Log to monitoring system
}
```

### Retry Configuration
Configured in `config/multichain.php`:

```php
'retry' => [
    'max_attempts' => 3,
    'delay_ms' => 1000,
    'multiplier' => 2, // Exponential backoff
],
```

## Performance Considerations

### 1. Batch Publishing
Use `publishMulti()` to publish multiple items in one transaction:

```php
// ❌ Inefficient - Multiple transactions
foreach ($documents as $doc) {
    $multichainService->publish($stream, $doc['key'], $doc['data']);
}

// ✅ Efficient - Single transaction
$items = array_map(fn($doc) => [
    'key' => $doc['key'],
    'data' => $doc['data']
], $documents);

$multichainService->publishMulti($stream, $items);
```

### 2. Pagination
Always use `count` and `start` parameters for large datasets:

```php
// Get items in pages
$perPage = 100;
$page = 1;

$items = $multichainService->listStreamKeyItems(
    $stream,
    $key,
    count: $perPage,
    start: -($perPage * $page)
);
```

### 3. Stream Subscription
Subscribe to streams during setup, not per-request:

```php
// ✅ During deployment (once)
php artisan multichain:setup --subscribe

// ❌ Don't subscribe on every request
public function handle() {
    $this->multichainService->subscribe('procurement.documents');
    // ... rest of logic
}
```

## Security Best Practices

### 1. Permission-Based Publishing
Always publish from authorized addresses:

```php
// ✅ Use role-specific addresses
$fromAddress = match(auth()->user()->role) {
    'admin' => config('multichain.addresses.admin'),
    'bac_secretariat' => config('multichain.addresses.bac_secretariat'),
    'bac_chairman' => config('multichain.addresses.bac_chairman'),
    'hope' => config('multichain.addresses.hope'),
};

$multichainService->publishFrom($fromAddress, $stream, $key, $data);
```

### 2. Data Validation
Validate data before publishing to blockchain:

```php
// ✅ Validate before blockchain write
$validated = $request->validate([
    'hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
    'file_size' => ['required', 'integer', 'max:10485760'],
    'document_type' => ['required', Rule::in(StageEnums::cases())],
]);

$multichainService->publish($stream, $key, ['json' => $validated]);
```

### 3. Sensitive Data Protection
Never store sensitive data on blockchain:

```php
// ❌ Don't store sensitive data
$data = [
    'user_password' => $user->password, // ❌
    'ssn' => $user->ssn, // ❌
];

// ✅ Store only hashes and references
$data = [
    'document_hash' => hash_file('sha256', $path),
    'file_key' => 's3://bucket/key', // Reference to off-chain storage
    'user_address' => $user->blockchain_address,
];
```

## Testing

### Unit Testing
Mock the MultichainService in tests:

```php
use App\Services\MultichainService;

test('publishes document to blockchain', function () {
    $mockService = Mockery::mock(MultichainService::class);
    $mockService->shouldReceive('publishFrom')
        ->once()
        ->with('stream', 'key', Mockery::type('array'))
        ->andReturn('fake-txid-12345');
    
    $this->app->instance(MultichainService::class, $mockService);
    
    // Test your code...
});
```

### Integration Testing
Test against a local MultiChain node:

```php
test('can connect to multichain node', function () {
    $service = app(MultichainService::class);
    $info = $service->getInfo();
    
    expect($info)->toHaveKeys(['chainname', 'version', 'blocks']);
});
```

## Debugging

### Enable RPC Logging
Add to `.env`:

```env
MULTICHAIN_LOG_ENABLED=true
LOG_LEVEL=debug
```

### View Recent Transactions
```php
// Get latest stream items
$items = $multichainService->listStreamItems(
    'procurement.documents',
    verbose: true,
    count: 10,
    start: -10
);

foreach ($items as $item) {
    dump([
        'txid' => $item['txid'],
        'key' => $item['key'],
        'publisher' => $item['publishers'][0],
        'data' => $item['data'],
        'confirmations' => $item['confirmations'],
    ]);
}
```

## CLI Commands

ProcuChain provides Artisan commands for blockchain operations:

```bash
# Setup blockchain
php artisan multichain:setup

# Deploy smart contracts
php artisan smartcontract:setup

# Check deployment status
php artisan smartcontract:setup --check

# Subscribe to streams
php artisan multichain:setup --subscribe
```

## API Reference Summary

Total Methods: **90+**

| Category | Methods | Primary Use in ProcuChain |
|----------|---------|---------------------------|
| General Utilities | 5 | Node status, configuration |
| Address Management | 7 | User address creation |
| Permissions | 4 | RBAC implementation |
| Assets | 7 | Not actively used |
| Stream Management | 4 | Stream creation during setup |
| Stream Publishing | 4 | **Document/status/event recording** |
| Stream Subscription | 2 | Stream indexing |
| Stream Querying | 12 | **Verification and history retrieval** |
| Wallet & UTXO | 7 | Minimal use |
| Raw Transactions | 10 | Advanced scenarios only |
| Network Management | 3 | Node administration |
| Message Signing | 2 | Digital signatures |
| Blockchain Queries | 6 | Verification, monitoring |
| Binary Cache | 2 | Large data handling |
| Wallet Backup | 5 | Admin operations |
| Smart Filters | 4 | **Validation enforcement** |
| Variables & Libraries | 6 | **Configuration management** |
| Advanced Control | 5 | Node maintenance |

## Related Documentation

- [Smart Contract Implementation Plan](./smart-contract-plan.md)
- [Network Topology](./network-topology.md)
- [Deployment Guide](../DEPLOYMENT_GUIDE.md)
- [Official MultiChain API Docs](https://www.multichain.com/developers/json-rpc-api/)

## Configuration Reference

See `config/multichain.php` for complete configuration options:
- RPC connection settings
- Stream names
- Admin addresses
- Retry behavior
- Logging preferences

---

**Last Updated:** October 31, 2025  
**MultiChain Version:** Community Edition 2.3.3  
**Laravel Version:** 12.36.1

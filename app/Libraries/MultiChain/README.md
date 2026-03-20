<div align="center">

# MultiChain PHP Library for Laravel

</div>

<div align="center">

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-BSD%203--Clause-green.svg)](LICENSE)

*A Laravel-integrated wrapper for the official MultiChain JSON-RPC API library, providing seamless blockchain integration with automatic connection management, retry logic, and Laravel conventions.*

[Overview](#overview) • [Installation](#installation) • [Usage](#usage) • [API Reference](#json-rpc-api-overview) • [Contributing](#contributing) • [License](#license)

</div>

---

## 📋 Table of Contents

- [About](#-about)
- [Overview](#-overview)
- [Installation](#-installation)
- [Basic Usage](#-basic-usage)
- [API Reference](#-api-reference)
- [Smart Filters](#-smart-filters)
- [Configuration](#-configuration)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)
- [Resources](#-resources)

---

## 🎯 About

**MultiChain PHP Library for Laravel** is a comprehensive Laravel wrapper for the official MultiChain JSON-RPC API. This library enables seamless integration of MultiChain blockchain functionality into Laravel applications, providing:

- 🚀 **Rapid Deployment**: Just two steps to create a new blockchain
- 💰 **Unlimited Assets**: Issue millions of assets and tokens
- 📊 **Data Streams**: Create key-value databases with timestamping and immutability
- 🔐 **Fine-grained Permissions**: Control who can connect, send, receive, create, and administer
- 👨‍💻 **Developer Friendly**: Designed for minimum hassle in building blockchain applications
- ⚙️ **Customizable**: Full control over blockchain parameters and proof-of-work
- 🛡️ **Flexible Security**: Support for multisignatures, external keys, and cold nodes

### Key Features

- **Automatic Connection Management**: Handles MultiChain node connections with retry logic
- **Laravel Integration**: Seamlessly integrates with Laravel's service container and conventions
- **Error Handling**: Comprehensive error handling with context-aware timeouts
- **Type Safety**: Full PHP type declarations and modern PHP features
- **Comprehensive API Coverage**: Complete coverage of MultiChain JSON-RPC commands
- **Production Ready**: Battle-tested for enterprise blockchain applications

---

## 🔍 Overview

This library consists of two main components:

- **`Client.php`** - Official MultiChain JSON-RPC client with magic `__call()` method support
- **`Manager.php`** - Laravel wrapper providing connection management, retry logic, and error handling (implemented in `app/Services/Manager.php`)

The library is specifically designed for the **Procuchain** procurement management system, providing blockchain-backed immutable audit trails, asset tokenization, and secure data streams for procurement workflows.

### Architecture

```
Laravel Application
    ↓
MultiChain Manager (app/Services/Manager.php)
    ↓
MultiChain Client (app/Libraries/MultiChain/Client.php)
    ↓
MultiChain Node (Blockchain Network)
```

---

## 📦 Installation

The library is already integrated into this Laravel application. The Manager is implemented in `app/Services/Manager.php` and provides a Laravel wrapper for the MultiChain Client.

### Requirements

- **PHP**: 8.3 or higher
- **Laravel**: 13.x or higher
- **MultiChain Node**: 2.x or higher
- **Extensions**: BCMath, JSON, cURL

### Dependencies

This library depends on the official MultiChain PHP API library and integrates with Laravel's service container for dependency injection.

---

## 🚀 Basic Usage

### Dependency Injection

Inject the Manager into your controllers, services, or repositories:

```php
use App\Services\Manager;

class BlockchainController extends Controller
{
    public function __construct(
        protected Manager $multichain
    ) {}

    public function getInfo()
    {
        $info = $this->multichain->getinfo();
        return response()->json($info);
    }
}
```

### Service Resolution

You can also resolve the Manager from the container:

```php
$manager = app(\App\Services\Manager::class);
$info = $manager->getinfo();
```

### Error Handling

The Manager provides automatic retry logic with configurable attempts:

```php
use App\Services\Manager;
use Exception;

try {
    $result = $this->multichain->getinfo();
} catch (Exception $e) {
    Log::error('MultiChain error: ' . $e->getMessage());
}
```

Check operation success (using Client methods):

```php
$client = $this->multichain->getClient();
$client->setruntimeparam('maxshowndata', 16384);

if ($client->success()) {
    // Operation succeeded
} else {
    $error = $client->errormessage();
    $code = $client->errorcode();
}
```

---

## 📚 API Reference

### General Utilities

```php
// Get blockchain parameters
$result = $multichain->getblockchainparams();

// Check node health (Enterprise)
$result = $multichain->gethealthcheck();

// Get runtime parameters
$result = $multichain->getruntimeparams();

// Set runtime parameter
$multichain->setruntimeparam('maxshowndata', 16384);

// Get node information
$result = $multichain->getinfo();

// Get initialization status
$result = $multichain->getinitstatus();
```

### Managing Wallet Addresses

```php
// Add multisig address (2-of-3)
$address = $multichain->addmultisigaddress(2, [$address1, $address2, $address3]);

// Get new address
$address = $multichain->getnewaddress();

// Import address for watching
$multichain->importaddress($address);

// List all addresses
$result = $multichain->listaddresses();

// List specific address
$result = $multichain->listaddresses($address);

// Dump private key
$privkey = $multichain->dumpprivkey($address);

// Import private key
$multichain->importprivkey($privkey);
```

### Permissions Management

```php
// Grant global permissions
$txid = $multichain->grant($address, 'send,receive');

// Grant per-entity permissions
$txid = $multichain->grant($address, 'stream1.write');

// Grant to multiple addresses
$txid = $multichain->grant($address1.','.$address2, 'connect');

// Grant from specific address
$txid = $multichain->grantfrom($fromaddress, $address, 'create');

// Grant with metadata
$txid = $multichain->grantwithdata($address, 'connect', 'a1b2c3d4');

// List all permissions
$result = $multichain->listpermissions();

// List specific permissions
$result = $multichain->listpermissions('send,receive');

// List for specific address
$result = $multichain->listpermissions('*', $address);

// Revoke permissions
$txid = $multichain->revoke($address, 'send,receive');

// Verify permission
$result = $multichain->verifypermission($address, 'send');
```

### Asset Management

```php
// Get asset information
$result = $multichain->getassetinfo('asset1');

// Get token information
$result = $multichain->gettokeninfo('asset1', 'token1');

// Issue fungible asset
$txid = $multichain->issue($address, ['name' => 'asset1'], 1000, 0.01);

// Issue with reissuance allowed
$txid = $multichain->issue($address, ['name' => 'asset1', 'open' => true], 1000, 0.01);

// Issue with custom fields
$txid = $multichain->issue($address, ['name' => 'asset1'], 1000, 0.01, 0, ['origin' => 'US']);

// Issue non-fungible asset (NFTs)
$txid = $multichain->issue($address, ['name' => 'nfts1', 'fungible' => false, 'open' => true], 0, 1);

// Issue more of existing asset
$txid = $multichain->issuemore($address, 'asset1', 100);

// Issue non-fungible token
$txid = $multichain->issuetoken($address, 'nfts1', 'token1', 1);

// List all assets
$result = $multichain->listassets();

// List specific asset
$result = $multichain->listassets('asset1');

// Update asset
$txid = $multichain->update('asset1', ['open' => true]);
```

### Querying Wallet Balances

```php
// Get address balances
$result = $multichain->getaddressbalances($address);

// Include unconfirmed transactions
$result = $multichain->getaddressbalances($address, 0);

// Get all balances (all addresses, all assets)
$result = $multichain->getmultibalances();

// Get balances for specific address
$result = $multichain->getmultibalances($address);

// Get balances for specific asset
$result = $multichain->getmultibalances('*', 'asset1');

// Get token balances
$result = $multichain->gettokenbalances();

// Get total balances
$result = $multichain->gettotalbalances();

// Get wallet transaction
$result = $multichain->getwallettransaction($txid);

// List address transactions
$result = $multichain->listaddresstransactions($address);

// List wallet transactions
$result = $multichain->listwallettransactions();
```

### Sending Payments

```php
// Send fungible asset
$txid = $multichain->send($address, ['asset1' => 20]);

// Send NFT token
$txid = $multichain->send($address, ['asset1' => ['token' => 'token1', 'qty' => 5]]);

// Send from specific address
$txid = $multichain->sendfrom($fromaddress, $address, ['asset1' => 20]);

// Send asset
$txid = $multichain->sendasset($address, 'asset1', 20);

// Send with metadata
$txid = $multichain->sendwithdata($address, ['asset1' => 20], 'a1b2c3d4');

// Send with JSON stream item
$txid = $multichain->sendwithdata($address, ['asset1' => 20], [
    'for' => 'stream1',
    'keys' => ['key1'],
    'data' => ['json' => ['name' => 'Mary']]
]);
```

### Data Streams

MultiChain streams enable a blockchain to be used as a general purpose append-only database, with the blockchain providing timestamping, notarization and immutability. A MultiChain blockchain can contain any number of streams, each of which has a name and permissions.

#### Stream Characteristics

Each stream item has the following characteristics:

- **Publishers**: One or more addresses that have digitally signed the item
- **Keys**: One or more keys (0-256 bytes) for efficient retrieval and indexing
- **Data**: Content in JSON, text, or binary format
- **Metadata**: Transaction and block information (txid, blockhash, blocktime, etc.)

#### On-Chain vs Off-Chain Data

MultiChain supports two types of data storage in streams:

##### On-Chain Data
- Data is embedded directly within the blockchain transaction
- Received and stored by every node in the network
- Immediately available with the transaction
- Maximum size: Up to 64 MB (configurable via `max-std-op-return-size`)
- Transaction size: Few hundred bytes + full data size

##### Off-Chain Data
- Only a hash of the data is embedded in the transaction
- Data is requested and verified asynchronously by subscribed nodes
- Available within a split second of the transaction
- Maximum size: Up to 1 GB (configurable via `maximum-chunk-size` and `maximum-chunk-count`)
- Transaction size: Few hundred bytes + 37 bytes per 1 MB of data
- Supports Enterprise features: read restrictions, encryption, selective retrieval, data purging

#### Stream Permissions

Streams support granular permission control:

- **create**: Required to create new streams (unless `anyone-can-create` is true)
- **admin**: Full control over stream permissions and settings
- **activate**: Can modify admin/activate permissions
- **write**: Required to publish items to write-restricted streams
- **read**: Required to retrieve data from read-restricted streams (Enterprise only)

#### Stream Restrictions

Streams can be configured with various restrictions:

- **Write-restricted**: Only addresses with `write` permission can publish
- **Read-restricted**: Only authorized addresses can retrieve data (Enterprise + off-chain only)
- **Data restrictions**: Can limit to on-chain only, off-chain only, or require salted off-chain data

#### Referring to Streams

Streams can be referenced in three ways:

1. **Name**: Optional unique identifier (UTF-8, up to 32 bytes, case-insensitive)
2. **createtxid**: Transaction ID of the stream creation transaction
3. **streamref**: Encodes block number, byte offset, and partial txid

#### Working with Streams

```php
// Create an open stream (anyone can write)
$txid = $multichain->create('stream', 'procurement.documents', true);

// Create a write-restricted stream
$txid = $multichain->create('stream', 'sensitive.data', ['restrict' => 'write']);

// Get stream information
$info = $multichain->getstreaminfo('procurement.documents');

// List all streams
$streams = $multichain->liststreams();

// Subscribe to a stream
$multichain->subscribe('procurement.documents');

// Publish data to a stream
$txid = $multichain->publish('procurement.documents', 'contract-001', [
    'json' => [
        'title' => 'Office Supplies Contract',
        'vendor' => 'ABC Supplies Inc',
        'amount' => 5000,
        'status' => 'pending'
    ]
]);

// Publish off-chain data
$txid = $multichain->publish('large.files', 'document.pdf', $binaryData, 'offchain');

// Query stream items
$items = $multichain->liststreamitems('procurement.documents');

// Query by key
$contractItems = $multichain->liststreamkeyitems('procurement.documents', 'contract-001');

// Query by publisher
$userItems = $multichain->liststreampublisheritems('procurement.documents', $address);
```

#### Stream Filters

Stream filters enable custom validation rules for stream data using JavaScript. They can validate data format, enforce business rules, and trigger actions based on published items.

```php
// Create a stream with a filter (requires MultiChain Enterprise)
$txid = $multichain->create('stream', 'validated.contracts', true, [
    'filter' => 'validateContractData'
]);
```

#### Best Practices

1. **Choose data type wisely**: Use on-chain for small, critical data; off-chain for large files or sensitive data
2. **Use meaningful keys**: Design key structures for efficient querying
3. **Implement permissions**: Use write/read restrictions to control access
4. **Consider filters**: Use stream filters for data validation (Enterprise)
5. **Monitor performance**: Large streams may require indexing optimizations

For more technical details, see the [official MultiChain data streams documentation](https://www.multichain.com/developers/data-streams/).

## 🔍 Smart Filters

Smart filters provide powerful JavaScript-based validation and processing capabilities for MultiChain transactions and stream items. They run in a secure V8 JavaScript engine within the blockchain nodes, enabling complex business logic validation without compromising network security.

### Filter Types

#### Transaction Filters
Transaction filters validate entire transactions before they're added to blocks. They can:
- Check transaction inputs and outputs
- Validate asset transfers and permissions
- Enforce custom business rules
- Reject invalid transactions

#### Stream Filters
Stream filters validate data being published to streams. They can:
- Validate stream item data format and content
- Check publisher permissions
- Process and transform data
- Enforce data quality standards

### Creating Filters

#### Basic Filter Structure
```javascript
function filtertransaction() {
    // Transaction filter logic
    return true; // Accept transaction
}

function filterstreamitem() {
    // Stream filter logic
    return true; // Accept stream item
}
```

#### Advanced Filter with Callbacks
```javascript
function filterstreamitem() {
    // Access transaction data
    var tx = getfiltertransaction();
    
    // Access stream item data
    var item = getfilterstreamitem();
    
    // Custom validation logic
    if (item.keys[0] === "procurement") {
        // Validate procurement data
        return validateProcurementData(item.data);
    }
    
    return true;
}

function validateProcurementData(data) {
    // Custom validation logic
    return data.json && data.json.amount > 0;
}
```

### Available Functions

#### Transaction Functions
- `getfiltertransaction()` - Get current transaction details
- `getlastblockinfo()` - Get information about the last block
- `getassetinfo(asset)` - Get asset information
- `getstreaminfo(stream)` - Get stream information

#### Stream Functions
- `getfilterstreamitem()` - Get current stream item details
- `getfilterstream()` - Get stream information for current item
- `getfiltertxid()` - Get transaction ID of current item

#### Utility Functions
- `log(message)` - Log messages (visible in node debug logs)
- `btoa(data)` - Base64 encode data
- `atob(data)` - Base64 decode data

### Determinism Requirements

Smart filters must be **deterministic** - they must produce the same result every time they're run with the same input:

✅ **Deterministic operations:**
- Mathematical calculations
- String operations
- Array/object manipulation
- Accessing transaction/stream data

❌ **Non-deterministic operations:**
- `Date.now()` or `new Date()`
- `Math.random()`
- External API calls
- File system access

### Timeout Protection

Filters have execution time limits to prevent abuse:
- **Transaction filters**: 10 seconds
- **Stream filters**: 5 seconds

Filters that exceed these limits are automatically rejected.

### Development Best Practices

#### 1. Error Handling
```javascript
function filterstreamitem() {
    try {
        var item = getfilterstreamitem();
        // Validation logic
        return validateData(item);
    } catch (error) {
        log("Filter error: " + error.message);
        return false; // Reject on error
    }
}
```

#### 2. Performance Optimization
```javascript
function filterstreamitem() {
    // Cache expensive operations
    if (!this.assetCache) {
        this.assetCache = {};
    }
    
    // Quick validation first
    var item = getfilterstreamitem();
    if (!item.data || !item.data.json) {
        return false;
    }
    
    // Detailed validation
    return validateComplexRules(item.data.json);
}
```

#### 3. Modular Design
```javascript
// Shared validation functions
function isValidAmount(amount) {
    return typeof amount === 'number' && amount > 0;
}

function isValidDate(date) {
    return date && !isNaN(Date.parse(date));
}

function filterstreamitem() {
    var data = getfilterstreamitem().data.json;
    
    return isValidAmount(data.amount) && 
           isValidDate(data.created_at) &&
           data.vendor_id && data.procurement_id;
}
```

### Testing Filters

#### Local Testing
```javascript
// Test filter logic locally
function testFilter() {
    // Mock transaction data
    var mockTx = {
        inputs: [...],
        outputs: [...]
    };
    
    // Test filter function
    var result = filtertransaction();
    console.log("Filter result:", result);
}
```

#### Node.js Testing
```javascript
const vm = require('vm');

function testFilterLocally(filterCode, mockData) {
    const sandbox = {
        getfiltertransaction: () => mockData.transaction,
        getfilterstreamitem: () => mockData.streamItem,
        log: console.log
    };
    
    const script = new vm.Script(filterCode);
    const context = vm.createContext(sandbox);
    
    try {
        const result = script.runInContext(context);
        return result;
    } catch (error) {
        console.error("Filter execution error:", error);
        return false;
    }
}
```

### Deployment Process

#### 1. Create Filter Library
```bash
# Create a new stream for filters
multichain-cli procuchain create stream filters false
```

#### 2. Publish Filter Code
```javascript
// Example: Publish procurement validation filter
var filterCode = `
function filterstreamitem() {
    var item = getfilterstreamitem();
    
    // Only validate procurement items
    if (item.keys[0] !== "procurement") {
        return true;
    }
    
    var data = item.data.json;
    
    // Required fields validation
    if (!data.amount || !data.vendor_id || !data.description) {
        return false;
    }
    
    // Amount validation
    if (typeof data.amount !== 'number' || data.amount <= 0) {
        return false;
    }
    
    // Date validation
    if (data.created_at && isNaN(Date.parse(data.created_at))) {
        return false;
    }
    
    return true;
}
`;

multichain.publish('filters', 'procurement-validator', {text: filterCode});
```

#### 3. Create Stream Filter
```bash
# Create filter for procurement stream
multichain.create 'streamfilter', 'procurement', 'filters', 'procurement-validator', 'javascript'
```

#### 4. Test Filter
```bash
# Test with valid data
multichain.publish 'procurement', 'test-1', '{"json":{"amount":1000,"vendor_id":"V001","description":"Office supplies"}}'

# Test with invalid data (should be rejected)
multichain.publish 'procurement', 'test-2', '{"json":{"amount":-100,"vendor_id":"V001"}}'
```

### Common Use Cases

#### Procurement Validation
```javascript
function filterstreamitem() {
    var item = getfilterstreamitem();
    
    if (item.keys[0] !== "procurement") return true;
    
    var data = item.data.json;
    
    // Business rules validation
    return (
        data.amount > 0 &&
        data.amount <= 1000000 && // Max procurement amount
        data.vendor_id &&
        data.procurement_id &&
        ['pending', 'approved', 'rejected'].includes(data.status)
    );
}
```

#### Asset Transfer Validation
```javascript
function filtertransaction() {
    var tx = getfiltertransaction();
    
    // Check for restricted asset transfers
    for (var output of tx.outputs) {
        if (output.assets && output.assets['restricted-asset']) {
            // Check if sender has permission
            var hasPermission = checkPermission(tx.inputs[0].address);
            if (!hasPermission) {
                return false;
            }
        }
    }
    
    return true;
}
```

#### Data Quality Enforcement
```javascript
function filterstreamitem() {
    var item = getfilterstreamitem();
    
    if (item.keys[0] === "sensor-data") {
        var data = item.data.json;
        
        // Validate sensor readings
        return (
            typeof data.temperature === 'number' &&
            data.temperature >= -50 && data.temperature <= 100 &&
            typeof data.humidity === 'number' &&
            data.humidity >= 0 && data.humidity <= 100 &&
            data.timestamp && !isNaN(Date.parse(data.timestamp))
        );
    }
    
    return true;
}
```

### Troubleshooting

#### Common Issues

1. **Filter Rejected**: Check node logs for JavaScript errors
2. **Timeout Errors**: Simplify filter logic or optimize performance
3. **Non-deterministic Results**: Remove random/date operations
4. **Memory Issues**: Avoid large data structures in filter state

#### Debug Commands
```bash
# Check filter status
multichain.liststreamfilters

# Get filter details
multichain.getstreamfilter 'filter-id'

# View node debug logs
tail -f ~/.multichain/procuchain/debug.log
```

### Security Considerations

- Filters run with limited permissions
- No access to external resources
- Execution is sandboxed
- Failed filters don't crash the node
- Filters can be updated by stream administrators

For more technical details, see the [official MultiChain smart filters documentation](https://www.multichain.com/developers/smart-filters/).

### Publishing Stream Items

```php
// Publish raw binary data
$txid = $multichain->publish('stream1', 'key1', 'a1b2c3d4');

// Publish text data
$txid = $multichain->publish('stream1', 'key1', ['text' => 'hello world']);

// Publish JSON data
$txid = $multichain->publish('stream1', 'key1', ['json' => ['name' => 'John', 'age' => 30]]);

// Publish with multiple keys
$txid = $multichain->publish('stream1', ['key1', 'key2'], 'a1b2c3d4');

// Publish off-chain data (recommended for large data)
$txid = $multichain->publish('stream1', 'key1', $largeBinaryData, 'offchain');

// Publish from specific address
$txid = $multichain->publishfrom($fromaddress, 'stream1', 'key1', 'a1b2c3d4');

// Multi-publish (atomically publish multiple items)
$txid = $multichain->publishmulti('stream1', [
    ['key' => 'key1', 'data' => ['json' => ['name' => 'John', 'age' => 30]]],
    ['keys' => ['key2', 'key3'], 'data' => ['json' => ['name' => 'Mary', 'age' => 25]]]
]);
```

### Querying Stream Items

```php
// List stream items (10 most recent)
$result = $multichain->liststreamitems('stream1');

// Paging through items
$result = $multichain->liststreamitems('stream1', false, 10, 30);

// List items by key
$result = $multichain->liststreamkeyitems('stream1', 'key1');

// List items by publisher
$result = $multichain->liststreampublisheritems('stream1', $address);

// List all keys in stream
$result = $multichain->liststreamkeys('stream1');

// List all publishers
$result = $multichain->liststreampublishers('stream1');

// Get specific stream item
$result = $multichain->getstreamitem('stream1', $txid);

// Query items with filters
$result = $multichain->liststreamqueryitems('stream1', ['keys' => ['key1', 'key2']]);
```

### Blockchain Queries

```php
// Get best block hash
$hash = $multichain->getbestblockhash();

// Get block by hash/height
$result = $multichain->getblock($hashOrHeight);

// Get block with verbose level
$result = $multichain->getblock($hashOrHeight, 4);

// Get blockchain information
$result = $multichain->getblockchaininfo();

// Get raw transaction
$result = $multichain->getrawtransaction($txid, true);

// Get transaction output data
$hex = $multichain->gettxoutdata($txid, $vout);

// List blocks
$result = $multichain->listblocks('1-100');

// Get mempool info
$result = $multichain->getmempoolinfo();
```

### Working with Raw Transactions

```php
// Create raw transaction
$txhex = $multichain->createrawtransaction(
    [['txid' => $txid1, 'vout' => $vout1]],
    [$address1 => ['asset1' => 10]]
);

// Create raw send from
$txhex = $multichain->createrawsendfrom($fromaddress, [$address => ['asset1' => 10]]);

// Append raw data
$txhex = $multichain->appendrawdata($txhex_in, [
    'for' => 'stream1',
    'key' => 'key1',
    'data' => 'a1b2c3d4'
]);

// Decode raw transaction
$result = $multichain->decoderawtransaction($txhex);

// Sign raw transaction
$result = $multichain->signrawtransaction($txhex);

// Send raw transaction
$txid = $multichain->sendrawtransaction($txhex);
```

### Network & Peer Management

```php
// Add node
$multichain->addnode('12.34.56.78', 'add');

// Get added node info
$result = $multichain->getaddednodeinfo(true);

// Get network info
$result = $multichain->getnetworkinfo();

// Get peer info
$result = $multichain->getpeerinfo();

// Ping peers
$multichain->ping();
```

## ⚙️ Configuration

MultiChain connection settings are in `config/multichain.php`:

```php
return [
    'rpc' => [
        'host' => env('MULTICHAIN_RPC_HOST', '127.0.0.1'),
        'port' => env('MULTICHAIN_RPC_PORT', 4786),
        'username' => env('MULTICHAIN_RPC_USERNAME', 'multichainrpc'),
        'password' => env('MULTICHAIN_RPC_PASSWORD', 'default_password_change_me'),
    ],
    'chain_name' => env('MULTICHAIN_CHAIN_NAME', 'procuchain'),
    'use_ssl' => env('MULTICHAIN_USE_SSL', false),
    'verify_ssl' => env('MULTICHAIN_VERIFY_SSL', false),
];
```

### Advanced Features

#### Retry Logic

The Manager automatically retries failed operations with configurable parameters:

```php
// Default: 3 retries with 1 second delay
protected int $maxRetries = 3;
protected int $retryDelay = 1;
```

#### Context-Aware Timeouts

The Manager uses different timeouts based on operation context:

- Block operations: 60 seconds
- Stream queries: 45 seconds
- Write operations: 45 seconds
- Default operations: 30 seconds

#### Direct Client Access

Access the underlying Client for advanced use cases:

```php
$client = $multichain->getClient();
```

## 🧪 Testing

Test the Manager using Laravel's tinker:

```bash
php artisan tinker
```

```php
$manager = app(\App\Services\Manager::class);

// Test connection
$info = $manager->getinfo();

// List streams
$streams = $manager->liststreams('*', true, 1000, 0);

// Get blockchain parameters
$params = $manager->getblockchainparams();
```

## ⚠️ Important Notes

**Method Names Are Case-Sensitive**

All MultiChain RPC method names must be **lowercase**. The magic `__call()` method forwards calls directly to the RPC API.

✅ **Correct:**
```php
$streams = $multichain->liststreams();
$info = $multichain->getinfo();
$block = $multichain->getblock($height);
```

❌ **Incorrect:**
```php
$streams = $multichain->listStreams();  // Will fail
$info = $multichain->getInfo();         // Will fail
$block = $multichain->getBlock($height); // Will fail
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup

1. Fork the repository
2. Clone your fork: `git clone https://github.com/your-username/procuchain.git`
3. Install dependencies: `composer install`
4. Create a feature branch: `git checkout -b feature/amazing-feature`
5. Make your changes and add tests
6. Run tests: `php artisan test`
7. Commit your changes: `git commit -m 'Add some amazing feature'`
8. Push to the branch: `git push origin feature/amazing-feature`
9. Open a Pull Request

### Guidelines

- Follow PSR-12 coding standards
- Add tests for new features
- Update documentation as needed
- Ensure all tests pass before submitting PR

## 📄 License

This library follows the official MultiChain PHP library license:
Copyright (c) Coin Sciences Ltd - www.multichain.com
All rights reserved under BSD 3-clause license

## 🔗 Resources

- [MultiChain Official Documentation](https://www.multichain.com/developers/json-rpc-api/)
- [MultiChain GitHub Repository](https://github.com/MultiChain/multichain-api-libraries)
- [Original PHP Examples](https://github.com/MultiChain/multichain-api-libraries/blob/main/php/examples.php)
- [Laravel Documentation](https://laravel.com/docs)
- [Procuchain Project](https://github.com/leodyversemilla07/procuchain)

---

<div align="center">

**Built with ❤️ for the Procuchain procurement management system**

[⬆️ Back to Top](#-table-of-contents)

</div>

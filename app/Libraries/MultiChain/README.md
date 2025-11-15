# MultiChain PHP Library for Laravel

A Laravel-integrated wrapper for the official MultiChain JSON-RPC API library, providing seamless blockchain integration with automatic connection management, retry logic, and Laravel conventions.

## Overview

This library consists of two main components:

- **Client.php** - Official MultiChain JSON-RPC client with magic `__call()` method support
- **Manager.php** - Laravel wrapper providing connection management, retry logic, and error handling

## Installation

The library is already integrated into this Laravel application. The Manager is bound as a singleton in `app/Providers/AppServiceProvider.php`:

```php
use App\Libraries\MultiChain\Manager;

$this->app->singleton(Manager::class);
```

## Basic Usage

### Dependency Injection

Inject the Manager into your controllers, services, or repositories:

```php
use App\Libraries\MultiChain\Manager;

class BlockchainController extends Controller
{
    public function __construct(
        protected Manager $multichain
    ) {}
    
    public function index()
    {
        $info = $this->multichain->getinfo();
        return response()->json($info);
    }
}
```

### Using the Helper

You can also resolve the Manager from the container:

```php
$manager = app(\App\Libraries\MultiChain\Manager::class);
$info = $manager->getinfo();
```

## General Utilities

```php
// Get blockchain parameters
$result = $multichain->getblockchainparams();

// Check node health
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

## Managing Wallet Addresses

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

## Working with Non-Wallet Addresses

```php
// Create key pairs
$result = $multichain->createkeypairs();

// Create multisig address
$result = $multichain->createmultisig(2, [$address1, $address2]);

// Validate address
$result = $multichain->validateaddress($address);
```

## Permissions Management

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

## Asset Management

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

## Querying Wallet Balances

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

## Sending Payments

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

## Stream Management

```php
// Create open stream
$txid = $multichain->create('stream', 'stream1', true);

// Create write-restricted stream
$txid = $multichain->create('stream', 'stream1', ['restrict' => 'write']);

// Create with custom fields
$txid = $multichain->create('stream', 'stream1', false, ['purpose' => 'inventory']);

// Get stream information
$result = $multichain->getstreaminfo('stream1');

// List all streams
$result = $multichain->liststreams();

// List specific stream
$result = $multichain->liststreams('stream1');

// List multiple streams
$result = $multichain->liststreams(['stream1', 'stream2']);

// Subscribe to stream
$multichain->subscribe('stream1');

// Unsubscribe from stream
$multichain->unsubscribe('stream1');
```

## Publishing Stream Items

```php
// Publish raw binary data
$txid = $multichain->publish('stream1', 'key1', 'a1b2c3d4');

// Publish text data
$txid = $multichain->publish('stream1', 'key1', ['text' => 'hello world']);

// Publish JSON data
$txid = $multichain->publish('stream1', 'key1', ['json' => ['name' => 'John', 'age' => 30]]);

// Publish with multiple keys
$txid = $multichain->publish('stream1', ['key1', 'key2'], 'a1b2c3d4');

// Publish offchain
$txid = $multichain->publish('stream1', 'key1', 'a1b2c3d4', 'offchain');

// Publish from specific address
$txid = $multichain->publishfrom($fromaddress, 'stream1', 'key1', 'a1b2c3d4');

// Multi-publish
$txid = $multichain->publishmulti('stream1', [
    ['key' => 'key1', 'data' => ['json' => ['name' => 'John', 'age' => 30]]],
    ['keys' => ['key2', 'key3'], 'data' => ['json' => ['name' => 'Mary', 'age' => 25]]]
]);
```

## Querying Stream Items

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

## Blockchain Queries

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

## Working with Raw Transactions

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

## Network & Peer Management

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

## Error Handling

The Manager provides automatic retry logic with configurable attempts:

```php
use App\Libraries\MultiChain\Manager;
use Exception;

try {
    $result = $multichain->getinfo();
} catch (Exception $e) {
    // Connection failed after retries
    Log::error('MultiChain error: ' . $e->getMessage());
}
```

Check operation success:

```php
$multichain->setruntimeparam('maxshowndata', 16384);

if ($multichain->success()) {
    // Operation succeeded
} else {
    // Operation failed
    $error = $multichain->error();
}
```

## Configuration

MultiChain connection settings are in `config/multichain.php`:

```php
return [
    'host' => env('MULTICHAIN_HOST', 'localhost'),
    'port' => env('MULTICHAIN_PORT', 8570),
    'username' => env('MULTICHAIN_USERNAME'),
    'password' => env('MULTICHAIN_PASSWORD'),
    'chain_name' => env('MULTICHAIN_CHAIN_NAME', 'procuchain'),
];
```

## Advanced Features

### Retry Logic

The Manager automatically retries failed operations with configurable parameters:

```php
// Default: 3 retries with 1 second delay
protected int $maxRetries = 3;
protected int $retryDelay = 1;
```

### Context-Aware Timeouts

The Manager uses different timeouts based on operation context:

- Block operations: 60 seconds
- Stream queries: 45 seconds  
- Write operations: 45 seconds
- Default operations: 30 seconds

### Direct Client Access

Access the underlying Client for advanced use cases:

```php
$client = $multichain->getClient();
```

## Important Notes

⚠️ **Method Names Are Case-Sensitive**

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

## Testing

Test the Manager using Laravel's tinker:

```bash
php artisan tinker
```

```php
$manager = app(\App\Libraries\MultiChain\Manager::class);

// Test connection
$info = $manager->getinfo();

// List streams
$streams = $manager->liststreams('*', true, 1000, 0);

// Get blockchain parameters
$params = $manager->getblockchainparams();
```

## Resources

- [MultiChain Official Documentation](https://www.multichain.com/developers/json-rpc-api/)
- [MultiChain GitHub Repository](https://github.com/MultiChain/multichain-api-libraries)
- [Original PHP Examples](https://github.com/MultiChain/multichain-api-libraries/blob/main/php/examples.php)

## License

This library follows the official MultiChain PHP library license:
Copyright (c) Coin Sciences Ltd - www.multichain.com
All rights reserved under BSD 3-clause license

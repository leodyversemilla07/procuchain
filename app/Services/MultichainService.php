<?php

namespace App\Services;

use App\Libraries\MultichainClient;
use Exception;
use Illuminate\Support\Facades\Log;

class MultichainService
{
    private $mc;

    private $maxRetries;

    private $retryDelay = 2; // seconds (web total ~ timeout*retries + delays)

    private $timeout;

    public function __construct()
    {
        // Use shorter caps for web requests to avoid 60s limit
        $isConsole = app()->runningInConsole();
        $this->maxRetries = $isConsole
            ? (int) config('multichain.max_retries', 3)
            : (int) config('multichain.web_max_retries', 1); // Reduced from 2 to 1 for faster failures

        $this->timeout = $isConsole
            ? (int) config('multichain.connection_timeout', 30)
            : (int) config('multichain.web_connection_timeout', 5); // Reduced from 12 to 5 seconds
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $this->mc = new MultichainClient(
            config('multichain.rpc.host'),
            config('multichain.rpc.port'),
            config('multichain.rpc.username'),
            config('multichain.rpc.password'),
            config('multichain.use_ssl')
        );

        // Set additional options
        $this->mc->setoption('chain_name', config('multichain.chain_name'));
        $this->mc->setoption('verify_ssl', config('multichain.verify_ssl'));
        $this->mc->setoption('use_curl', true); // Use CURL for better remote connection handling
        $this->mc->setTimeout($this->timeout);
    }

    public function getHost(): string
    {
        return config('multichain.rpc.host');
    }

    /*******************/
    /*  Error Handler */
    /*******************/

    private function handleRequest(callable $operation): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                // Skip validation check - let the actual RPC call validate the connection
                // This prevents double timeout issues (validation + actual call)

                $result = $operation();
                if (! $this->mc->success()) {
                    $error = $this->mc->errormessage();
                    $code = $this->mc->errorcode();

                    if ($this->isConnectionError($error)) {
                        throw new Exception("Connection error: $error", $code);
                    }

                    Log::error('MultiChain RPC Error', [
                        'error' => $error,
                        'code' => $code,
                        'host' => config('multichain.rpc.host'),
                        'port' => config('multichain.rpc.port'),
                    ]);
                    throw new Exception("MultiChain Error: $error", $code);
                }

                return $result;

            } catch (Exception $e) {
                $lastException = $e;

                if (! $this->isConnectionError($e->getMessage())) {
                    throw $e;
                }

                $attempts++;
                if ($attempts < $this->maxRetries) {
                    Log::warning("Connection attempt {$attempts} failed. Retrying in {$this->retryDelay} seconds...", [
                        'error' => $e->getMessage(),
                    ]);
                    sleep($this->retryDelay);
                    $this->initializeClient(); // Reinitialize the client
                }
            }
        }

        Log::error('All connection attempts failed', [
            'host' => config('multichain.rpc.host'),
            'port' => config('multichain.rpc.port'),
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
        ]);

        throw new Exception(
            'Failed to connect to MultiChain node after '.$this->maxRetries.' attempts. '.
            'Please check if the blockchain service is running at '.
            config('multichain.rpc.host').':'.config('multichain.rpc.port')
        );
    }

    private function validateConnection(): void
    {
        try {
            $info = $this->mc->getinfo();
            if (! $this->mc->success()) {
                throw new Exception($this->mc->errormessage(), $this->mc->errorcode());
            }

            // Verify we're connected to the right chain
            if ($info['chainname'] !== config('multichain.chain_name')) {
                throw new Exception('Connected to wrong blockchain: '.$info['chainname']);
            }

            // Verify node is fully initialized
            $initStatus = $this->mc->getinitstatus();
            if (! $this->mc->success() || ! $initStatus['initialized']) {
                throw new Exception('Node is not fully initialized');
            }

        } catch (Exception $e) {
            if ($this->isConnectionError($e->getMessage())) {
                throw new Exception(
                    'Failed to connect to MultiChain node at '.
                    config('multichain.rpc.host').':'.
                    config('multichain.rpc.port').
                    '. Please ensure the blockchain service is accessible and RPC credentials are correct.'
                );
            }
            throw $e;
        }
    }

    private function isConnectionError(string $message): bool
    {
        $connectionErrors = [
            'Failed to connect',
            'Connection refused',
            'Unable to connect',
            'Connection timed out',
            'Network is unreachable',
            // Windows-specific Winsock messages
            'A connection attempt failed',
            'connected party did not properly respond',
            'host has failed to respond',
        ];

        foreach ($connectionErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }

    /********************************/
    /*  General Utilities */
    /********************************/

    public function getInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getinfo());
    }

    public function getBlockchainParams(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getblockchainparams());
    }

    public function getRuntimeParams(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getruntimeparams());
    }

    public function setRuntimeParam($param, $value): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->setruntimeparam($param, $value));
    }

    public function getInitStatus(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getinitstatus());
    }

    /********************************/
    /*  Managing Wallet Addresses */
    /********************************/

    public function getAddresses(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getaddresses());
    }

    public function getNewAddress(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getnewaddress());
    }

    public function importAddress(string $address, ?string $label = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $label !== null
            ? $this->mc->importaddress($address, $label)
            : $this->mc->importaddress($address)
        );
    }

    public function listAddresses($address = null, bool $verbose = false): mixed
    {
        if ($address) {
            return $this->handleRequest(fn (): mixed => $this->mc->listaddresses($address, $verbose));
        }

        return $this->handleRequest(fn (): mixed => $this->mc->listaddresses());
    }

    /********************************/
    /*  Working with Non-wallet Addresses */
    /********************************/

    public function createKeyPairs(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createkeypairs());
    }

    public function createMultiSig(int $required, array $publicKeys): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createmultisig($required, $publicKeys));
    }

    public function validateAddress(string $address): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->validateaddress($address));
    }

    /********************************/
    /*  Permissions Management */
    /********************************/

    public function grant(string $addresses, string $permissions): mixed
    {
        try {
            // Validate and normalize permissions
            $permParts = explode(',', $permissions);
            $validatedPerms = [];

            foreach ($permParts as $perm) {
                $perm = trim($perm);

                // For stream permissions, verify stream exists first
                if (strpos($perm, '.') !== false) {
                    // For stream permissions like "procurement.documents.write",
                    // we need to get the stream name which is the first two parts
                    $parts = explode('.', $perm);
                    if (count($parts) === 3) {
                        $streamName = $parts[0].'.'.$parts[1];
                        $action = $parts[2];

                        try {
                            $streamInfo = $this->getStreamInfo($streamName);
                            if (! $streamInfo) {
                                throw new Exception("Stream '$streamName' does not exist");
                            }
                            // Use the full permission string
                            $validatedPerms[] = $perm;
                        } catch (Exception $e) {
                            Log::warning("Stream validation failed for $streamName", [
                                'error' => $e->getMessage(),
                            ]);
                            // If stream doesn't exist, wait briefly and retry once
                            sleep(2);
                            $streamInfo = $this->getStreamInfo($streamName);
                            if (! $streamInfo) {
                                throw new Exception("Stream '$streamName' does not exist or is not ready");
                            }
                            $validatedPerms[] = $perm;
                        }
                    } else {
                        // Not a valid stream permission format
                        $validatedPerms[] = $perm;
                    }
                } else {
                    // Global permission, add as is
                    $validatedPerms[] = $perm;
                }
            }

            // Join validated permissions
            $validatedPermString = implode(',', $validatedPerms);

            return $this->handleRequest(
                fn (): mixed => $this->mc->grant($addresses, $validatedPermString)
            );
        } catch (Exception $e) {
            Log::error('Failed to grant permissions', [
                'addresses' => $addresses,
                'permissions' => $permissions,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function grantWithData(string $addresses, string $permissions, mixed $data): mixed
    {
        try {
            return $this->handleRequest(
                fn (): mixed => $this->mc->grantwithdata($addresses, $permissions, $data)
            );
        } catch (Exception $e) {
            Log::error('Failed to grant permissions with data', [
                'addresses' => $addresses,
                'permissions' => $permissions,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function revoke(string $addresses, string $permissions): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->revoke($addresses, $permissions));
    }

    public function listPermissions(string $permissions = '*', string $address = '*', bool $verbose = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->listpermissions($permissions, $address, $verbose));
    }

    /********************************/
    /*  Asset Management */
    /********************************/

    public function createAsset(string $address, array $assetParams, float $quantity, float $units = 1): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->issue($address, $assetParams, $quantity, $units));
    }

    public function issueMore(string $address, string $asset, float $quantity, array $details = []): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->issuemore($address, $asset, $quantity, 0, $details));
    }

    public function getAssetInfo(string $assetName): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getassetinfo($assetName));
    }

    public function listAssets(string $asset = '*', bool $verbose = false, int $count = 1000, int $start = -1): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->listassets($asset, $verbose, $count, $start));
    }

    /********************************/
    /*  Querying Wallet Balances */
    /********************************/

    public function getAddressBalances(string $address, int $minconf = 1): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getaddressbalances($address, $minconf));
    }

    public function getMultiBalances($addresses = '*', $assets = '*', int $minconf = 1, bool $includeWatchOnly = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getmultibalances($addresses, $assets, $minconf, $includeWatchOnly));
    }

    public function getTotalBalances(int $minconf = 1, bool $includeWatchOnly = false, bool $includeDeleted = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->gettotalbalances($minconf, $includeWatchOnly, $includeDeleted));
    }

    /********************************/
    /*  Stream Management */
    /********************************/

    private function waitForStreamAvailability(string $streamName, int $maxRetries = 3): bool
    {
        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                $streamInfo = $this->getStreamInfo($streamName);
                if (! empty($streamInfo)) {
                    return true;
                }
            } catch (Exception $e) {
                Log::warning("Attempt $i: Waiting for stream $streamName to be available: ".$e->getMessage());
                if ($i < $maxRetries) {
                    sleep(5);
                }
            }
        }

        return false;
    }

    public function createStream(string $streamName, array|bool $options = true, array $details = []): mixed
    {
        try {
            // First check if stream already exists
            try {
                $existing = $this->getStreamInfo($streamName);
                if (! empty($existing)) {
                    // If stream exists, ensure it's subscribed
                    $this->subscribe($streamName, true);
                    Log::info("Stream $streamName already exists and subscription verified");

                    return ['status' => 'exists', 'stream' => $existing];
                }
            } catch (Exception $e) {
                // Stream doesn't exist, continue with creation
            }

            // Validate stream name
            if (! preg_match('/^[a-z0-9\.\_\-]+$/i', $streamName)) {
                throw new Exception("Invalid stream name: $streamName. Use only alphanumeric characters, dots, underscores, and hyphens.");
            }

            // Create stream with proper options handling
            $result = $this->handleRequest(function () use ($streamName, $options, $details): mixed {
                if (empty($details)) {
                    return $this->mc->create('stream', $streamName, $options);
                }

                $detailPayload = is_array($details) ? (object) $details : $details;

                return $this->mc->create('stream', $streamName, $options, $detailPayload);
            });

            // Wait for stream to be created and subscribe with retries
            $maxRetries = 3;
            $retryDelay = 2;

            for ($i = 1; $i <= $maxRetries; $i++) {
                try {
                    sleep($retryDelay); // Wait for blockchain to process
                    $this->subscribe($streamName, true);
                    Log::info("Stream $streamName created and subscribed successfully", [
                        'attempt' => $i,
                        'txid' => $result,
                    ]);

                    return $result;
                } catch (Exception $e) {
                    Log::warning("Subscription attempt $i failed for stream $streamName: ".$e->getMessage());
                    if ($i === $maxRetries) {
                        throw new Exception("Failed to subscribe to stream after $maxRetries attempts");
                    }
                }
            }

            throw new Exception("Stream $streamName created but subscription failed after $maxRetries attempts");
        } catch (Exception $e) {
            Log::error('Failed to create/subscribe to stream', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createStreamFrom(string $fromAddress, string $streamName, array|bool $options = true, array $details = []): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createfrom($fromAddress, 'stream', $streamName, $options, $details));
    }

    public function getStreamInfo(string $streamName, bool $verbose = false): mixed
    {
        try {
            return $this->handleRequest(
                fn (): mixed => $this->mc->getstreaminfo($streamName, $verbose)
            );
        } catch (Exception $e) {
            Log::error('Failed to get stream info', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function listStreams(string|array $streams = '*', bool $verbose = false, int $count = 1000, int $start = -1): mixed
    {
        try {
            return $this->handleRequest(
                fn (): mixed => $this->mc->liststreams($streams, $verbose, $count, $start)
            );
        } catch (Exception $e) {
            Log::error('Failed to list streams', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /********************************/
    /*  Publishing Stream Items */
    /********************************/

    public function publish(string $streamName, string|array $key, mixed $data, ?string $options = null): mixed
    {
        try {
            // Ensure key is properly formatted
            if (is_string($key)) {
                $key = trim($key);
                // Allow alphanumeric, underscores, and hyphens
                $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            }

            Log::info('Publishing to MultiChain', [
                'stream' => $streamName,
                'key' => $key,
                'data_type' => gettype($data),
            ]);

            return $this->handleRequest(fn (): mixed => $this->mc->publish($streamName, $key, $data, $options));
        } catch (Exception $e) {
            Log::error('Failed to publish to MultiChain', [
                'stream' => $streamName,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function publishFrom(string $fromAddress, string $streamName, string|array $key, mixed $data): mixed
    {
        try {
            // Ensure key is properly formatted
            if (is_string($key)) {
                $key = trim($key);
                // Allow alphanumeric, underscores, and hyphens
                $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            }

            Log::info('Publishing to MultiChain from address', [
                'address' => $fromAddress,
                'stream' => $streamName,
                'key' => $key,
                'data_type' => gettype($data),
            ]);

            return $this->handleRequest(fn (): mixed => $this->mc->publishfrom($fromAddress, $streamName, $key, $data));
        } catch (Exception $e) {
            Log::error('Failed to publish from address to MultiChain', [
                'address' => $fromAddress,
                'stream' => $streamName,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function publishMulti(string $streamName, array $items): mixed
    {
        try {
            // Format all keys in items array
            $formattedItems = array_map(function ($item) {
                if (isset($item['for'])) {
                    $item['for'] = trim($item['for']);
                    // Allow alphanumeric, underscores, and hyphens
                    $item['for'] = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $item['for']);
                }

                return $item;
            }, $items);

            Log::info('Publishing multiple items to MultiChain', [
                'stream' => $streamName,
                'item_count' => count($items),
            ]);

            return $this->handleRequest(fn (): mixed => $this->mc->publishmulti($streamName, $formattedItems));
        } catch (Exception $e) {
            Log::error('Failed to publish multiple items to MultiChain', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function publishMultiFrom(string $fromAddress, string $streamName, array $items): mixed
    {
        try {
            // Format all keys in items array
            $formattedItems = array_map(function ($item) {
                if (isset($item['for'])) {
                    $item['for'] = trim($item['for']);
                    // Allow alphanumeric, underscores, and hyphens
                    $item['for'] = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $item['for']);
                }

                return $item;
            }, $items);

            Log::info('Publishing multiple items to MultiChain from address', [
                'address' => $fromAddress,
                'stream' => $streamName,
                'item_count' => count($items),
            ]);

            return $this->handleRequest(fn (): mixed => $this->mc->publishmultifrom($fromAddress, $streamName, $formattedItems));
        } catch (Exception $e) {
            Log::error('Failed to publish multiple items from address to MultiChain', [
                'address' => $fromAddress,
                'stream' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /********************************/
    /*  Managing Stream Subscriptions */
    /********************************/

    public function subscribe(string|array $streams, bool $rescan = true): mixed
    {
        try {
            // For Community Edition, keep it simple with just stream name and rescan
            if (is_array($streams)) {
                foreach ($streams as $stream) {
                    $this->getStreamInfo($stream); // Verify stream exists
                    $this->handleRequest(fn (): mixed => $this->mc->subscribe($stream, $rescan));
                    Log::info('Stream subscription successful', [
                        'stream' => $stream,
                        'rescan' => $rescan,
                    ]);
                }
            } else {
                $this->getStreamInfo($streams); // Verify stream exists
                $this->handleRequest(fn (): mixed => $this->mc->subscribe($streams, $rescan));
                Log::info('Stream subscription successful', [
                    'stream' => $streams,
                    'rescan' => $rescan,
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to subscribe to stream(s)', [
                'streams' => $streams,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function unsubscribe($streams, bool $purge = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->unsubscribe($streams, $purge));
    }

    /********************************/
    /*  Querying Subscribed Streams */
    /********************************/

    public function listStreamItems(string $streamName, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamitems($streamName, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamKeyItems(string $streamName, string $key, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamkeyitems($streamName, $key, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamPublisherItems(string $streamName, string $address, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreampublisheritems($streamName, $address, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamKeys(string $streamName, string|array $keys = '*', bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1, bool $localOrdering = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamkeys($streamName, $keys, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamPublishers(string $streamName, string|array $addresses = '*', bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1, bool $localOrdering = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreampublishers($streamName, $addresses, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamBlockItems(string $streamName, string|int|array $blocks, bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamblockitems($streamName, $blocks, $verbose, $count, $start)
        );
    }

    public function listStreamQueryItems(string $streamName, array $query, bool $verbose = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamqueryitems($streamName, $query, $verbose)
        );
    }

    public function getStreamItem(string $streamName, string $txid, bool $verbose = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->getstreamitem($streamName, $txid, $verbose)
        );
    }

    public function listStreamTxItems(string $streamName, string $txid, bool $verbose = false): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->liststreamtxitems($streamName, $txid, $verbose)
        );
    }

    public function getStreamKeySummary(string $streamName, string $key, string $mode = 'jsonobjectmerge'): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->getstreamkeysummary($streamName, $key, $mode)
        );
    }

    public function getStreamPublisherSummary(string $streamName, string $publisher, string $mode = 'jsonobjectmerge'): mixed
    {
        return $this->handleRequest(
            fn (): mixed => $this->mc->getstreampublishersummary($streamName, $publisher, $mode)
        );
    }

    public function getTxOutData(string $txid, int $vout, ?int $countBytes = null, int $startByte = 0): mixed
    {
        if ($countBytes !== null) {
            return $this->handleRequest(
                fn (): mixed => $this->mc->gettxoutdata($txid, $vout, $countBytes, $startByte)
            );
        }

        return $this->handleRequest(
            fn (): mixed => $this->mc->gettxoutdata($txid, $vout)
        );
    }

    /********************************/
    /*  Managing Wallet Unspent */
    /********************************/

    public function listUnspent(int $minconf = 1, int $maxconf = 999999999, array $addresses = []): mixed
    {
        if (! empty($addresses)) {
            return $this->handleRequest(fn (): mixed => $this->mc->listunspent($minconf, $maxconf, $addresses));
        }

        return $this->handleRequest(fn (): mixed => $this->mc->listunspent($minconf));
    }

    public function listLockUnspent(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->listlockunspent());
    }

    public function lockUnspent(bool $unlock, array $outputs = []): mixed
    {
        if (! empty($outputs)) {
            return $this->handleRequest(fn (): mixed => $this->mc->lockunspent($unlock, $outputs));
        }

        return $this->handleRequest(fn (): mixed => $this->mc->lockunspent($unlock));
    }

    public function prepareLockUnspent(array $assets, bool $lock = true): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->preparelockunspent($assets, $lock));
    }

    public function prepareLockUnspentFrom(string $fromAddress, array $assets, bool $lock = true): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->preparelockunspentfrom($fromAddress, $assets, $lock));
    }

    public function combineUnspent(array $params = []): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->combineunspent($params));
    }

    /********************************/
    /*  Working with Raw Transactions */
    /********************************/

    public function createRawTransaction(array $inputs, array $outputs, array $data = [], ?string $action = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createrawtransaction($inputs, $outputs, $data, $action));
    }

    public function signRawTransaction(string $hexString): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->signrawtransaction($hexString));
    }

    public function sendRawTransaction(string $hexString): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->sendrawtransaction($hexString));
    }

    public function getRawTransaction(string $txid, bool $verbose = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getrawtransaction($txid, $verbose));
    }

    public function decodeRawTransaction(string $txHex): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->decoderawtransaction($txHex));
    }

    public function appendRawData(string $txHex, $data): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->appendrawdata($txHex, $data));
    }

    public function appendRawTransaction(string $txHex, array $inputs, array $outputs = [], array $data = [], $action = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->appendrawtransaction($txHex, $inputs, $outputs, $data, $action));
    }

    public function createRawSendFrom(string $fromAddress, array $outputs, array $data = [], $action = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createrawsendfrom($fromAddress, $outputs, $data, $action));
    }

    public function appendRawChange(string $txHex, string $address): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->appendrawchange($txHex, $address));
    }

    /********************************/
    /*  Peer-to-Peer Connections */
    /********************************/

    public function addNode(string $node, string $command): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->addnode($node, $command));
    }

    public function getNetworkInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getnetworkinfo());
    }

    public function getPeerInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getpeerinfo());
    }

    /********************************/
    /*  Message Signing and Verification */
    /********************************/

    public function signMessage(string $address, string $message): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->signmessage($address, $message));
    }

    public function verifyMessage(string $address, string $signature, string $message): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->verifymessage($address, $signature, $message));
    }

    /********************************/
    /*  Querying the Blockchain */
    /********************************/

    public function getBlock($hashOrHeight, int $verbose = 1): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getblock($hashOrHeight, $verbose));
    }

    public function getBlockchainInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getblockchaininfo());
    }

    public function getBlockHash(int $height): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getblockhash($height));
    }

    public function listBlocks($blocks, bool $verbose = false): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->listblocks($blocks, $verbose));
    }

    public function getLastBlockInfo(int $skip = 0): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getlastblockinfo($skip));
    }

    public function getTxOut(string $txid, int $vout): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->gettxout($txid, $vout));
    }

    /********************************/
    /*  Binary Cache */
    /********************************/

    public function createBinaryCache(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->createbinarycache());
    }

    public function appendBinaryCache(string $id, string $data): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->appendbinarycache($id, $data));
    }

    /********************************/
    /*  Advanced Wallet Control */
    /********************************/

    public function backupWallet(string $path): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->backupwallet($path));
    }

    public function getWalletInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getwalletinfo());
    }

    public function importPrivKey(string $privkey): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->importprivkey($privkey));
    }

    public function dumpWallet(string $filename): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->dumpwallet($filename));
    }

    public function importWallet(string $filename): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->importwallet($filename));
    }

    /********************************/
    /*  Smart Filters */
    /********************************/

    public function createTxFilter(string $name, array $params, string $code): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->create('txfilter', $name, $params, $code));
    }

    public function createStreamFilter(string $name, object|array $params, string $code): mixed
    {
        $restrictions = is_array($params) ? (object) $params : $params;

        return $this->handleRequest(fn (): mixed => $this->mc->create('streamfilter', $name, $restrictions, $code));
    }

    public function approveFrom(string $fromAddress, string $entityName, object|array|bool $approve): mixed
    {
        // Convert boolean to object format expected by MultiChain
        if (is_bool($approve)) {
            $approve = (object) ['approve' => $approve];
        } elseif (is_array($approve)) {
            $approve = (object) $approve;
        }

        return $this->handleRequest(fn (): mixed => $this->mc->approvefrom($fromAddress, $entityName, $approve));
    }

    public function getFilterCode(string $filterName): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getfiltercode($filterName));
    }

    /********************************/
    /*  Variables */
    /********************************/

    public function createVariable(string $name, bool $createUpgrade = false, $value = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->create('variable', $name, $createUpgrade, $value));
    }

    public function getVariableValue(string $name): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getvariablevalue($name));
    }

    public function setVariableValue(string $name, $value = null): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->setvariablevalue($name, $value));
    }

    public function listVariables(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->listvariables());
    }

    /********************************/
    /*  Libraries */
    /********************************/

    public function createLibrary(string $name, object|array $params, string $code): mixed
    {
        $restrictions = is_array($params) ? (object) $params : $params;

        return $this->handleRequest(fn (): mixed => $this->mc->create('library', $name, $restrictions, $code));
    }

    public function getLibraryCode(string $libraryName, string $updateName = ''): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getlibrarycode($libraryName, $updateName));
    }

    /********************************/
    /*  Advanced Node Control */
    /********************************/

    public function pause(string $tasks): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->pause($tasks));
    }

    public function resume(string $tasks): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->resume($tasks));
    }

    public function clearMemPool(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->clearmempool());
    }

    public function getChunkQueueInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getchunkqueueinfo());
    }

    public function getChunkQueueTotals(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getchunkqueuetotals());
    }
}

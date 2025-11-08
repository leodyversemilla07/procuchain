<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MultiChain Blockchain Service
 *
 * Provides comprehensive interface to MultiChain blockchain operations including
 * stream management, asset management, permissions, and wallet operations.
 *
 * Uses MultichainConnectionService for connection management and retry logic.
 */
class MultichainService
{
    /**
     * Initialize the MultiChain service with connection management
     */
    public function __construct(
        protected MultichainConnectionService $connection
    ) {}

    /**
     * Get the configured MultiChain host
     *
     * @return string The MultiChain RPC host address
     */
    public function getHost(): string
    {
        return $this->connection->getHost();
    }

    /********************************/
    /*  General Utilities */
    /********************************/

    /**
     * Get general information about the MultiChain node
     *
     * @return mixed Node information including version, protocol, balance
     *
     * @throws Exception On connection or RPC error
     */
    public function getInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getinfo());
    }

    /**
     * Get blockchain parameters (immutable)
     *
     * @return mixed Blockchain configuration parameters
     *
     * @throws Exception On connection or RPC error
     */
    public function getBlockchainParams(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getblockchainparams());
    }

    /**
     * Get runtime parameters (mutable)
     *
     * @return mixed Runtime configuration parameters
     *
     * @throws Exception On connection or RPC error
     */
    public function getRuntimeParams(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getruntimeparams());
    }

    /**
     * Set a runtime parameter value
     *
     * @param  mixed  $param  Parameter name
     * @param  mixed  $value  Parameter value
     * @return mixed Result of the operation
     *
     * @throws Exception On connection or RPC error
     */
    public function setRuntimeParam($param, $value): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->setruntimeparam($param, $value));
    }

    /**
     * Get blockchain initialization status
     *
     * @return mixed Initialization status information
     *
     * @throws Exception On connection or RPC error
     */
    public function getInitStatus(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getinitstatus());
    }

    /********************************/
    /*  Managing Wallet Addresses */
    /********************************/

    public function getAddresses(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getaddresses());
    }

    public function getNewAddress(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getnewaddress());
    }

    public function importAddress(string $address, ?string $label = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $label !== null
            ? $this->connection->getClient()->importaddress($address, $label)
            : $this->connection->getClient()->importaddress($address)
        );
    }

    public function listAddresses($address = null, bool $verbose = false): mixed
    {
        if ($address) {
            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listaddresses($address, $verbose));
        }

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listaddresses());
    }

    /********************************/
    /*  Working with Non-wallet Addresses */
    /********************************/

    public function createKeyPairs(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createkeypairs());
    }

    public function createMultiSig(int $required, array $publicKeys): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createmultisig($required, $publicKeys));
    }

    public function validateAddress(string $address): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->validateaddress($address));
    }

    /********************************/
    /*  Permissions Management */
    /********************************/

    /**
     * Grant blockchain permissions to address(es)
     *
     * Validates and grants permissions including global permissions (connect, send, receive)
     * and stream-specific permissions (write, activate, admin). Automatically validates
     * stream existence for stream permissions.
     *
     * @param  string  $addresses  Comma-separated addresses to grant permissions to
     * @param  string  $permissions  Comma-separated permissions (e.g., "send,receive" or "stream.write")
     * @return mixed Transaction ID of the grant operation
     *
     * @throws Exception If permission grant fails or stream doesn't exist
     */
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

            return $this->connection->handleRequest(
                fn (): mixed => $this->connection->getClient()->grant($addresses, $validatedPermString)
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
            return $this->connection->handleRequest(
                fn (): mixed => $this->connection->getClient()->grantwithdata($addresses, $permissions, $data)
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
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->revoke($addresses, $permissions));
    }

    public function listPermissions(string $permissions = '*', string $address = '*', bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listpermissions($permissions, $address, $verbose));
    }

    /********************************/
    /*  Asset Management */
    /********************************/

    public function createAsset(string $address, array $assetParams, float $quantity, float $units = 1): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->issue($address, $assetParams, $quantity, $units));
    }

    public function issueMore(string $address, string $asset, float $quantity, array $details = []): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->issuemore($address, $asset, $quantity, 0, $details));
    }

    public function getAssetInfo(string $assetName): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getassetinfo($assetName));
    }

    public function listAssets(string $asset = '*', bool $verbose = false, int $count = 1000, int $start = -1): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listassets($asset, $verbose, $count, $start));
    }

    /********************************/
    /*  Querying Wallet Balances */
    /********************************/

    public function getAddressBalances(string $address, int $minconf = 1): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getaddressbalances($address, $minconf));
    }

    public function getMultiBalances($addresses = '*', $assets = '*', int $minconf = 1, bool $includeWatchOnly = false): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getmultibalances($addresses, $assets, $minconf, $includeWatchOnly));
    }

    public function getTotalBalances(int $minconf = 1, bool $includeWatchOnly = false, bool $includeDeleted = false): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->gettotalbalances($minconf, $includeWatchOnly, $includeDeleted));
    }

    /********************************/
    /*  Stream Management */
    /********************************/

    /**
     * Wait for a stream to become available after creation
     *
     * @param  string  $streamName  The stream name to check
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @return bool True if stream becomes available, false otherwise
     */
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

    /**
     * Create a new blockchain stream
     *
     * Creates a stream for storing structured data on the blockchain. If the stream
     * already exists, verifies subscription. Automatically subscribes to the new stream
     * with retry logic to handle blockchain processing delays.
     *
     * @param  string  $streamName  The name of the stream to create
     * @param  array|bool  $options  Stream creation options or true for default
     * @param  array  $details  Additional metadata for the stream
     * @return mixed Transaction ID of stream creation or status array if exists
     *
     * @throws Exception If stream creation or subscription fails
     */
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
            $result = $this->connection->handleRequest(function () use ($streamName, $options, $details): mixed {
                if (empty($details)) {
                    return $this->connection->getClient()->create('stream', $streamName, $options);
                }

                $detailPayload = is_array($details) ? (object) $details : $details;

                return $this->connection->getClient()->create('stream', $streamName, $options, $detailPayload);
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

    /**
     * Create a stream from a specific address
     *
     * @param  string  $fromAddress  Address to create the stream from
     * @param  string  $streamName  The name of the stream to create
     * @param  array|bool  $options  Stream creation options
     * @param  array  $details  Additional metadata
     * @return mixed Transaction ID of stream creation
     *
     * @throws Exception On creation failure
     */
    public function createStreamFrom(string $fromAddress, string $streamName, array|bool $options = true, array $details = []): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createfrom($fromAddress, 'stream', $streamName, $options, $details));
    }

    /**
     * Get detailed information about a stream
     *
     * @param  string  $streamName  The stream name to query
     * @param  bool  $verbose  Include verbose details
     * @return mixed Stream information including subscribed status, items, keys, publishers
     *
     * @throws Exception If stream doesn't exist or query fails
     */
    public function getStreamInfo(string $streamName, bool $verbose = false): mixed
    {
        try {
            return $this->connection->handleRequest(
                fn (): mixed => $this->connection->getClient()->getstreaminfo($streamName, $verbose)
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
            return $this->connection->handleRequest(
                fn (): mixed => $this->connection->getClient()->liststreams($streams, $verbose, $count, $start)
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

    /**
     * Publish data to a blockchain stream
     *
     * Publishes structured data to a stream with a given key. Keys are sanitized
     * to alphanumeric characters, underscores, and hyphens.
     *
     * @param  string  $streamName  The stream to publish to
     * @param  string|array  $key  The key(s) for indexing the data
     * @param  mixed  $data  The data to publish (array, object, or JSON string)
     * @param  string|null  $options  Additional publish options
     * @return mixed Transaction ID of the published item
     *
     * @throws Exception If publishing fails or stream doesn't exist
     */
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

            // Only pass options parameter if it's explicitly provided and not null
            return $this->connection->handleRequest(function () use ($streamName, $key, $data, $options): mixed {
                if ($options !== null) {
                    return $this->connection->getClient()->publish($streamName, $key, $data, $options);
                }

                return $this->connection->getClient()->publish($streamName, $key, $data);
            });
        } catch (Exception $e) {
            Log::error('Failed to publish to MultiChain', [
                'stream' => $streamName,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Publish data to a stream from a specific blockchain address
     *
     * @param  string  $fromAddress  The address to publish from
     * @param  string  $streamName  The stream to publish to
     * @param  string|array  $key  The key(s) for indexing
     * @param  mixed  $data  The data to publish
     * @return mixed Transaction ID of the published item
     *
     * @throws Exception If publishing fails
     */
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

            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->publishfrom($fromAddress, $streamName, $key, $data));
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

            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->publishmulti($streamName, $formattedItems));
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

            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->publishmultifrom($fromAddress, $streamName, $formattedItems));
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
                    $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->subscribe($stream, $rescan));
                    Log::info('Stream subscription successful', [
                        'stream' => $stream,
                        'rescan' => $rescan,
                    ]);
                }
            } else {
                $this->getStreamInfo($streams); // Verify stream exists
                $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->subscribe($streams, $rescan));
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
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->unsubscribe($streams, $purge));
    }

    /********************************/
    /*  Querying Subscribed Streams */
    /********************************/

    public function listStreamItems(string $streamName, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamitems($streamName, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamKeyItems(string $streamName, string $key, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamkeyitems($streamName, $key, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamPublisherItems(string $streamName, string $address, bool $verbose = false, int $count = 1000, int $start = -10, bool $localOrdering = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreampublisheritems($streamName, $address, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamKeys(string $streamName, string|array $keys = '*', bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1, bool $localOrdering = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamkeys($streamName, $keys, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamPublishers(string $streamName, string|array $addresses = '*', bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1, bool $localOrdering = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreampublishers($streamName, $addresses, $verbose, $count, $start, $localOrdering)
        );
    }

    public function listStreamBlockItems(string $streamName, string|int|array $blocks, bool $verbose = false, int $count = PHP_INT_MAX, int $start = -1): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamblockitems($streamName, $blocks, $verbose, $count, $start)
        );
    }

    public function listStreamQueryItems(string $streamName, array $query, bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamqueryitems($streamName, $query, $verbose)
        );
    }

    public function getStreamItem(string $streamName, string $txid, bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->getstreamitem($streamName, $txid, $verbose)
        );
    }

    public function listStreamTxItems(string $streamName, string $txid, bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->liststreamtxitems($streamName, $txid, $verbose)
        );
    }

    public function getStreamKeySummary(string $streamName, string $key, string $mode = 'jsonobjectmerge'): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->getstreamkeysummary($streamName, $key, $mode)
        );
    }

    public function getStreamPublisherSummary(string $streamName, string $publisher, string $mode = 'jsonobjectmerge'): mixed
    {
        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->getstreampublishersummary($streamName, $publisher, $mode)
        );
    }

    public function getTxOutData(string $txid, int $vout, ?int $countBytes = null, int $startByte = 0): mixed
    {
        if ($countBytes !== null) {
            return $this->connection->handleRequest(
                fn (): mixed => $this->connection->getClient()->gettxoutdata($txid, $vout, $countBytes, $startByte)
            );
        }

        return $this->connection->handleRequest(
            fn (): mixed => $this->connection->getClient()->gettxoutdata($txid, $vout)
        );
    }

    /********************************/
    /*  Managing Wallet Unspent */
    /********************************/

    public function listUnspent(int $minconf = 1, int $maxconf = 999999999, array $addresses = []): mixed
    {
        if (! empty($addresses)) {
            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listunspent($minconf, $maxconf, $addresses));
        }

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listunspent($minconf));
    }

    public function listLockUnspent(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listlockunspent());
    }

    public function lockUnspent(bool $unlock, array $outputs = []): mixed
    {
        if (! empty($outputs)) {
            return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->lockunspent($unlock, $outputs));
        }

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->lockunspent($unlock));
    }

    public function prepareLockUnspent(array $assets, bool $lock = true): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->preparelockunspent($assets, $lock));
    }

    public function prepareLockUnspentFrom(string $fromAddress, array $assets, bool $lock = true): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->preparelockunspentfrom($fromAddress, $assets, $lock));
    }

    public function combineUnspent(array $params = []): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->combineunspent($params));
    }

    /********************************/
    /*  Working with Raw Transactions */
    /********************************/

    public function createRawTransaction(array $inputs, array $outputs, array $data = [], ?string $action = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createrawtransaction($inputs, $outputs, $data, $action));
    }

    public function signRawTransaction(string $hexString): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->signrawtransaction($hexString));
    }

    public function sendRawTransaction(string $hexString): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->sendrawtransaction($hexString));
    }

    public function getRawTransaction(string $txid, bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getrawtransaction($txid, $verbose));
    }

    public function decodeRawTransaction(string $txHex): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->decoderawtransaction($txHex));
    }

    public function appendRawData(string $txHex, $data): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->appendrawdata($txHex, $data));
    }

    public function appendRawTransaction(string $txHex, array $inputs, array $outputs = [], array $data = [], $action = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->appendrawtransaction($txHex, $inputs, $outputs, $data, $action));
    }

    public function createRawSendFrom(string $fromAddress, array $outputs, array $data = [], $action = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createrawsendfrom($fromAddress, $outputs, $data, $action));
    }

    public function appendRawChange(string $txHex, string $address): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->appendrawchange($txHex, $address));
    }

    /********************************/
    /*  Peer-to-Peer Connections */
    /********************************/

    public function addNode(string $node, string $command): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->addnode($node, $command));
    }

    public function getNetworkInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getnetworkinfo());
    }

    public function getPeerInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getpeerinfo());
    }

    /********************************/
    /*  Message Signing and Verification */
    /********************************/

    public function signMessage(string $address, string $message): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->signmessage($address, $message));
    }

    public function verifyMessage(string $address, string $signature, string $message): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->verifymessage($address, $signature, $message));
    }

    /********************************/
    /*  Querying the Blockchain */
    /********************************/

    public function getBlock($hashOrHeight, int $verbose = 1): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getblock($hashOrHeight, $verbose));
    }

    public function getBlockchainInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getblockchaininfo());
    }

    public function getBlockHash(int $height): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getblockhash($height));
    }

    public function listBlocks($blocks, bool $verbose = false): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listblocks($blocks, $verbose));
    }

    public function getLastBlockInfo(int $skip = 0): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getlastblockinfo($skip));
    }

    public function getTxOut(string $txid, int $vout): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->gettxout($txid, $vout));
    }

    /********************************/
    /*  Binary Cache */
    /********************************/

    public function createBinaryCache(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->createbinarycache());
    }

    public function appendBinaryCache(string $id, string $data): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->appendbinarycache($id, $data));
    }

    /********************************/
    /*  Advanced Wallet Control */
    /********************************/

    public function backupWallet(string $path): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->backupwallet($path));
    }

    public function getWalletInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getwalletinfo());
    }

    public function importPrivKey(string $privkey): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->importprivkey($privkey));
    }

    public function dumpWallet(string $filename): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->dumpwallet($filename));
    }

    public function importWallet(string $filename): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->importwallet($filename));
    }

    /********************************/
    /*  Smart Filters */
    /********************************/

    public function createTxFilter(string $name, array $params, string $code): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->create('txfilter', $name, $params, $code));
    }

    public function createStreamFilter(string $name, object|array $params, string $code): mixed
    {
        $restrictions = is_array($params) ? (object) $params : $params;

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->create('streamfilter', $name, $restrictions, $code));
    }

    public function approveFrom(string $fromAddress, string $entityName, object|array|bool $approve): mixed
    {
        // Convert boolean to object format expected by MultiChain
        if (is_bool($approve)) {
            $approve = (object) ['approve' => $approve];
        } elseif (is_array($approve)) {
            $approve = (object) $approve;
        }

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->approvefrom($fromAddress, $entityName, $approve));
    }

    public function getFilterCode(string $filterName): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getfiltercode($filterName));
    }

    /********************************/
    /*  Variables */
    /********************************/

    public function createVariable(string $name, bool $createUpgrade = false, $value = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->create('variable', $name, $createUpgrade, $value));
    }

    public function getVariableValue(string $name): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getvariablevalue($name));
    }

    public function setVariableValue(string $name, $value = null): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->setvariablevalue($name, $value));
    }

    public function listVariables(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->listvariables());
    }

    /********************************/
    /*  Libraries */
    /********************************/

    public function createLibrary(string $name, object|array $params, string $code): mixed
    {
        $restrictions = is_array($params) ? (object) $params : $params;

        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->create('library', $name, $restrictions, $code));
    }

    public function getLibraryCode(string $libraryName, string $updateName = ''): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getlibrarycode($libraryName, $updateName));
    }

    /********************************/
    /*  Advanced Node Control */
    /********************************/

    public function pause(string $tasks): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->pause($tasks));
    }

    public function resume(string $tasks): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->resume($tasks));
    }

    public function clearMemPool(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->clearmempool());
    }

    public function getChunkQueueInfo(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getchunkqueueinfo());
    }

    public function getChunkQueueTotals(): mixed
    {
        return $this->connection->handleRequest(fn (): mixed => $this->connection->getClient()->getchunkqueuetotals());
    }
}

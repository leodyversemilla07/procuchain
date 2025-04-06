<?php

namespace App\Services;

use App\Libraries\MultichainClient;
use App\Services\Multichain\StreamQueryOptions;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Provides a high-level interface for MultiChain blockchain operations
 * 
 * This service wraps the MultichainClient library to provide error handling,
 * logging, and convenient methods for common blockchain operations used in
 * the procurement system. It handles stream publishing, querying, and data
 * transformation between PHP and blockchain formats.
 */
class MultichainService
{
    /**
     * The MultichainClient instance for direct blockchain communication
     *
     * @var MultichainClient
     */
    public $client;

    /**
     * Creates a new MultichainService instance
     * 
     * Initializes the MultichainClient using configuration from config/multichain.php
     */
    public function __construct()
    {
        $this->client = MultichainClient::fromConfig(config('multichain'));
    }
    
    /**
     * Execute a blockchain command and handle common error scenarios
     *
     * Provides centralized error handling and logging for blockchain operations.
     * Ensures consistent error reporting and maintains an audit trail of operations.
     *
     * @param string $operation Description of the operation being performed
     * @param callable $callback Function that performs the actual blockchain operation
     * @param array $logContext Additional context for logging
     * @throws Exception If the operation fails
     * @return mixed The result of the operation
     */
    private function executeBlockchainCommand(string $operation, callable $callback, array $logContext = []): mixed
    {
        try {
            // Execute the operation
            $result = $callback();
            
            // Check if the operation was successful
            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }
            
            return $result;
        } catch (Exception $e) {
            // Log the error with context
            $errorContext = array_merge([
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
            ], $logContext);
            
            Log::error("Failed to $operation", $errorContext);
            throw new Exception("Failed to $operation: " . $e->getMessage());
        }
    }

    /**
     * Transform a hex-encoded item's data field to JSON
     *
     * @param array $item The blockchain item with hex-encoded data
     * @return array Item with decoded data
     */
    private function transformItemData(array $item): array
    {
        if (!isset($item['data'])) {
            return $item;
        }
        
        try {
            $hexData = $item['data'];
            $binData = hex2bin($hexData);
            
            if ($binData === false) {
                Log::warning('Failed to convert hex to binary: ' . $hexData);
                $item['data'] = [];
                return $item;
            }
            
            $jsonData = json_decode($binData, true);
            
            if ($jsonData === null && json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to decode JSON: ' . json_last_error_msg());
                $item['data'] = [];
                return $item;
            }
            
            $item['data'] = $jsonData;
        } catch (Exception $e) {
            Log::warning('Error processing blockchain item data: ' . $e->getMessage());
            $item['data'] = [];
        }
        
        return $item;
    }

    /**
     * Logs successful blockchain operations
     *
     * @param string $message Success message to log
     * @param array $context Additional context for logging
     * @return void
     */
    private function logSuccess(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
    
    /**
     * Tests the connection to the MultiChain node
     *
     * Attempts to retrieve blockchain information to validate connectivity
     * and returns details about the chain and node if successful.
     *
     * @return array Connection status and chain details if successful, error information if not
     */
    public function testConnection(): array
    {
        try {
            $info = $this->client->getinfo();

            if (! $this->client->success()) {
                throw new Exception(
                    sprintf(
                        'Connection failed - Error %d: %s',
                        $this->client->errorcode(),
                        $this->client->errormessage()
                    )
                );
            }

            return [
                'success' => true,
                'chain' => $info['chainname'],
                'version' => $info['version'],
                'protocol' => $info['protocol'],
                'node_address' => $info['nodeaddress'],
            ];

        } catch (Exception $e) {
            Log::error('MultiChain Connection Test Failed', [
                'host' => config('multichain.rpc.host'),
                'port' => config('multichain.rpc.port'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $this->client->errorcode(),
                'error_message' => $this->client->errormessage(),
            ];
        }
    }

    /**
     * Retrieves information about the blockchain
     *
     * @return array Blockchain information including version, protocol, and node details
     */
    public function getInfo(): array
    {
        return $this->executeBlockchainCommand('get blockchain info', function() {
            return $this->client->getinfo();
        });
    }

    /**
     * Publishes a single item to a blockchain stream
     *
     * @param string $fromAddress The blockchain address publishing the item
     * @param string $stream The stream to publish to
     * @param string $key The key to publish under
     * @param array $data The data to publish (will be JSON encoded)
     * @throws Exception If publishing fails
     * @return string Transaction ID of the published item
     */
    public function publishFrom(string $fromAddress, string $stream, string $key, array $data): string
    {
        $txid = $this->executeBlockchainCommand('publish to blockchain', function() use ($fromAddress, $stream, $key, $data) {
            return $this->client->publishfrom(
                $fromAddress, 
                $stream, 
                $key, 
                bin2hex(json_encode($data))
            );
        }, [
            'key' => $key,
            'from_address' => $fromAddress,
            'stream' => $stream
        ]);
        
        $this->logSuccess('Blockchain transaction created', [
            'txid' => $txid,
            'stream' => $stream,
            'key' => $key,
            'from_address' => $fromAddress,
        ]);
        
        return $txid;
    }

    /**
     * Publishes multiple items to a blockchain stream in a single transaction
     *
     * More efficient than multiple single publishes when adding related items.
     * Each item must include a key and data to be published.
     *
     * @param string $fromAddress The blockchain address publishing the items
     * @param string $stream The stream to publish to
     * @param array $items Array of items, each with 'key' and 'data' fields
     * @throws Exception If publishing fails
     * @return string Transaction ID of the published items
     */
    public function publishMultiFrom(string $fromAddress, string $stream, array $items): string
    {
        $txid = $this->executeBlockchainCommand('publish multiple items to blockchain', function() use ($fromAddress, $stream, $items) {
            $formattedItems = array_map(function ($item) {
                return [
                    'key' => $item['key'],
                    'data' => bin2hex(json_encode($item['data'])),
                ];
            }, $items);

            return $this->client->publishmultifrom(
                $fromAddress,
                $stream,
                $formattedItems
            );
        }, [
            'from_address' => $fromAddress,
            'stream' => $stream
        ]);
        
        $this->logSuccess('Blockchain multi-transaction created', [
            'txid' => $txid,
            'stream' => $stream,
            'item_count' => count($items),
            'from_address' => $fromAddress,
        ]);
        
        return $txid;
    }

    /**
     * List items from a blockchain stream
     * 
     * @param StreamQueryOptions $options Query parameters including verbosity and pagination
     * @return array The retrieved stream items
     */
    public function listStreamItems(StreamQueryOptions $options): array
    {
        if (!$options->isVerbose()) {
            // Special case for non-verbose mode - no need for data transformation
            return $this->executeBlockchainCommand('list stream items', function() use ($options) {
                $items = $this->client->liststreamitems(...$options->toStreamItemsParams());
                return $items ?? []; // Return empty array if null
            }, $options->getLogContext());
        }
        
        return $this->retrieveStreamItems(
            'liststreamitems',
            'list stream items',
            $options->toStreamItemsParams(),
            $options->getLogContext()
        );
    }

    /**
     * List items from a blockchain stream filtered by key
     * 
     * @param StreamQueryOptions $options Query parameters including the key to filter by
     * @return array The filtered stream items
     */
    public function listStreamKeyItems(StreamQueryOptions $options): array
    {
        return $this->retrieveStreamItems(
            'liststreamkeyitems',
            'list stream key items',
            $options->toStreamKeyItemsParams(),
            $options->getLogContext()
        );
    }

    /**
     * List items from a blockchain stream filtered by publisher address
     * 
     * @param StreamQueryOptions $options Query parameters including the publisher address
     * @return array The filtered stream items
     */
    public function listStreamPublisherItems(StreamQueryOptions $options): array
    {
        return $this->retrieveStreamItems(
            'liststreampublisheritems',
            'list stream publisher items',
            $options->toStreamPublisherItemsParams(),
            $options->getLogContext()
        );
    }

    /**
     * Retrieves and processes items from a blockchain stream
     *
     * Common implementation for different stream query methods.
     * Handles data transformation and error cases.
     *
     * @param string $streamMethod The client method to call
     * @param string $operation Description for logging
     * @param array $params Parameters for the stream method
     * @param array $logContext Additional context for logging
     * @return array The processed stream items
     */
    private function retrieveStreamItems(string $streamMethod, string $operation, array $params, array $logContext): array
    {
        $items = $this->executeBlockchainCommand($operation, function() use ($streamMethod, $params) {
            return $this->client->$streamMethod(...$params);
        }, $logContext);
        
        if ($items === null) {
            Log::warning("$streamMethod returned null");
            return [];
        }
        
        return array_map([$this, 'transformItemData'], $items);
    }

    /**
     * @deprecated Use listStreamItems with StreamQueryOptions
     */
    public function listStreamItemsWithParams(string $stream, bool $verbose = true, int $count = 10, int $start = -1, ?string $localOrdering = null): array
    {
        $options = new StreamQueryOptions($stream, $verbose, $count, $start, $localOrdering);
        return $this->listStreamItems($options);
    }

    /**
     * @deprecated Use listStreamKeyItems with StreamQueryOptions
     */
    public function listStreamKeyItemsWithParams(string $stream, string $key, int $count = 1000, bool $verbose = false, bool $localOrdering = false): array
    {
        $options = StreamQueryOptions::forKey($stream, $key, $verbose, $count, $localOrdering);
        return $this->listStreamKeyItems($options);
    }

    /**
     * @deprecated Use listStreamPublisherItems with StreamQueryOptions
     */
    public function listStreamPublisherItemsWithParams(string $stream, string $address, bool $verbose = false, int $count = 1000, bool $localOrdering = false): array
    {
        $options = StreamQueryOptions::forPublisher($stream, $address, $verbose, $count, $localOrdering);
        return $this->listStreamPublisherItems($options);
    }
}

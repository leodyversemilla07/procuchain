<?php

namespace App\Services;

use App\Libraries\MultichainClient;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MultichainConnectionService
 *
 * Manages blockchain connection, health checks, and retry logic for MultiChain RPC operations.
 * This service provides core infrastructure for all MultiChain operations including connection
 * management, error handling, and automatic retry mechanisms.
 */
class MultichainConnectionService
{
    protected MultichainClient $mc;

    private int $maxRetries;

    private int $retryDelay = 2;

    private int $timeout;

    /**
     * Initialize MultiChain connection with context-aware configuration
     *
     * Console commands use longer timeouts and more retries for reliability,
     * while web requests use shorter timeouts to stay within PHP-FPM limits.
     */
    public function __construct()
    {
        // Context-aware configuration based on execution environment
        $isConsole = app()->runningInConsole();

        $this->maxRetries = $isConsole
            ? (int) config('multichain.max_retries', 3)
            : (int) config('multichain.web_max_retries', 1);

        $this->timeout = $isConsole
            ? (int) config('multichain.connection_timeout', 30)
            : (int) config('multichain.web_connection_timeout', 3);

        $this->initializeClient();
    }

    /**
     * Initialize the MultiChain RPC client with configuration
     *
     * @throws Exception If configuration is invalid
     */
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
        $this->mc->setoption('use_curl', true);
        $this->mc->setTimeout($this->timeout);
    }

    /**
     * Get the configured blockchain host
     *
     * @return string The blockchain RPC host
     */
    public function getHost(): string
    {
        return config('multichain.rpc.host');
    }

    /**
     * Execute a MultiChain RPC request with automatic retry logic
     *
     * Handles connection errors by retrying with exponential backoff. Automatically
     * reinitializes the client if connection issues are detected. Logs all errors
     * for debugging and monitoring.
     *
     * @param  callable  $operation  The RPC operation to execute
     * @return mixed The result from the MultiChain RPC call
     *
     * @throws Exception If all retry attempts fail
     */
    public function handleRequest(callable $operation): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
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
                    $this->initializeClient();
                }
            }
        }

        Log::error('MultiChain connection failed after all attempts', [
            'attempts' => $attempts,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new Exception('MultiChain connection failed');
    }

    /**
     * Determine if an error message is related to connection issues
     *
     * @param  string  $message  The error message to check
     * @return bool True if the error is connection-related
     */
    private function isConnectionError(string $message): bool
    {
        $message = strtolower($message);

        $connectionErrors = [
            'connection refused',
            'connection timed out',
            'could not connect',
            'failed to connect',
            'network is unreachable',
            'no route to host',
            'operation timed out',
            'connection reset',
            'couldn\'t connect',
            'connection error',
        ];

        foreach ($connectionErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get general blockchain information
     *
     * @return mixed Blockchain info including version, chainname, protocol, etc.
     *
     * @throws Exception If request fails
     */
    public function getInfo(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getInfo());
    }

    /**
     * Get blockchain configuration parameters
     *
     * @return mixed Blockchain parameters
     *
     * @throws Exception If request fails
     */
    public function getBlockchainParams(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getBlockchainParams());
    }

    /**
     * Get runtime parameters
     *
     * @return mixed Runtime parameters
     *
     * @throws Exception If request fails
     */
    public function getRuntimeParams(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getRuntimeParams());
    }

    /**
     * Set a runtime parameter
     *
     * @param  string  $param  The parameter name to set
     * @param  mixed  $value  The value to set
     * @return mixed The result of the operation
     *
     * @throws Exception If request fails
     */
    public function setRuntimeParam(string $param, mixed $value): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->setRuntimeParam($param, $value));
    }

    /**
     * Get blockchain initialization status
     *
     * @return mixed Initialization status
     *
     * @throws Exception If request fails
     */
    public function getInitStatus(): mixed
    {
        return $this->handleRequest(fn (): mixed => $this->mc->getInitStatus());
    }

    /**
     * Wait for a stream to become available after creation
     *
     * Polls the blockchain for stream availability with configurable retries.
     * Useful after stream creation to ensure the stream is fully initialized
     * before attempting to publish or subscribe.
     *
     * @param  string  $streamName  The stream name to check
     * @param  callable  $getStreamInfo  Callback to get stream info
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @return bool True if stream becomes available, false otherwise
     */
    public function waitForStreamAvailability(string $streamName, callable $getStreamInfo, int $maxRetries = 3): bool
    {
        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                $streamInfo = $getStreamInfo($streamName);
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
     * Get the MultichainClient instance
     *
     * Allows other services to make RPC calls through the configured client.
     *
     * @return MultichainClient The configured MultiChain RPC client
     */
    public function getClient(): MultichainClient
    {
        return $this->mc;
    }
}

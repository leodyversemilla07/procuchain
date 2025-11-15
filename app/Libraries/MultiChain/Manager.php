<?php

namespace App\Libraries\MultiChain;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MultiChain Manager
 *
 * Laravel wrapper providing connection management, retry logic, and error handling.
 *
 * All MultiChain RPC methods are available via magic __call():
 * - $manager->getinfo()
 * - $manager->liststreamitems('stream1', true, 100)
 * - $manager->publish('stream1', 'key1', ['json' => $data])
 */
final class Manager
{
    private Client $client;

    private int $maxRetries;

    private int $retryDelay;

    private int $timeout;

    public function __construct()
    {
        $isConsole = app()->runningInConsole();

        $this->maxRetries = $isConsole
            ? (int) config('multichain.max_retries', 3)
            : (int) config('multichain.web_max_retries', 1);

        $this->retryDelay = (int) config('multichain.retry_delay', 2);

        $this->timeout = $isConsole
            ? (int) config('multichain.connection_timeout', 30)
            : (int) config('multichain.web_connection_timeout', 3);

        $this->initializeClient($this->timeout);
    }

    private function initializeClient(int $timeout): void
    {
        $this->client = new Client(
            config('multichain.rpc.host'),
            config('multichain.rpc.port'),
            config('multichain.rpc.username'),
            config('multichain.rpc.password'),
            config('multichain.use_ssl', false)
        );

        $this->client->setoption('chain_name', config('multichain.chain_name'));
        $this->client->setoption('verify_ssl', config('multichain.verify_ssl', true));
        $this->client->setoption('use_curl', true);
        $this->client->setTimeout($timeout);
    }

    /**
     * Magic method - forwards all RPC calls to the client with retry logic
     *
     * Examples:
     * $manager->getinfo()
     * $manager->liststreamitems('stream1', true, 100)
     * $manager->publish('stream1', 'key1', ['json' => $data])
     */
    public function __call(string $method, array $params): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $result = $this->client->$method(...$params);

                if (! $this->client->success()) {
                    $error = $this->client->errormessage();
                    $code = $this->client->errorcode();

                    if ($this->isConnectionError($error)) {
                        throw new Exception("Connection error: {$error}", $code);
                    }

                    Log::error('MultiChain RPC Error', [
                        'method' => $method,
                        'error' => $error,
                        'code' => $code,
                    ]);

                    throw new Exception("MultiChain Error: {$error}", $code);
                }

                return $result;
            } catch (Exception $e) {
                $lastException = $e;

                if (! $this->isConnectionError($e->getMessage())) {
                    throw $e;
                }

                $attempts++;
                if ($attempts < $this->maxRetries) {
                    Log::warning("Connection attempt {$attempts} failed. Retrying in {$this->retryDelay}s...", [
                        'method' => $method,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($this->retryDelay);

                    // Use consistent timeout across retries (Issue #7 fix)
                    $this->initializeClient($this->timeout);
                }
            }
        }

        Log::error('MultiChain connection failed after all attempts', [
            'method' => $method,
            'attempts' => $attempts,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new Exception('MultiChain connection failed');
    }

    private function isConnectionError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'connection refused')
            || str_contains($message, 'connection timed out')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'network is unreachable')
            || str_contains($message, 'no route to host')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection error')
            || str_contains($message, 'unable to connect');
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getHost(): string
    {
        return config('multichain.rpc.host');
    }
}

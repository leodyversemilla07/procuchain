<?php

namespace App\Services;

use App\Libraries\MultiChain\Client;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MultiChain RPC wrapper with automatic failover and retry logic.
 */
class BlockchainRpcClient
{
    private Client $client;

    private int $maxRetries;

    private int $retryDelay;

    private int $timeout;

    /** @var string The node ID currently serving as the active RPC endpoint */
    private string $activeNodeId;

    /** @var int Timestamp when the active node was last verified healthy */
    private int $activeNodeVerifiedAt = 0;

    /** @var int Seconds before re-checking if a demoted primary node has recovered */
    private int $primaryRecheckInterval;

    /** @var bool Whether we've failed over away from the primary node */
    private bool $failedOver = false;

    /** @var bool Whether the primary node was detected as intentionally purged (-703 on stream read).
     *           When true, tryPromotePrimaryBack stops retrying until resetByResync() is called. */
    private bool $primaryPurged = false;

    public function __construct()
    {
        $isConsole = app()->runningInConsole();

        $this->maxRetries = $isConsole
            ? (int) config('multichain.max_retries', 3)
            : (int) config('multichain.web_max_retries', 1);

        $this->retryDelay = $isConsole
            ? (int) config('multichain.retry_delay', 2)
            : 1;

        $this->timeout = $isConsole
            ? (int) config('multichain.connection_timeout', 30)
            : (int) config('multichain.web_connection_timeout', 3);

        $this->primaryRecheckInterval = (int) (config('multichain.primary_recheck_interval') ?? 60);

        $this->activeNodeId = 'primary';
        $this->initializeClient($this->timeout);
    }

    private function initializeClient(int $timeout, ?string $host = null, ?int $port = null): void
    {
        $this->client = new Client(
            $host ?? config('multichain.rpc.host'),
            $port ?? config('multichain.rpc.port'),
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
     * Get the list of fallback nodes from config, excluding the active (failed) node.
     *
     * @return array<int, array{id: string, name: string, private_ip: string, rpc_port: int}>
     */
    private function getFallbackNodes(): array
    {
        $nodes = config('multichain.nodes', []);

        // Get the active node's IP to exclude it from fallback candidates
        $activeIp = $this->client ? $this->getActiveHost() : config('multichain.rpc.host');
        $activePort = $this->client ? $this->getActivePort() : config('multichain.rpc.port');

        return collect($nodes)
            ->filter(function ($node) use ($activeIp, $activePort) {
                $nodeIp = $node['private_ip'] ?? '';
                $nodePort = $node['rpc_port'] ?? 6834;

                // Skip nodes with same IP:port as the active (failed) connection
                if ($nodeIp === $activeIp && $nodePort === $activePort) {
                    return false;
                }

                return ! empty($nodeIp);
            })
            ->values()
            ->all();
    }

    /**
     * Attempt to find a working peer node and switch to it.
     * Returns true if a working fallback was found, false otherwise.
     */
    private function failoverToPeer(): bool
    {
        $fallbackNodes = $this->getFallbackNodes();

        if (empty($fallbackNodes)) {
            Log::warning('MultiChain failover: no fallback nodes configured');

            return false;
        }

        foreach ($fallbackNodes as $node) {
            $nodeId = $node['id'] ?? 'unknown';
            $nodeIp = $node['private_ip'];
            $nodePort = $node['rpc_port'] ?? 6834;

            Log::info("MultiChain failover: trying node '{$nodeId}' at {$nodeIp}:{$nodePort}");

            try {
                $testClient = new Client(
                    $nodeIp,
                    $nodePort,
                    config('multichain.rpc.username'),
                    config('multichain.rpc.password'),
                    config('multichain.use_ssl', false)
                );
                $testClient->setoption('chain_name', config('multichain.chain_name'));
                $testClient->setoption('verify_ssl', config('multichain.verify_ssl', true));
                $testClient->setoption('use_curl', true);
                $testClient->setTimeout($this->timeout);

                // Verify the node is alive and subscribed
                $testClient->getinfo();

                if (! $testClient->success()) {
                    Log::warning("MultiChain failover: node '{$nodeId}' getinfo failed — skipping");

                    continue;
                }

                // Verify the node can read streams (not purged/unsubscribed)
                // Use the procurement.status stream (actual stream from Stream)
                $testClient->liststreamitems(
                    'procurement.status',
                    false,
                    1,
                    0,
                    false
                );

                if (! $testClient->success()) {
                    $errCode = $testClient->errorcode();
                    Log::warning("MultiChain failover: node '{$nodeId}' liststreamitems failed (code {$errCode}) — skipping");

                    // RPC -703 = not subscribed, meaning this node is also purged
                    // RPC -708 = stream not found (may need rescan)
                    continue;
                }

                // This node works — switch to it
                $this->client = $testClient;
                $this->activeNodeId = $nodeId;
                $this->activeNodeVerifiedAt = time();
                $this->failedOver = true;

                Log::info("MultiChain failover: switched to node '{$nodeId}' at {$nodeIp}:{$nodePort}");

                return true;
            } catch (Exception $e) {
                Log::warning("MultiChain failover: node '{$nodeId}' exception — {$e->getMessage()}");

                continue;
            }
        }

        Log::error('MultiChain failover: all fallback nodes exhausted — no working peer found');

        return false;
    }

    /**
     * Check if the primary node has recovered and switch back if it has.
     * Only checks once per primaryRecheckInterval to avoid hammering a dead node.
     */
    private function tryPromotePrimaryBack(): void
    {
        if (! $this->failedOver) {
            return;
        }

        // If the primary was intentionally purged, stop retrying until a
        // resync explicitly clears this flag via resetByResync().
        if ($this->primaryPurged) {
            return;
        }

        // Throttle: don't recheck more often than the configured interval
        if ((time() - $this->activeNodeVerifiedAt) < $this->primaryRecheckInterval) {
            return;
        }

        $primaryHost = config('multichain.rpc.host');
        $primaryPort = config('multichain.rpc.port');

        try {
            $testClient = new Client(
                $primaryHost,
                $primaryPort,
                config('multichain.rpc.username'),
                config('multichain.rpc.password'),
                config('multichain.use_ssl', false)
            );
            $testClient->setoption('chain_name', config('multichain.chain_name'));
            $testClient->setoption('verify_ssl', config('multichain.verify_ssl', true));
            $testClient->setoption('use_curl', true);
            $testClient->setTimeout($this->timeout);

            $testClient->getinfo();

            if (! $testClient->success()) {
                $this->activeNodeVerifiedAt = time();

                return;
            }

            // Primary is alive — verify it can read streams
            // Use the procurement.status stream (actual stream from Stream)
            $testClient->liststreamitems(
                'procurement.status',
                false,
                1,
                0,
                false
            );

            if (! $testClient->success()) {
                $errCode = $testClient->errorcode();

                // RPC -703 = not subscribed — primary was intentionally purged.
                // Stop retrying to avoid hammering a purged node forever.
                if ($errCode === -703) {
                    $this->primaryPurged = true;
                    $this->activeNodeVerifiedAt = time();

                    Log::info('MultiChain failover: primary node detected as purged (RPC -703) — stopping primary recheck until resync');

                    return;
                }

                // Other error — primary is alive but stream has issues, keep retrying
                $this->activeNodeVerifiedAt = time();

                return;
            }

            // Primary has recovered — switch back
            $this->client = $testClient;
            $this->activeNodeId = 'primary';
            $this->activeNodeVerifiedAt = time();
            $this->failedOver = false;

            Log::info('MultiChain failover: primary node recovered — switched back');
        } catch (Exception $e) {
            $this->activeNodeVerifiedAt = time();

            Log::debug("MultiChain failover: primary recheck failed — {$e->getMessage()}");
        }
    }

    /**
     * Check if an RPC error indicates the node is purged/unsubscribed
     * and should trigger failover.
     */
    private function isFailoverEligibleError(int $code, string $message): bool
    {
        // RPC -703 = not subscribed (node was purged)
        if ($code === -703) {
            return true;
        }

        // RPC -701 = invalid parameter (stream not found on this node)
        if ($code === -701) {
            return true;
        }

        // Connection-level errors — the node might be down entirely
        return $this->isConnectionError($message);
    }

    /**
     * Magic method - forwards all RPC calls to the client with retry + failover logic
     *
     * Examples:
     * $BlockchainRpcClient->getinfo()
     * $BlockchainRpcClient->liststreamitems('stream1', true, 100)
     * $BlockchainRpcClient->publish('stream1', 'key1', ['json' => $data])
     */
    public function __call(string $method, array $params): mixed
    {
        // If we're on a fallback node, opportunistically check if primary recovered
        $this->tryPromotePrimaryBack();

        $attempts = 0;
        $lastException = null;
        $hasAttemptedFailover = false;

        while ($attempts < $this->maxRetries) {
            try {
                $result = $this->client->$method(...$params);

                if (! $this->client->success()) {
                    $error = $this->client->errormessage();
                    $code = $this->client->errorcode();

                    if ($this->isConnectionError($error)) {
                        throw new Exception("Connection error: {$error}", $code);
                    }

                    // If the error is failover-eligible (purged/unsubscribed),
                    // try switching to a peer node instead of failing immediately
                    if (! $hasAttemptedFailover && $this->isFailoverEligibleError($code, $error)) {
                        Log::warning("MultiChain RPC failover-eligible error on '{$method}'", [
                            'method' => $method,
                            'error' => $error,
                            'code' => $code,
                            'active_node' => $this->activeNodeId,
                        ]);

                        if ($this->failoverToPeer()) {
                            $hasAttemptedFailover = true;

                            // Retry the call on the new node (don't count as an attempt)
                            continue;
                        }
                    }

                    Log::error('MultiChain RPC Error', [
                        'method' => $method,
                        'error' => $error,
                        'code' => $code,
                        'active_node' => $this->activeNodeId,
                    ]);

                    throw new Exception("MultiChain Error: {$error}", $code);
                }

                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();

                // If connection error and we haven't tried failover yet, attempt it
                if (! $hasAttemptedFailover && $this->isConnectionError($errorMessage)) {
                    if ($this->failoverToPeer()) {
                        $hasAttemptedFailover = true;

                        continue;
                    }
                }

                if (! $this->isConnectionError($errorMessage)) {
                    throw $e;
                }

                $attempts++;
                if ($attempts < $this->maxRetries) {
                    Log::warning("Connection attempt {$attempts} failed. Retrying in {$this->retryDelay}s...", [
                        'method' => $method,
                        'error' => $errorMessage,
                        'active_node' => $this->activeNodeId,
                    ]);
                    sleep($this->retryDelay);

                    // Use consistent timeout across retries
                    $this->initializeClient($this->timeout);
                }
            }
        }

        Log::error('MultiChain connection failed after all attempts', [
            'method' => $method,
            'attempts' => $attempts,
            'last_error' => $lastException?->getMessage(),
            'active_node' => $this->activeNodeId,
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

    /**
     * Get the active node ID (for monitoring/debugging).
     * 'primary' = the original configured RPC node, otherwise the peer node ID.
     */
    public function getActiveNodeId(): string
    {
        return $this->activeNodeId;
    }

    /**
     * Whether the BlockchainRpcClient has failed over to a peer node.
     */
    public function isFailedOver(): bool
    {
        return $this->failedOver;
    }

    /**
     * Whether the primary node was detected as intentionally purged.
     * When true, tryPromotePrimaryBack stops retrying until resetByResync().
     */
    public function isPrimaryPurged(): bool
    {
        return $this->primaryPurged;
    }

    /**
     * Clear the primaryPurged flag after a resync operation.
     * This re-enables tryPromotePrimaryBack so the BlockchainRpcClient can
     * detect when the primary has recovered and switch back.
     */
    public function resetByResync(): void
    {
        $this->primaryPurged = false;
        $this->activeNodeVerifiedAt = 0;
        $this->failedOver = false;

        Log::info('MultiChain failover: primary purge flag cleared by resync — primary recheck re-enabled');
    }

    /**
     * Get the host of the currently active client connection.
     */
    private function getActiveHost(): string
    {
        // The Client class stores host privately, so we reconstruct from config
        if ($this->activeNodeId === 'primary') {
            return config('multichain.rpc.host');
        }

        $nodes = config('multichain.nodes', []);
        $activeNode = collect($nodes)->first(fn ($n) => ($n['id'] ?? '') === $this->activeNodeId);

        return $activeNode['private_ip'] ?? config('multichain.rpc.host');
    }

    /**
     * Get the port of the currently active client connection.
     */
    private function getActivePort(): int
    {
        if ($this->activeNodeId === 'primary') {
            return config('multichain.rpc.port');
        }

        $nodes = config('multichain.nodes', []);
        $activeNode = collect($nodes)->first(fn ($n) => ($n['id'] ?? '') === $this->activeNodeId);

        return $activeNode['rpc_port'] ?? config('multichain.rpc.port');
    }
}

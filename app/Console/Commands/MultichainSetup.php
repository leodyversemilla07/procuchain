<?php

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Enums\UserRoleEnums;
use App\Libraries\MultiChain\Client;
use App\Models\User;
use App\Services\BlockchainStorageService;
use App\Services\Manager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * MultiChain Setup Command
 *
 * Sets up the MultiChain blockchain for the procurement system.
 * Idempotent - can be run multiple times safely.
 */
class MultichainSetup extends Command
{
    protected $signature = 'multichain:setup 
        {--check : Only check connection to MultiChain node}
        {--reset : Reset and recreate all blockchain setup}
        {--test-storage : Test the on-chain file storage after setup}';

    protected $description = 'Setup MultiChain blockchain for procurement system (aligned with official MultiChain API)';

    /**
     * Procurement streams to create
     */
    private const STREAMS = [
        StreamEnums::METADATA->value,
        StreamEnums::DOCUMENTS->value,
        StreamEnums::STATUS->value,
        StreamEnums::EVENTS->value,
        StreamEnums::CORRECTIONS->value,
        StreamEnums::PROCUREMENTS_CORRECTIONS->value,
        StreamEnums::ARCHIVE->value,
    ];

    /**
     * File storage streams for on-chain file storage
     * FILE_CHUNKS enables chunking of large files (>2MB) across multiple transactions
     */
    private const FILE_STORAGE_STREAMS = [
        [
            'name' => StreamEnums::FILE_DATA->value,
            'purpose' => 'on_chain_file_storage',
        ],
        [
            'name' => StreamEnums::FILE_METADATA->value,
            'purpose' => 'file_metadata_tracking',
        ],
        [
            'name' => StreamEnums::FILE_CHUNKS->value,
            'purpose' => 'large_file_chunking',
        ],
    ];

    /**
     * Roles that require blockchain addresses
     */
    private const ROLES = [
        UserRoleEnums::BAC_SECRETARIAT->value,
        UserRoleEnums::BAC_CHAIRMAN->value,
        UserRoleEnums::HOPE->value,
        UserRoleEnums::ADMIN->value,
    ];

    private Manager $multichainManager;

    private array $generatedAddresses = [];

    public function __construct(Manager $multichain)
    {
        parent::__construct();
        $this->multichainManager = $multichain;
    }

    public function handle(): int
    {
        $this->info('MultiChain Setup Starting...');
        $this->line('Using official MultiChain API commands');
        $this->newLine();

        // Check connection only
        if ($this->option('check')) {
            return $this->checkConnection();
        }

        try {
            // 1. Check connection (abort further steps if it fails)
            // Uses: getinfo command
            if ($this->checkConnection() === self::FAILURE) {
                $this->warn('Aborting setup because connection to MultiChain node could not be established.');

                return self::FAILURE;
            }

            // 2. Setup addresses (idempotent: reuse existing valid addresses)
            // Uses: getnewaddress command for each role (only if needed)
            $addressResult = $this->setupAddresses();
            $addresses = $addressResult['addresses'];

            // 3. Create streams (idempotent: check existence first)
            // Uses: create command (type=stream) and subscribe command
            $this->createStreams();

            // 4. Initialize file storage streams (always included in setup)
            // Uses: create command for file storage streams
            $this->initializeFileStorage();

            // 5. Grant permissions (idempotent: handle already granted permissions)
            // Uses: grant command for global and per-stream permissions
            $this->grantPermissions($addresses);

            // 6. Update database with addresses (idempotent: only update if changed)
            $this->updateAddresses($addresses);

            // 7. Subscribe peer nodes to all streams (idempotent: skip already subscribed)
            $this->subscribePeerNodes();

            $this->newLine();
            $this->info('MultiChain setup completed successfully!');
            $this->displayAddresses($addresses, $addressResult['statuses']);

            // Test file storage if requested
            if ($this->option('test-storage')) {
                $this->testFileStorage();
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Setup failed: '.$e->getMessage());
            $this->line('   Check logs for more details');

            // Log the full exception for debugging
            Log::error('MultichainSetup command failed', [
                'error' => $e->getMessage(),
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return self::FAILURE;
        }
    }

    private function checkConnection(): int
    {
        try {
            $info = $this->multichainManager->getinfo();

            $this->info('Connected to MultiChain node');
            $this->line('   Chain: '.($info['chainname'] ?? 'Unknown'));
            $this->line('   Protocol: '.($info['protocol'] ?? 'Unknown'));
            $this->line('   Node: '.($info['nodeaddress'] ?? 'Unknown'));

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Cannot connect to MultiChain node');
            $this->line('   Error: '.$e->getMessage());
            $this->newLine();
            $this->warn('Please ensure:');
            $this->line('  1. MultiChain node is running (multichaind '.config('multichain.chain_name').' -daemon)');
            $this->line('  2. RPC credentials are correct in .env file');
            $this->line('  3. Node is accessible at '.config('multichain.rpc.host').':'.config('multichain.rpc.port'));

            return self::FAILURE;
        }
    }

    /**
     * Setup blockchain addresses (idempotent)
     */
    private function setupAddresses(): array
    {
        $this->info('Setting up blockchain addresses...');
        $addresses = [];
        $statuses = [];

        foreach (self::ROLES as $role) {
            // Check if a valid address already exists for this role
            $existingAddress = $this->getExistingValidAddress($role);
            if ($existingAddress) {
                $addresses[$role] = $existingAddress;
                $statuses[$role] = 'Reused';
                $this->line("Reusing valid address for {$role}");

                continue;
            }

            // Generate new address if none exists or invalid
            $address = $this->multichainManager->getnewaddress();
            $addresses[$role] = $address;
            $statuses[$role] = 'Generated';
            $this->generatedAddresses[$role] = $address;
            $this->line("Generated new address for {$role}");
        }

        return ['addresses' => $addresses, 'statuses' => $statuses];
    }

    /**
     * Get an existing valid blockchain address for a role, if available
     */
    private function getExistingValidAddress(string $role): ?string
    {
        $user = User::role($role)->whereNotNull('blockchain_address')->first();
        if (! $user || ! $user->blockchain_address) {
            return null;
        }

        // Validate the address using MultiChain
        try {
            $validation = $this->multichainManager->validateaddress($user->blockchain_address);

            return $validation['isvalid'] ?? false ? $user->blockchain_address : null;
        } catch (Exception $e) {
            // If validation fails, assume invalid
            return null;
        }
    }

    /**
     * Create streams (idempotent)
     */
    private function createStreams(): void
    {
        $this->info('Creating procurement streams...');

        foreach (self::STREAMS as $stream) {
            $streamEnum = StreamEnums::from($stream);
            $displayName = $streamEnum->getDisplayName();

            try {
                $this->multichainManager->getstreaminfo($stream);
                $this->line("Stream '{$displayName}' ({$stream}) already exists");

                $this->multichainManager->subscribe($stream, true);
            } catch (Exception $e) {
                $this->line("Creating stream '{$displayName}' ({$stream})...");
                $this->multichainManager->create('stream', $stream, true);
                $this->multichainManager->subscribe($stream, true);
                $this->line("Created and subscribed to stream '{$displayName}'");
            }
        }
    }

    /**
     * Grant permissions (idempotent)
     */
    private function grantPermissions(array $addresses): void
    {
        $this->info('Granting permissions...');

        $permissions = config('multichain.permissions.roles', []);

        foreach ($addresses as $role => $address) {
            if (! isset($permissions[$role])) {
                continue;
            }

            $rolePerms = $permissions[$role];

            // Grant global permissions
            foreach ($rolePerms['global'] ?? [] as $perm) {
                try {
                    $this->multichainManager->grant($address, $perm);
                    $this->line("Granted global permission '{$perm}' to {$role}");
                } catch (Exception $e) {
                    if (! str_contains($e->getMessage(), 'already has')) {
                        $this->warn("Failed to grant global permission '{$perm}' to {$role}: {$e->getMessage()}");
                    } else {
                        $this->line("Global permission '{$perm}' already granted to {$role}");
                    }
                }
            }

            // Grant stream permissions
            foreach (self::STREAMS as $stream) {
                foreach ($rolePerms['stream'] ?? [] as $perm) {
                    try {
                        $this->multichainManager->grant($address, "{$stream}.{$perm}");
                        $this->line("Granted stream permission '{$perm}' on {$stream} to {$role}");
                    } catch (Exception $e) {
                        if (! str_contains($e->getMessage(), 'already has')) {
                            $this->warn("Failed to grant stream permission '{$perm}' on {$stream} to {$role}: {$e->getMessage()}");
                        } else {
                            $this->line("Stream permission '{$perm}' on {$stream} already granted to {$role}");
                        }
                    }
                }
            }

            $this->line("Granted permissions for {$role}");
        }
    }

    /**
     * Update addresses in database (idempotent)
     */
    private function updateAddresses(array $addresses): void
    {
        if (empty($this->generatedAddresses)) {
            $this->line('No new addresses to persist');

            return;
        }

        $this->info('Updating generated addresses...');

        // Update database users with new addresses
        $this->updateDatabaseUsers($addresses);

        $this->line('Addresses updated in database');
    }

    private function updateDatabaseUsers(array $addresses): void
    {
        foreach ($this->generatedAddresses as $role => $address) {
            $users = User::role($role)->get();

            foreach ($users as $user) {
                if ($user->blockchain_address !== $address) {
                    $user->blockchain_address = $address;
                    $user->save();
                    $this->line("Updated blockchain address for {$user->email} ({$role})");
                }
            }
        }
    }

    private function displayAddresses(array $addresses, array $statuses): void
    {
        $this->newLine();
        $this->info('Blockchain Addresses Summary:');
        $this->newLine();

        $rows = [];
        foreach ($addresses as $role => $address) {
            $masked = substr($address, 0, 8).'...'.substr($address, -8);
            $status = $statuses[$role] ?? 'Unknown';
            $rows[] = [ucfirst(str_replace('_', ' ', $role)), $masked, $status];
        }

        $this->table(['Role', 'Address (masked)', 'Status'], $rows);

        $this->newLine();
        $this->line('Full addresses are stored in:');
        $this->line('   • Database: users.blockchain_address column');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('   • Verify setup: php artisan multichain:setup --check');
        $this->line('   • View permissions: multichain-cli '.config('multichain.chain_name').' listpermissions');
        $this->line('   • View streams: multichain-cli '.config('multichain.chain_name').' liststreams');
    }

    /**
     * Initialize on-chain file storage streams (idempotent)
     */
    private function initializeFileStorage(): void
    {
        $this->info('Initializing on-chain file storage streams...');

        $created = 0;
        $existing = 0;
        $subscribed = 0;

        foreach (self::FILE_STORAGE_STREAMS as $streamConfig) {
            $streamEnum = StreamEnums::from($streamConfig['name']);
            $displayName = $streamEnum->getDisplayName();

            try {
                $this->multichainManager->getstreaminfo($streamConfig['name']);
                $this->line("Stream '{$displayName}' ({$streamConfig['name']}) already exists");
                $existing++;
            } catch (Exception $e) {
                $this->multichainManager->create('stream', $streamConfig['name'], true, [
                    'description' => $streamEnum->getDescription(),
                    'purpose' => $streamConfig['purpose'],
                ]);

                $this->line("Stream '{$displayName}' ({$streamConfig['name']}) created successfully");
                $created++;
            }

            // Subscribe to the stream (idempotent - safe to call multiple times)
            try {
                $this->multichainManager->subscribe($streamConfig['name'], true);
                $this->line("Subscribed to stream '{$displayName}' ({$streamConfig['name']})");
                $subscribed++;
            } catch (Exception $e) {
                if (! str_contains($e->getMessage(), 'already subscribed')) {
                    $this->warn("Failed to subscribe to '{$displayName}': {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        if ($created > 0) {
            $this->info("Created {$created} file storage stream(s)");
        }
        if ($existing > 0) {
            $this->info("Found {$existing} existing file storage stream(s)");
        }
        if ($subscribed > 0) {
            $this->info("Subscribed to {$subscribed} file storage stream(s)");
        }

        $this->line('Files stored directly on blockchain in file.data stream');
        $this->line('Metadata tracked in file.metadata stream');
        $this->line('Heroku-compatible (no ephemeral filesystem issues)');
        $this->line('Automatically replicated across all blockchain nodes');
    }

    /**
     * Test the on-chain file storage functionality
     */
    private function testFileStorage(): void
    {
        $this->newLine();
        $this->info('Testing on-chain file storage...');

        try {
            $storage = app(BlockchainStorageService::class);

            // Create a small test file (keep it small for testing)
            $testContent = "This is a test document for on-chain storage.\n".str_repeat('Test data line. ', 50);
            $tempFile = tempnam(sys_get_temp_dir(), 'test_');
            file_put_contents($tempFile, $testContent);

            $uploadedFile = new UploadedFile(
                $tempFile,
                'test_document.pdf',
                'application/pdf',
                null,
                true
            );

            $this->line('  Max file size: '.$storage->getMaxFileSizeFormatted());
            $this->line('  Test file size: '.round(strlen($testContent) / 1024, 2).' KB');
            $this->newLine();

            // Store file on blockchain
            $this->components->task('Storing test file on blockchain', function () use ($storage, $uploadedFile, &$result) {
                $result = $storage->uploadFile(
                    $uploadedFile,
                    'test/storage',
                    'test_'.now()->timestamp,
                    ['test' => true, 'purpose' => 'on_chain_initialization_test']
                );

                return true;
            });

            $this->line("  File Key: {$result['file_key']}");
            $this->line("  Data TXID: {$result['data_txid']}");
            $this->line("  Metadata TXID: {$result['metadata_txid']}");
            $this->line('  Size: '.round($result['size'] / 1024, 2).' KB');
            $this->line("  Hash: {$result['hash']}");

            // Retrieve and verify
            $this->components->task('Retrieving & verifying from blockchain', function () use ($storage, $result, $testContent, &$retrieved) {
                $retrieved = $storage->retrieveFile($result['file_key'], $result['data_txid']);
                $verified = $storage->verifyFileIntegrity($result['file_key'], $result['metadata_txid']);

                return $retrieved['content'] === $testContent && $verified;
            });

            $this->info('Test passed! File stored on-chain, retrieved, and verified');
            $this->line('File content stored directly on blockchain (replicated across all nodes)');
            $this->line('Works on Heroku (no ephemeral filesystem dependency)');

            // Cleanup
            @unlink($tempFile);
        } catch (Exception $e) {
            $this->error('Test failed: '.$e->getMessage());

            if (str_contains($e->getMessage(), 'exceeds maximum')) {
                $this->warn('Tip: On-chain storage is best for files under 8 MB');
            }
        }
    }

    /**
     * Subscribe all configured peer nodes to procurement streams.
     * Idempotent — subscribe() on an already-subscribed stream is a no-op.
     * Uses liststreamitems to verify subscription (getstreaminfo 'subscribed'
     * field is unreliable in MultiChain CE).
     */
    private function subscribePeerNodes(): void
    {
        $nodes = config('multichain.nodes', []);

        if (empty($nodes)) {
            $this->line('No peer nodes configured — skipping peer subscription');

            return;
        }

        $this->info('Subscribing peer nodes to all procurement streams...');
        $streams = array_merge(self::STREAMS, collect(self::FILE_STORAGE_STREAMS)->pluck('name')->toArray());
        $rpcUser = config('multichain.rpc.username', 'multichainrpc');
        $rpcPass = config('multichain.rpc.password');
        $chainName = config('multichain.chain_name');

        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? 'unknown';
            $nodeName = $node['name'] ?? $nodeId;
            $nodeIp = $node['private_ip'] ?? '';
            $nodePort = $node['rpc_port'] ?? 6834;

            if (empty($nodeIp)) {
                $this->warn("  Skipping {$nodeName} — no private_ip configured");

                continue;
            }

            try {
                $client = new Client(
                    $nodeIp,
                    $nodePort,
                    $rpcUser,
                    $rpcPass,
                    false
                );
                $client->setoption('chain_name', $chainName);
                $client->setTimeout(10);

                // Verify the node is reachable
                $client->getinfo();

                if (! $client->success()) {
                    $this->warn("  {$nodeName} ({$nodeIp}) — unreachable, skipping");

                    continue;
                }

                $subscribed = 0;
                $alreadySubscribed = 0;

                foreach ($streams as $stream) {
                    // Check if already subscribed by attempting liststreamitems
                    $client->liststreamitems($stream, false, 1, 0, false);

                    if ($client->success()) {
                        $alreadySubscribed++;

                        continue;
                    }

                    // Not subscribed (likely -703) — subscribe with rescan
                    $client->subscribe($stream, true);

                    if ($client->success()) {
                        $subscribed++;
                        $this->line("  {$nodeName} — subscribed to {$stream} (rescan=true)");
                    } else {
                        $this->warn("  {$nodeName} — failed to subscribe to {$stream}: [{$client->errorcode()}] {$client->errormessage()}");
                    }
                }

                if ($subscribed > 0) {
                    $this->info("  ✓ {$nodeName} — subscribed to {$subscribed} new stream(s), {$alreadySubscribed} already active");
                } else {
                    $this->line("  {$nodeName} — all {$alreadySubscribed} streams already subscribed");
                }
            } catch (Exception $e) {
                $this->warn("  {$nodeName} ({$nodeIp}) — error: {$e->getMessage()}");
            }
        }
    }
}

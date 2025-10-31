<?php

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;

/**
 * MultiChain Setup Command
 *
 * Sets up the MultiChain blockchain for the procurement system using official MultiChain API commands.
 * This command follows the official MultiChain documentation for all blockchain operations.
 *
 * Official API Reference: https://www.multichain.com/developers/json-rpc-api/
 * Getting Started Guide: https://www.multichain.com/getting-started/
 *
 * Prerequisites (from install_procuchain.sh):
 * 1. MultiChain must be installed (version 2.3.3 or compatible)
 * 2. Blockchain node must be running (multichaind <chain-name> -daemon)
 * 3. RPC credentials must be configured in .env file
 *
 * Key Operations (aligned with official API):
 * - Connection validation using getinfo
 * - Address generation using getnewaddress
 * - Stream creation using create (type=stream)
 * - Permission management using grant
 * - Stream subscription using subscribe
 *
 * This command complements the install_procuchain.sh script which:
 * - Downloads and installs MultiChain binaries
 * - Creates the blockchain using multichain-util create
 * - Starts the daemon using multichaind -daemon
 * - Configures network and RPC settings
 *
 * @see https://www.multichain.com/developers/json-rpc-api/
 * @see scripts/install_procuchain.sh
 */
class MultichainSetup extends Command
{
    protected $signature = 'multichain:setup 
        {--check : Only check connection to MultiChain node}
        {--reset : Reset and recreate all blockchain setup}';

    protected $description = 'Setup MultiChain blockchain for procurement system (aligned with official MultiChain API)';

    /**
     * Streams to create on the blockchain
     * Each stream is created using the official 'create' command with type='stream'
     *
     * @see https://www.multichain.com/developers/data-streams/
     */
    private const STREAMS = [
        StreamEnums::DOCUMENTS->value,
        StreamEnums::STATUS->value,
        StreamEnums::EVENTS->value,
        StreamEnums::CORRECTIONS->value,
    ];

    /**
     * Roles that require blockchain addresses
     * Addresses are generated using the official 'getnewaddress' command
     *
     * @see https://www.multichain.com/developers/json-rpc-api/
     */
    private const ROLES = [
        'bac_secretariat',
        'bac_chairman',
        'hope',
        'admin',
    ];

    private MultichainService $multichainService;

    private array $generatedAddresses = [];

    public function __construct(MultichainService $multichainService)
    {
        parent::__construct();
        $this->multichainService = $multichainService;
    }

    public function handle(): int
    {
        $this->info('🔗 MultiChain Setup Starting...');
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

            // 2. Setup addresses
            // Uses: getnewaddress command for each role
            $addresses = $this->setupAddresses();

            // 3. Create streams
            // Uses: create command (type=stream) and subscribe command
            $this->createStreams();

            // 4. Grant permissions
            // Uses: grant command for global and per-stream permissions
            $this->grantPermissions($addresses);

            // 5. Update .env and database with new addresses
            $this->updateAddresses($addresses);

            $this->newLine();
            $this->info('✅ MultiChain setup completed successfully!');
            $this->displayAddresses($addresses);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Setup failed: '.$e->getMessage());
            $this->line('   Check logs for more details');

            return self::FAILURE;
        }
    }

    private function checkConnection(): int
    {
        try {
            // Official docs: getinfo command returns general information about node and blockchain
            // Returns: chainname, description, protocol, port, nodeaddress, burnaddress, etc.
            // Reference: https://www.multichain.com/developers/json-rpc-api/
            $info = $this->multichainService->getInfo();

            $this->info('✅ Connected to MultiChain node');
            $this->line('   Chain: '.($info['chainname'] ?? 'Unknown'));
            $this->line('   Protocol: '.($info['protocol'] ?? 'Unknown'));
            $this->line('   Node: '.($info['nodeaddress'] ?? 'Unknown'));

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Cannot connect to MultiChain node');
            $this->line('   Error: '.$e->getMessage());
            $this->newLine();
            $this->warn('Please ensure:');
            $this->line('  1. MultiChain node is running (multichaind '.config('multichain.chain_name').' -daemon)');
            $this->line('  2. RPC credentials are correct in .env file');
            $this->line('  3. Node is accessible at '.config('multichain.rpc.host').':'.config('multichain.rpc.port'));

            return self::FAILURE;
        }
    }

    private function setupAddresses(): array
    {
        $this->info('📍 Setting up blockchain addresses...');
        $addresses = [];
        $generatedAddresses = [];

        foreach (self::ROLES as $role) {
            // Try to get from config first
            $address = config("multichain.addresses.{$role}");
            $needsNewAddress = false;

            // If not in config or is a placeholder, generate new one
            if (! $address || str_contains($address, 'default_')) {
                $needsNewAddress = true;
            } else {
                try {
                    $validation = $this->multichainService->validateAddress($address);
                    $isValid = (bool) ($validation['isvalid'] ?? false);
                    $isMine = (bool) ($validation['ismine'] ?? false);

                    if (! $isValid) {
                        $this->line("⚠️ Configured address for {$role} is not valid on this chain");
                        $needsNewAddress = true;
                    } elseif (! $isMine) {
                        $this->line("⚠️ Configured address for {$role} is not controlled by this node");
                        $needsNewAddress = true;
                    } else {
                        $this->line("✓ Using configured address for {$role}");
                    }
                } catch (Exception $e) {
                    $this->line("⚠️ Unable to validate configured address for {$role}: {$e->getMessage()}");
                    $needsNewAddress = true;
                }
            }

            if ($needsNewAddress) {
                $address = $this->multichainService->getNewAddress();
                $generatedAddresses[$role] = $address;
                $this->line("✓ Generated new address for {$role}");
            }

            $addresses[$role] = $address;
        }

        // Store generated addresses for later persistence
        $this->generatedAddresses = $generatedAddresses;

        return $addresses;
    }

    private function createStreams(): void
    {
        $this->info('🏗️ Creating procurement streams...');

        foreach (self::STREAMS as $stream) {
            try {
                // Check if stream exists using official getstreaminfo command
                // Reference: https://www.multichain.com/developers/json-rpc-api/
                $this->multichainService->getStreamInfo($stream);
                $this->line("✓ Stream {$stream} already exists");

                // Ensure we're subscribed to existing stream
                // Official docs: subscribe command with stream name and rescan parameter
                $this->multichainService->subscribe($stream, true);
            } catch (Exception $e) {
                // Stream doesn't exist, create it
                // Official docs: create command with type='stream', name, and open parameter
                // Syntax: create stream name open|restrictions
                $this->line("Creating stream {$stream}...");
                $this->multichainService->createStream($stream, true);
                $this->multichainService->subscribe($stream, true);
                $this->line("✓ Created and subscribed to stream {$stream}");
            }
        }
    }

    private function grantPermissions(array $addresses): void
    {
        $this->info('🔐 Granting permissions...');

        $permissions = config('multichain.permissions.roles', []);

        foreach ($addresses as $role => $address) {
            if (! isset($permissions[$role])) {
                continue;
            }

            $rolePerms = $permissions[$role];

            // Grant global permissions
            // Official docs: grant command - grant addresses permissions
            // Syntax: grant address(es) permissions (native-amount)
            // Reference: https://www.multichain.com/developers/json-rpc-api/
            foreach ($rolePerms['global'] ?? [] as $perm) {
                try {
                    $this->multichainService->grant($address, $perm);
                } catch (Exception $e) {
                    // Permission might already be granted, log but continue
                    if (! str_contains($e->getMessage(), 'already has')) {
                        throw $e;
                    }
                }
            }

            // Grant stream permissions
            // For per-stream permissions, use entity.permission format
            // e.g., stream1.write, stream1.read, stream1.admin
            foreach (self::STREAMS as $stream) {
                foreach ($rolePerms['stream'] ?? [] as $perm) {
                    try {
                        $this->multichainService->grant($address, "{$stream}.{$perm}");
                    } catch (Exception $e) {
                        // Permission might already be granted
                        if (! str_contains($e->getMessage(), 'already has')) {
                            throw $e;
                        }
                    }
                }
            }

            $this->line("✓ Granted permissions for {$role}");
        }
    }

    private function updateAddresses(array $addresses): void
    {
        if (empty($this->generatedAddresses)) {
            $this->line('No new addresses to persist');

            return;
        }

        $this->info('💾 Updating generated addresses...');

        // Update .env file
        $this->updateEnvFile();

        // Update database users
        $this->updateDatabaseUsers($addresses);

        $this->line('✅ Addresses updated in .env and database');
    }

    private function updateEnvFile(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath) || ! is_writable($envPath)) {
            $this->warn('Cannot update .env file - not writable');

            return;
        }

        $envContent = file_get_contents($envPath);

        foreach ($this->generatedAddresses as $role => $address) {
            // Convert role to proper env key format
            $envKey = match ($role) {
                'bac_secretariat' => 'MULTICHAIN_BAC_SECRETARIAT_ADDRESS',
                'bac_chairman' => 'MULTICHAIN_BAC_CHAIRMAN_ADDRESS',
                'hope' => 'MULTICHAIN_HOPE_ADDRESS',
                'admin' => 'MULTICHAIN_ADMIN_ADDRESS',
                default => 'MULTICHAIN_'.strtoupper(str_replace('_', '_', $role)).'_ADDRESS'
            };

            $pattern = '/^'.preg_quote($envKey, '/').'=.*/m';
            $replacement = $envKey.'='.$address;

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= PHP_EOL.$replacement;
            }

            $this->line("Updated {$envKey} in .env");
        }

        file_put_contents($envPath, $envContent);
    }

    private function updateDatabaseUsers(array $addresses): void
    {
        foreach ($addresses as $role => $address) {
            $users = User::where('role', $role)->get();

            foreach ($users as $user) {
                if ($user->blockchain_address !== $address) {
                    $user->blockchain_address = $address;
                    $user->save();
                    $this->line("Updated blockchain address for {$user->email} ({$role})");
                }
            }
        }
    }

    private function displayAddresses(array $addresses): void
    {
        $this->newLine();
        $this->info('📋 Blockchain Addresses Summary:');
        $this->newLine();

        $rows = [];
        foreach ($addresses as $role => $address) {
            $masked = substr($address, 0, 8).'...'.substr($address, -8);
            $status = isset($this->generatedAddresses[$role]) ? '🆕 Generated' : '✓ Existing';
            $rows[] = [ucfirst(str_replace('_', ' ', $role)), $masked, $status];
        }

        $this->table(['Role', 'Address (masked)', 'Status'], $rows);

        $this->newLine();
        $this->line('💡 Full addresses are stored in:');
        $this->line('   • Configuration: config/multichain.php');
        $this->line('   • Environment: .env file');
        $this->line('   • Database: users.blockchain_address column');
        $this->newLine();
        $this->line('📚 Next steps:');
        $this->line('   • Verify setup: php artisan multichain:setup --check');
        $this->line('   • View permissions: multichain-cli '.config('multichain.chain_name').' listpermissions');
        $this->line('   • View streams: multichain-cli '.config('multichain.chain_name').' liststreams');
    }
}

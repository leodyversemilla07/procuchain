<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;

class MultichainSetup extends Command
{
    protected $signature = 'multichain:setup 
        {--check : Only check connection to MultiChain node}';

    protected $description = 'Setup MultiChain blockchain for procurement system';

    private const STREAMS = [
        'procurement.documents',
        'procurement.status',
        'procurement.events',
        'procurement.corrections',
    ];

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

        // Check connection only
        if ($this->option('check')) {
            return $this->checkConnection();
        }

        try {
            // 1. Check connection
            $this->checkConnection();

            // 2. Setup addresses
            $addresses = $this->setupAddresses();

            // 3. Create streams
            $this->createStreams();

            // 4. Grant permissions
            $this->grantPermissions($addresses);

            // 5. Update .env and database with new addresses
            $this->updateAddresses($addresses);

            $this->info('✅ MultiChain setup completed successfully!');
            $this->displayAddresses($addresses);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Setup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function checkConnection(): int
    {
        try {
            $info = $this->multichainService->getInfo();
            $this->info('✅ Connected to MultiChain node');
            $this->line('Chain: '.($info['chainname'] ?? 'Unknown'));

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Cannot connect to MultiChain node: '.$e->getMessage());

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

            // If not in config or is a placeholder, generate new one
            if (! $address || str_contains($address, 'default_')) {
                $address = $this->multichainService->getNewAddress();
                $generatedAddresses[$role] = $address;
                $this->line("Generated new address for {$role}");
            } else {
                $this->line("Using configured address for {$role}");
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
                // Check if stream exists
                $this->multichainService->getStreamInfo($stream);
                $this->line("Stream {$stream} already exists");
            } catch (Exception $e) {
                // Stream doesn't exist, create it
                $this->multichainService->createStream($stream, true);
                $this->multichainService->subscribe($stream, true);
                $this->line("Created stream {$stream}");
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
            foreach ($rolePerms['global'] ?? [] as $perm) {
                $this->multichainService->grant($address, $perm);
            }

            // Grant stream permissions
            foreach (self::STREAMS as $stream) {
                foreach ($rolePerms['stream'] ?? [] as $perm) {
                    $this->multichainService->grant($address, "{$stream}.{$perm}");
                }
            }

            $this->line("Granted permissions for {$role}");
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
        $this->info('📋 Blockchain Addresses:');

        $rows = [];
        foreach ($addresses as $role => $address) {
            $masked = substr($address, 0, 6).'...'.substr($address, -6);
            $rows[] = [ucfirst(str_replace('_', ' ', $role)), $masked];
        }

        $this->table(['Role', 'Address (masked)'], $rows);
        $this->line('💡 Full addresses are stored in your configuration');
    }
}

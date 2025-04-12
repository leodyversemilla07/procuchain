<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MultichainSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multichain:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup MultiChain streams, address and permissions';

    private $multichainService;

    public function __construct(MultichainService $multichainService)
    {
        parent::__construct();
        $this->multichainService = $multichainService;
    }

    protected function setupAddresses(): array
    {
        $this->info('<fg=blue>📍 Step 1: Generating Blockchain Addresses...</>');
        $addresses = [
            'BAC_SECRETARIAT_ADDRESS' => $this->createNewAddress(),
            'BAC_CHAIRMAN_ADDRESS' => $this->createNewAddress(),
            'HOPE_ADDRESS' => $this->createNewAddress(),
        ];

        $this->updateEnvFile($addresses);

        return $addresses;
    }

    protected function updateEnvFile(array $addresses): void
    {
        $envContent = file_get_contents(base_path('.env'));
        foreach ($addresses as $key => $address) {
            $envContent = preg_replace("/$key=.*/", "$key=$address", $envContent);
            $this->line("  └─ <fg=green>✓</> $key: <fg=yellow>$address</>");
        }
        file_put_contents(base_path('.env'), $envContent);
    }

    protected function setupStreams(): array
    {
        $this->info('<fg=blue>📍 Step 3: Creating Blockchain Streams...</>');
        $streams = [
            'procurement.documents',
            'procurement.status',
            'procurement.events',
            'procurement.corrections',
        ];

        $streamIds = [];
        $bar = $this->output->createProgressBar(count($streams));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($streams as $stream) {
            $bar->setMessage("Creating $stream...");

            try {
                $streamIds[$stream] = $this->createNewStream($stream);

                // Wait for blockchain to process
                sleep(2);

                // Verify stream exists and is ready
                $maxRetries = 3;
                $retryDelay = 2;

                for ($i = 1; $i <= $maxRetries; $i++) {
                    try {
                        $streamInfo = $this->multichainService->getStreamInfo($stream);
                        if (! empty($streamInfo)) {
                            // Stream exists, attempt subscription
                            $this->multichainService->subscribe($stream, true);
                            $this->line("\n  └─ <fg=green>✓</> Stream <options=bold>$stream</> verified and subscribed");
                            break;
                        }
                    } catch (Exception $e) {
                        if ($i === $maxRetries) {
                            $this->warn("\n  └─ Warning: Could not verify/subscribe to $stream: ".$e->getMessage());
                        } else {
                            sleep($retryDelay);
                        }
                    }
                }

                $this->line("\n  └─ <fg=green>✓</> Stream <options=bold>$stream</> created with ID: <fg=yellow>{$streamIds[$stream]}</>");
                $bar->advance();

            } catch (Exception $e) {
                $this->error("\n  └─ Failed to create/subscribe to stream $stream: ".$e->getMessage());
                if (! $this->confirm('Would you like to continue with the next stream?')) {
                    throw $e;
                }
            }
        }

        $bar->finish();

        return $streamIds;
    }

    protected function setupPermissions(array $addresses, array $streams): void
    {
        $this->info('<fg=blue>📍 Step 4: Setting Up Permissions...</>');

        // Wait for blockchain to stabilize after stream creation
        $this->info('Waiting for blockchain confirmation...');
        sleep(5);

        // First grant admin permissions to BAC_SECRETARIAT_ADDRESS
        $secretariatAddress = $addresses['BAC_SECRETARIAT_ADDRESS'];
        $this->line("\n<fg=yellow>➤ Configuring BAC_SECRETARIAT_ADDRESS (Admin):</>");

        try {
            // Verify address is valid
            $validation = $this->multichainService->validateAddress($secretariatAddress);
            if (! $validation || ! isset($validation['isvalid']) || ! $validation['isvalid']) {
                throw new Exception('Invalid address for BAC_SECRETARIAT_ADDRESS');
            }

            // First grant only admin permission
            $this->multichainService->grant($secretariatAddress, 'admin');
            $this->line('  └─ <fg=green>✓</> Admin permission granted');

            sleep(2); // Wait for admin permission to be confirmed

            // Then grant other global permissions
            $globalPerms = 'send,receive,create,issue,mine,activate';
            $this->multichainService->grant($secretariatAddress, $globalPerms);
            $this->line('  └─ <fg=green>✓</> Global permissions granted');

            // Grant stream permissions one at a time
            foreach ($streams as $stream) {
                try {
                    $streamInfo = $this->multichainService->getStreamInfo($stream);
                    if (! $streamInfo) {
                        throw new Exception("Stream $stream does not exist");
                    }

                    // Grant stream-level permissions directly
                    $this->multichainService->grant($secretariatAddress, "$stream.admin");
                    $this->line("  └─ <fg=green>✓</> Granted $stream.admin permission");
                    sleep(1);

                    $this->multichainService->grant($secretariatAddress, "$stream.write");
                    $this->line("  └─ <fg=green>✓</> Granted $stream.write permission");
                    sleep(1);

                    $this->multichainService->grant($secretariatAddress, "$stream.read");
                    $this->line("  └─ <fg=green>✓</> Granted $stream.read permission");
                    sleep(1);

                } catch (Exception $e) {
                    $this->error("  └─ Failed to grant permissions for stream $stream: ".$e->getMessage());
                    if (! $this->confirm('Would you like to continue with the next stream?')) {
                        throw $e;
                    }
                }
            }

            // Now grant permissions to other roles
            $otherRoles = [
                'BAC_CHAIRMAN_ADDRESS' => [
                    'global' => ['send', 'receive'],
                    'stream' => ['write', 'read'],
                ],
                'HOPE_ADDRESS' => [
                    'global' => ['send', 'receive'],
                    'stream' => ['write', 'read'],
                ],
            ];

            foreach ($otherRoles as $role => $perms) {
                $this->line("\n<fg=yellow>➤ Configuring $role:</>");
                $address = $addresses[$role];

                try {
                    // Verify address is valid
                    $validation = $this->multichainService->validateAddress($address);
                    if (! $validation || ! isset($validation['isvalid']) || ! $validation['isvalid']) {
                        throw new Exception("Invalid address for $role");
                    }

                    // Grant global permissions
                    $globalPerms = implode(',', $perms['global']);
                    $this->multichainService->grant($address, $globalPerms);
                    $this->line('  └─ <fg=green>✓</> Global permissions granted');

                    // Grant stream permissions one at a time
                    foreach ($streams as $stream) {
                        foreach ($perms['stream'] as $perm) {
                            $streamPerm = "$stream.$perm";
                            try {
                                $this->multichainService->grant($address, $streamPerm);
                                $this->line("  └─ <fg=green>✓</> Granted $streamPerm permission");
                                sleep(1);
                            } catch (Exception $e) {
                                $this->error("  └─ Failed to grant $streamPerm permission: ".$e->getMessage());
                                if (! $this->confirm('Would you like to continue?')) {
                                    throw $e;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->error("  └─ Failed to configure $role: ".$e->getMessage());
                    if (! $this->confirm('Would you like to continue with the next role?')) {
                        throw $e;
                    }
                }
            }

        } catch (Exception $e) {
            $this->error('  └─ Failed to configure admin permissions: '.$e->getMessage());
            throw $e;
        }
    }

    protected function displaySummary(): void
    {
        $this->newLine(2);
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║    🎉 MultiChain Setup Complete!     ║');
        $this->info('║        Everything is ready!          ║');
        $this->info('╚══════════════════════════════════════╝');

        $this->table(
            ['Component', 'Status'],
            [
                ['Addresses', '<fg=green>✓ Generated & Synced</>'],
                ['Streams', '<fg=green>✓ Created & Subscribed</>'],
                ['Permissions', '<fg=green>✓ Configured</>'],
            ]
        );
    }

    public function handle()
    {
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║      MultiChain Setup Starting       ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();

        // Step 1: Generate and update addresses
        $addresses = $this->setupAddresses();

        // Step 2: Sync addresses to database
        $this->newLine();
        $this->info('<fg=blue>📍 Step 2: Syncing Addresses to Database...</>');
        $this->syncAddressesToDatabase($addresses);

        // Step 3: Setup streams
        $this->newLine();
        $streamIds = $this->setupStreams();

        // Step 4: Setup permissions
        $this->newLine(2);
        $this->setupPermissions($addresses, array_keys($streamIds));

        // Display final summary
        $this->displaySummary();
    }

    public function createNewStream(string $streamName): string
    {
        try {
            $this->info("  └─ Creating stream: $streamName");

            // Verify blockchain connection
            try {
                $this->multichainService->getBlockchainParams();
                $this->info('  └─ Blockchain connection verified');
            } catch (Exception $e) {
                $this->warn('  └─ Blockchain verification failed: '.$e->getMessage());
                throw $e;
            }

            // Create stream with proper options
            $options = true; // Simple open stream for Community Edition
            $details = ['purpose' => 'procurement'];
            $result = $this->multichainService->createStream($streamName, $options, $details);

            // Handle array result from createStream
            $txid = is_array($result) && isset($result['txid']) ? $txid = $result['txid'] :
                   (is_array($result) && isset($result['status']) && $result['status'] === 'exists' ? 'exists' :
                   (is_string($result) ? $result : 'unknown'));

            $this->info("  └─ Stream creation initiated with status: $txid");

            // In Community Edition, streams are automatically subscribed
            // Just verify the stream exists
            try {
                $this->multichainService->getStreamInfo($streamName);
                $this->info('  └─ Stream verified');
            } catch (Exception $e) {
                $this->warn('  └─ Stream verification failed: '.$e->getMessage());
            }

            // Verify stream creation
            $maxRetries = 30;
            $this->info('  └─ Verifying stream availability...');
            $progress = $this->output->createProgressBar($maxRetries);

            for ($i = 0; $i < $maxRetries; $i++) {
                try {
                    $streamInfo = $this->multichainService->getStreamInfo($streamName);
                    if (! empty($streamInfo)) {
                        $progress->finish();
                        $this->newLine();
                        $this->info('  └─ Stream verified successfully');

                        return $txid;
                    }
                } catch (Exception $e) {
                    // Continue waiting
                }
                sleep(1);
                $progress->advance();
            }

            $progress->finish();
            $this->newLine();
            $this->warn('  └─ Stream created but verification timed out');

            return $txid;

        } catch (Exception $e) {
            Log::error('Stream creation failed', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createNewAddress(): string
    {
        try {
            $this->info('  └─ Attempting to connect to blockchain node...');

            // Generate a new address using MultichainService
            $address = $this->multichainService->getNewAddress();

            if (empty($address)) {
                throw new Exception('MultiChain returned an empty address');
            }

            $this->info('  └─ <fg=green>✓</> Successfully connected to blockchain');
            Log::info('New blockchain address created', ['address' => $address]);

            return (string) $address;

        } catch (Exception $e) {
            Log::error('Failed to create new address', [
                'error' => $e->getMessage(),
            ]);

            $this->error('  └─ Connection failed: '.$e->getMessage());
            $this->info("\nTroubleshooting steps:");
            $this->line(' 1. Check if MultiChain daemon is running');
            $this->line(' 2. Verify network connectivity to '.$this->multichainService->getHost());
            $this->line(' 3. Check firewall settings');
            $this->line(' 4. Verify RPC credentials in .env file');

            throw new Exception('Failed to create new blockchain address: '.$e->getMessage());
        }
    }

    public function grantPermissions(string $address, string $permission): void
    {
        try {
            $this->multichainService->grant($address, $permission);

            Log::info('Permissions granted', [
                'address' => $address,
                'permission' => $permission,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to grant permissions', [
                'address' => $address,
                'permission' => $permission,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to grant permissions: '.$e->getMessage());
        }
    }

    /**
     * Sync blockchain addresses to the user database
     */
    protected function syncAddressesToDatabase(array $addresses): void
    {
        $this->info('Syncing blockchain addresses to user database...');

        // Fix the keys to match those used in handle() method
        $secretariatAddress = $addresses['BAC_SECRETARIAT_ADDRESS'];
        $chairmanAddress = $addresses['BAC_CHAIRMAN_ADDRESS'];
        $hopeAddress = $addresses['HOPE_ADDRESS'];

        // Update users based on role
        $secretariatUpdated = User::where('role', 'bac_secretariat')
            ->update(['blockchain_address' => $secretariatAddress]);

        $chairmanUpdated = User::where('role', 'bac_chairman')
            ->update(['blockchain_address' => $chairmanAddress]);

        $hopeUpdated = User::where('role', 'hope')
            ->update(['blockchain_address' => $hopeAddress]);

        $totalUpdated = $secretariatUpdated + $chairmanUpdated + $hopeUpdated;
        $this->info("Updated blockchain addresses for {$totalUpdated} users in the database");
    }
}

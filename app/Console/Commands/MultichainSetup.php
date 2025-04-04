<?php

namespace App\Console\Commands;

use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\User;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║      MultiChain Setup Starting       ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();

        $this->info('<fg=blue>📍 Step 1: Generating Blockchain Addresses...</>');
        $addresses = [
            'BAC_SECRETARIAT_ADDRESS' => $this->createNewAddress(),
            'BAC_CHAIRMAN_ADDRESS' => $this->createNewAddress(),
            'HOPE_ADDRESS' => $this->createNewAddress(),
        ];

        $envContent = file_get_contents(base_path('.env'));
        foreach ($addresses as $key => $address) {
            $envContent = preg_replace("/$key=.*/", "$key=$address", $envContent);
            $this->line("  └─ <fg=green>✓</> $key: <fg=yellow>$address</>");
        }
        file_put_contents(base_path('.env'), $envContent);

        $this->newLine();
        $this->info('<fg=blue>📍 Step 2: Syncing Addresses to Database...</>');
        $this->syncAddressesToDatabase($addresses);

        $this->newLine();
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
            $streamIds[$stream] = $this->createNewStream($stream);
            $this->line("\n  └─ <fg=green>✓</> Stream <options=bold>$stream</> created with ID: <fg=yellow>{$streamIds[$stream]}</>");
            $bar->advance();
        }
        $bar->finish();

        $this->newLine(2);
        $this->info('<fg=blue>📍 Step 4: Setting Up Permissions...</>');
        $permissions = [
            'BAC_SECRETARIAT_ADDRESS' => ['connect', 'receive', 'send', 'create', 'write', 'read', 'activate', 'admin'],
            'BAC_CHAIRMAN_ADDRESS' => ['connect', 'receive', 'read'],
            'HOPE_ADDRESS' => ['connect', 'receive', 'read'],
        ];

        foreach ($permissions as $role => $perms) {
            $this->line("\n<fg=yellow>➤ Configuring $role:</>");
            $address = $addresses[$role];
            $globalPerms = array_intersect($perms, ['send', 'connect', 'receive', 'create', 'issue', 'mine', 'activate', 'admin']);
            $streamPerms = array_diff($perms, $globalPerms);

            if (!empty($globalPerms)) {
                $this->grantPermissions($address, implode(',', $globalPerms));
                $this->line("  └─ <fg=green>✓</> Global permissions granted");
            }

            foreach ($streams as $stream) {
                foreach ($streamPerms as $perm) {
                    $this->grantPermissions($address, "$stream.$perm");
                }
            }
            $this->line("  └─ <fg=green>✓</> Stream permissions granted");
        }

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
                ['Permissions', '<fg=green>✓ Configured</>']
            ]
        );
    }

    public function createNewStream(string $streamName): string
    {
        try {
            $result = $this->multichainService->client->create('stream', $streamName, true);

            if (!$this->multichainService->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->multichainService->client->errorcode(),
                        $this->multichainService->client->errormessage()
                    )
                );
            }

            Log::info('New stream created', [
                'stream' => $streamName,
                'txid' => $result,
            ]);

            $this->multichainService->client->subscribe($streamName);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to create new stream', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
                'error_code' => $this->multichainService->client->errorcode(),
                'error_message' => $this->multichainService->client->errormessage(),
            ]);
            throw new Exception('Failed to create new stream: ' . $e->getMessage());
        }
    }

    public function createNewAddress(): string
    {
        try {
            $address = $this->multichainService->client->getnewaddress();

            if (!$this->multichainService->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->multichainService->client->errorcode(),
                        $this->multichainService->client->errormessage()
                    )
                );
            }

            Log::info('New blockchain address created', ['address' => $address]);

            return $address;

        } catch (Exception $e) {
            Log::error('Failed to create new address', [
                'error' => $e->getMessage(),
                'error_code' => $this->multichainService->client->errorcode(),
                'error_message' => $this->multichainService->client->errormessage(),
            ]);
            throw new Exception('Failed to create new blockchain address: ' . $e->getMessage());
        }
    }

    public function grantPermissions(string $address, string $permission): void
    {
        try {
            $this->multichainService->client->grant($address, $permission);

            if (!$this->multichainService->client->success()) {
                throw new Exception(
                    sprintf(
                        'Error %d: %s',
                        $this->multichainService->client->errorcode(),
                        $this->multichainService->client->errormessage()
                    )
                );
            }

            Log::info('Permissions granted', [
                'address' => $address,
                'permission' => $permission,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to grant permissions', [
                'address' => $address,
                'permission' => $permission,
                'error' => $e->getMessage(),
                'error_code' => $this->multichainService->client->errorcode(),
                'error_message' => $this->multichainService->client->errormessage(),
            ]);
            throw new Exception('Failed to grant permissions: ' . $e->getMessage());
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

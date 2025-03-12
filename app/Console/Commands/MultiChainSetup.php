<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MultichainService;
use Exception;
use Illuminate\Support\Facades\Log;

class MultiChainSetup extends Command
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
        $addresses = [
            'BAC_SECRETARIAT_ADDRESS' => $this->createNewAddress(),
            'BAC_CHAIRMAN_ADDRESS' => $this->createNewAddress(),
            'HOPE_ADDRESS' => $this->createNewAddress(),
        ];

        $envContent = file_get_contents(base_path('.env'));
        foreach ($addresses as $key => $address) {
            $envContent = preg_replace("/$key=.*/", "$key=$address", $envContent);
            $this->info("$key: $address");
        }
        file_put_contents(base_path('.env'), $envContent);

        $streams = [
            'procurement.documents',
            'procurement.state',
            'procurement.events',
            'procurement.corrections'
        ];

        $streamIds = [];
        foreach ($streams as $stream) {
            $streamIds[$stream] = $this->createNewStream($stream);
            $this->info("Stream $stream: {$streamIds[$stream]}");
        }

        $permissions = [
            'BAC_SECRETARIAT_ADDRESS' => ['connect', 'receive', 'send', 'create', 'write', 'read', 'activate', 'admin'],
            'BAC_CHAIRMAN_ADDRESS' => ['connect', 'receive', 'read'],
            'HOPE_ADDRESS' => ['connect', 'receive', 'read'],
        ];

        foreach ($permissions as $role => $perms) {
            $address = $addresses[$role];
            $globalPerms = array_intersect($perms, ['send', 'connect', 'receive', 'create', 'issue', 'mine', 'activate', 'admin']);
            $streamPerms = array_diff($perms, $globalPerms);

            if (!empty($globalPerms)) {
                $this->grantPermissions($address, implode(',', $globalPerms));
            }

            foreach ($streams as $stream) {
                foreach ($streamPerms as $perm) {
                    $this->grantPermissions($address, "$stream.$perm");
                }
            }
        }

        $this->info('MultiChain setup completed successfully!');
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
}

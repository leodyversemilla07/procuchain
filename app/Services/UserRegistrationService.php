<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Stream;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserRegistrationService
{
    public function __construct(
        private readonly BlockchainRpcClient $blockchainRpc,
        private readonly BlockchainRecordSyncService $syncService,
    ) {}

    public function publishRegistration(User $user, string $registeredBy): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'name' => $user->name,
            'blockchain_address' => $user->blockchain_address,
            'role' => $user->roles->first()?->name ?? 'unknown',
            'registered_by' => $registeredBy,
            'registered_at' => now()->toIso8601String(),
        ];

        $this->publishAndSync($user, $data);
    }

    public function publishAddressChange(User $user, string $oldAddress, string $changedBy): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'name' => $user->name,
            'blockchain_address' => $user->blockchain_address,
            'role' => $user->roles->first()?->name ?? 'unknown',
            'registered_by' => $changedBy,
            'registered_at' => now()->toIso8601String(),
            'previous_address' => $oldAddress,
            'change_type' => 'address_change',
        ];

        $this->publishAndSync($user, $data);
    }

    private function publishAndSync(User $user, array $data): void
    {
        try {
            $txid = $this->blockchainRpc->publish(
                Stream::USER_REGISTRATIONS->value,
                (string) $user->id,
                ['json' => $data]
            );

            Log::info('UserRegistrationService: published to blockchain', [
                'user_id' => $user->id,
                'stream' => Stream::USER_REGISTRATIONS->value,
                'txid' => $txid,
            ]);

            try {
                $this->syncService->syncToMirror(
                    stream: Stream::USER_REGISTRATIONS->value,
                    key: (string) $user->id,
                    txid: $txid ?? '',
                    publisherAddress: $user->blockchain_address ?? '',
                    blocktime: null,
                    data: $data,
                );
            } catch (\Exception $mirrorError) {
                Log::warning('UserRegistrationService: mirror sync failed', [
                    'user_id' => $user->id,
                    'error' => $mirrorError->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('UserRegistrationService: failed to publish to blockchain', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * User Registration Service
 *
 * Publishes user registration receipts and address change events
 * to the blockchain user.registrations stream.
 *
 * Failures are always caught and logged — they MUST never block
 * user creation or updates.
 */
class UserRegistrationService
{
    /**
     * Publish a user registration receipt to the blockchain.
     *
     * Called after a new user is created and assigned a role.
     * Publishes to user.registrations stream and mirrors to procurement_records.
     *
     * @param  User  $user  The newly registered user
     * @param  string  $registeredBy  Who initiated the registration
     */
    public function publishRegistration(User $user, string $registeredBy): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $manager = app(Manager::class);

            $data = [
                'user_id' => $user->id,
                'name' => $user->name,
                'blockchain_address' => $user->blockchain_address,
                'role' => $user->roles->first()?->name ?? 'unknown',
                'registered_by' => $registeredBy,
                'registered_at' => now()->toIso8601String(),
            ];

            $txid = $manager->publish(
                StreamEnums::USER_REGISTRATIONS->value,
                (string) $user->id,
                ['json' => $data]
            );

            Log::info('UserRegistrationService: registration published to blockchain', [
                'user_id' => $user->id,
                'stream' => StreamEnums::USER_REGISTRATIONS->value,
                'txid' => $txid,
            ]);

            // Mirror to procurement_records
            try {
                $syncService = app(BlockchainRecordSyncService::class);
                $syncService->upstream(
                    stream: StreamEnums::USER_REGISTRATIONS->value,
                    key: (string) $user->id,
                    txid: $txid ?? '',
                    publisherAddress: $user->blockchain_address ?? '',
                    blocktime: null,
                    data: $data,
                );
            } catch (\Exception $mirrorError) {
                Log::warning('UserRegistrationService: mirror sync failed for registration', [
                    'user_id' => $user->id,
                    'error' => $mirrorError->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('UserRegistrationService: failed to publish registration to blockchain', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish a blockchain address change event to the blockchain.
     *
     * Called when a user's blockchain_address is updated.
     * Publishes to user.registrations stream with change metadata
     * and mirrors to procurement_records.
     *
     * @param  User  $user  The user whose address changed
     * @param  string  $oldAddress  The previous blockchain address
     * @param  string  $changedBy  Who initiated the address change
     */
    public function publishAddressChange(User $user, string $oldAddress, string $changedBy): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $manager = app(Manager::class);

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

            $txid = $manager->publish(
                StreamEnums::USER_REGISTRATIONS->value,
                (string) $user->id,
                ['json' => $data]
            );

            Log::info('UserRegistrationService: address change published to blockchain', [
                'user_id' => $user->id,
                'stream' => StreamEnums::USER_REGISTRATIONS->value,
                'txid' => $txid,
            ]);

            // Mirror to procurement_records
            try {
                $syncService = app(BlockchainRecordSyncService::class);
                $syncService->upstream(
                    stream: StreamEnums::USER_REGISTRATIONS->value,
                    key: (string) $user->id,
                    txid: $txid ?? '',
                    publisherAddress: $user->blockchain_address ?? '',
                    blocktime: null,
                    data: $data,
                );
            } catch (\Exception $mirrorError) {
                Log::warning('UserRegistrationService: mirror sync failed for address change', [
                    'user_id' => $user->id,
                    'error' => $mirrorError->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('UserRegistrationService: failed to publish address change to blockchain', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

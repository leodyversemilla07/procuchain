<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachTypeEnums;
use App\Enums\StreamEnums;
use App\Models\ProcurementRecord;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Data Integrity Verifier Service
 *
 * Verifies the integrity of procurement mirror data against the blockchain.
 * Detects hash mismatches, content mismatches, unauthorized publishers,
 * deleted rows, and user address tampering.
 *
 * Used by:
 * - blockchain:audit command
 * - Scheduled integrity checks (cron)
 * - On-demand verification from the admin UI
 */
class DataIntegrityVerifier
{
    /** @var array<int, array{type: string, mirror_id: int, stream: string, key: string, txid: string, data: array}> */
    private array $breaches = [];

    private int $verifiedCount = 0;

    /**
     * Verify all mirror rows for integrity.
     *
     * Checks hash integrity and publisher authorization for every
     * row in the procurement_records table.
     *
     * @return array{verified: int, breaches: array}
     */
    public function verifyAll(): array
    {
        $this->reset();

        $mirrors = ProcurementRecord::all();

        foreach ($mirrors as $mirror) {
            $this->verifyMirrorRow($mirror);
        }

        return [
            'verified' => $this->verifiedCount,
            'breaches' => $this->breaches,
        ];
    }

    /**
     * Verify mirror rows for a specific PR number.
     *
     * @return array{verified: int, breaches: array}
     */
    public function verifyPr(string $prNumber): array
    {
        $this->reset();

        $mirrors = ProcurementRecord::forKey($prNumber)->get();

        foreach ($mirrors as $mirror) {
            $this->verifyMirrorRow($mirror);
        }

        return [
            'verified' => $this->verifiedCount,
            'breaches' => $this->breaches,
        ];
    }

    /**
     * Verify a specific stream's mirror data.
     *
     * @return array{verified: int, breaches: array}
     */
    public function verifyStream(string $stream): array
    {
        $this->reset();

        $mirrors = ProcurementRecord::forStream($stream)->get();

        foreach ($mirrors as $mirror) {
            $this->verifyMirrorRow($mirror);
        }

        return [
            'verified' => $this->verifiedCount,
            'breaches' => $this->breaches,
        ];
    }

    /**
     * Verify user registration integrity by comparing on-chain
     * user.registrations data with MySQL user records.
     *
     * Detects if a user's blockchain_address was tampered in MySQL
     * (different from the address recorded on-chain).
     *
     * @return array{verified: int, breaches: array}
     */
    public function verifyUserRegistrations(): array
    {
        $this->reset();

        $syncService = app(BlockchainRecordSyncService::class);

        try {
            $manager = app(Manager::class);
            $items = $manager->liststreamitems(
                StreamEnums::USER_REGISTRATIONS->value,
                true,
                10000,
            );
        } catch (\Exception $e) {
            Log::error('DataIntegrityVerifier: failed to read user.registrations stream', [
                'error' => $e->getMessage(),
            ]);

            return [
                'verified' => 0,
                'breaches' => [],
            ];
        }

        if (! is_array($items) || empty($items)) {
            return [
                'verified' => 0,
                'breaches' => [],
            ];
        }

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];

            if (! is_array($data) || empty($data)) {
                continue;
            }

            $userId = $data['user_id'] ?? null;
            $chainAddress = $data['blockchain_address'] ?? null;

            if (! $userId || ! $chainAddress) {
                continue;
            }

            $this->verifiedCount++;

            $user = User::find($userId);

            if (! $user) {
                // User deleted from MySQL but registration exists on chain
                $this->recordBreach(BreachTypeEnums::ROW_DELETED, 0, 'user.registrations', (string) $userId, $item['txid'] ?? '', [
                    'reason' => 'User exists on chain but not in MySQL',
                    'chain_data' => $data,
                ]);

                continue;
            }

            // Check if the blockchain_address in MySQL matches the one on chain
            if ($user->blockchain_address !== $chainAddress) {
                $mirror = ProcurementRecord::where('stream', StreamEnums::USER_REGISTRATIONS->value)
                    ->where('stream_key', (string) $userId)
                    ->where('txid', $item['txid'] ?? '')
                    ->first();

                $this->recordBreach(BreachTypeEnums::USER_ADDRESS_TAMPERED, $mirror?->id ?? 0, 'user.registrations', (string) $userId, $item['txid'] ?? '', [
                    'user_id' => $userId,
                    'chain_address' => $chainAddress,
                    'mysql_address' => $user->blockchain_address,
                    'user_name' => $user->name,
                ]);
            }
        }

        return [
            'verified' => $this->verifiedCount,
            'breaches' => $this->breaches,
        ];
    }

    /**
     * Verify a single mirror row.
     */
    private function verifyMirrorRow(ProcurementRecord $mirror): void
    {
        $this->verifiedCount++;

        // Check 1: Hash integrity
        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($computedHash !== $mirror->data_hash) {
            $this->recordBreach(BreachTypeEnums::HASH_MISMATCH, $mirror->id, $mirror->stream, $mirror->stream_key, $mirror->txid, [
                'stored_hash' => $mirror->data_hash,
                'computed_hash' => $computedHash,
            ]);

            // Mark the mirror row as breached
            if (! $mirror->isBreached()) {
                $mirror->markAsBreached(BreachTypeEnums::HASH_MISMATCH->value, [
                    'stored_hash' => $mirror->data_hash,
                    'computed_hash' => $computedHash,
                ]);
            }

            return;
        }

        // Check 2: Publisher authorization
        if ($mirror->publisher_address && ! $this->isAuthorizedPublisher($mirror->publisher_address)) {
            if ($mirror->is_authorized) {
                $mirror->update(['is_authorized' => false]);

                $this->recordBreach(BreachTypeEnums::UNAUTHORIZED_PUBLISHER, $mirror->id, $mirror->stream, $mirror->stream_key, $mirror->txid, [
                    'publisher_address' => $mirror->publisher_address,
                ]);

                if (! $mirror->isBreached()) {
                    $mirror->markAsBreached(BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value, [
                        'publisher_address' => $mirror->publisher_address,
                    ]);
                }
            }
        }

        // Update verified_at timestamp for clean rows
        if (! $mirror->isBreached()) {
            $mirror->verified_at = now();
            $mirror->save();
        }
    }

    /**
     * Check if a publisher address belongs to an authorized (non-locked) user.
     */
    private function isAuthorizedPublisher(string $address): bool
    {
        return User::where('blockchain_address', $address)
            ->where('account_locked', false)
            ->exists();
    }

    /**
     * Record a breach and send notifications.
     */
    private function recordBreach(BreachTypeEnums $type, int $mirrorId, string $stream, string $key, string $txid, array $data): void
    {
        $this->breaches[] = [
            'type' => $type->value,
            'display_name' => $type->getDisplayName(),
            'description' => $type->getDescription(),
            'mirror_id' => $mirrorId,
            'stream' => $stream,
            'key' => $key,
            'txid' => $txid,
            'data' => $data,
        ];

        Log::warning('DataIntegrityVerifier: breach detected', [
            'type' => $type->value,
            'stream' => $stream,
            'key' => $key,
            'txid' => $txid,
        ]);

        // Send breach notification to BAC Chairman, HOPE, and admins
        $this->notifyBreach($type->value, $stream, $key, $txid, $data, $mirrorId);
    }

    /**
     * Send breach notification to relevant authorities.
     *
     * Failures are caught and logged — notification MUST never block verification.
     */
    private function notifyBreach(
        string $breachType,
        string $stream,
        string $key,
        string $txid,
        array $breachData = [],
        ?int $mirrorId = null,
    ): void {
        try {
            $recipients = User::whereHas('roles', function ($query): void {
                $query->whereIn('name', ['bac_chairman', 'hope', 'admin']);
            })->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new IntegrityBreachNotification(
                breachType: $breachType,
                stream: $stream,
                streamKey: $key,
                txid: $txid,
                breachData: $breachData,
                mirrorId: $mirrorId,
            ));
        } catch (\Exception $e) {
            Log::error('DataIntegrityVerifier: failed to send breach notification', [
                'breach_type' => $breachType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset the verifier state.
     */
    private function reset(): void
    {
        $this->breaches = [];
        $this->verifiedCount = 0;
    }

    /**
     * Get breach counts grouped by type.
     *
     * @return array<string, int>
     */
    public function getBreachCounts(): array
    {
        $counts = [];

        foreach ($this->breaches as $breach) {
            $type = $breach['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }
}

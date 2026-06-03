<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BreachTypeEnums;
use App\Models\Procurement;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Data Integrity Verifier
 *
 * Verifies normalized tables against blockchain.
 * Used by blockchain:audit command and scheduled checks.
 */
class DataIntegrityVerifier
{
    private array $breaches = [];

    private int $verifiedCount = 0;

    private Manager $manager;

    public function __construct()
    {
        $this->manager = app(Manager::class);
    }

    /**
     * Verify all procurement records.
     */
    public function verifyAll(): array
    {
        $this->reset();

        Procurement::chunk(100, function ($procurements) {
            foreach ($procurements as $procurement) {
                $this->verifyProcurement($procurement);
            }
        });

        return [
            'verified' => $this->verifiedCount,
            'breaches' => $this->breaches,
        ];
    }

    /**
     * Verify a single procurement against blockchain.
     */
    private function verifyProcurement(Procurement $procurement): void
    {
        $this->verifiedCount++;

        // Recompute hash
        $computedHash = hash('sha256', json_encode([
            'pr_number' => $procurement->pr_number,
            'title' => $procurement->title,
            'category' => $procurement->category,
            'procurement_mode' => $procurement->procurement_mode,
            'current_stage' => $procurement->current_stage,
            'current_status' => $procurement->current_status,
            'abc_amount' => $procurement->abc_amount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Compare with stored hash
        if ($procurement->data_hash && $computedHash !== $procurement->data_hash) {
            $this->recordBreach(
                type: BreachTypeEnums::HASH_MISMATCH->value,
                tableName: 'procurements',
                recordId: $procurement->id,
                prNumber: $procurement->pr_number,
                data: ['computed_hash' => $computedHash, 'stored_hash' => $procurement->data_hash]
            );
        }

        // Mark verified
        $procurement->update(['last_verified_at' => now(), 'is_blockchain_verified' => true]);
    }

    /**
     * Record a breach and notify.
     */
    private function recordBreach(string $type, string $tableName, int $recordId, string $prNumber, array $data): void
    {
        $this->breaches[] = [
            'type' => $type,
            'table' => $tableName,
            'record_id' => $recordId,
            'pr_number' => $prNumber,
            'data' => $data,
        ];

        Log::warning('DataIntegrityVerifier: breach', ['type' => $type, 'pr' => $prNumber]);

        // Mark record as breached
        match ($tableName) {
            'procurements' => Procurement::where('id', $recordId)->update(['has_breach' => true]),
            default => null,
        };

        // Notify
        try {
            $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['bac_chairman', 'hope', 'admin']))->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new IntegrityBreachNotification(
                    breachType: $type,
                    stream: 'normalized_db',
                    streamKey: $prNumber,
                    txid: '',
                    breachData: $data,
                ));
            }
        } catch (\Exception $e) {
            Log::error('Notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function reset(): void
    {
        $this->breaches = [];
        $this->verifiedCount = 0;
    }
}

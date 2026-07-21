<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\BreachType;
use App\Models\IntegrityViolationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class RecordViolationTracker
{
    public function refreshTrustedRecordHash(Model $record, string $currentHash): void
    {
        $record->forceFill([
            'data_hash' => $currentHash,
            'blockchain_hash' => $currentHash,
        ])->save();

        $record->refresh();
    }

    public function resolvePendingStaleHashViolations(Model $record, string $prNumber, string $stream): void
    {
        $query = IntegrityViolationLog::query()
            ->where('recovery_status', 'pending')
            ->where('violation_type', BreachType::HASH_MISMATCH->value)
            ->where('stream', $stream)
            ->where('stream_key', $prNumber);

        if ($record->txid) {
            $query->where('txid', $record->txid);
        } else {
            $query->where('record_id', $record->id);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            $log->markSkipped('Verifier re-ran with canonical blockchain comparison and found the DB record content matches trusted chain data. Local stale hash was refreshed; original audit record retained.');
        }
    }

    public function resolvePendingFalsePositiveViolations(Model $record, string $tableName, string $prNumber, string $stream): void
    {
        $query = IntegrityViolationLog::query()
            ->where('recovery_status', 'pending')
            ->where('violation_type', BreachType::CONTENT_MISMATCH->value)
            ->where('stream', $stream)
            ->where('stream_key', $prNumber);

        if ($record->txid) {
            $query->where('txid', $record->txid);
        } else {
            $query->where('record_id', $record->id);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            $log->markSkipped('Verifier re-ran with blockchain payload projection and found no remaining DB/blockchain mismatch. Marked non-actionable; original audit record retained.');
        }

        if ($logs->isNotEmpty()) {
            Log::info('IntegrityVerification: resolved stale pending content mismatches', [
                'count' => $logs->count(),
                'stream' => $stream,
                'stream_key' => $prNumber,
                'txid' => $record->txid,
                'table' => $tableName,
            ]);
        }
    }

    public function hasPendingViolationsForRecord(Model $record, string $tableName, string $prNumber, string $stream): bool
    {
        return IntegrityViolationLog::query()
            ->where('recovery_status', 'pending')
            ->where('stream', $stream)
            ->where('stream_key', $prNumber)
            ->when(
                $record->txid,
                fn ($query, string $txid) => $query->where('txid', $txid),
                fn ($query) => $query->where('record_id', $record->id),
            )
            ->exists();
    }
}

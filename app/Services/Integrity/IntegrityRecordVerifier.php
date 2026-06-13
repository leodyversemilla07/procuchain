<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\BreachType;
use App\Enums\Stream;
use App\Models\File;
use App\Models\IntegrityViolationLog;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use App\Services\BlockchainRpcClient;
use App\Services\Concerns\HashesData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class IntegrityRecordVerifier
{
    use HashesData;

    private const TABLE_STREAM_MAP = [
        'procurements' => Stream::METADATA,
        'procurement_stages' => Stream::STATUS,
        'procurement_documents' => Stream::DOCUMENTS,
        'procurement_events' => Stream::EVENTS,
        'procurement_corrections' => Stream::CORRECTIONS,
        'procurement_archives' => Stream::ARCHIVE,
        'procurement_metadata_corrections' => Stream::PROCUREMENTS_CORRECTIONS,
        'Files' => Stream::FILE_METADATA,
    ];

    public function __construct(
        private readonly IntegrityViolationRecorder $recorder,
        private readonly IntegrityVerificationRunState $state,
        private readonly BlockchainVerificationIndex $blockchainIndex,
        private readonly BlockchainPayloadProjector $payloadProjector,
        private readonly IntegrityComparator $comparator,
        private readonly BlockchainRpcClient $rpcClient,
    ) {}

    public function verifyAllTables(): void
    {
        $this->verifyTableModel('procurements', Stream::METADATA, Procurement::class);
        $this->verifyTableModel('procurement_stages', Stream::STATUS, ProcurementStage::class);
        $this->verifyTableModel('procurement_documents', Stream::DOCUMENTS, ProcurementDocument::class);
        $this->verifyTableModel('procurement_events', Stream::EVENTS, ProcurementEvent::class);
        $this->verifyTableModel('procurement_corrections', Stream::CORRECTIONS, ProcurementCorrection::class);
        $this->verifyTableModel('procurement_archives', Stream::ARCHIVE, ProcurementArchive::class);
        $this->verifyTableModel('procurement_metadata_corrections', Stream::PROCUREMENTS_CORRECTIONS, ProcurementMetadataCorrection::class);
        $this->verifyTableModel('Files', Stream::FILE_METADATA, File::class);
    }

    public function verifyPrRecords(Procurement $procurement): void
    {
        $this->verifyRecord($procurement, 'procurements', Stream::METADATA);

        foreach ($procurement->stages as $stage) {
            $this->verifyRecord($stage, 'procurement_stages', Stream::STATUS);
        }
        foreach ($procurement->documents as $doc) {
            $this->verifyRecord($doc, 'procurement_documents', Stream::DOCUMENTS);
        }
        foreach ($procurement->events as $event) {
            $this->verifyRecord($event, 'procurement_events', Stream::EVENTS);
        }
        foreach ($procurement->corrections as $correction) {
            $this->verifyRecord($correction, 'procurement_corrections', Stream::CORRECTIONS);
        }
        foreach (ProcurementArchive::where('procurement_id', $procurement->id)->get() as $archive) {
            $this->verifyRecord($archive, 'procurement_archives', Stream::ARCHIVE);
        }
        foreach (ProcurementMetadataCorrection::where('procurement_id', $procurement->id)->get() as $metadataCorrection) {
            $this->verifyRecord($metadataCorrection, 'procurement_metadata_corrections', Stream::PROCUREMENTS_CORRECTIONS);
        }
        foreach (File::where('pr_number', $procurement->pr_number)->get() as $file) {
            $this->verifyRecord($file, 'Files', Stream::FILE_METADATA);
        }
    }

    public function verifyRecord(Model $record, string $tableName, Stream $stream): void
    {
        $this->state->verifiedCount++;

        $prNumber = $record->pr_number ?? $record->procurement?->pr_number ?? null;
        if (! $prNumber) {
            return;
        }

        $recordHadViolation = false;

        if ($this->recordReferencesSupersededChainRevision($record, $tableName, $prNumber, $stream)) {
            return;
        }

        // Layer 1: Hash check
        $currentHash = $this->computeRecordHash($record, $tableName);
        $storedHash = $record->data_hash;

        if ($storedHash && $currentHash !== $storedHash) {
            $chainData = $this->fetchChainData($stream->value, $prNumber, $record->txid);
            $fieldDiffs = $chainData
                ? $this->computeFieldDifferences(
                    $this->recordToArray($record, $tableName),
                    $this->payloadProjector->projectForTable($chainData, $tableName, $record),
                )
                : null;

            if ($chainData && empty($fieldDiffs)) {
                $this->refreshTrustedRecordHash($record, $currentHash);
                $this->resolvePendingStaleHashViolations($record, $prNumber, $stream->value);
            } else {
                $this->recorder->record(
                    type: BreachType::HASH_MISMATCH->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'Record was modified in database since last sync',
                    currentHash: $currentHash,
                    storedHash: $storedHash,
                    fieldDiffs: ! empty($fieldDiffs) ? $fieldDiffs : null,
                    chainData: $chainData,
                );

                return;
            }
        }

        // Layer 2: Content comparison
        $chainData = $this->fetchChainData($stream->value, $prNumber, $record->txid);
        if ($chainData) {
            $fieldDiffs = $this->computeFieldDifferences(
                $this->recordToArray($record, $tableName),
                $this->payloadProjector->projectForTable($chainData, $tableName, $record),
            );

            if (! empty($fieldDiffs)) {
                $recordHadViolation = true;

                $this->recorder->record(
                    type: BreachType::CONTENT_MISMATCH->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'Record differs from blockchain',
                    fieldDiffs: $fieldDiffs,
                    chainData: $chainData,
                );
            }

            // Layer 3: User address tampering
            $dbUserAddress = $record->user_address ?? null;
            $chainUserAddress = $chainData['user_address'] ?? null;
            if ($dbUserAddress && $chainUserAddress && $dbUserAddress !== $chainUserAddress) {
                $recordHadViolation = true;

                $this->recorder->record(
                    type: BreachType::USER_ADDRESS_TAMPERED->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: 'User address was modified from original blockchain record',
                    fieldDiffs: [['field' => 'user_address', 'old_value' => $chainUserAddress, 'new_value' => $dbUserAddress]],
                    chainData: $chainData,
                );
            }
        }

        if ($tableName === 'procurements' && $this->procurementStatusDiffersFromLatestStatusStream($record, $prNumber)) {
            $recordHadViolation = true;
        }

        // Layer 4: Publisher authorization
        if ($this->state->verifyPublishers && $record->txid) {
            $recordHadViolation = $this->checkUnauthorizedPublisher($record, $tableName, $prNumber, $stream->value) || $recordHadViolation;
        }

        if (! $recordHadViolation) {
            $this->resolvePendingFalsePositiveViolations($record, $tableName, $prNumber, $stream->value);

            $record->update([
                'last_verified_at' => now(),
                'is_blockchain_verified' => true,
                'has_breach' => $this->hasPendingViolationsForRecord($record, $tableName, $prNumber, $stream->value),
            ]);
        }
    }

    public function computeRecordHash(Model $record, string $tableName): string
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            'procurement_corrections' => ProcurementCorrection::getHashableFields(),
            'procurement_archives' => ProcurementArchive::getHashableFields(),
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::getHashableFields(),
            'Files' => File::getHashableFields(),
            default => [],
        };

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->normaliseHashValue($record->{$field} ?? null);
        }

        return $this->computeHash($data);
    }

    public function recordToArray(Model $record, string $tableName): array
    {
        $fields = match ($tableName) {
            'procurements' => Procurement::getHashableFields(),
            'procurement_stages' => ProcurementStage::getHashableFields(),
            'procurement_documents' => ProcurementDocument::getHashableFields(),
            'procurement_events' => ProcurementEvent::getHashableFields(),
            'procurement_corrections' => ProcurementCorrection::getHashableFields(),
            'procurement_archives' => ProcurementArchive::getHashableFields(),
            'procurement_metadata_corrections' => ProcurementMetadataCorrection::getHashableFields(),
            'Files' => File::getHashableFields(),
            default => [],
        };

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->normaliseHashValue($record->{$field} ?? null);
        }

        return $data;
    }

    public function computeFieldDifferences(array $dbData, array $chainData): array
    {
        return $this->comparator->diff($dbData, $chainData);
    }

    // ----------------------------------------------------------------
    // PRIVATE HELPERS
    // ----------------------------------------------------------------

    private function verifyTableModel(string $tableName, Stream $stream, string $modelClass): void
    {
        $modelClass::chunk(100, function ($records) use ($tableName, $stream) {
            foreach ($records as $record) {
                $this->verifyRecord($record, $tableName, $stream);
            }
        });
    }

    private function checkUnauthorizedPublisher(Model $record, string $tableName, string $prNumber, string $stream): bool
    {
        try {
            $txid = $record->txid;
            if (! $txid) {
                return false;
            }

            $txData = $this->rpcClient->getrawtransaction($txid, 1);
            if (! $txData || ! is_array($txData)) {
                return false;
            }

            $publishers = [];
            $dataSection = $txData['data'] ?? [];

            if (is_array($dataSection)) {
                foreach ($dataSection as $keyData) {
                    if (is_array($keyData) && isset($keyData['publishers'])) {
                        $publishers = array_merge($publishers, (array) $keyData['publishers']);
                    }
                }
            }

            $publishers = array_unique($publishers);

            if (empty($publishers)) {
                return false;
            }

            $authorizedAddress = $record->user_address ?? null;

            if (! $authorizedAddress) {
                return false;
            }

            foreach ($publishers as $publisher) {
                if ($publisher !== $authorizedAddress) {
                    $this->recorder->record(
                        type: BreachType::UNAUTHORIZED_PUBLISHER->value,
                        tableName: $tableName,
                        record: $record,
                        prNumber: $prNumber,
                        message: "Unauthorized publisher {$publisher} - expected {$authorizedAddress}",
                        chainData: ['publishers' => $publishers, 'authorized_address' => $authorizedAddress],
                    );

                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::debug('IntegrityVerification: publisher check failed', [
                'txid' => $record->txid,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function procurementStatusDiffersFromLatestStatusStream(Model $record, string $prNumber): bool
    {
        $diffs = $this->procurementStatusDifferencesFromLatestStatusStream($record, $prNumber);

        if (empty($diffs)) {
            return false;
        }

        $latestStatus = $this->latestStatusItemForPrNumber($prNumber);
        $chainData = is_array($latestStatus) ? ($latestStatus['data']['json'] ?? null) : null;

        $this->recorder->record(
            type: BreachType::CONTENT_MISMATCH->value,
            tableName: 'procurements',
            record: $record,
            prNumber: $prNumber,
            message: 'Procurement mirror status differs from the latest procurement.status blockchain entry',
            fieldDiffs: $diffs,
            chainData: is_array($chainData) ? $chainData : null,
        );

        return true;
    }

    public function procurementStatusDifferencesFromLatestStatusStream(Model $record, string $prNumber): array
    {
        $latestStatus = $this->latestStatusItemForPrNumber($prNumber);

        if (! $latestStatus) {
            return [];
        }

        $chainData = $latestStatus['data']['json'] ?? null;
        if (! is_array($chainData)) {
            return [];
        }

        $diffs = [];
        $chainStatus = $chainData['current_status'] ?? null;
        $chainStage = $chainData['stage'] ?? null;

        if ($chainStatus !== null && ! $this->valuesMatch($chainStatus, $record->current_status ?? null)) {
            $diffs[] = ['field' => 'current_status', 'old_value' => $chainStatus, 'new_value' => $record->current_status ?? null];
        }

        if ($chainStage !== null && ! $this->valuesMatch($chainStage, $record->current_stage ?? null)) {
            $diffs[] = ['field' => 'current_stage', 'old_value' => $chainStage, 'new_value' => $record->current_stage ?? null];
        }

        return $diffs;
    }

    private function latestStatusItemForPrNumber(string $prNumber): ?array
    {
        $items = $this->blockchainIndex->itemsByPrNumber(Stream::STATUS, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }

    private function valuesMatch(mixed $chainValue, mixed $dbValue): bool
    {
        if ($chainValue === $dbValue) {
            return true;
        }

        if (is_numeric($chainValue) && is_numeric($dbValue)) {
            return (float) $chainValue === (float) $dbValue;
        }

        return (string) $chainValue === (string) $dbValue;
    }

    private function recordReferencesSupersededChainRevision(Model $record, string $tableName, string $prNumber, Stream $stream): bool
    {
        if ($this->recordReferencesLatestChainRevision($record, $tableName, $prNumber, $stream)) {
            return false;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        $latestTxid = $latest['txid'] ?? null;
        $recordTxid = $record->txid ?? null;
        $fieldDiffs = [[
            'field' => 'txid',
            'old_value' => $latestTxid,
            'new_value' => $recordTxid,
        ]];

        $recordTxidChainData = is_string($recordTxid) && $recordTxid !== ''
            ? $this->blockchainIndex->jsonByTxid($stream, $recordTxid)
            : null;
        $recordTxidPrNumber = $recordTxidChainData['pr_number'] ?? null;
        if (is_string($recordTxidPrNumber) && $recordTxidPrNumber !== '' && $recordTxidPrNumber !== $prNumber) {
            $fieldDiffs[] = [
                'field' => 'pr_number',
                'old_value' => $recordTxidPrNumber,
                'new_value' => $prNumber,
            ];
        }

        $this->recorder->record(
            type: BreachType::CONTENT_MISMATCH->value,
            tableName: $tableName,
            record: $record,
            prNumber: $prNumber,
            message: 'Procurement mirror references an older blockchain revision instead of the latest trusted chain record',
            fieldDiffs: $fieldDiffs,
            chainData: is_array($latest['data']['json'] ?? null) ? $latest['data']['json'] : null,
        );

        return true;
    }

    private function recordReferencesLatestChainRevision(Model $record, string $tableName, string $prNumber, Stream $stream): bool
    {
        if ($tableName !== 'procurements') {
            return true;
        }

        $latest = $this->latestChainItemForRecord($tableName, $prNumber, $stream);
        if (! is_array($latest)) {
            return true;
        }

        $latestTxid = $latest['txid'] ?? null;

        return ! is_string($latestTxid) || $latestTxid === '' || $latestTxid === ($record->txid ?? null);
    }

    private function latestChainItemForRecord(string $tableName, string $prNumber, Stream $stream): ?array
    {
        if ($tableName !== 'procurements') {
            return null;
        }

        $items = $this->blockchainIndex->itemsByPrNumber($stream, $prNumber);
        $latest = end($items);

        return is_array($latest) ? $latest : null;
    }

    private function refreshTrustedRecordHash(Model $record, string $currentHash): void
    {
        $record->forceFill([
            'data_hash' => $currentHash,
            'blockchain_hash' => $currentHash,
        ])->save();

        $record->refresh();
    }

    private function resolvePendingStaleHashViolations(Model $record, string $prNumber, string $stream): void
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

    private function resolvePendingFalsePositiveViolations(Model $record, string $tableName, string $prNumber, string $stream): void
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

    private function hasPendingViolationsForRecord(Model $record, string $tableName, string $prNumber, string $stream): bool
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

    private function normaliseHashValue(mixed $value): mixed
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function fetchChainData(string $stream, string $prNumber, ?string $txid): ?array
    {
        try {
            if ($this->blockchainIndex->isLoaded($stream)) {
                if ($txid) {
                    $json = $this->blockchainIndex->jsonByTxid($stream, $txid);
                    if ($json) {
                        return $json;
                    }
                } else {
                    $json = $this->blockchainIndex->latestJsonByPrNumber($stream, $prNumber);
                    if ($json) {
                        return $json;
                    }
                }
            }

            $items = $this->rpcClient->liststreamkeyitems($stream, $prNumber);
            $items = is_array($items) ? $items : [];

            if ($txid) {
                foreach ($items as $item) {
                    if (($item['txid'] ?? null) === $txid) {
                        $json = $item['data']['json'] ?? null;

                        return is_array($json) ? $json : null;
                    }
                }

                $this->blockchainIndex->loadStream($stream);

                return $this->blockchainIndex->jsonByTxid($stream, $txid);
            }

            if (empty($items)) {
                return null;
            }

            $latest = end($items);
            $json = is_array($latest) ? ($latest['data']['json'] ?? null) : null;

            return is_array($json) ? $json : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

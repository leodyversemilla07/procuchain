<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Enums\BreachType;
use App\Enums\Stream;
use App\Models\File;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use Illuminate\Database\Eloquent\Model;

class IntegrityRecordVerifier
{
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
        private readonly RecordHashService $hashService,
        private readonly ChainRecordComparator $chainComparator,
        private readonly PublisherAuthorizationChecker $publisherChecker,
        private readonly RecordViolationTracker $violationTracker,
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

        // Superseded chain revision check
        $superseded = $this->chainComparator->supersededRevisionViolationData($record, $tableName, $prNumber, $stream);
        if ($superseded !== null) {
            $this->recorder->record(
                type: BreachType::CONTENT_MISMATCH->value,
                tableName: $tableName,
                record: $record,
                prNumber: $prNumber,
                message: 'Procurement mirror references an older blockchain revision instead of the latest trusted chain record',
                fieldDiffs: $superseded['fieldDiffs'],
                chainData: $superseded['chainData'],
            );

            return;
        }

        // Layer 1: Hash check
        $currentHash = $this->hashService->computeRecordHash($record, $tableName);
        $storedHash = $record->data_hash;

        if ($storedHash && $currentHash !== $storedHash) {
            $chainData = $this->chainComparator->fetchChainData($stream->value, $prNumber, $record->txid);
            $fieldDiffs = $chainData
                ? $this->chainComparator->computeProjectedDifferences(
                    $this->hashService->recordToArray($record, $tableName),
                    $record,
                    $tableName,
                    $chainData,
                )
                : null;

            if ($chainData && empty($fieldDiffs)) {
                $this->violationTracker->refreshTrustedRecordHash($record, $currentHash);
                $this->violationTracker->resolvePendingStaleHashViolations($record, $prNumber, $stream->value);
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
        $chainData = $this->chainComparator->fetchChainData($stream->value, $prNumber, $record->txid);
        if ($chainData) {
            $fieldDiffs = $this->chainComparator->computeProjectedDifferences(
                $this->hashService->recordToArray($record, $tableName),
                $record,
                $tableName,
                $chainData,
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

        if ($tableName === 'procurements' && $this->recordProcurementStatusDifferences($record, $prNumber)) {
            $recordHadViolation = true;
        }

        // Layer 4: Publisher authorization
        if ($this->state->verifyPublishers && $record->txid) {
            $recordHadViolation = $this->publisherChecker->check($record, $tableName, $prNumber, $stream->value) || $recordHadViolation;
        }

        if (! $recordHadViolation) {
            $this->violationTracker->resolvePendingFalsePositiveViolations($record, $tableName, $prNumber, $stream->value);

            $record->update([
                'last_verified_at' => now(),
                'is_blockchain_verified' => true,
                'has_breach' => $this->violationTracker->hasPendingViolationsForRecord($record, $tableName, $prNumber, $stream->value),
            ]);
        }
    }

    private function recordProcurementStatusDifferences(Model $record, string $prNumber): bool
    {
        $diffs = $this->chainComparator->procurementStatusDifferencesFromLatestStatusStream($record, $prNumber);

        if (empty($diffs)) {
            return false;
        }

        $latestStatus = $this->chainComparator->fetchChainData(Stream::STATUS->value, $prNumber, null);
        $chainData = is_array($latestStatus) ? $latestStatus : null;

        $this->recorder->record(
            type: BreachType::CONTENT_MISMATCH->value,
            tableName: 'procurements',
            record: $record,
            prNumber: $prNumber,
            message: 'Procurement mirror status differs from the latest procurement.status blockchain entry',
            fieldDiffs: $diffs,
            chainData: $chainData,
        );

        return true;
    }

    private function verifyTableModel(string $tableName, Stream $stream, string $modelClass): void
    {
        $modelClass::chunk(100, function ($records) use ($tableName, $stream) {
            foreach ($records as $record) {
                $this->verifyRecord($record, $tableName, $stream);
            }
        });
    }
}

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
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Detects records on blockchain missing from DB (deleted) and records in DB missing from blockchain (injected).
 */
class DeletedRecordDetector
{
    public function __construct(
        private readonly IntegrityViolationRecorder $recorder,
        private readonly IntegrityVerificationRunState $state,
        private readonly BlockchainVerificationIndex $blockchainIndex,
        private readonly BlockchainRpcClient $rpcClient,
        private readonly IntegrityComparator $comparator,
        private readonly BlockchainPayloadProjector $payloadProjector,
    ) {}

    public function detect(): void
    {
        $this->detectMissingFromDb();
        $this->detectUnauthorizedInDb();
    }

    private function detectMissingFromDb(): void
    {
        $this->detectMissingProcurementsFromDb();
        $this->detectMissingStreamRowsFromDb(Stream::STATUS, ProcurementStage::class, 'procurement_stages');
        $this->detectMissingStreamRowsFromDb(Stream::DOCUMENTS, ProcurementDocument::class, 'procurement_documents');
        $this->detectMissingStreamRowsFromDb(Stream::EVENTS, ProcurementEvent::class, 'procurement_events', skipSystemPr: true);
        $this->detectMissingStreamRowsFromDb(Stream::CORRECTIONS, ProcurementCorrection::class, 'procurement_corrections');
        $this->detectMissingStreamRowsFromDb(Stream::ARCHIVE, ProcurementArchive::class, 'procurement_archives');
        $this->detectMissingStreamRowsFromDb(Stream::PROCUREMENTS_CORRECTIONS, ProcurementMetadataCorrection::class, 'procurement_metadata_corrections');
        $this->detectMissingFileMetadataRowsFromDb();
    }

    private function detectMissingProcurementsFromDb(): void
    {
        try {
            $reportedPrNumbers = [];

            foreach ($this->blockchainIndex->items(Stream::METADATA) as $item) {
                $data = $item['data']['json'] ?? [];
                $prNumber = $data['pr_number'] ?? null;

                if (! $prNumber || isset($reportedPrNumbers[$prNumber]) || Procurement::where('pr_number', $prNumber)->exists()) {
                    continue;
                }

                $reportedPrNumbers[$prNumber] = true;

                $this->recorder->record(
                    type: BreachType::ROW_DELETED->value,
                    tableName: 'procurements',
                    record: null,
                    prNumber: $prNumber,
                    message: 'PR exists on blockchain but not in database',
                    chainData: $data,
                    chainTxid: $item['txid'] ?? null,
                );
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted procurements', ['error' => $e->getMessage()]);
        }
    }

    private function detectMissingStreamRowsFromDb(Stream $stream, string $modelClass, string $tableName, bool $skipSystemPr = false): void
    {
        try {
            foreach ($this->blockchainIndex->items($stream) as $item) {
                $txid = $item['txid'] ?? null;
                $data = $item['data']['json'] ?? [];
                $prNumber = $data['pr_number'] ?? data_get($item, 'keys.0');

                if (! $txid || ! $prNumber || ($skipSystemPr && $prNumber === 'system')) {
                    continue;
                }

                if (! $modelClass::where('txid', $txid)->exists()) {
                    $this->recorder->record(
                        type: BreachType::ROW_DELETED->value,
                        tableName: $tableName,
                        record: null,
                        prNumber: $prNumber,
                        message: "{$stream->value} item exists on blockchain but not in database",
                        chainData: $data,
                        chainTxid: $txid,
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted stream rows', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function detectMissingFileMetadataRowsFromDb(): void
    {
        try {
            foreach ($this->blockchainIndex->items(Stream::FILE_METADATA) as $item) {
                $txid = $item['txid'] ?? null;
                $data = $item['data']['json'] ?? [];
                $fileKey = $data['file_key'] ?? null;

                if (! $txid || ! $fileKey) {
                    continue;
                }

                if (! File::where('txid', $txid)->exists()) {
                    $this->recorder->record(
                        type: BreachType::ROW_DELETED->value,
                        tableName: 'Files',
                        record: null,
                        prNumber: (string) ($data['pr_number'] ?? $fileKey),
                        message: 'File.metadata item exists on blockchain but not in database',
                        chainData: $data,
                        chainTxid: $txid,
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check deleted File metadata rows', ['error' => $e->getMessage()]);
        }
    }

    private function detectUnauthorizedInDb(): void
    {
        try {
            $blockchainPrNumbers = $this->getBlockchainPrNumbers();

            if (empty($blockchainPrNumbers)) {
                return;
            }

            $fakeRecords = Procurement::whereNotIn('pr_number', $blockchainPrNumbers)->get();

            foreach ($fakeRecords as $record) {
                $fieldDiffs = null;
                $chainData = null;

                if ($record->txid) {
                    $chainData = $this->blockchainIndex->jsonByTxid(Stream::METADATA->value, $record->txid);

                    if (! $chainData) {
                        try {
                            $txData = $this->rpcClient->getrawtransaction($record->txid, 1);
                            if (is_array($txData)) {
                                foreach ($txData['data'] ?? [] as $dataItem) {
                                    if (isset($dataItem['json']) && is_array($dataItem['json'])) {
                                        $chainData = $dataItem['json'];
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception) {
                            // Non-fatal
                        }
                    }

                    if ($chainData) {
                        $fieldDiffs = $this->comparator->diff(
                            $this->recordToArray($record),
                            $this->payloadProjector->projectForTable($chainData, 'procurements', $record),
                        );
                    }
                }

                $this->recorder->record(
                    type: BreachType::UNAUTHORIZED_RECORD->value,
                    tableName: 'procurements',
                    record: $record,
                    prNumber: $record->pr_number,
                    message: 'Record exists in database but not on blockchain - unauthorized injection or PR tampering',
                    fieldDiffs: $fieldDiffs,
                    chainData: $chainData,
                );
            }

            $this->detectUnauthorizedProcurementTxidsInDb($blockchainPrNumbers);
            $this->detectUnauthorizedStreamRowsInDb(Stream::STATUS, ProcurementStage::class, 'procurement_stages');
            $this->detectUnauthorizedStreamRowsInDb(Stream::DOCUMENTS, ProcurementDocument::class, 'procurement_documents');
            $this->detectUnauthorizedStreamRowsInDb(Stream::EVENTS, ProcurementEvent::class, 'procurement_events');
            $this->detectUnauthorizedStreamRowsInDb(Stream::CORRECTIONS, ProcurementCorrection::class, 'procurement_corrections');
            $this->detectUnauthorizedStreamRowsInDb(Stream::ARCHIVE, ProcurementArchive::class, 'procurement_archives');
            $this->detectUnauthorizedStreamRowsInDb(Stream::PROCUREMENTS_CORRECTIONS, ProcurementMetadataCorrection::class, 'procurement_metadata_corrections');
            $this->detectUnauthorizedStreamRowsInDb(Stream::FILE_METADATA, File::class, 'Files');

            if ($fakeRecords->isNotEmpty()) {
                Log::warning('IntegrityVerification: detected unauthorized DB records', [
                    'count' => $fakeRecords->count(),
                    'pr_numbers' => $fakeRecords->pluck('pr_number')->toArray(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check unauthorized records', ['error' => $e->getMessage()]);
        }
    }

    private function detectUnauthorizedProcurementTxidsInDb(array $blockchainPrNumbers): void
    {
        $chainTxids = $this->getBlockchainTxids(Stream::METADATA);

        if (empty($chainTxids)) {
            return;
        }

        $fakeRecords = Procurement::query()
            ->whereIn('pr_number', $blockchainPrNumbers)
            ->where(function ($query) use ($chainTxids) {
                $query->whereNull('txid')
                    ->orWhere('txid', '')
                    ->orWhereNotIn('txid', $chainTxids);
            })
            ->get();

        foreach ($fakeRecords as $record) {
            $this->recorder->record(
                type: BreachType::UNAUTHORIZED_RECORD->value,
                tableName: 'procurements',
                record: $record,
                prNumber: $record->pr_number,
                message: 'Procurement record exists in database but its txid is absent from procurement.metadata blockchain stream',
                chainData: null,
            );
        }
    }

    private function detectUnauthorizedStreamRowsInDb(Stream $stream, string $modelClass, string $tableName): void
    {
        try {
            $chainTxids = $this->getBlockchainTxids($stream);

            $fakeRows = $modelClass::query()
                ->where(function ($query) use ($chainTxids) {
                    $query->whereNull('txid')
                        ->orWhere('txid', '');

                    if (empty($chainTxids)) {
                        $query->orWhereNotNull('txid');
                    } else {
                        $query->orWhereNotIn('txid', $chainTxids);
                    }
                })
                ->get();

            foreach ($fakeRows as $record) {
                $prNumber = $record->pr_number ?? $record->procurement?->pr_number ?? 'unknown';

                $this->recorder->record(
                    type: BreachType::UNAUTHORIZED_RECORD->value,
                    tableName: $tableName,
                    record: $record,
                    prNumber: $prNumber,
                    message: "Record exists in {$tableName} but txid is absent from {$stream->value} blockchain stream",
                    chainData: null,
                );
            }
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to check unauthorized stream rows', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getBlockchainPrNumbers(): array
    {
        try {
            return $this->blockchainIndex->prNumbers(Stream::METADATA);
        } catch (\Exception $e) {
            Log::warning('Failed to get blockchain PR numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getBlockchainTxids(Stream $stream): array
    {
        try {
            return $this->blockchainIndex->txids($stream);
        } catch (\Exception $e) {
            Log::warning('IntegrityVerification: failed to get blockchain txids', [
                'stream' => $stream->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function recordToArray(Procurement $record): array
    {
        $fields = Procurement::getHashableFields();
        $data = [];
        foreach ($fields as $field) {
            $value = $record->{$field} ?? null;
            if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_string($value) && is_numeric($value)) {
                $value = (float) $value;
            }
            $data[$field] = $value;
        }

        return $data;
    }
}

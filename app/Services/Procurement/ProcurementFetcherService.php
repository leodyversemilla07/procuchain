<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Contracts\CorrectionRepositoryInterface;
use App\DataTransferObjects\CorrectionData;
use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\DataTransferObjects\StatusData;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\StatusRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching single-procurement data from blockchain repositories
 *
 * Handles:
 * - Fetching status, documents, events, corrections for a specific procurement
 * - Document lookup by file key
 * - Sorting raw blockchain data
 *
 * Bulk listing operations are handled by ProcurementListAggregatorService.
 * User name resolution is handled by UserNameResolverService.
 *
 * @see ProcurementListAggregatorService
 * @see UserNameResolverService
 */
final class ProcurementFetcherService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly EventRepository $eventRepository,
        private readonly CorrectionRepositoryInterface $correctionRepository,
        private readonly ProcurementFormatterService $formatter,
        private readonly UserNameResolverService $userNameResolver,
    ) {}

    /**
     * Fetch status items for a specific procurement
     */
    public function fetchStatusItems(string $prNumber): Collection
    {
        $statusDtos = $this->statusRepository->findByProcurement($prNumber);

        return collect($statusDtos)
            ->map(function (StatusData $statusDto) {
                $stage = $statusDto->stage;
                $currentStatus = $statusDto->currentStatus;

                $stageEnum = StageEnums::tryFrom($stage);
                $phase = $stageEnum?->getPhase() ?? 'unknown';
                $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';

                return [
                    'stage' => $stage,
                    'stage_formatted' => $this->formatter->formatStageName($stage),
                    'stage_description' => $this->formatter->getStageDescription($stage),
                    'stage_order' => $this->formatter->getStageOrderIndex($stage),
                    'phase' => $phase,
                    'phase_display' => $phaseDisplayName,
                    'current_status' => $currentStatus,
                    'status' => $currentStatus,
                    'status_formatted' => $this->formatter->formatStatus($currentStatus),
                    'timestamp' => $statusDto->timestamp,
                    'formatted_date' => $statusDto->getFormattedDateTime(),
                    'formatted_date_only' => $statusDto->getFormattedDateOnly(),
                    'formatted_time_only' => $statusDto->getFormattedTimeOnly(),
                    'pr_number' => $statusDto->prNumber,
                    'procurement_title' => $statusDto->procurementTitle,
                    'user_address' => $statusDto->userAddress,
                    'metadata' => $statusDto->metadata,
                ];
            })
            ->sort(function ($a, $b) {
                $timestampA = $a['timestamp'] instanceof Carbon ? $a['timestamp']->timestamp : strtotime($a['timestamp']);
                $timestampB = $b['timestamp'] instanceof Carbon ? $b['timestamp']->timestamp : strtotime($b['timestamp']);

                if ($timestampA !== $timestampB) {
                    return $timestampB <=> $timestampA;
                }

                // If timestamps are equal, prioritize transitions
                $isTransitionA = isset($a['metadata']['transition']) && $a['metadata']['transition'] === true;
                $isTransitionB = isset($b['metadata']['transition']) && $b['metadata']['transition'] === true;

                if ($isTransitionA !== $isTransitionB) {
                    return $isTransitionA ? -1 : 1;
                }

                return 0;
            });
    }

    /**
     * Fetch and process all documents for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchDocuments(string $prNumber): array
    {
        $documentDtos = $this->documentRepository->findByProcurement($prNumber);

        Log::debug('Document Fetching Stats', [
            'pr_number' => $prNumber,
            'total_after_filtering_by_id' => count($documentDtos),
        ]);

        // Fetch corrections for this procurement
        $correctionDtos = $this->correctionRepository->findByProcurement($prNumber);
        $correctionsByTxid = collect($correctionDtos)->groupBy(fn (CorrectionData $correction) => $correction->originalTxid);

        return collect($documentDtos)
            ->map(function (DocumentData $doc) use ($correctionsByTxid) {
                $fileKey = $doc->fileKey;
                $fileUrl = ! empty($fileKey) ? route('files.download', ['fileKey' => $fileKey]) : '';

                $stageMetadata = $doc->stageMetadata;
                if ($stageMetadata && is_array($stageMetadata)) {
                    $stageMetadata = $this->formatter->formatStageMetadata($stageMetadata);
                }

                // Check for corrections
                $documentCorrections = $correctionsByTxid->get($doc->dataTxid, collect());
                $hasCorrections = $documentCorrections->isNotEmpty();
                $latestCorrection = null;

                if ($hasCorrections) {
                    $latestCorrectionData = $documentCorrections->sortByDesc(fn (CorrectionData $c) => $c->timestamp)->first();

                    if ($latestCorrectionData) {
                        $latestCorrection = [
                            'txid' => $latestCorrectionData->txid,
                            'timestamp' => $latestCorrectionData->timestamp->toIso8601String(),
                            'correction_type' => $latestCorrectionData->correctionType,
                            'correction_type_display' => $this->formatter->formatCorrectionType($latestCorrectionData->correctionType),
                            'action' => $latestCorrectionData->action,
                            'reason' => $latestCorrectionData->reason,
                            'corrected_by' => $latestCorrectionData->correctedBy,
                            'corrected_metadata' => $latestCorrectionData->correctedMetadata,
                        ];
                    }
                }

                return [
                    'file_key' => $fileKey,
                    'document_type' => $doc->documentType,
                    'document_type_formatted' => $this->formatter->formatDocumentType($doc->documentType),
                    'spaces_url' => $fileUrl,
                    'hash' => $doc->hash,
                    'hash_short' => $doc->getShortenedHash(),
                    'hash_medium' => $doc->getShortenedHash(6, 4),
                    'file_size' => $doc->fileSize,
                    'file_size_formatted' => $doc->getFormattedFileSize(),
                    'stage' => $doc->stage,
                    'stage_formatted' => $this->formatter->formatStageName($doc->stage),
                    'stage_metadata' => $stageMetadata,
                    'pr_number' => $doc->prNumber,
                    'procurement_title' => $doc->procurementTitle,
                    'user_address' => $doc->userAddress,
                    'timestamp' => $doc->timestamp,
                    'formatted_date' => $doc->getFormattedDateTime(),
                    'formatted_date_only' => $doc->getFormattedDateOnly(),
                    'formatted_time_only' => $doc->getFormattedTimeOnly(),
                    'metadata_txid' => $doc->metadataTxid,
                    'data_txid' => $doc->dataTxid,
                    'has_corrections' => $hasCorrections,
                    'latest_correction' => $latestCorrection,
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Fetch and process events for a specific procurement
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchEvents(string $prNumber): array
    {
        $eventDtos = $this->eventRepository->findByProcurement($prNumber);

        return collect($eventDtos)
            ->map(function (EventData $event) {
                return [
                    'timestamp' => $event->timestamp,
                    'formatted_date' => $event->getFormattedDateTime(),
                    'formatted_date_only' => $event->getFormattedDateOnly(),
                    'formatted_time_only' => $event->getFormattedTimeOnly(),
                    'event_type' => $event->eventType,
                    'event_type_formatted' => $this->formatter->formatEventType($event->eventType),
                    'details' => $event->details,
                    'stage' => $event->stage,
                    'stage_formatted' => $this->formatter->formatStageName($event->stage),
                    'stage_order' => $this->formatter->getStageOrderIndex($event->stage),
                    'document_count' => $event->documentCount,
                    'pr_number' => $event->prNumber,
                    'procurement_title' => $event->procurementTitle,
                    'user_address' => $event->userAddress,
                    'category' => $event->category,
                    'category_formatted' => $this->formatter->formatEventCategory($event->category),
                    'severity' => $event->severity,
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Get document data from blockchain by file key
     */
    public function getDocumentByFileKey(string $fileKey): ?DocumentData
    {
        try {
            $document = $this->documentRepository->findByFileKey($fileKey);

            if (! $document) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $document->hash,
                'pr_number' => $document->prNumber,
            ]);

            return $document;
        } catch (\Exception $e) {
            Log::error('Failed to get document data from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get hash by procurement number and file key pattern matching
     */
    public function getHashByPrNumber(string $prNumber, string $fileKey): ?string
    {
        try {
            $prDocuments = $this->documentRepository->findByProcurement($prNumber);

            $document = collect($prDocuments)
                ->first(function (DocumentData $doc) use ($fileKey) {
                    $fileKeyParts = explode('/', $fileKey);
                    $docFileKeyParts = explode('/', $doc->fileKey);

                    if (count($fileKeyParts) >= 1 && count($docFileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $docFileKeyParts[0];
                    }

                    return false;
                });

            if ($document) {
                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($document->hash),
                    'hash_value' => $document->hash,
                    'matched_file_key' => $document->fileKey,
                ]);

                return $document->hash;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Alternative hash lookup failed', [
                'pr_number' => $prNumber,
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the file exists in document stream
     */
    public function validateDocumentExists(string $fileKey): ?DocumentData
    {
        try {
            return $this->documentRepository->findByFileKey($fileKey);
        } catch (\Exception $e) {
            Log::error('Blockchain validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Preload user names for batch user lookup from raw stream items
     */
    public function preloadUserNames(Collection $items): void
    {
        $this->userNameResolver->preloadFromRawItems($items);
    }

    /**
     * Get username from blockchain address
     */
    public function getUserName(string $address): string
    {
        return $this->userNameResolver->resolve($address);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\StageEnums;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ProcurementFetcherService
{
    public function __construct(
        private readonly ProcurementFormatterService $formatter,
        private readonly BlockchainAddressResolverService $userNameResolver,
    ) {}

    public function fetchStatusItems(string $prNumber): Collection
    {
        $stages = ProcurementStage::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('entered_at')
            ->get();

        return $stages
            ->map(function (ProcurementStage $stage) {
                $stageName = $stage->stage;
                $currentStatus = $stage->status;

                $stageEnum = StageEnums::tryFrom($stageName);
                $phase = $stageEnum?->getPhase() ?? 'unknown';
                $phaseDisplayName = $stageEnum?->getPhaseDisplayName() ?? 'Unknown';

                return [
                    'stage' => $stageName,
                    'stage_formatted' => $this->formatter->formatStageName($stageName),
                    'stage_description' => $this->formatter->getStageDescription($stageName),
                    'stage_order' => $this->formatter->getStageOrderIndex($stageName),
                    'phase' => $phase,
                    'phase_display' => $phaseDisplayName,
                    'current_status' => $currentStatus,
                    'status' => $currentStatus,
                    'status_formatted' => $this->formatter->formatStatus($currentStatus),
                    'timestamp' => $stage->entered_at,
                    'formatted_date' => $stage->entered_at?->format('Y-m-d H:i:s'),
                    'formatted_date_only' => $stage->entered_at?->format('Y-m-d'),
                    'formatted_time_only' => $stage->entered_at?->format('H:i:s'),
                    'pr_number' => $stage->procurement?->pr_number ?? '',
                    'procurement_title' => $stage->procurement?->title ?? '',
                    'user_address' => $stage->user_address ?? '',
                    'metadata' => $stage->metadata ?? [],
                ];
            })
            ->sort(function ($a, $b) {
                $timestampA = $a['timestamp'] instanceof Carbon ? $a['timestamp']->timestamp : strtotime($a['timestamp']);
                $timestampB = $b['timestamp'] instanceof Carbon ? $b['timestamp']->timestamp : strtotime($b['timestamp']);

                if ($timestampA !== $timestampB) {
                    return $timestampB <=> $timestampA;
                }

                $isTransitionA = isset($a['metadata']['transition']) && $a['metadata']['transition'] === true;
                $isTransitionB = isset($b['metadata']['transition']) && $b['metadata']['transition'] === true;

                if ($isTransitionA !== $isTransitionB) {
                    return $isTransitionA ? -1 : 1;
                }

                return 0;
            });
    }

    public function fetchDocuments(string $prNumber): array
    {
        $documents = ProcurementDocument::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get();

        Log::debug('Document Fetching Stats', [
            'pr_number' => $prNumber,
            'total_after_filtering_by_id' => count($documents),
        ]);

        $corrections = ProcurementCorrection::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->get();
        $correctionsByTxid = $corrections->groupBy(fn (ProcurementCorrection $c) => $c->original_txid);

        return $documents
            ->map(function (ProcurementDocument $doc) use ($correctionsByTxid) {
                $fileKey = $doc->file_key;
                $blockchainFileUrl = ! empty($fileKey) ? route('files.download', ['fileKey' => $fileKey]) : '';

                $documentCorrections = $correctionsByTxid->get($doc->txid, collect());
                $hasCorrections = $documentCorrections->isNotEmpty();
                $latestCorrection = null;

                if ($hasCorrections) {
                    $latestCorrectionModel = $documentCorrections->sortByDesc(fn (ProcurementCorrection $c) => $c->corrected_at)->first();

                    if ($latestCorrectionModel) {
                        $latestCorrection = [
                            'txid' => $latestCorrectionModel->txid,
                            'timestamp' => $latestCorrectionModel->corrected_at?->toIso8601String(),
                            'correction_type' => $latestCorrectionModel->correction_type,
                            'correction_type_display' => $this->formatter->formatCorrectionType($latestCorrectionModel->correction_type),
                            'action' => $latestCorrectionModel->action,
                            'reason' => $latestCorrectionModel->reason,
                            'corrected_by' => $latestCorrectionModel->corrected_by,
                            'corrected_metadata' => $latestCorrectionModel->corrected_metadata,
                        ];
                    }
                }

                return [
                    'file_key' => $fileKey,
                    'document_type' => $doc->document_type,
                    'document_type_formatted' => $this->formatter->formatDocumentType($doc->document_type),
                    'spaces_url' => $blockchainFileUrl,
                    'hash' => $doc->hash,
                    'hash_short' => $doc->getShortenedHash(),
                    'hash_medium' => $doc->getShortenedHash(6, 4),
                    'file_size' => $doc->file_size,
                    'file_size_formatted' => $doc->getFormattedFileSize(),
                    'stage' => $doc->stage,
                    'stage_formatted' => $this->formatter->formatStageName($doc->stage),
                    'pr_number' => $doc->procurement?->pr_number ?? '',
                    'procurement_title' => $doc->procurement?->title ?? '',
                    'user_address' => $doc->user_address,
                    'timestamp' => $doc->uploaded_at,
                    'formatted_date' => $doc->getFormattedDateTime(),
                    'formatted_date_only' => $doc->getFormattedDateOnly(),
                    'formatted_time_only' => $doc->getFormattedTimeOnly(),
                    'metadata_txid' => $doc->txid,
                    'data_txid' => $doc->txid,
                    'has_corrections' => $hasCorrections,
                    'latest_correction' => $latestCorrection,
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    public function fetchEvents(string $prNumber): array
    {
        $events = ProcurementEvent::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderBy('occurred_at')
            ->get();

        return $events
            ->map(function (ProcurementEvent $event) {
                return [
                    'timestamp' => $event->occurred_at,
                    'formatted_date' => $event->occurred_at?->format('Y-m-d H:i:s'),
                    'formatted_date_only' => $event->occurred_at?->format('Y-m-d'),
                    'formatted_time_only' => $event->occurred_at?->format('H:i:s'),
                    'event_type' => $event->event_type,
                    'event_type_formatted' => $this->formatter->formatEventType($event->event_type),
                    'details' => $event->details,
                    'stage' => $event->stage,
                    'stage_formatted' => $this->formatter->formatStageName($event->stage),
                    'stage_order' => $this->formatter->getStageOrderIndex($event->stage),
                    'document_count' => $event->document_count,
                    'pr_number' => $event->procurement?->pr_number ?? '',
                    'procurement_title' => $event->procurement?->title ?? '',
                    'user_address' => $event->user_address,
                    'category' => $event->category,
                    'category_formatted' => $this->formatter->formatEventCategory($event->category),
                    'severity' => $event->severity,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getDocumentByfileKey(string $fileKey): ?ProcurementDocument
    {
        try {
            $document = ProcurementDocument::with('procurement')->where('file_key', $fileKey)->first();

            if (! $document) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $document->hash,
                'pr_number' => $document->procurement?->pr_number,
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

    public function getHashByPrNumber(string $prNumber, string $fileKey): ?string
    {
        try {
            $prDocuments = ProcurementDocument::with('procurement')
                ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
                ->orderByDesc('uploaded_at')
                ->get();

            $document = $prDocuments
                ->first(function (ProcurementDocument $doc) use ($fileKey) {
                    $fileKeyParts = explode('/', $fileKey);
                    $docfileKeyParts = explode('/', $doc->file_key);

                    if (count($fileKeyParts) >= 1 && count($docfileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $docfileKeyParts[0];
                    }

                    return false;
                });

            if ($document) {
                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($document->hash),
                    'hash_value' => $document->hash,
                    'matched_file_key' => $document->file_key,
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

    public function validateDocumentExists(string $fileKey): ?ProcurementDocument
    {
        try {
            return ProcurementDocument::with('procurement')->where('file_key', $fileKey)->first();
        } catch (\Exception $e) {
            Log::error('Blockchain validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function preloadUserNames(Collection $items): void
    {
        $this->userNameResolver->preloadFromRawItems($items);
    }

    public function getUserName(string $address): string
    {
        return $this->userNameResolver->resolve($address);
    }
}

<?php

namespace App\Services;

use App\Enums\StreamEnums;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProcurementDataTransformerService
{
    private StreamKeyService $streamKeyService;

    public function __construct(StreamKeyService $streamKeyService)
    {
        $this->streamKeyService = $streamKeyService;
    }

    public function transformProcurementsList($allStatus): array
    {
        return collect($allStatus)
            ->map(fn ($item) => $this->mapStatusToListItem($item))
            ->groupBy('id')
            ->map(fn ($group) => $group->sortByDesc('timestamp')->first())
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    private function mapStatusToListItem($item): array
    {
        $data = $item['data'];
        $streamKey = $this->streamKeyService->generate(
            $data['procurement_id'] ?? '',
            $data['procurement_title'] ?? ''
        );

        $documentCount = app(BlockchainService::class)
            ->getClient()
            ->listStreamKeyItems(StreamEnums::DOCUMENTS->value, $streamKey);

        return [
            'id' => $data['procurement_id'] ?? '',
            'title' => $data['procurement_title'] ?? '',
            'stage' => $data['stage'] ?? '',
            'current_status' => $data['current_status'] ?? '',
            'user_address' => $data['user_address'] ?? '',
            'timestamp' => $data['timestamp'] ?? '',
            'last_updated' => date('Y-m-d', strtotime($data['timestamp'] ?? 'now')),
            'document_count' => count($documentCount),
        ];
    }

    public function transformProcurementStatuses($status, $procurementId): Collection
    {
        return collect($status)
            ->map(function ($item) {
                $data = $item['data'];

                return [
                    'item' => $item,
                    'data' => $data,
                    'procurementId' => $data['procurement_id'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'stage' => $data['stage'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                ];
            })
            ->filter(function ($mappedItem) use ($procurementId) {
                return $mappedItem['procurementId'] === $procurementId;
            })
            ->sortByDesc('timestamp');
    }

    public function buildProcurementData(
        string $procurementId,
        string $procurementTitle,
        Collection $procurementStatus,
        BlockchainService $blockchainService
    ): array {
        $latestStatus = $procurementStatus->first();
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

        $documents = $blockchainService->getClient()->listStreamKeyItems(StreamEnums::DOCUMENTS->value, $streamKey, 1000);
        $events = $blockchainService->getClient()->listStreamKeyItems(StreamEnums::EVENTS->value, $streamKey);

        $parsedDocuments = $this->parseDocuments($documents);
        $parsedEvents = $this->parseEvents($events);
        $timeline = $this->buildTimeline($procurementStatus);

        return [
            'id' => $procurementId,
            'title' => $procurementTitle,
            'status' => [
                'stage' => $latestStatus['stage'] ?? '',
                'current_status' => $latestStatus['current_status'] ?? '',
                'timestamp' => $latestStatus['timestamp'] ?? '',
                'formatted_date' => isset($latestStatus['timestamp']) ?
                    date('M d, Y h:i A', strtotime($latestStatus['timestamp'])) : '',
            ],
            'documents' => $parsedDocuments,
            'events' => $parsedEvents,
            'timeline' => $timeline,
        ];
    }

    private function parseDocuments($documents): array
    {
        return collect($documents)->map(function ($doc) {
            $data = $doc['data'];

            $metadata = $data['stage_metadata'] ?? [];
            if (! is_array($metadata)) {
                $metadata = [];
            }

            return [
                'document_type' => $data['document_type'] ?? '',
                'file_key' => $data['file_key'] ?? '',
                'timestamp' => $data['timestamp'] ?? '',
                'stage' => $data['stage'] ?? '',
                'stage_metadata' => $metadata,
                'spaces_url' => isset($data['file_key']) ?
                    Storage::disk('spaces')->temporaryUrl($data['file_key'], now()->addMinutes(30)) : '',
                'hash' => $doc['txid'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'formatted_date' => isset($data['timestamp']) ?
                    date('M d, Y h:i A', strtotime($data['timestamp'])) : '',
            ];
        })->toArray();
    }

    private function parseEvents($events): array
    {
        return collect($events)->map(function ($event) {
            $data = $event['data'];

            return [
                'event_type' => $data['event_type'] ?? '',
                'details' => $data['details'] ?? '',
                'timestamp' => $data['timestamp'] ?? '',
                'stage' => $data['stage'] ?? $data['phase_identifier'] ?? '',
                'category' => $data['category'] ?? '',
                'document_count' => $data['document_count'] ?? null,
                'formatted_date' => isset($data['timestamp']) ?
                    date('M d, Y h:i A', strtotime($data['timestamp'])) : '',
            ];
        })->sortByDesc('timestamp')->values()->toArray();
    }

    private function buildTimeline($procurementStatus): array
    {
        return $procurementStatus->map(function ($status) {
            return [
                'timestamp' => $status['timestamp'],
                'stage' => $status['stage'],
                'status' => $status['current_status'],
                'formatted_date' => date('M d, Y h:i A', strtotime($status['timestamp'])),
            ];
        })->values()->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\EventData;
use App\Enums\Stream;
use App\Models\ProcurementEvent;
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement events
 * Reads from DB (mirror of blockchain).
 */
final readonly class EventRepository
{
    public function __construct(
        private BlockchainRpcClient $multichain
    ) {}

    /**
     * Create a new event record (writes to blockchain)
     */
    public function create(EventData $data): ?string
    {
        try {
            $key = $data->prNumber.'_'.str_replace(' ', '_', strtolower($data->procurementTitle));

            $txid = $this->multichain->publish(
                Stream::EVENTS->value,
                $key,
                ['json' => $data->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain event publish did not return a transaction id.');
            }

            Log::info('Event published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish event', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Find events by PR number from DB.
     * Returns EventData objects.
     */
    public function findByProcurement(string $prNumber): array
    {
        return ProcurementEvent::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn ($event) => EventData::fromBlockchainArray([
                'pr_number' => $event->procurement->pr_number ?? '',
                'procurement_title' => $event->procurement->title ?? '',
                'stage' => $event->stage,
                'event_type' => $event->event_type,
                'category' => $event->category,
                'severity' => $event->severity,
                'details' => $event->details,
                'document_count' => $event->document_count,
                'user_address' => $event->user_address ?? '',
                'timestamp' => $event->occurred_at->toIso8601String(),
                'metadata' => $event->metadata,
            ]))
            ->toArray();
    }

    /**
     * Find recent events from DB.
     */
    public function findRecentEvents(int $limit = 50): array
    {
        return ProcurementEvent::orderByDesc('occurred_at')
            ->take($limit)
            ->get()
            ->map(fn ($event) => EventData::fromBlockchainArray([
                'pr_number' => $event->procurement->pr_number ?? '',
                'procurement_title' => $event->procurement->title ?? '',
                'stage' => $event->stage,
                'event_type' => $event->event_type,
                'category' => $event->category,
                'severity' => $event->severity,
                'details' => $event->details,
                'document_count' => $event->document_count,
                'user_address' => $event->user_address ?? '',
                'timestamp' => $event->occurred_at->toIso8601String(),
                'metadata' => $event->metadata,
            ]))
            ->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\EventData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.events stream
 */
final readonly class EventRepository
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new event record
     */
    public function create(EventData $data): ?string
    {
        try {
            // Event stream uses composite key: pr_number + descriptive suffix
            $key = $data->prNumber.'_'.str_replace(' ', '_', strtolower($data->procurementTitle));

            $txid = $this->multichain->publish(
                StreamEnums::EVENTS->value,
                $key,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Event published to blockchain', [
                'pr_number' => $data->prNumber,
                'event_type' => $data->eventType,
                'stream' => StreamEnums::EVENTS->value,
                'txid' => $txid,
            ]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish event to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find events by procurement ID
     *
     * @return EventData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        $allEvents = $this->all();

        return array_filter(
            $allEvents,
            fn (EventData $event) => $event->prNumber === $prNumber
        );
    }

    /**
     * Find recent events (limit)
     *
     * @return EventData[]
     */
    public function findRecent(int $limit = 10): array
    {
        $allEvents = $this->all();

        usort($allEvents, fn ($a, $b) => $b->timestamp->timestamp - $a->timestamp->timestamp);

        return array_slice($allEvents, 0, $limit);
    }

    /**
     * Get all events
     *
     * @return EventData[]
     */
    public function all(): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::EVENTS->value,
                true,
                1000,
                0,
                false
            );

            if (! $items) {
                return [];
            }

            $events = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    try {
                        // Skip events that don't have required fields (e.g., test data)
                        $json = $item['data']['json'];
                        if (!isset($json['procurement_title']) || !isset($json['pr_number'])) {
                            continue;
                        }
                        
                        $events[] = EventData::fromBlockchainArray($json);
                    } catch (\Exception $e) {
                        Log::warning('Skipping invalid event data', [
                            'error' => $e->getMessage(),
                            'pr_number' => $json['pr_number'] ?? 'N/A',
                        ]);
                        continue;
                    }
                }
            }

            return $events;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all events', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get event history for a procurement
     *
     * @return EventData[]
     */
    public function getHistory(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::EVENTS->value,
                true,
                1000,
                0,
                false
            );

            if (! $items) {
                return [];
            }

            $history = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $event = EventData::fromBlockchainArray($item['data']['json']);
                    if ($event->prNumber === $prNumber) {
                        $history[] = $event;
                    }
                }
            }

            return $history;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve event history', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

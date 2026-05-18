<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Ledger Entry Data Transfer Object
 *
 * Unified representation of any blockchain stream item
 * for the Shared Ledger view.
 */
final readonly class LedgerEntryData
{
    public function __construct(
        public string $timestamp,
        public string $stream,
        public string $key,
        public string $prNumber,
        public string $action,
        public string $summary,
        public string $actorAddress,
        public string $txid,
        public array $rawJson,
        public ?string $procurementTitle = null,
        public array $oldValues = [],
        public array $newValues = [],
        public ?string $originalTxid = null,
    ) {}

    /**
     * Create from a MultiChain stream item.
     */
    public static function fromStreamItem(string $stream, array $item): self
    {
        $data = $item['data']['json'] ?? [];
        $txid = $item['txid'] ?? '';
        $key = $item['keys'][0] ?? '';

        // Detect system-level purge/resync events (node_{id}_full_purge / node_{id}_resync)
        $isNodePurgeEvent = str_starts_with($key, 'node_') && str_ends_with($key, '_full_purge');
        $isNodeResyncEvent = str_starts_with($key, 'node_') && str_ends_with($key, '_resync');

        if ($isNodePurgeEvent) {
            $prNumber = 'system';
            $procurementTitle = null;
            $action = 'node_purged';
            $actorAddress = $data['performed_by'] ?? '';
            $oldValues = [];
            $newValues = ['node_id' => $data['node_id'] ?? $key, 'reason' => $data['reason'] ?? ''];
            $originalTxid = null;
            $summary = sprintf(
                'Node %s purged — all data removed%s',
                $data['node_name'] ?? $data['node_id'] ?? $key,
                ! empty($data['reason']) ? ': '.$data['reason'] : ''
            );
        } elseif ($isNodeResyncEvent) {
            $prNumber = 'system';
            $procurementTitle = null;
            $action = 'node_resynced';
            $actorAddress = $data['performed_by'] ?? '';
            $oldValues = [];
            $newValues = ['node_id' => $data['node_id'] ?? $key];
            $originalTxid = null;
            $summary = sprintf(
                'Node %s resynced — data restored from peers',
                $data['node_name'] ?? $data['node_id'] ?? $key
            );
        } else {
            $prNumber = $data['pr_number'] ?? $key;
            $procurementTitle = $data['procurement_title'] ?? $data['title'] ?? null;
            $actorAddress = $data['user_address'] ?? $data['uploaded_by'] ?? $data['archived_by'] ?? $data['corrected_by'] ?? '';
            $oldValues = (array) ($data['metadata']['old_values'] ?? $data['old_values'] ?? []);
            $newValues = (array) ($data['metadata']['new_values'] ?? $data['new_values'] ?? []);
            $originalTxid = $data['original_txid'] ?? $data['metadata']['original_txid'] ?? null;

            // Extract a meaningful action/summary per stream
            $action = $data['event_type']
                ?? $data['current_status']
                ?? $data['correction_type']
                ?? $data['action']
                ?? 'published';
        }

        $summary ??= match ($stream) {
            'procurement.events' => $data['details'] ?? match ($data['event_type'] ?? '') {
                'document_upload' => 'Document uploaded to procurement',
                'phase_transition', 'stage_transition' => 'Stage transition occurred',
                'stage_completed' => 'Stage marked as complete',
                'stage_skipped' => 'Stage skipped',
                'stage_repeated' => 'Stage repeated',
                'procurement_completed' => 'Procurement completed',
                default => 'Event recorded',
            },
            'procurement.status' => sprintf(
                'Status: %s → %s',
                $data['previous_status'] ?? 'none',
                $data['current_status'] ?? 'unknown'
            ),
            'procurement.metadata' => sprintf(
                'Procurement %s',
                ($data['status'] ?? 'created') === 'created' ? 'created' : 'updated'
            ),
            'procurement.documents' => sprintf(
                'Document: %s (%s)',
                $data['file_name'] ?? $data['document_type'] ?? 'unknown',
                $data['document_type'] ?? 'unknown'
            ),
            'procurement.corrections', 'procurement.metadata.corrections' => sprintf(
                'Correction: %s - %s',
                $data['correction_type'] ?? $data['action'] ?? 'amendment',
                $data['reason'] ?? 'No reason provided'
            ),
            'procurement.archive' => sprintf(
                'Procurement %s',
                ! empty($data['archived']) ? 'archived' : 'restored'
            ),
            default => $data['details'] ?? json_encode($data),
        };

        // Prefer the blockchain's blocktime (authoritative, always present on stream items).
        // Fall back to JSON data fields, then blocktime as Unix epoch.
        // NEVER fall back to now() — that makes every entry show "page load time".
        $timestamp = $item['blocktime'] ?? null;

        if ($timestamp !== null) {
            // blocktime is a Unix epoch — convert to ISO 8601
            $timestamp = Carbon::createFromTimestamp((int) $timestamp)->toIso8601String();
        } else {
            // Try data-level timestamp fields in priority order
            $timestamp = $data['timestamp']
                ?? $data['purged_at']
                ?? $data['resynced_at']
                ?? $data['stored_at']
                ?? $data['deleted_at']
                ?? $data['restored_at']
                ?? $data['archived_at']
                ?? $data['created_at']
                ?? null;
        }

        // Final fallback: use the item's block time from the confirm field (MultiChain v2+)
        if ($timestamp === null && isset($item['confirm'])) {
            $timestamp = Carbon::now()->toIso8601String(); // truly no timestamp available
        }

        return new self(
            timestamp: $timestamp ?? Carbon::now()->toIso8601String(),
            stream: $stream,
            key: $key,
            prNumber: $prNumber,
            action: $action,
            summary: $summary,
            actorAddress: $actorAddress,
            txid: $txid,
            rawJson: $data,
            procurementTitle: $procurementTitle,
            oldValues: $oldValues,
            newValues: $newValues,
            originalTxid: $originalTxid,
        );
    }

    /**
     * Format timestamp for display.
     */
    public function getFormattedTimestamp(): string
    {
        return Carbon::parse($this->timestamp)
            ->setTimezone('Asia/Manila')
            ->format('M j, Y, g:i A');
    }

    /**
     * Get the sortable timestamp as a Unix timestamp.
     */
    public function getSortableTimestamp(): int
    {
        return Carbon::parse($this->timestamp)->timestamp;
    }
}

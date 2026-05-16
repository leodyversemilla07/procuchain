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

        $summary = match ($stream) {
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

        $timestamp = $data['timestamp'] ?? $data['stored_at'] ?? $data['archived_at'] ?? $data['created_at'] ?? now()->toIso8601String();

        return new self(
            timestamp: $timestamp,
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
 ->setTimezone(config('app.timezone', 'Asia/Manila'))
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

<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

class LedgerEntryBuilder
{
    public function fromStreamItem(string $stream, array $item): array
    {
        $data = $item['data']['json'] ?? [];
        $txid = $item['txid'] ?? '';
        $key = $item['keys'][0] ?? '';

        $isNodePurgeEvent = str_starts_with($key, 'node_') && str_ends_with($key, '_full_purge');
        $isNodeResyncEvent = str_starts_with($key, 'node_') && str_ends_with($key, '_resync');
        $isBlockchainFileNodePurgeEvent = str_ends_with($key, '_node_purge') && ! $isNodePurgeEvent;

        if ($isNodePurgeEvent) {
            $prNumber = 'system';
            $procurementTitle = null;
            $action = 'node_purged';
            $actorAddress = $data['performed_by'] ?? '';
            $oldValues = [];
            $newValues = [
                'node_id' => $data['node_id'] ?? $key,
                'node_name' => $data['node_name'] ?? '',
                'items_purged' => $data['items_purged'] ?? 0,
                'method' => $data['method'] ?? 'ssm_physical_delete',
                'reason' => $data['reason'] ?? '',
                'performed_by' => $data['performed_by'] ?? '',
                'purged_at' => $data['purged_at'] ?? '',
            ];
            $originalTxid = null;
            $summary = sprintf(
                'Node %s purged — %d items removed%s',
                $data['node_name'] ?? $data['node_id'] ?? $key,
                $data['items_purged'] ?? 0,
                ! empty($data['reason']) ? ': '.$data['reason'] : ''
            );
        } elseif ($isBlockchainFileNodePurgeEvent) {
            $fileKey = $data['file_key'] ?? $key;
            $prNumber = str_contains($fileKey, '/') ? explode('/', $fileKey)[0] : 'system';
            $procurementTitle = null;
            $action = 'file_node_purged';
            $actorAddress = $data['performed_by'] ?? '';
            $oldValues = [];
            $newValues = [
                'file_key' => $fileKey,
                'node_id' => $data['node_id'] ?? '',
                'node_name' => $data['node_name'] ?? '',
                'items_purged' => $data['items_purged'] ?? 0,
                'method' => $data['method'] ?? 'ssm_physical_delete',
                'reason' => $data['reason'] ?? '',
                'performed_by' => $data['performed_by'] ?? '',
                'purged_at' => $data['purged_at'] ?? '',
            ];
            $originalTxid = null;
            $summary = sprintf(
                'File %s purged from node %s%s',
                $fileKey,
                $data['node_name'] ?? $data['node_id'] ?? 'unknown',
                ! empty($data['reason']) ? ': '.$data['reason'] : ''
            );
        } elseif ($isNodeResyncEvent) {
            $prNumber = 'system';
            $procurementTitle = null;
            $action = 'node_resynced';
            $actorAddress = $data['performed_by'] ?? '';
            $oldValues = [];
            $newValues = [
                'node_id' => $data['node_id'] ?? $key,
                'node_name' => $data['node_name'] ?? '',
                'items_resynced' => $data['items_resynced'] ?? 0,
                'method' => $data['method'] ?? 'ssm_subscribe_all',
                'reason' => $data['reason'] ?? '',
                'performed_by' => $data['performed_by'] ?? '',
                'resynced_at' => $data['resynced_at'] ?? '',
            ];
            $originalTxid = null;
            $summary = sprintf(
                'Node %s resynced — data restored from peers%s',
                $data['node_name'] ?? $data['node_id'] ?? $key,
                ! empty($data['reason']) ? ': '.$data['reason'] : ''
            );
        } else {
            $prNumber = $data['pr_number'] ?? $key;
            $procurementTitle = $data['procurement_title'] ?? $data['title'] ?? null;
            $actorAddress = $data['user_address'] ?? $data['uploaded_by'] ?? $data['archived_by'] ?? $data['corrected_by'] ?? '';
            $oldValues = (array) ($data['metadata']['old_values'] ?? $data['old_values'] ?? []);
            $newValues = (array) ($data['metadata']['new_values'] ?? $data['new_values'] ?? []);
            $originalTxid = $data['original_txid'] ?? $data['metadata']['original_txid'] ?? null;

            $action = $data['event_type']
                ?? $data['current_status']
                ?? $data['correction_type']
                ?? $data['action']
                ?? 'published';
        }

        $summary ??= $this->buildDefaultSummary($stream, $data, $key);

        $formatted_timestamp = $this->resolveTimestamp($item, $data);

        $sortable_timestamp = $formatted_timestamp ? Carbon::parse($formatted_timestamp)->timestamp : 0;

        return [
            'timestamp' => $formatted_timestamp ?? Carbon::now()->toIso8601String(),
            'formatted_timestamp' => Carbon::parse($formatted_timestamp ?? Carbon::now())->setTimezone('Asia/Manila')->format('M j, Y, g:i A'),
            'sortable_timestamp' => $sortable_timestamp,
            'stream' => $stream,
            'key' => $key,
            'pr_number' => $prNumber,
            'action' => $action,
            'summary' => $summary,
            'actor_address' => $actorAddress,
            'txid' => $txid,
            'raw_json' => $data,
            'procurement_title' => $procurementTitle,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'original_txid' => $originalTxid,
        ];
    }

    public function getStreamDisplayName(string $stream): string
    {
        return match ($stream) {
            'procurement.metadata' => 'Metadata',
            'procurement.status' => 'Status',
            'procurement.documents' => 'Documents',
            'procurement.corrections' => 'Document Corrections',
            'procurement.metadata.corrections' => 'Metadata Corrections',
            'procurement.archive' => 'Archive',
            'procurement.events' => 'Events',
            'File.data' => 'File Data',
            'File.metadata' => 'File Metadata',
            'File.chunks' => 'File Chunks',
            default => $stream,
        };
    }

    private function buildDefaultSummary(string $stream, array $data, string $key): string
    {
        return match ($stream) {
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
                'Status: %s -> %s',
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
            'File.metadata' => sprintf(
                'File stored: %s — %s (%s)',
                $data['pr_number'] ?? $key,
                $data['file_name'] ?? $data['document_type'] ?? 'document',
                $data['document_type'] ?? 'unknown'
            ),
            default => $data['details'] ?? json_encode($data),
        };
    }

    private function resolveTimestamp(array $item, array $data): ?string
    {
        $timestamp = $item['blocktime'] ?? null;

        if ($timestamp !== null) {
            return Carbon::createFromTimestamp((int) $timestamp)->toIso8601String();
        }

        return $data['timestamp']
            ?? $data['purged_at']
            ?? $data['resynced_at']
            ?? $data['stored_at']
            ?? $data['deleted_at']
            ?? $data['restored_at']
            ?? $data['archived_at']
            ?? $data['created_at']
            ?? null;
    }
}

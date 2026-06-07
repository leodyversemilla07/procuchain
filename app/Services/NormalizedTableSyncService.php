<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\File;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Normalized Table Sync Service
 *
 * Syncs data DIRECTLY FROM blockchain to normalized tables.
 * Blockchain is source of truth. No procurement_records table needed.
 *
 * Flow:
 * 1. Read data FROM blockchain (MultiChain)
 * 2. Extract structured fields
 * 3. Compute hash of extracted data
 * 4. Store in normalized table with data_hash + blockchain_hash
 */
class NormalizedTableSyncService
{
    private Manager $manager;

    public function __construct()
    {
        $this->manager = app(Manager::class);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Sync all procurement streams from blockchain to normalized tables.
     */
    public function syncAll(): array
    {
        return Cache::lock('normalized-table-sync:all', 600)->block(10, function (): array {
            $counts = [
                'procurements' => 0,
                'stages' => 0,
                'documents' => 0,
                'events' => 0,
                'corrections' => 0,
                'archives' => 0,
                'metadata_corrections' => 0,
                'files' => 0,
            ];

            Log::info('NormalizedTableSync: starting full sync from blockchain');

            // 1. Sync procurement metadata → procurements table
            $counts['procurements'] = $this->syncProcurementMetadata();

            // 2. Sync status updates → procurement_stages table
            $counts['stages'] = $this->syncStatusUpdates();

            // 3. Sync documents → procurement_documents table
            $counts['documents'] = $this->syncDocuments();

            // 4. Sync events → procurement_events table
            $counts['events'] = $this->syncEvents();

            // 5. Sync corrections → procurement_corrections table
            $counts['corrections'] = $this->syncCorrections();

            // 6. Sync archive flags → procurement_archives table
            $counts['archives'] = $this->syncArchives();

            // 7. Sync procurement metadata corrections → procurement_metadata_corrections table
            $counts['metadata_corrections'] = $this->syncMetadataCorrections();

            // 8. Sync file metadata → files table
            $counts['files'] = $this->syncFileMetadata();

            Log::info('NormalizedTableSync: sync completed', $counts);

            return $counts;
        });
    }

    /**
     * Sync only a specific PR's data from blockchain.
     * Used after blockchain writes for instant DB updates.
     *
     * @return array{stages: int, events: int, documents: int, metadata: int, corrections: int}
     */
    public function syncPr(string $prNumber): array
    {
        return Cache::lock("normalized-table-sync:pr:{$prNumber}", 300)->block(10, function () use ($prNumber): array {
            Log::info('NormalizedTableSync: syncing PR', ['pr_number' => $prNumber]);

            $counts = [
                'stages' => 0,
                'events' => 0,
                'documents' => 0,
                'metadata' => 0,
                'corrections' => 0,
            ];

            $counts['metadata'] = $this->syncMetadataForPr($prNumber);
            $counts['stages'] = $this->syncStatusUpdatesForPr($prNumber);
            $counts['events'] = $this->syncEventsForPr($prNumber);
            $counts['documents'] = $this->syncDocumentsForPr($prNumber);
            $counts['corrections'] = $this->syncCorrectionsForPr($prNumber);
            $counts['archives'] = $this->syncArchivesForPr($prNumber);
            $counts['metadata_corrections'] = $this->syncMetadataCorrectionsForPr($prNumber);
            $counts['files'] = $this->syncFilesForPr($prNumber);

            Log::info('NormalizedTableSync: PR sync completed', ['pr_number' => $prNumber] + $counts);

            return $counts;
        });
    }

    /**
     * Sync procurement metadata for a specific PR only.
     */
    private function syncMetadataForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::METADATA->value, $prNumber);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data) || ($data['pr_number'] ?? '') !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;
            $userId = $data['user_id'] ?? null;
            if (is_array($userId)) {
                $userId = $userId['id'] ?? null;
            }

            $attributes = [
                'app_reference' => $data['app_reference'] ?? null,
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'goods',
                'procurement_mode' => $data['procurement_mode'] ?? 'competitive_bidding',
                'office' => $data['office'] ?? null,
                'end_user' => $data['end_user'] ?? null,
                'fund_source' => $data['funding_source'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
                'abc_amount' => (float) ($data['abc_amount'] ?? 0),
                'delivery_location' => $data['delivery_location'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'delivery_term_days' => $data['delivery_term_days'] ?? null,
                'current_status' => $data['status'] ?? 'draft',
                'user_address' => $data['user_address'] ?? null,
                'user_id' => $userId !== null ? (string) $userId : null,
                'initiated_at' => $this->normaliseDate($data['created_at'] ?? null),
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'last_updated_at' => $blocktime ? date('Y-m-d H:i:s', $blocktime) : now(),
            ];

            $dataHash = $this->computeHash($this->extractFields(
                ['pr_number' => $prNumber, ...$attributes],
                Procurement::getHashableFields()
            ));

            $existing = Procurement::withTrashed()->where('pr_number', $prNumber)->first();
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update([...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]);
            } else {
                Procurement::create([
                    'pr_number' => $prNumber,
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Sync status updates for a specific PR only.
     */
    private function syncStatusUpdatesForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::STATUS->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data) || ($data['pr_number'] ?? '') !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => $data['current_status'] ?? 'unknown',
                'category' => $data['category'] ?? 'goods',
                'procurement_mode' => $data['procurement_mode'] ?? 'competitive_bidding',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'stage' => $data['stage'] ?? 'unknown',
                'status' => $data['current_status'] ?? 'unknown',
                'previous_status' => $data['previous_status'] ?? null,
                'entered_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementStage::getHashableFields()));

            ProcurementStage::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            // Update procurement current stage (only if newer)
            $enteredAt = $attributes['entered_at'];
            if ($enteredAt && strtotime($enteredAt) > $procurement->last_updated_at?->timestamp) {
                $procurement->update([
                    'current_stage' => $data['stage'] ?? $procurement->current_stage,
                    'current_status' => $data['current_status'] ?? $procurement->current_status,
                    'previous_status' => $data['previous_status'] ?? $procurement->current_status,
                    'last_updated_at' => $enteredAt,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Sync events for a specific PR only.
     */
    private function syncEventsForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::EVENTS->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data) || ($data['pr_number'] ?? '') !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => 'active',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'event_type' => $data['event_type'] ?? 'unknown',
                'category' => $data['category'] ?? 'general',
                'severity' => $data['severity'] ?? 'info',
                'details' => $data['details'] ?? '',
                'stage' => $data['stage'] ?? 'unknown',
                'document_count' => (int) ($data['document_count'] ?? 0),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementEvent::getHashableFields()));

            ProcurementEvent::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync documents for a specific PR only.
     */
    private function syncDocumentsForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::DOCUMENTS->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data) || ($data['pr_number'] ?? '') !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => 'active',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'document_type' => $data['document_type'] ?? 'unknown',
                'stage' => $data['stage'] ?? 'unknown',
                'filename' => $data['file_name'] ?? $data['filename'] ?? 'unknown',
                'file_key' => $data['file_key'] ?? '',
                'mime_type' => $data['mime_type'] ?? null,
                'file_size' => (int) ($data['file_size'] ?? 0),
                'hash' => $data['hash'] ?? '',
                'description' => $data['description'] ?? null,
                'uploaded_by' => $data['uploaded_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'is_active' => true,
                'uploaded_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementDocument::getHashableFields()));

            $document = ProcurementDocument::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            if ($document->wasRecentlyCreated) {
                $procurement->increment('documents_count');
            }

            $procurement->update(['last_updated_at' => now()]);

            $count++;
        }

        return $count;
    }

    /**
     * Sync corrections for a specific PR only.
     */
    private function syncCorrectionsForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::CORRECTIONS->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data) || ($data['pr_number'] ?? '') !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => 'correction',
                'current_status' => 'corrected',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'correction_type' => $data['correction_type'] ?? 'unknown',
                'action' => $data['action'] ?? 'correct',
                'reason' => $data['reason'] ?? '',
                'original_txid' => $data['original_txid'] ?? '',
                'original_document_hash' => $data['original_document_hash'] ?? '',
                'corrected_by' => $data['corrected_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'corrected_metadata' => $data['corrected_metadata'] ?? null,
                'corrected_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementCorrection::getHashableFields()));

            ProcurementCorrection::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Get stream items filtered by key (PR number).
     */
    private function getStreamItemsForKey(string $stream, string $key): array
    {
        try {
            $items = $this->manager->liststreamkeyitems($stream, $key, false, 1000);

            return is_array($items) ? $items : [];
        } catch (\Exception $e) {
            Log::error('NormalizedTableSync: failed to read stream key items', [
                'stream' => $stream,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Find or create a Procurement, handling soft-deleted records.
     *
     * Procurement uses SoftDeletes, so firstOrCreate skips soft-deleted rows
     * and tries to INSERT, hitting the pr_number unique constraint.
     * This method uses withTrashed() to find existing soft-deleted records.
     */
    private function findOrCreateProcurement(string $prNumber, array $defaults): Procurement
    {
        $existing = Procurement::withTrashed()->where('pr_number', $prNumber)->first();
        if ($existing) {
            // Restore soft-deleted records so they appear in queries
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return Procurement::create(['pr_number' => $prNumber, ...$defaults]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STREAM SYNC METHODS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Sync procurement.metadata stream → procurements table
     */
    private function syncProcurementMetadata(): int
    {
        $items = $this->getStreamItems(StreamEnums::METADATA->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? null;
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;
            $userId = $data['user_id'] ?? null;
            if (is_array($userId)) {
                $userId = $userId['id'] ?? null;
            }

            $attributes = [
                'app_reference' => $data['app_reference'] ?? null,
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'goods',
                'procurement_mode' => $data['procurement_mode'] ?? 'competitive_bidding',
                'office' => $data['office'] ?? null,
                'end_user' => $data['end_user'] ?? null,
                'fund_source' => $data['funding_source'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
                'abc_amount' => (float) ($data['abc_amount'] ?? 0),
                'approved_budget' => isset($data['approved_budget']) ? (float) $data['approved_budget'] : null,
                'contract_price' => isset($data['contract_price']) ? (float) $data['contract_price'] : null,
                'delivery_location' => $data['delivery_location'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'delivery_term_days' => $data['delivery_term_days'] ?? null,
                'philgeps_reference' => $data['philgeps_reference'] ?? null,
                'philgeps_posting_date' => $data['philgeps_posting_date'] ?? null,
                'bac_resolution_number' => $data['bac_resolution_number'] ?? null,
                'bac_resolution_date' => $data['bac_resolution_date'] ?? null,
                'approved_by' => $data['approved_by'] ?? null,
                'approval_date' => $data['approval_date'] ?? null,
                'current_status' => $data['status'] ?? 'draft',
                'user_address' => $data['user_address'] ?? null,
                'user_id' => $userId !== null ? (string) $userId : null,
                'initiated_at' => $this->normaliseDate($data['created_at'] ?? null),
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'last_updated_at' => $blocktime ? date('Y-m-d H:i:s', $blocktime) : now(),
            ];

            $dataHash = $this->computeHash($this->extractFields(
                ['pr_number' => $prNumber, ...$attributes],
                Procurement::getHashableFields()
            ));

            // Use withTrashed() to find soft-deleted records and avoid
            // UniqueConstraintViolationException from the pr_number unique index.
            $existing = Procurement::withTrashed()->where('pr_number', $prNumber)->first();
            if ($existing) {
                $existing->update([...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]);
            } else {
                Procurement::create([
                    'pr_number' => $prNumber,
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]);
            }

            $count++;
        }

        Log::info('NormalizedTableSync: metadata synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.status stream → procurement_stages table
     */
    private function syncStatusUpdates(): int
    {
        $items = $this->getStreamItems(StreamEnums::STATUS->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? null;
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            // Find or create procurement
            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => $data['current_status'] ?? 'unknown',
                'category' => $data['category'] ?? 'goods',
                'procurement_mode' => $data['procurement_mode'] ?? 'competitive_bidding',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'stage' => $data['stage'] ?? 'unknown',
                'status' => $data['current_status'] ?? 'unknown',
                'previous_status' => $data['previous_status'] ?? null,
                'entered_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementStage::getHashableFields()));

            $procurementStage = ProcurementStage::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            // Update procurement current stage only if this status is newer than the current one
            $enteredAt = $attributes['entered_at'];
            $shouldUpdate = false;

            if ($procurement->last_updated_at === null) {
                // No previous update, always update
                $shouldUpdate = true;
            } elseif ($enteredAt && strtotime($enteredAt) > $procurement->last_updated_at->timestamp) {
                // This status is newer than the current one
                $shouldUpdate = true;
            }

            if ($shouldUpdate) {
                $procurement->update([
                    'current_stage' => $data['stage'] ?? $procurement->current_stage,
                    'current_status' => $data['current_status'] ?? $procurement->current_status,
                    'previous_status' => $data['previous_status'] ?? $procurement->current_status,
                    'last_updated_at' => $enteredAt ? date('Y-m-d H:i:s', strtotime($enteredAt)) : now(),
                ]);
            }

            $count++;
        }

        Log::info('NormalizedTableSync: stages synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.documents stream → procurement_documents table
     */
    private function syncDocuments(): int
    {
        $items = $this->getStreamItems(StreamEnums::DOCUMENTS->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? null;
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            // Find or create procurement
            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => 'active',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'document_type' => $data['document_type'] ?? 'unknown',
                'stage' => $data['stage'] ?? 'unknown',
                'filename' => $data['file_name'] ?? $data['filename'] ?? 'unknown',
                'file_key' => $data['file_key'] ?? '',
                'mime_type' => $data['mime_type'] ?? null,
                'file_size' => (int) ($data['file_size'] ?? 0),
                'hash' => $data['hash'] ?? '',
                'description' => $data['description'] ?? null,
                'uploaded_by' => $data['uploaded_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'is_active' => true,
                'uploaded_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementDocument::getHashableFields()));

            $document = ProcurementDocument::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            if ($document->wasRecentlyCreated) {
                $procurement->increment('documents_count');
            }

            $procurement->update(['last_updated_at' => now()]);

            $count++;
        }

        Log::info('NormalizedTableSync: documents synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.events stream → procurement_events table
     */
    private function syncEvents(): int
    {
        $items = $this->getStreamItems(StreamEnums::EVENTS->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? null;
            if (empty($prNumber) || $prNumber === 'system') {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            // Find or create procurement
            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => $data['stage'] ?? 'unknown',
                'current_status' => 'active',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'event_type' => $data['event_type'] ?? 'unknown',
                'category' => $data['category'] ?? 'general',
                'severity' => $data['severity'] ?? 'info',
                'details' => $data['details'] ?? '',
                'stage' => $data['stage'] ?? 'unknown',
                'document_count' => (int) ($data['document_count'] ?? 0),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementEvent::getHashableFields()));

            ProcurementEvent::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            $count++;
        }

        Log::info('NormalizedTableSync: events synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.corrections stream → procurement_corrections table
     */
    private function syncCorrections(): int
    {
        $items = $this->getStreamItems(StreamEnums::CORRECTIONS->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? null;
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            // Find or create procurement
            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => 'correction',
                'current_status' => 'corrected',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'correction_type' => $data['correction_type'] ?? 'unknown',
                'action' => $data['action'] ?? 'correct',
                'reason' => $data['reason'] ?? '',
                'original_txid' => $data['original_txid'] ?? '',
                'original_document_hash' => $data['original_document_hash'] ?? '',
                'corrected_by' => $data['corrected_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'corrected_metadata' => $data['corrected_metadata'] ?? null,
                'corrected_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementCorrection::getHashableFields()));

            ProcurementCorrection::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            $count++;
        }

        Log::info('NormalizedTableSync: corrections synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.archive stream → procurement_archives table.
     */
    private function syncArchives(): int
    {
        $items = $this->getStreamItems(StreamEnums::ARCHIVE->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? data_get($item, 'keys.0');
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => 'archive',
                'current_status' => $data['action'] ?? 'archived',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'action' => $data['action'] ?? 'archive',
                'reason' => $data['reason'] ?? null,
                'user_address' => $data['user_address'] ?? null,
                'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'archived_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementArchive::getHashableFields()));

            ProcurementArchive::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            $count++;
        }

        Log::info('NormalizedTableSync: archives synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync procurement.metadata.corrections stream → procurement_metadata_corrections table.
     */
    private function syncMetadataCorrections(): int
    {
        $items = $this->getStreamItems(StreamEnums::PROCUREMENTS_CORRECTIONS->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $prNumber = $data['pr_number'] ?? data_get($item, 'keys.0');
            if (empty($prNumber)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => $data['corrected_category'] ?? $data['original_category'] ?? 'goods',
                'procurement_mode' => $data['corrected_procurement_mode'] ?? $data['original_procurement_mode'] ?? 'competitive_bidding',
                'current_stage' => 'metadata_correction',
                'current_status' => 'corrected',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'correction_type' => $data['correction_type'] ?? 'metadata',
                'reason' => $data['reason'] ?? '',
                'corrected_by' => $data['corrected_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'original_title' => $data['original_title'] ?? null,
                'original_description' => $data['original_description'] ?? null,
                'original_abc_amount' => isset($data['original_abc_amount']) ? (float) $data['original_abc_amount'] : null,
                'original_funding_source' => $data['original_funding_source'] ?? null,
                'original_category' => $data['original_category'] ?? null,
                'original_procurement_mode' => $data['original_procurement_mode'] ?? null,
                'original_office' => $data['original_office'] ?? null,
                'original_end_user' => $data['original_end_user'] ?? null,
                'original_delivery_date' => $this->normaliseDate($data['original_delivery_date'] ?? null),
                'original_bac_resolution_number' => $data['original_bac_resolution_number'] ?? null,
                'original_bac_resolution_date' => $this->normaliseDate($data['original_bac_resolution_date'] ?? null),
                'original_approved_by' => $data['original_approved_by'] ?? null,
                'original_approval_date' => $this->normaliseDate($data['original_approval_date'] ?? null),
                'corrected_title' => $data['corrected_title'] ?? null,
                'corrected_description' => $data['corrected_description'] ?? null,
                'corrected_abc_amount' => isset($data['corrected_abc_amount']) ? (float) $data['corrected_abc_amount'] : null,
                'corrected_funding_source' => $data['corrected_funding_source'] ?? null,
                'corrected_category' => $data['corrected_category'] ?? null,
                'corrected_procurement_mode' => $data['corrected_procurement_mode'] ?? null,
                'corrected_office' => $data['corrected_office'] ?? null,
                'corrected_end_user' => $data['corrected_end_user'] ?? null,
                'corrected_delivery_date' => $this->normaliseDate($data['corrected_delivery_date'] ?? null),
                'corrected_bac_resolution_number' => $data['corrected_bac_resolution_number'] ?? null,
                'corrected_bac_resolution_date' => $this->normaliseDate($data['corrected_bac_resolution_date'] ?? null),
                'corrected_approved_by' => $data['corrected_approved_by'] ?? null,
                'corrected_approval_date' => $this->normaliseDate($data['corrected_approval_date'] ?? null),
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'corrected_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementMetadataCorrection::getHashableFields()));

            ProcurementMetadataCorrection::updateOrCreate(
                ['txid' => $txid],
                [
                    ...$attributes,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                ]
            );

            $count++;
        }

        Log::info('NormalizedTableSync: metadata corrections synced', ['count' => $count]);

        return $count;
    }

    /**
     * Sync file.metadata stream → files table
     */
    private function syncFileMetadata(): int
    {
        $items = $this->getStreamItems(StreamEnums::FILE_METADATA->value);
        $count = 0;

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $fileKey = $data['file_key'] ?? null;
            if (empty($fileKey)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            $blocktime = $item['blocktime'] ?? null;

            // Compute hash
            $hashableData = $this->extractFields($data, File::getHashableFields());
            $dataHash = $this->computeHash($hashableData);

            // Check for duplicate
            $exists = File::where('file_key', $fileKey)->exists();
            if ($exists) {
                continue;
            }

            File::create([
                'file_key' => $fileKey,
                'filename' => $data['filename'] ?? 'unknown',
                'mime_type' => $data['mime_type'] ?? 'application/octet-stream',
                'size' => (int) ($data['size'] ?? 0),
                'hash' => $data['hash'] ?? '',
                'storage_method' => $data['storage_method'] ?? 'blockchain',
                'data_txid' => $data['data_txid'] ?? null,
                'data_key' => $data['data_key'] ?? null,
                'pr_number' => $data['pr_number'] ?? null,
                'stage' => $data['stage'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'additional_metadata' => $data['additional_metadata'] ?? null,
                'stored_at' => $data['stored_at'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
            ]);

            $count++;
        }

        Log::info('NormalizedTableSync: files synced', ['count' => $count]);

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PR-SPECIFIC SYNC METHODS (for instant updates after blockchain write)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Sync archives for a specific PR only.
     */
    private function syncArchivesForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::ARCHIVE->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $itemPrNumber = $data['pr_number'] ?? data_get($item, 'keys.0');
            if ($itemPrNumber !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $prNumber,
                'category' => 'goods',
                'procurement_mode' => 'competitive_bidding',
                'current_stage' => 'archive',
                'current_status' => $data['action'] ?? 'archived',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'action' => $data['action'] ?? 'archive',
                'reason' => $data['reason'] ?? null,
                'user_address' => $data['user_address'] ?? null,
                'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'archived_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementArchive::getHashableFields()));

            ProcurementArchive::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync metadata corrections for a specific PR only.
     */
    private function syncMetadataCorrectionsForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::PROCUREMENTS_CORRECTIONS->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $itemPrNumber = $data['pr_number'] ?? data_get($item, 'keys.0');
            if ($itemPrNumber !== $prNumber) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $procurement = $this->findOrCreateProcurement($prNumber, [
                'title' => $data['procurement_title'] ?? $prNumber,
                'category' => $data['corrected_category'] ?? $data['original_category'] ?? 'goods',
                'procurement_mode' => $data['corrected_procurement_mode'] ?? $data['original_procurement_mode'] ?? 'competitive_bidding',
                'current_stage' => 'metadata_correction',
                'current_status' => 'corrected',
            ]);

            $attributes = [
                'procurement_id' => $procurement->id,
                'correction_type' => $data['correction_type'] ?? 'metadata',
                'reason' => $data['reason'] ?? '',
                'corrected_by' => $data['corrected_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'original_title' => $data['original_title'] ?? null,
                'original_description' => $data['original_description'] ?? null,
                'original_abc_amount' => isset($data['original_abc_amount']) ? (float) $data['original_abc_amount'] : null,
                'original_funding_source' => $data['original_funding_source'] ?? null,
                'original_category' => $data['original_category'] ?? null,
                'original_procurement_mode' => $data['original_procurement_mode'] ?? null,
                'original_office' => $data['original_office'] ?? null,
                'original_end_user' => $data['original_end_user'] ?? null,
                'original_delivery_date' => $this->normaliseDate($data['original_delivery_date'] ?? null),
                'original_bac_resolution_number' => $data['original_bac_resolution_number'] ?? null,
                'original_bac_resolution_date' => $this->normaliseDate($data['original_bac_resolution_date'] ?? null),
                'original_approved_by' => $data['original_approved_by'] ?? null,
                'original_approval_date' => $this->normaliseDate($data['original_approval_date'] ?? null),
                'corrected_title' => $data['corrected_title'] ?? null,
                'corrected_description' => $data['corrected_description'] ?? null,
                'corrected_abc_amount' => isset($data['corrected_abc_amount']) ? (float) $data['corrected_abc_amount'] : null,
                'corrected_funding_source' => $data['corrected_funding_source'] ?? null,
                'corrected_category' => $data['corrected_category'] ?? null,
                'corrected_procurement_mode' => $data['corrected_procurement_mode'] ?? null,
                'corrected_office' => $data['corrected_office'] ?? null,
                'corrected_end_user' => $data['corrected_end_user'] ?? null,
                'corrected_delivery_date' => $this->normaliseDate($data['corrected_delivery_date'] ?? null),
                'corrected_bac_resolution_number' => $data['corrected_bac_resolution_number'] ?? null,
                'corrected_bac_resolution_date' => $this->normaliseDate($data['corrected_bac_resolution_date'] ?? null),
                'corrected_approved_by' => $data['corrected_approved_by'] ?? null,
                'corrected_approval_date' => $this->normaliseDate($data['corrected_approval_date'] ?? null),
                'txid' => $txid,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'corrected_at' => $this->normaliseDate($data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now())),
            ];

            $dataHash = $this->computeHash($this->extractFields($attributes, ProcurementMetadataCorrection::getHashableFields()));

            ProcurementMetadataCorrection::updateOrCreate(
                ['txid' => $txid],
                [...$attributes, 'data_hash' => $dataHash, 'blockchain_hash' => $dataHash]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync file metadata for a specific PR only.
     */
    private function syncFilesForPr(string $prNumber): int
    {
        $items = $this->getStreamItemsForKey(StreamEnums::FILE_METADATA->value, $prNumber);
        $count = 0;

        // Sort by blocktime to ensure correct order
        usort($items, fn ($a, $b) => ($a['blocktime'] ?? 0) - ($b['blocktime'] ?? 0));

        // Track seen txids to skip duplicates
        $seenTxids = [];

        foreach ($items as $item) {
            $data = $item['data']['json'] ?? [];
            if (empty($data)) {
                continue;
            }

            $filePrNumber = $data['pr_number'] ?? null;
            if ($filePrNumber !== $prNumber) {
                continue;
            }

            $fileKey = $data['file_key'] ?? null;
            if (empty($fileKey)) {
                continue;
            }

            $txid = $item['txid'] ?? '';
            if (empty($txid) || in_array($txid, $seenTxids, true)) {
                continue;
            }
            $seenTxids[] = $txid;

            $blocktime = $item['blocktime'] ?? null;

            $hashableData = $this->extractFields($data, File::getHashableFields());
            $dataHash = $this->computeHash($hashableData);

            $exists = File::where('file_key', $fileKey)->exists();
            if ($exists) {
                continue;
            }

            File::create([
                'file_key' => $fileKey,
                'filename' => $data['filename'] ?? 'unknown',
                'mime_type' => $data['mime_type'] ?? 'application/octet-stream',
                'size' => (int) ($data['size'] ?? 0),
                'hash' => $data['hash'] ?? '',
                'storage_method' => $data['storage_method'] ?? 'blockchain',
                'data_txid' => $data['data_txid'] ?? null,
                'data_key' => $data['data_key'] ?? null,
                'pr_number' => $filePrNumber,
                'stage' => $data['stage'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'additional_metadata' => $data['additional_metadata'] ?? null,
                'stored_at' => $data['stored_at'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
            ]);

            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Get all items from a blockchain stream.
     */
    private function getStreamItems(string $stream): array
    {
        try {
            $items = $this->manager->liststreamitems($stream, false, 10000);

            return is_array($items) ? $items : [];
        } catch (\Exception $e) {
            Log::error('NormalizedTableSync: failed to read stream', [
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract only the fields that should be included in the hash.
     */
    private function extractFields(array $data, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $data[$field] ?? null;
        }

        return $result;
    }

    private function normaliseDate(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            try {
                return date('Y-m-d H:i:s', strtotime($value));
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Compute SHA-256 hash of data.
     */
    private function computeHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

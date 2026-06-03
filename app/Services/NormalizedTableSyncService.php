<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\File;
use App\Models\Procurement;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementStage;
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
        $counts = [
            'procurements' => 0,
            'stages' => 0,
            'documents' => 0,
            'events' => 0,
            'corrections' => 0,
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

        // 6. Sync file metadata → files table
        $counts['files'] = $this->syncFileMetadata();

        Log::info('NormalizedTableSync: sync completed', $counts);

        return $counts;
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

            // Compute hash from blockchain data
            $hashableData = $this->extractFields($data, Procurement::getHashableFields());
            $dataHash = $this->computeHash($hashableData);

            Procurement::updateOrCreate(
                ['pr_number' => $prNumber],
                [
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
                    'initiated_at' => $data['created_at'] ?? null,
                    'txid' => $txid,
                    'data_hash' => $dataHash,
                    'blockchain_hash' => $dataHash,
                    'is_blockchain_verified' => true,
                    'last_verified_at' => now(),
                    'has_breach' => false,
                    'last_updated_at' => $blocktime ? date('Y-m-d H:i:s', $blocktime) : now(),
                ]
            );

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
            $procurement = Procurement::firstOrCreate(
                ['pr_number' => $prNumber],
                [
                    'title' => $data['procurement_title'] ?? $prNumber,
                    'current_stage' => $data['stage'] ?? 'unknown',
                    'current_status' => $data['current_status'] ?? 'unknown',
                    'category' => $data['category'] ?? null,
                    'procurement_mode' => $data['procurement_mode'] ?? null,
                ]
            );

            // Compute hash
            $hashableData = $this->extractFields($data, ProcurementStage::getHashableFields());
            $hashableData['procurement_id'] = $procurement->id;
            $dataHash = $this->computeHash($hashableData);

            // Check for duplicate txid
            $exists = ProcurementStage::where('txid', $txid)->exists();
            if ($exists) {
                continue;
            }

            ProcurementStage::create([
                'procurement_id' => $procurement->id,
                'stage' => $data['stage'] ?? 'unknown',
                'status' => $data['current_status'] ?? 'unknown',
                'previous_status' => $data['previous_status'] ?? null,
                'entered_at' => $data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Update procurement current stage
            $procurement->update([
                'current_stage' => $data['stage'] ?? $procurement->current_stage,
                'current_status' => $data['current_status'] ?? $procurement->current_status,
                'previous_status' => $data['previous_status'] ?? $procurement->current_status,
                'last_updated_at' => now(),
            ]);

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
            $procurement = Procurement::firstOrCreate(
                ['pr_number' => $prNumber],
                [
                    'title' => $data['procurement_title'] ?? $prNumber,
                    'current_stage' => $data['stage'] ?? 'unknown',
                    'current_status' => 'active',
                ]
            );

            // Compute hash
            $hashableData = $this->extractFields($data, ProcurementDocument::getHashableFields());
            $hashableData['procurement_id'] = $procurement->id;
            $dataHash = $this->computeHash($hashableData);

            // Check for duplicate txid
            $exists = ProcurementDocument::where('txid', $txid)->exists();
            if ($exists) {
                continue;
            }

            ProcurementDocument::create([
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
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'is_active' => true,
                'uploaded_at' => $data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
            ]);

            // Update documents count
            $procurement->increment('documents_count');
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
            $procurement = Procurement::firstOrCreate(
                ['pr_number' => $prNumber],
                [
                    'title' => $data['procurement_title'] ?? $prNumber,
                    'current_stage' => $data['stage'] ?? 'unknown',
                    'current_status' => 'active',
                ]
            );

            // Compute hash
            $hashableData = $this->extractFields($data, ProcurementEvent::getHashableFields());
            $hashableData['procurement_id'] = $procurement->id;
            $dataHash = $this->computeHash($hashableData);

            // Check for duplicate txid
            $exists = ProcurementEvent::where('txid', $txid)->exists();
            if ($exists) {
                continue;
            }

            ProcurementEvent::create([
                'procurement_id' => $procurement->id,
                'event_type' => $data['event_type'] ?? 'unknown',
                'category' => $data['category'] ?? 'general',
                'severity' => $data['severity'] ?? 'info',
                'details' => $data['details'] ?? '',
                'stage' => $data['stage'] ?? 'unknown',
                'document_count' => (int) ($data['document_count'] ?? 0),
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => $data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
            ]);

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
            $procurement = Procurement::firstOrCreate(
                ['pr_number' => $prNumber],
                [
                    'title' => $data['procurement_title'] ?? $prNumber,
                    'current_stage' => 'correction',
                    'current_status' => 'corrected',
                ]
            );

            // Compute hash
            $hashableData = $this->extractFields($data, ProcurementCorrection::getHashableFields());
            $hashableData['procurement_id'] = $procurement->id;
            $dataHash = $this->computeHash($hashableData);

            // Check for duplicate txid
            $exists = ProcurementCorrection::where('txid', $txid)->exists();
            if ($exists) {
                continue;
            }

            ProcurementCorrection::create([
                'procurement_id' => $procurement->id,
                'correction_type' => $data['correction_type'] ?? 'unknown',
                'action' => $data['action'] ?? 'correct',
                'reason' => $data['reason'] ?? '',
                'original_txid' => $data['original_txid'] ?? '',
                'original_document_hash' => $data['original_document_hash'] ?? '',
                'corrected_by' => $data['corrected_by'] ?? '',
                'user_address' => $data['user_address'] ?? null,
                'txid' => $txid,
                'data_hash' => $dataHash,
                'blockchain_hash' => $dataHash,
                'is_blockchain_verified' => true,
                'last_verified_at' => now(),
                'has_breach' => false,
                'corrected_metadata' => $data['corrected_metadata'] ?? null,
                'corrected_at' => $data['timestamp'] ?? ($blocktime ? date('Y-m-d H:i:s', $blocktime) : now()),
            ]);

            $count++;
        }

        Log::info('NormalizedTableSync: corrections synced', ['count' => $count]);

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

    /**
     * Compute SHA-256 hash of data.
     */
    private function computeHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

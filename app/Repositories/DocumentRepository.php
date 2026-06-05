<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\DocumentRepositoryInterface;
use App\DataTransferObjects\DocumentData;
use App\Enums\StreamEnums;
use App\Models\ProcurementDocument;
use App\Services\Manager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repository for procurement documents
 * Reads from DB (mirror of blockchain).
 */
class DocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new document record (writes to blockchain)
     */
    public function create(DocumentData $data): string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::DOCUMENTS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Document published to blockchain', ['txid' => $txid]);

            return $txid ?? '';
        } catch (\Exception $e) {
            Log::error('Failed to publish document', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Find documents by PR number from DB.
     * Returns Collection of DocumentData objects.
     */
    public function findByProcurement(string $prNumber): Collection
    {
        return ProcurementDocument::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(fn ($d) => DocumentData::fromBlockchainArray([
                'pr_number' => $d->procurement->pr_number ?? '',
                'procurement_title' => $d->procurement->title ?? '',
                'user_address' => $d->user_address ?? '',
                'stage' => $d->stage,
                'status' => '',
                'document_type' => $d->document_type,
                'file_key' => $d->file_key,
                'file_name' => $d->filename,
                'file_size' => $d->file_size,
                'mime_type' => $d->mime_type ?? '',
                'hash' => $d->hash,
                'data_txid' => $d->txid ?? '',
                'metadata_txid' => '',
                'uploaded_by' => $d->uploaded_by,
                'timestamp' => $d->uploaded_at->toIso8601String(),
                'description' => $d->description,
            ]));
    }

    /**
     * Find documents by stage from DB.
     */
    public function findByStage(string $prNumber, string $stage): Collection
    {
        return ProcurementDocument::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->where('stage', $stage)
            ->orderByDesc('uploaded_at')
            ->get()
            ->map(fn ($d) => DocumentData::fromBlockchainArray([
                'pr_number' => $d->procurement->pr_number ?? '',
                'procurement_title' => $d->procurement->title ?? '',
                'user_address' => $d->user_address ?? '',
                'stage' => $d->stage,
                'status' => '',
                'document_type' => $d->document_type,
                'file_key' => $d->file_key,
                'file_name' => $d->filename,
                'file_size' => $d->file_size,
                'mime_type' => $d->mime_type ?? '',
                'hash' => $d->hash,
                'data_txid' => $d->txid ?? '',
                'metadata_txid' => '',
                'uploaded_by' => $d->uploaded_by,
                'timestamp' => $d->uploaded_at->toIso8601String(),
                'description' => $d->description,
            ]));
    }

    /**
     * Find a document by hash from DB.
     */
    public function findByHash(string $hash): ?DocumentData
    {
        $doc = ProcurementDocument::where('hash', $hash)->first();

        if (! $doc) {
            return null;
        }

        return DocumentData::fromBlockchainArray([
            'pr_number' => $doc->procurement->pr_number ?? '',
            'procurement_title' => $doc->procurement->title ?? '',
            'user_address' => $doc->user_address ?? '',
            'stage' => $doc->stage,
            'status' => '',
            'document_type' => $doc->document_type,
            'file_key' => $doc->file_key,
            'file_name' => $doc->filename,
            'file_size' => $doc->file_size,
            'mime_type' => $doc->mime_type ?? '',
            'hash' => $doc->hash,
            'data_txid' => $doc->txid ?? '',
            'metadata_txid' => '',
            'uploaded_by' => $doc->uploaded_by,
            'timestamp' => $doc->uploaded_at->toIso8601String(),
            'description' => $doc->description,
        ]);
    }

    /**
     * Find a document by file key from DB.
     */
    public function findByFileKey(string $fileKey): ?DocumentData
    {
        $doc = ProcurementDocument::where('file_key', $fileKey)->first();

        if (! $doc) {
            return null;
        }

        return DocumentData::fromBlockchainArray([
            'pr_number' => $doc->procurement->pr_number ?? '',
            'procurement_title' => $doc->procurement->title ?? '',
            'user_address' => $doc->user_address ?? '',
            'stage' => $doc->stage,
            'status' => '',
            'document_type' => $doc->document_type,
            'file_key' => $doc->file_key,
            'file_name' => $doc->filename,
            'file_size' => $doc->file_size,
            'mime_type' => $doc->mime_type ?? '',
            'hash' => $doc->hash,
            'data_txid' => $doc->txid ?? '',
            'metadata_txid' => '',
            'uploaded_by' => $doc->uploaded_by,
            'timestamp' => $doc->uploaded_at->toIso8601String(),
            'description' => $doc->description,
        ]);
    }

    /**
     * Find a document by transaction ID from DB.
     */
    public function findByTxid(string $txid): ?DocumentData
    {
        $doc = ProcurementDocument::where('txid', $txid)->first();

        if (! $doc) {
            return null;
        }

        return DocumentData::fromBlockchainArray([
            'pr_number' => $doc->procurement->pr_number ?? '',
            'procurement_title' => $doc->procurement->title ?? '',
            'user_address' => $doc->user_address ?? '',
            'stage' => $doc->stage,
            'status' => '',
            'document_type' => $doc->document_type,
            'file_key' => $doc->file_key,
            'file_name' => $doc->filename,
            'file_size' => $doc->file_size,
            'mime_type' => $doc->mime_type ?? '',
            'hash' => $doc->hash,
            'data_txid' => $doc->txid ?? '',
            'metadata_txid' => '',
            'uploaded_by' => $doc->uploaded_by,
            'timestamp' => $doc->uploaded_at->toIso8601String(),
            'description' => $doc->description,
        ]);
    }

    /**
     * Count documents by PR number from DB.
     */
    public function countByProcurement(string $prNumber): int
    {
        return ProcurementDocument::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->count();
    }

    /**
     * Verify document integrity from DB.
     */
    public function verifyIntegrity(string $prNumber, string $expectedHash): bool
    {
        return ProcurementDocument::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->where('hash', $expectedHash)
            ->exists();
    }

    /**
     * Find recent documents from DB.
     */
    public function findRecent(int $limit = 10): Collection
    {
        return ProcurementDocument::orderByDesc('uploaded_at')
            ->take($limit)
            ->get()
            ->map(fn ($d) => DocumentData::fromBlockchainArray([
                'pr_number' => $d->procurement->pr_number ?? '',
                'procurement_title' => $d->procurement->title ?? '',
                'user_address' => $d->user_address ?? '',
                'stage' => $d->stage,
                'status' => '',
                'document_type' => $d->document_type,
                'file_key' => $d->file_key,
                'file_name' => $d->filename,
                'file_size' => $d->file_size,
                'mime_type' => $d->mime_type ?? '',
                'hash' => $d->hash,
                'data_txid' => $d->txid ?? '',
                'metadata_txid' => '',
                'uploaded_by' => $d->uploaded_by,
                'timestamp' => $d->uploaded_at->toIso8601String(),
                'description' => $d->description,
            ]));
    }

    /**
     * Get all documents from DB.
     */
    public function all(int $limit = 5000): Collection
    {
        return ProcurementDocument::orderByDesc('uploaded_at')
            ->take($limit)
            ->get()
            ->map(fn ($d) => DocumentData::fromBlockchainArray([
                'pr_number' => $d->procurement->pr_number ?? '',
                'procurement_title' => $d->procurement->title ?? '',
                'user_address' => $d->user_address ?? '',
                'stage' => $d->stage,
                'status' => '',
                'document_type' => $d->document_type,
                'file_key' => $d->file_key,
                'file_name' => $d->filename,
                'file_size' => $d->file_size,
                'mime_type' => $d->mime_type ?? '',
                'hash' => $d->hash,
                'data_txid' => $d->txid ?? '',
                'metadata_txid' => '',
                'uploaded_by' => $d->uploaded_by,
                'timestamp' => $d->uploaded_at->toIso8601String(),
                'description' => $d->description,
            ]));
    }
}

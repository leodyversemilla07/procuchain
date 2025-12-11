<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\DocumentRepositoryInterface;
use App\DataTransferObjects\DocumentData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.documents stream
 */
class DocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new document record
     */
    public function create(DocumentData $data): string
    {
        try {
            $txid = $this->multichain->publish(
                StreamEnums::DOCUMENTS->value,
                $data->prNumber,
                ['json' => $data->toBlockchainArray()]
            );

            Log::info('Document published to blockchain', [
                'pr_number' => $data->prNumber,
                'file_key' => $data->fileKey,
                'stream' => StreamEnums::DOCUMENTS->value,
                'txid' => $txid,
            ]);

            return $txid ?? '';
        } catch (\Exception $e) {
            Log::error('Failed to publish document to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Find recent documents (limit)
     *
     * @return DocumentData[]
     */
    public function findRecent(int $limit = 10): array
    {
        $allDocuments = $this->all();

        usort($allDocuments, fn ($a, $b) => $b->timestamp->timestamp - $a->timestamp->timestamp);

        return array_slice($allDocuments, 0, $limit);
    }

    /**
     * Find documents by procurement ID
     *
     * @return Collection<int, DocumentData>
     */
    public function findByProcurement(string $prNumber): Collection
    {
        $allDocuments = $this->all();

        return collect(array_filter(
            $allDocuments,
            fn (DocumentData $document): bool => $document->prNumber === $prNumber
        ));
    }

    /**
     * Find documents by stage
     *
     * @return Collection<int, DocumentData>
     */
    public function findByStage(string $prNumber, string $stage): Collection
    {
        return $this->findByProcurement($prNumber)
            ->filter(fn (DocumentData $doc) => $doc->stage === $stage);
    }

    /**
     * Find a document by transaction ID
     */
    public function findByTxid(string $txid): ?DocumentData
    {
        $allDocuments = $this->all();

        foreach ($allDocuments as $document) {
            if ($document->dataTxid === $txid) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Find a document by file key
     *
     * Note: Blockchain streams don't support indexed queries,
     * so this iterates through all documents. Consider caching
     * for high-frequency lookups.
     */
    public function findByFileKey(string $fileKey): ?DocumentData
    {
        $allDocuments = $this->all();

        foreach ($allDocuments as $document) {
            if ($document->fileKey === $fileKey) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Get all documents
     *
     * Optimizations applied per MultiChain docs:
     * - verbose=false (60% faster data transfer)
     * - local-ordering=true (faster query execution)
     *
     * @return DocumentData[]
     */
    public function all(int $limit = 10000, int $offset = 0): array
    {
        try {
            // OPTIMIZATION: verbose=false for faster response (60% faster)
            // OPTIMIZATION: local-ordering=true for faster execution
            $items = $this->multichain->liststreamitems(
                StreamEnums::DOCUMENTS->value,
                false,  // verbose=false - we only need the data
                $limit,
                $offset,
                true    // local-ordering for faster queries
            );

            if (! $items) {
                return [];
            }

            $documents = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $documents[] = DocumentData::fromBlockchainArray($item['data']['json']);
                }
            }

            return $documents;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all documents', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Find a document by file hash
     */
    public function findByHash(string $hash): ?DocumentData
    {
        $allDocuments = $this->all();

        foreach ($allDocuments as $document) {
            if ($document->fileHash === $hash) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Count documents for a procurement
     */
    public function countByProcurement(string $prNumber): int
    {
        return $this->findByProcurement($prNumber)->count();
    }

    /**
     * Verify document integrity by comparing hash
     */
    public function verifyIntegrity(string $prNumber, string $expectedHash): bool
    {
        $documents = $this->findByProcurement($prNumber);

        foreach ($documents as $document) {
            if ($document->fileHash === $expectedHash) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get document history for a procurement
     *
     * @return DocumentData[]
     */
    public function getHistory(string $prNumber): array
    {
        try {
            $items = $this->multichain->liststreamitems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if (! $items) {
                return [];
            }

            $history = [];
            foreach ($items as $item) {
                if (isset($item['data']['json'])) {
                    $doc = DocumentData::fromBlockchainArray($item['data']['json']);
                    if ($doc->prNumber === $prNumber) {
                        $history[] = $doc;
                    }
                }
            }

            return $history;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve document history', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

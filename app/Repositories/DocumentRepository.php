<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\DocumentData;
use App\Enums\StreamEnums;
use App\Services\Manager;
use Illuminate\Support\Facades\Log;

/**
 * Repository for managing procurement.documents stream
 */
class DocumentRepository
{
    public function __construct(
        private Manager $multichain
    ) {}

    /**
     * Create a new document record
     */
    public function create(DocumentData $data): ?string
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

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish document to blockchain', [
                'pr_number' => $data->prNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
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
     * @return DocumentData[]
     */
    public function findByProcurement(string $prNumber): array
    {
        $allDocuments = $this->all();

        return array_filter(
            $allDocuments,
            fn (DocumentData $document): bool => $document->prNumber === $prNumber
        );
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
     * Get all documents
     *
     * @return DocumentData[]
     */
    public function all(): array
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

<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\DocumentData;
use Illuminate\Support\Collection;

/**
 * Interface for document repository operations
 *
 * Implementations handle CRUD operations for documents on blockchain
 */
interface DocumentRepositoryInterface
{
    /**
     * Create a new document record
     *
     * @param  DocumentData  $document  Document data to store
     * @return string Transaction ID from blockchain
     *
     * @throws \App\Exceptions\BlockchainException If creation fails
     */
    public function create(DocumentData $document): string;

    /**
     * Find all documents for a procurement
     *
     * @param  string  $prNumber  The PR number to search for
     * @return Collection<int, DocumentData>
     */
    public function findByProcurement(string $prNumber): Collection;

    /**
     * Find documents by stage
     *
     * @param  string  $prNumber  The PR number
     * @param  string  $stage  The stage to filter by
     * @return Collection<int, DocumentData>
     */
    public function findByStage(string $prNumber, string $stage): Collection;

    /**
     * Find a specific document by hash
     *
     * @param  string  $hash  The document hash
     */
    public function findByHash(string $hash): ?DocumentData;

    /**
     * Get document count for a procurement
     *
     * @param  string  $prNumber  The PR number
     * @return int Number of documents
     */
    public function countByProcurement(string $prNumber): int;

    /**
     * Verify document integrity by comparing hash
     *
     * @param  string  $prNumber  The PR number
     * @param  string  $expectedHash  The expected hash
     * @return bool True if document hash matches
     */
    public function verifyIntegrity(string $prNumber, string $expectedHash): bool;
}

<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\ProcurementData;
use Illuminate\Support\Collection;

/**
 * Interface for procurement data repository operations
 *
 * Implementations handle CRUD operations for procurement metadata on blockchain
 */
interface ProcurementRepositoryInterface
{
    /**
     * Create a new procurement record
     *
     * @param  ProcurementData  $procurement  Procurement data to store
     *
     * @throws \App\Exceptions\BlockchainException If creation fails
     */
    public function create(ProcurementData $procurement): void;

    /**
     * Find a procurement by its PR number
     *
     * @param  string  $prNumber  The PR number to search for
     * @return ProcurementData|null The procurement data or null if not found
     */
    public function findByProcurement(string $prNumber): ?ProcurementData;

    /**
     * Find multiple procurements by their PR numbers.
     *
     * @param  array<string>  $prNumbers
     * @return array<string, ProcurementData|null>
     */
    public function findManyByProcurement(array $prNumbers): array;

    /**
     * Get all procurement records
     *
     * @return Collection<int, ProcurementData>
     */
    public function all(): Collection;

    /**
     * Update a procurement record
     *
     * @param  ProcurementData  $procurement  Updated procurement data
     *
     * @throws \App\Exceptions\BlockchainException If update fails
     */
    public function update(ProcurementData $procurement): void;

    /**
     * Get the complete history of a procurement
     *
     * @param  string  $prNumber  The PR number to get history for
     * @return Collection<int, ProcurementData> All versions of the procurement
     */
    public function getHistory(string $prNumber): Collection;

    /**
     * Check if a procurement exists
     *
     * @param  string  $prNumber  The PR number to check
     * @return bool True if the procurement exists
     */
    public function exists(string $prNumber): bool;
}

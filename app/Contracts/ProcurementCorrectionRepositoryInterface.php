<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\ProcurementCorrectionData;

/**
 * Interface for procurement correction repository operations.
 *
 * Implementations handle CRUD operations for procurement metadata corrections
 * on the procurement.corrections blockchain stream.
 */
interface ProcurementCorrectionRepositoryInterface
{
    /**
     * Create a new procurement correction record.
     *
     * @param  ProcurementCorrectionData  $data  Correction data to publish
     * @return string|null Blockchain transaction ID, or null on failure
     */
    public function create(ProcurementCorrectionData $data): ?string;

    /**
     * Find all corrections for a given procurement.
     *
     * @param  string  $prNumber  The PR number to search for
     * @return ProcurementCorrectionData[]
     */
    public function findByProcurement(string $prNumber): array;

    /**
     * Get all procurement correction records.
     *
     * @return ProcurementCorrectionData[]
     */
    public function all(): array;

    /**
     * Get the full correction history for a procurement.
     *
     * @param  string  $prNumber  The PR number
     * @return ProcurementCorrectionData[]
     */
    public function getHistory(string $prNumber): array;

    /**
     * Check if a procurement has any corrections.
     *
     * @param  string  $prNumber  The PR number
     */
    public function hasCorrections(string $prNumber): bool;

    /**
     * Get the most recent correction for a procurement.
     *
     * @param  string  $prNumber  The PR number
     */
    public function getLatest(string $prNumber): ?ProcurementCorrectionData;
}

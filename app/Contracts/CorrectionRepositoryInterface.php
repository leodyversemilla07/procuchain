<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\CorrectionData;

/**
 * Interface for document correction repository operations.
 *
 * Implementations handle CRUD operations for document corrections on the
 * procurement.corrections blockchain stream.
 */
interface CorrectionRepositoryInterface
{
    /**
     * Create a new document correction record.
     *
     * @param  CorrectionData  $data  Correction data to publish
     * @return string|null Blockchain transaction ID, or null on failure
     */
    public function create(CorrectionData $data): ?string;

    /**
     * Find all corrections for a given procurement.
     *
     * @param  string  $prNumber  The PR number to search for
     * @return CorrectionData[]
     */
    public function findByProcurement(string $prNumber): array;

    /**
     * Find corrections by the original transaction ID they correct.
     *
     * @param  string  $originalTxid  The blockchain transaction ID being corrected
     * @return CorrectionData[]
     */
    public function findByOriginalTxid(string $originalTxid): array;

    /**
     * Get all correction records.
     *
     * @return CorrectionData[]
     */
    public function all(): array;

    /**
     * Get the full correction history for a procurement.
     *
     * @param  string  $prNumber  The PR number
     * @return CorrectionData[]
     */
    public function getHistory(string $prNumber): array;
}

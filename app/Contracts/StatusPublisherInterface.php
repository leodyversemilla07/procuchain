<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Exceptions\BlockchainException;

/**
 * Interface for publishing status updates to the blockchain
 *
 * Implementations handle status transitions and blockchain recording
 */
interface StatusPublisherInterface
{
    /**
     * Publish a status update
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $stage  Stage identifier
     * @param  StatusEnums  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  StatusEnums|null  $previousStatus  Previous status
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array{
     *     success: bool,
     *     status_txid: string,
     *     stage: string,
     *     status: string,
     *     timestamp: string
     * }
     *
     * @throws BlockchainException If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $currentStatus,
        string $userAddress,
        ?StatusEnums $previousStatus = null,
        ?array $metadata = null
    ): array;

    /**
     * Publish a completion status for a stage
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $stage  Stage identifier
     * @param  string  $userAddress  User blockchain address
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array<string, mixed>
     *
     * @throws BlockchainException If publication fails
     */
    public function publishCompletion(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        string $userAddress,
        ?array $metadata = null
    ): array;
}

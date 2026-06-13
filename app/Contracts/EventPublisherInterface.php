<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\BlockchainException;

/**
 * Interface for publishing events to the blockchain audit trail
 *
 * Implementations handle event recording for the procurement timeline
 */
interface EventPublisherInterface
{
    /**
     * Publish an event to the timeline
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $stage  Stage identifier
     * @param  string  $eventType  Type of event
     * @param  string  $category  Event category
     * @param  string  $severity  Event severity (info, warning, error)
     * @param  string  $details  Event details
     * @param  int  $documentCount  Number of documents involved
     * @param  string  $userAddress  User blockchain address
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array{
     *     success: bool,
     *     event_txid: string,
     *     event_type: string,
     *     timestamp: string
     * }
     *
     * @throws BlockchainException If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        string $stage,
        string $eventType,
        string $category,
        string $severity,
        string $details,
        int $documentCount,
        string $userAddress,
        ?array $metadata = null
    ): array;

    /**
     * Publish a stage transition event
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $fromStage  Previous stage
     * @param  string  $toStage  New stage
     * @param  string  $userAddress  User blockchain address
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array<string, mixed>
     *
     * @throws BlockchainException If publication fails
     */
    public function publishStageTransition(
        string $prNumber,
        string $procurementTitle,
        string $fromStage,
        string $toStage,
        string $userAddress,
        ?array $metadata = null
    ): array;

    /**
     * Publish a document upload event
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $stage  Stage identifier
     * @param  string  $documentType  Type of document uploaded
     * @param  string  $filename  Name of the uploaded File
     * @param  string  $userAddress  User blockchain address
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array<string, mixed>
     *
     * @throws BlockchainException If publication fails
     */
    public function publishDocumentUpload(
        string $prNumber,
        string $procurementTitle,
        string $stage,
        string $documentType,
        string $filename,
        string $userAddress,
        ?array $metadata = null
    ): array;
}

<?php

namespace App\Services;

use App\Enums\StreamEnums;

/**
 * Handles blockchain interactions for the procurement system using MultiChain
 * 
 * This service manages document publishing, status updates, event logging,
 * and stage transitions for procurement processes on the blockchain.
 * All operations are recorded with appropriate metadata and timestamps.
 */
class BlockchainService
{
    /**
     * The MultiChain service instance for blockchain interactions
     *
     * @var MultichainService
     */
    private $multiChain;

    /**
     * The service for generating stream keys for blockchain operations
     *
     * @var StreamKeyService
     */
    protected $streamKeyService;

    /**
     * Creates a new BlockchainService instance
     *
     * @param MultichainService $multiChain The MultiChain service for blockchain operations
     * @param StreamKeyService $streamKeyService The service for generating stream keys
     */
    public function __construct(MultichainService $multiChain, StreamKeyService $streamKeyService)
    {
        $this->multiChain = $multiChain;
        $this->streamKeyService = $streamKeyService;
    }

    /**
     * Retrieves the MultiChain client instance
     *
     * @return MultichainService The MultiChain service instance
     */
    public function getClient(): MultichainService
    {
        return $this->multiChain;
    }

    /**
     * Publishes procurement documents and their metadata to the blockchain
     *
     * Documents are published to the DOCUMENTS stream with associated metadata,
     * updates the procurement status, and logs the upload event. Each document
     * is assigned a unique index and includes file-specific metadata.
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @param string $state Current stage in the procurement workflow
     * @param string $status Current status of the procurement process
     * @param array $metadataArray Document metadata including hashes and file information
     * @param string $userAddress Blockchain address of the publishing user
     * @throws \Exception When blockchain publishing fails
     * @return void
     */
    public function publishDocuments(
        string $procurementId,
        string $procurementTitle,
        string $state,
        string $status,
        array $metadataArray,
        string $userAddress
    ): void {
        $timestamp = now()->toIso8601String();
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

        $documentItems = [];
        foreach ($metadataArray as $index => $metadata) {
            $docData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'stage' => $state,
                'timestamp' => $timestamp,
                'document_index' => $index + 1,
                'document_type' => $metadata['document_type'],
                'hash' => $metadata['hash'],
                'file_key' => $metadata['file_key'],
                'user_address' => $userAddress,
                'file_size' => $metadata['file_size'],
                'stage_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
            ];
            $documentItems[] = [
                'key' => $streamKey,
                'data' => $docData,
            ];
        }
        $this->multiChain->publishMultiFrom($userAddress, StreamEnums::DOCUMENTS->value, $documentItems);

        $this->updateStatus($procurementId, $procurementTitle, $status, $state, $userAddress, $timestamp);

        $this->logEvent(
            $procurementId,
            $procurementTitle,
            $state,
            'Uploaded '.count($metadataArray)." finalized $state documents",
            count($metadataArray),
            $userAddress,
            'document_upload',
            'workflow',
            'info',
            $timestamp
        );
    }

    /**
     * Updates the procurement status on the blockchain
     *
     * Records status changes in the STATUS stream with timestamp and user information.
     * Used to track the progress and current state of procurement processes.
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @param string $status New status to be recorded
     * @param string $stage Current stage in the procurement workflow
     * @param string $userAddress Blockchain address of the user making the change
     * @param string $timestamp ISO 8601 formatted timestamp of the status update
     * @throws \Exception When blockchain publishing fails
     * @return void
     */
    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        string $status,
        string $stage,
        string $userAddress,
        string $timestamp
    ): void {
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
        $statusData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'current_status' => $status,
            'stage' => $stage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);
    }

    /**
     * Logs procurement-related events to the blockchain
     *
     * Records various events in the EVENTS stream for audit and tracking purposes.
     * Events include document uploads, status changes, and workflow transitions.
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @param string $stage Current stage in the procurement workflow
     * @param string $details Description of the event
     * @param int $documentCount Number of documents involved
     * @param string $userAddress Blockchain address of the user triggering the event
     * @param string $eventType Type of event (e.g., document_upload, stage_transition)
     * @param string $category Event category (e.g., workflow)
     * @param string $severity Event severity level (info, warning)
     * @param string $timestamp ISO 8601 formatted timestamp of the event
     * @throws \Exception When blockchain publishing fails
     * @return void
     */
    public function logEvent(
        string $procurementId,
        string $procurementTitle,
        string $stage,
        string $details,
        int $documentCount,
        string $userAddress,
        string $eventType,
        string $category,
        string $severity,
        string $timestamp
    ): void {
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
        $eventData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'event_type' => $eventType,
            'stage' => $stage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
            'details' => $details,
            'category' => $category,
            'severity' => $severity,
            'document_count' => $documentCount,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::EVENTS->value, $streamKey, $eventData);
    }

    /**
     * Manages procurement stage transitions on the blockchain
     *
     * Records both the status change and a transition event when a procurement
     * moves from one stage to another. This creates an audit trail of workflow
     * progression and maintains the current state.
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @param string $fromStatus Status before the transition
     * @param string $toStatus Status after the transition
     * @param string $fromStage Stage before the transition
     * @param string $toStage Stage after the transition
     * @param string $userAddress Blockchain address of the user initiating the transition
     * @param string $details Additional context about the transition
     * @throws \Exception When blockchain publishing fails
     * @return void
     */
    public function handleStageTransition(
        string $procurementId,
        string $procurementTitle,
        string $fromStatus,
        string $toStatus,
        string $fromStage,
        string $toStage,
        string $userAddress,
        string $details
    ): void {
        $timestamp = now()->toIso8601String();
        $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);

        $statusData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'previous_status' => $fromStatus,
            'current_status' => $toStatus,
            'previous_stage' => $fromStage,
            'stage' => $toStage,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];
        $this->multiChain->publishFrom($userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

        $this->logEvent(
            $procurementId,
            $procurementTitle,
            $toStage,
            "$details (from $fromStage:$fromStatus to $toStage:$toStatus)",
            0,
            $userAddress,
            'stage_transition',
            'workflow',
            'info',
            $timestamp
        );
    }
}

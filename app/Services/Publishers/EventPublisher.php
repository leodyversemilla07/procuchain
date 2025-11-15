<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\DataTransferObjects\EventData;
use App\Repositories\EventRepository;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Event Publisher Service
 *
 * Publishes events to the blockchain
 * - Records procurement events
 * - Publishes to procurement.events stream
 * - Provides audit trail
 */
class EventPublisher
{
    public function __construct(
        private EventRepository $events
    ) {}

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
     * @param  array|null  $metadata  Additional metadata
     * @return array Event transaction information
     *
     * @throws Exception If publication fails
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
    ): array {
        try {
            Log::info('EventPublisher: Publishing event', [
                'pr_number' => $prNumber,
                'event_type' => $eventType,
                'category' => $category,
                'severity' => $severity,
            ]);

            $event = new EventData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $stage,
                eventType: $eventType,
                category: $category,
                severity: $severity,
                details: $details,
                documentCount: $documentCount,
                userAddress: $userAddress,
                timestamp: now(),
                metadata: $metadata,
            );

            $txid = $this->events->create($event);

            Log::info('EventPublisher: Success', [
                'pr_number' => $prNumber,
                'event_txid' => $txid,
            ]);

            return [
                'success' => true,
                'event_txid' => $txid,
                'event_type' => $eventType,
                'category' => $category,
            ];
        } catch (Exception $e) {
            Log::error('EventPublisher: Failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish a document upload event
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $stage  Stage identifier
     * @param  string  $documentType  Type of document uploaded
     * @param  string  $userAddress  User blockchain address
     * @return array Event transaction information
     */
    public function publishDocumentUpload(
        string $prNumber,
        string $procurementTitle,
        string $stage,
        string $documentType,
        string $userAddress
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $stage,
            eventType: 'document_upload',
            category: 'document',
            severity: 'info',
            details: "Document uploaded: {$documentType}",
            documentCount: 1,
            userAddress: $userAddress,
        );
    }

    /**
     * Publish a stage transition event
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $fromStage  Previous stage
     * @param  string  $toStage  New stage
     * @param  string  $userAddress  User blockchain address
     * @return array Event transaction information
     */
    public function publishStageTransition(
        string $prNumber,
        string $procurementTitle,
        string $fromStage,
        string $toStage,
        string $userAddress
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $toStage,
            eventType: 'phase_transition',
            category: 'workflow',
            severity: 'info',
            details: "Stage transitioned from {$fromStage} to {$toStage}",
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ],
        );
    }

    /**
     * Publish a procurement completion event
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $userAddress  User blockchain address
     * @return array Event transaction information
     */
    public function publishCompletion(
        string $prNumber,
        string $procurementTitle,
        string $userAddress
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: 'completed',
            eventType: 'procurement_completed',
            category: 'milestone',
            severity: 'info',
            details: 'Procurement process completed',
            documentCount: 0,
            userAddress: $userAddress,
        );
    }
}

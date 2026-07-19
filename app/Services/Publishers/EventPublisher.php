<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Enums\Stream;
use App\Models\ProcurementEvent;
use App\Services\BlockchainRpcClient;
use Exception;
use Illuminate\Support\Facades\Log;

class EventPublisher
{
    public function __construct(
        private readonly BlockchainRpcClient $rpcClient,
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

            $event = new ProcurementEvent;
            $event->stage = $stage;
            $event->event_type = $eventType;
            $event->category = $category;
            $event->severity = $severity;
            $event->details = $details;
            $event->document_count = $documentCount;
            $event->user_address = $userAddress;
            $event->occurred_at = now();
            $event->metadata = $metadata;

            $key = ProcurementEvent::eventStreamKey($prNumber, $procurementTitle);

            $txid = $this->rpcClient->publish(
                Stream::EVENTS->value,
                $key,
                ['json' => $event->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new Exception('Blockchain event publish did not return a transaction id.');
            }

            Log::info('Event published to blockchain', ['txid' => $txid]);

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
     * @param  string  $filename  Name of the uploaded File
     * @param  string  $userAddress  User blockchain address
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array Event transaction information
     */
    public function publishDocumentUpload(
        string $prNumber,
        string $procurementTitle,
        string $stage,
        string $documentType,
        string $filename,
        string $userAddress,
        ?array $metadata = null
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $stage,
            eventType: 'document_upload',
            category: 'document',
            severity: 'info',
            details: "Document uploaded: {$documentType} ({$filename})",
            documentCount: 1,
            userAddress: $userAddress,
            metadata: array_merge($metadata ?? [], ['filename' => $filename]),
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
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @return array Event transaction information
     */
    public function publishStageTransition(
        string $prNumber,
        string $procurementTitle,
        string $fromStage,
        string $toStage,
        string $userAddress,
        ?array $metadata = null
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
            metadata: array_merge($metadata ?? [], [
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ]),
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

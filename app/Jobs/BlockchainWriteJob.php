<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Publishers\CorrectionPublisher;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementCorrectionPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles all asynchronous blockchain write operations via the Redis queue.
 *
 * Controllers dispatch this job instead of performing RPC writes synchronously
 * in the HTTP request cycle. The job stores its result (done/failed) in Redis
 * under the key "blockchain_job:{jobId}", which the status endpoint polls.
 *
 * Supported operations: upload_document | mark_stage_complete | initiate_procurement
 *                        correct_document | correct_procurement | skip_stage
 *                        repeat_stage | update_delivery_details | publish_decision
 */
class BlockchainWriteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum attempts before the job is marked failed */
    public int $tries = 3;

    /** @var int Seconds the job may run before timing out */
    public int $timeout = 90;

    public function __construct(
        public readonly string $operation,
        public readonly array $data,
        public readonly string $jobId,
    ) {}

    public function handle(
        ProcurementOrchestrator $orchestrator,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        CorrectionPublisher $correctionPublisher,
        ProcurementCorrectionPublisher $procurementCorrectionPublisher,
        ProcurementRepository $procurementRepository,
        DecisionPublisher $decisionPublisher,
    ): void {
        try {
            $result = match ($this->operation) {
                'upload_document' => $this->handleUploadDocument($orchestrator),
                'mark_stage_complete' => $this->handleMarkStageComplete($statusPublisher, $eventPublisher),
                'initiate_procurement' => $this->handleInitiateProcurement($orchestrator),
                'correct_document' => $this->handleCorrectDocument($correctionPublisher),
                'correct_procurement' => $this->handleCorrectProcurement($procurementCorrectionPublisher),
                'skip_stage' => $this->handleSkipStage($statusPublisher, $eventPublisher, $procurementRepository),
                'repeat_stage' => $this->handleRepeatStage($statusPublisher, $eventPublisher, $procurementRepository),
                'update_delivery_details' => $this->handleUpdateDeliveryDetails($eventPublisher, $procurementRepository),
                'publish_decision' => $this->handlePublishDecision($decisionPublisher, $procurementRepository),
                default => throw new Exception("Unknown blockchain operation: {$this->operation}"),
            };

            Cache::put("blockchain_job:{$this->jobId}", [
                'status' => 'done',
                'result' => $result,
            ], now()->addHour());

            Log::info("BlockchainWriteJob[{$this->operation}]: completed", [
                'job_id' => $this->jobId,
                'pr_number' => $this->data['pr_number'] ?? 'N/A',
            ]);
        } catch (Exception $e) {
            Cache::put("blockchain_job:{$this->jobId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], now()->addHour());

            Log::error("BlockchainWriteJob[{$this->operation}]: failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Operation handlers
    // -------------------------------------------------------------------------

    private function handleUploadDocument(ProcurementOrchestrator $orchestrator): array
    {
        $file = $this->reconstituteTempFile(
            $this->data['temp_file_path'],
            $this->data['original_filename'],
            $this->data['mime_type'],
        );

        try {
            $result = $orchestrator->publishDocumentWorkflow(
                procurementData: [
                    'pr_number' => $this->data['pr_number'],
                    'procurement_title' => $this->data['procurement_title'],
                    'user_address' => $this->data['user_address'],
                ],
                file: $file,
                documentData: [
                    'stage' => StageEnums::from($this->data['stage']),
                    'status' => $this->data['status'],
                    'document_type' => DocumentTypeEnums::from($this->data['document_type']),
                    'uploaded_by' => $this->data['uploaded_by'],
                    'description' => $this->data['description'] ?? null,
                    'stage_metadata' => $this->data['stage_metadata'] ?? [],
                ],
                statusData: [
                    'stage' => StageEnums::from($this->data['stage']),
                    'current_status' => StatusEnums::from($this->data['current_status']),
                    'metadata' => [
                        'documents_uploaded' => 1,
                        'uploaded_at' => now()->toIso8601String(),
                        'progressive_upload' => true,
                    ],
                ],
                eventData: [
                    'stage' => $this->data['stage'],
                    'event_type' => 'document_uploaded',
                    'category' => 'procurement',
                    'severity' => 'info',
                    'details' => sprintf(
                        'Document "%s" uploaded to stage "%s"',
                        DocumentTypeEnums::from($this->data['document_type'])->getDisplayName(),
                        StageEnums::from($this->data['stage'])->getDisplayName(),
                    ),
                    'document_count' => 1,
                ],
            );

            if (! $result['success']) {
                throw new Exception($result['error'] ?? 'Orchestrator returned failure');
            }

            return $result;
        } finally {
            $this->cleanupTempFile($this->data['temp_file_path']);
        }
    }

    private function handleMarkStageComplete(StatusPublisher $statusPublisher, EventPublisher $eventPublisher): array
    {
        // Procurement Initiation uses a slightly different publish pattern
        if (($this->data['operation_variant'] ?? '') === 'initiation_complete') {
            return $this->handleInitiationComplete($statusPublisher, $eventPublisher);
        }

        $stage = StageEnums::from($this->data['current_stage']);
        $completionStatus = StatusEnums::from($this->data['completion_status']);
        $previousStatus = isset($this->data['previous_status'])
            ? StatusEnums::tryFrom($this->data['previous_status'])
            : null;

        $statusResult = $statusPublisher->publish(
            prNumber: $this->data['pr_number'],
            procurementTitle: $this->data['procurement_title'],
            stage: $stage,
            currentStatus: $completionStatus,
            userAddress: $this->data['user_address'],
            previousStatus: $previousStatus,
            metadata: [
                'documents_uploaded' => $this->data['document_count'],
                'marked_complete_at' => now()->toIso8601String(),
                'procurement_mode' => $this->data['procurement_mode'],
            ],
        );

        $eventResult = $eventPublisher->publish(
            prNumber: $this->data['pr_number'],
            procurementTitle: $this->data['procurement_title'],
            stage: $stage->value,
            eventType: 'stage_completed',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$stage->getDisplayName()} marked as complete with all required documents uploaded.",
            documentCount: $this->data['document_count'],
            userAddress: $this->data['user_address'],
            metadata: [
                'stage' => $stage->value,
                'completion_status' => $completionStatus->value,
                'procurement_mode' => $this->data['procurement_mode'],
            ],
        );

        $nextStageName = null;

        if (isset($this->data['next_stage'])) {
            $nextStage = StageEnums::from($this->data['next_stage']);
            $nextStageStatus = StatusEnums::from($this->data['next_stage_status']);
            $nextStageName = $nextStage->getDisplayName();

            $statusPublisher->publishTransition(
                prNumber: $this->data['pr_number'],
                procurementTitle: $this->data['procurement_title'],
                fromStage: $stage,
                toStage: $nextStage,
                currentStatus: $nextStageStatus,
                userAddress: $this->data['user_address'],
                previousStatus: $completionStatus,
            );

            $eventPublisher->publishStageTransition(
                prNumber: $this->data['pr_number'],
                procurementTitle: $this->data['procurement_title'],
                fromStage: $stage->value,
                toStage: $nextStage->value,
                userAddress: $this->data['user_address'],
            );
        }

        if (! empty($this->data['is_pre_procurement'])) {
            $this->sendStageNotification(
                $stage,
                $completionStatus,
                $nextStageName,
            );
        }

        return [
            'success' => true,
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'next_stage' => $this->data['next_stage'] ?? null,
            'next_stage_name' => $nextStageName,
        ];
    }

    private function handleInitiationComplete(StatusPublisher $statusPublisher, EventPublisher $eventPublisher): array
    {
        $nextStage = StageEnums::from($this->data['next_stage']);
        $nextStageStatus = StatusEnums::from($this->data['next_stage_status']);
        $currentStageEnum = StageEnums::from($this->data['current_stage']);

        $statusPublisher->publish(
            prNumber: $this->data['pr_number'],
            procurementTitle: $this->data['procurement_title'],
            stage: $nextStage,
            currentStatus: $nextStageStatus,
            userAddress: $this->data['user_address'],
            previousStatus: null,
            metadata: [
                'documents_uploaded' => $this->data['document_count'],
                'marked_complete_at' => now()->toIso8601String(),
                'previous_stage' => $this->data['current_stage'],
                'stage_transition' => true,
            ],
        );

        $eventPublisher->publish(
            prNumber: $this->data['pr_number'],
            procurementTitle: $this->data['procurement_title'],
            stage: $nextStage->value,
            eventType: 'stage_completed',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$currentStageEnum->getDisplayName()} completed. Transitioned to {$nextStage->getDisplayName()} with status {$nextStageStatus->getDisplayName()}.",
            documentCount: $this->data['document_count'],
            userAddress: $this->data['user_address'],
            metadata: [
                'previous_stage' => $this->data['current_stage'],
                'new_stage' => $nextStage->value,
                'completion_status' => $nextStageStatus->value,
            ],
        );

        return [
            'success' => true,
            'next_stage' => $nextStage->value,
            'next_stage_name' => $nextStage->getDisplayName(),
        ];
    }

    private function handleInitiateProcurement(ProcurementOrchestrator $orchestrator): array
    {
        // Files are uploaded separately after initiation; this handles metadata only
        $result = $orchestrator->initiateProcurement(
            procurementData: $this->data['procurement_data'],
            files: [],
            userName: $this->data['user_name'],
        );

        if (! $result['success']) {
            throw new Exception($result['message'] ?? 'Orchestrator returned failure during initiation');
        }

        return $result;
    }

    private function handleCorrectDocument(CorrectionPublisher $correctionPublisher): array
    {
        $correctedFile = null;

        if (! empty($this->data['temp_file_path'])) {
            $correctedFile = $this->reconstituteTempFile(
                $this->data['temp_file_path'],
                $this->data['original_filename'],
                $this->data['mime_type'],
            );
        }

        try {
            return $correctionPublisher->publish(
                prNumber: $this->data['pr_number'],
                procurementTitle: $this->data['procurement_title'],
                originalTxid: $this->data['original_txid'],
                originalDocumentHash: $this->data['original_document_hash'],
                correctionType: $this->data['correction_type'],
                action: $this->data['action'],
                reason: $this->data['reason'],
                correctedBy: $this->data['corrected_by'],
                userAddress: $this->data['user_address'],
                correctedFile: $correctedFile,
                originalStage: $this->data['original_stage'] ?? null,
            );
        } finally {
            if (! empty($this->data['temp_file_path'])) {
                $this->cleanupTempFile($this->data['temp_file_path']);
            }
        }
    }

    private function handleCorrectProcurement(ProcurementCorrectionPublisher $procurementCorrectionPublisher): array
    {
        $originalProcurement = ProcurementData::fromArray($this->data['original_procurement']);

        return $procurementCorrectionPublisher->publishCorrection(
            originalProcurement: $originalProcurement,
            correctedData: $this->data['corrected_data'],
            reason: $this->data['reason'],
            correctedBy: $this->data['corrected_by'],
            userAddress: $this->data['user_address'],
        );
    }

    private function handleSkipStage(
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementRepository $procurementRepository,
    ): array {
        $prNumber = $this->data['pr_number'];
        $stage = StageEnums::from($this->data['stage']);
        $reason = $this->data['reason'] ?? 'Stage marked as optional and skipped by user.';
        $userAddress = $this->data['user_address'];
        $procurement = $procurementRepository->findByProcurement($prNumber);

        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        $statusResult = $statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: StatusEnums::STAGE_SKIPPED,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: [
                'skipped_at' => now()->toIso8601String(),
                'skip_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        $eventResult = $eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage->value,
            eventType: 'stage_skipped',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$stage->getDisplayName()} skipped. Reason: {$reason}",
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'stage' => $stage->value,
                'skip_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        return [
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'stage' => $stage->value,
        ];
    }

    private function handleRepeatStage(
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementRepository $procurementRepository,
    ): array {
        $prNumber = $this->data['pr_number'];
        $stage = StageEnums::from($this->data['stage']);
        $reason = $this->data['reason'] ?? 'Additional bulletin required';
        $userAddress = $this->data['user_address'];
        $procurement = $procurementRepository->findByProcurement($prNumber);

        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        // Resolve ongoing status for this stage by using the existing status mapper helper
        $ongoingStatus = StatusEnums::STAGE_ONGOING;

        $eventResult = $eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage->value,
            eventType: 'stage_repeated',
            category: 'stage_transition',
            severity: 'info',
            details: "Another {$stage->getDisplayName()} is being issued per NGPA IRR provisions.",
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'stage' => $stage->value,
                'repeat_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        $statusResult = $statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: $ongoingStatus,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: [
                'action' => 'repeat_stage',
                'repeated_at' => now()->toIso8601String(),
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        return [
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'stage' => $stage->value,
        ];
    }

    private function handleUpdateDeliveryDetails(
        EventPublisher $eventPublisher,
        ProcurementRepository $procurementRepository,
    ): array {
        $prNumber = $this->data['pr_number'];
        $userAddress = $this->data['user_address'];
        $deliveryLocation = $this->data['delivery_location'];
        $deliveryDate = $this->data['delivery_date'];
        $deliveryTermDays = (int) $this->data['delivery_term_days'];

        $procurement = $procurementRepository->findByProcurement($prNumber);
        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        $updatedProcurement = new ProcurementData(
            prNumber: $procurement->prNumber,
            appReference: $procurement->appReference,
            title: $procurement->title,
            description: $procurement->description,
            abcAmount: $procurement->abcAmount,
            fundingSource: $procurement->fundingSource,
            category: $procurement->category,
            procurementMode: $procurement->procurementMode,
            office: $procurement->office,
            endUser: $procurement->endUser,
            deliveryLocation: $deliveryLocation,
            deliveryDate: \Carbon\Carbon::parse($deliveryDate),
            deliveryTermDays: $deliveryTermDays,
            preparedBy: $procurement->preparedBy,
            bacResolutionNumber: $procurement->bacResolutionNumber,
            bacResolutionDate: $procurement->bacResolutionDate,
            philgepsReference: $procurement->philgepsReference,
            philgepsPostingDate: $procurement->philgepsPostingDate,
            approvedBy: $procurement->approvedBy,
            approvalDate: $procurement->approvalDate,
            status: $procurement->status,
            userId: $procurement->userId,
            createdAt: $procurement->createdAt,
        );

        $procurementRepository->update($updatedProcurement);

        $eventResult = $eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: StageEnums::NOTICE_TO_PROCEED->value,
            eventType: 'delivery_details_updated',
            category: 'procurement',
            severity: 'info',
            details: sprintf(
                'Delivery details updated: Location: %s, Date: %s, Term: %d days',
                $deliveryLocation,
                $deliveryDate,
                $deliveryTermDays,
            ),
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'delivery_location' => $deliveryLocation,
                'delivery_date' => $deliveryDate,
                'delivery_term_days' => $deliveryTermDays,
            ],
        );

        return ['event_txid' => $eventResult['event_txid'] ?? null];
    }

    private function handlePublishDecision(
        DecisionPublisher $decisionPublisher,
        ProcurementRepository $procurementRepository,
    ): array {
        $prNumber = $this->data['pr_number'];
        $userAddress = $this->data['user_address'];
        $procurement = $procurementRepository->findByProcurement($prNumber);

        $result = $decisionPublisher->publishDecision(
            decisionType: $this->data['decision_type'],
            prNumber: $prNumber,
            procurementTitle: $this->data['procurement_title'],
            wasHeld: (bool) $this->data['was_held'],
            userAddress: $userAddress,
            procurement: $procurement,
        );

        return $result;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function reconstituteTempFile(string $tempPath, string $originalName, string $mimeType): UploadedFile
    {
        $fullPath = Storage::path($tempPath);

        if (! file_exists($fullPath)) {
            throw new Exception("Temp file not found: {$tempPath}");
        }

        return new UploadedFile(
            path: $fullPath,
            originalName: $originalName,
            mimeType: $mimeType,
            error: null,
            test: true,
        );
    }

    private function cleanupTempFile(string $tempPath): void
    {
        try {
            Storage::delete($tempPath);
        } catch (Exception $e) {
            Log::warning('BlockchainWriteJob: Failed to cleanup temp file', [
                'path' => $tempPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendStageNotification(StageEnums $stage, StatusEnums $completionStatus, ?string $nextStageName): void
    {
        try {
            app(\App\Services\NotificationService::class)->notifyStageUpdate(
                pr_number: $this->data['pr_number'],
                procurementTitle: $this->data['procurement_title'],
                stageIdentifier: $stage->getDisplayName(),
                currentStatus: $completionStatus->getDisplayName(),
                timestamp: now()->toDateTimeString(),
                actionType: 'marked complete',
                documentCount: $this->data['document_count'],
                stageTransition: $nextStageName !== null,
                nextStage: $nextStageName ?? '',
                rolesToNotify: ['bac_chairman', 'hope', 'admin'],
            );
        } catch (Exception $e) {
            Log::warning('BlockchainWriteJob: Notification failed (non-critical)', [
                'pr_number' => $this->data['pr_number'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}

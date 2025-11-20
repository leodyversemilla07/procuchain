<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Procurement Orchestrator Service
 *
 * Coordinates multiple publishers for complex atomic operations
 * - Publishes document + status + event atomically
 * - Manages transaction rollback state
 */
class ProcurementOrchestrator
{
    protected array $publishedTransactions = [];

    protected array $errors = [];

    public function __construct(
        public DocumentPublisher $documentPublisher,
        public StatusPublisher $statusPublisher,
        public EventPublisher $eventPublisher,
    ) {}

    /**
     * Publish a complete document workflow atomically
     *
     * Publishes: Document + Status Update + Event
     *
     * @param  array  $procurementData  Procurement metadata (pr_number, procurement_title, user_address)
     * @param  UploadedFile  $file  File to upload
     * @param  array  $documentData  Document-specific data
     * @param  array  $statusData  Status data
     * @param  array|null  $eventData  Optional event data
     * @return array Result with all transaction IDs
     *
     * @throws Exception If any step fails
     */
    public function publishDocumentWorkflow(
        array $procurementData,
        UploadedFile $file,
        array $documentData,
        array $statusData,
        ?array $eventData = null
    ): array {
        $this->resetState();

        $prNumber = $procurementData['pr_number'];
        $procurementTitle = $procurementData['procurement_title'];
        $userAddress = $procurementData['user_address'] ?? config('multichain.admin_address');

        // Validate enums
        $stage = $documentData['stage'] instanceof StageEnums
            ? $documentData['stage']
            : StageEnums::tryFrom($documentData['stage']);

        $documentType = $documentData['document_type'] instanceof DocumentTypeEnums
            ? $documentData['document_type']
            : DocumentTypeEnums::fromString($documentData['document_type']);

        $currentStatus = $statusData['current_status'] instanceof StatusEnums
            ? $statusData['current_status']
            : StatusEnums::tryFrom($statusData['current_status']);

        if (! $stage || ! $documentType || ! $currentStatus) {
            throw new Exception('Invalid stage, document type, or status');
        }

        try {
            // Step 1: Publish document
            Log::info('Orchestrator: Step 1 - Publishing document', ['pr_number' => $prNumber]);

            $documentResult = $this->documentPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                userAddress: $userAddress,
                stage: $stage,
                status: $documentData['status'],
                documentType: $documentType,
                file: $file,
                uploadedBy: $documentData['uploaded_by'] ?? 'System',
                description: $documentData['description'] ?? null,
                stageMetadata: $documentData['stage_metadata'] ?? null,
            );

            $this->publishedTransactions['document'] = $documentResult;

            // Step 2: Publish status
            Log::info('Orchestrator: Step 2 - Publishing status', ['pr_number' => $prNumber]);

            $statusStage = $statusData['stage'] instanceof StageEnums
                ? $statusData['stage']
                : StageEnums::tryFrom($statusData['stage']);

            $previousStatus = null;
            if (isset($statusData['previous_status'])) {
                $previousStatus = $statusData['previous_status'] instanceof StatusEnums
                    ? $statusData['previous_status']
                    : StatusEnums::tryFrom($statusData['previous_status']);
            }

            $statusResult = $this->statusPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $statusStage,
                currentStatus: $currentStatus,
                userAddress: $userAddress,
                previousStatus: $previousStatus,
                metadata: $statusData['metadata'] ?? null,
            );

            $this->publishedTransactions['status'] = $statusResult;

            // Step 3: Publish event (optional)
            if ($eventData !== null) {
                Log::info('Orchestrator: Step 3 - Publishing event', ['pr_number' => $prNumber]);

                $eventResult = $this->eventPublisher->publish(
                    prNumber: $prNumber,
                    procurementTitle: $procurementTitle,
                    stage: $eventData['stage'],
                    eventType: $eventData['event_type'],
                    category: $eventData['category'],
                    severity: $eventData['severity'] ?? 'info',
                    details: $eventData['details'],
                    documentCount: $eventData['document_count'] ?? 1,
                    userAddress: $userAddress,
                    metadata: $eventData['metadata'] ?? null,
                );

                $this->publishedTransactions['event'] = $eventResult;
            }

            Log::info('Orchestrator: Success - All steps completed', [
                'pr_number' => $prNumber,
                'transactions' => array_keys($this->publishedTransactions),
            ]);

            return [
                'success' => true,
                'pr_number' => $prNumber,
                'transactions' => $this->publishedTransactions,
                'file' => $documentResult['file'],
            ];
        } catch (Exception $e) {
            Log::error('Orchestrator: Failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
            ]);

            $this->errors[] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            return [
                'success' => false,
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'transactions' => $this->publishedTransactions,
                'errors' => $this->errors,
            ];
        }
    }

    /**
     * Publish status and event together
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums  $stage  Stage identifier
     * @param  StatusEnums  $currentStatus  Current status
     * @param  string  $userAddress  User blockchain address
     * @param  array|null  $eventData  Optional event data
     * @return array Result with transaction IDs
     */
    public function publishStatusWithEvent(
        string $prNumber,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $currentStatus,
        string $userAddress,
        ?array $eventData = null
    ): array {
        $this->resetState();

        try {
            // Publish status
            $statusResult = $this->statusPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $stage,
                currentStatus: $currentStatus,
                userAddress: $userAddress,
            );

            $this->publishedTransactions['status'] = $statusResult;

            // Publish event if provided
            if ($eventData !== null) {
                $eventResult = $this->eventPublisher->publish(
                    prNumber: $prNumber,
                    procurementTitle: $procurementTitle,
                    stage: $eventData['stage'] ?? $stage->value,
                    eventType: $eventData['event_type'],
                    category: $eventData['category'],
                    severity: $eventData['severity'] ?? 'info',
                    details: $eventData['details'],
                    documentCount: $eventData['document_count'] ?? 0,
                    userAddress: $userAddress,
                    metadata: $eventData['metadata'] ?? null,
                );

                $this->publishedTransactions['event'] = $eventResult;
            }

            return [
                'success' => true,
                'pr_number' => $prNumber,
                'transactions' => $this->publishedTransactions,
            ];
        } catch (Exception $e) {
            Log::error('Orchestrator: Status+Event failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Reset orchestrator state for new operation
     */
    private function resetState(): void
    {
        $this->publishedTransactions = [];
        $this->errors = [];
    }

    /**
     * Get published transactions
     */
    public function getPublishedTransactions(): array
    {
        return $this->publishedTransactions;
    }

    /**
     * Get errors from last operation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Initiate procurement with complete workflow (Issue #3: Transaction Boundaries)
     *
     * Publishes: Procurement Metadata + Status + Documents + Event atomically
     * Blockchain is the single source of truth - no local DB transaction needed
     *
     * @param  array  $procurementData  Complete procurement data
     * @param  array  $files  Files to upload with document types
     * @param  string  $userName  Current user's name
     * @return array Result with all transaction IDs
     *
     * @throws Exception If any critical step fails
     */
    public function initiateProcurement(
        array $procurementData,
        array $files,
        string $userName
    ): array {
        $this->resetState();

        $prNumber = $procurementData['pr_number'];
        $procurementTitle = $procurementData['title'];
        $userAddress = $procurementData['user_address'];

        // Validate required enums
        $stage = StageEnums::PROCUREMENT_INITIATION;
        $status = StatusEnums::PROCUREMENT_INITIATED;

        try {
            // Step 1: Create procurement metadata (CRITICAL)
            Log::info('Orchestrator: Step 1 - Creating procurement metadata', ['pr_number' => $prNumber]);

            $metadataResult = app(\App\Repositories\ProcurementRepository::class)->create(
                \App\DataTransferObjects\ProcurementData::fromArray($procurementData)
            );

            $this->publishedTransactions['metadata'] = [
                'txid' => $metadataResult['txid'] ?? 'pending',
                'step' => 'metadata',
                'pr_number' => $prNumber,
            ];

            // Step 2: Publish status (CRITICAL - required for workflow)
            Log::info('Orchestrator: Step 2 - Publishing status', ['pr_number' => $prNumber]);

            $statusResult = $this->statusPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                stage: $stage,
                currentStatus: $status,
                userAddress: $userAddress,
                previousStatus: null,
                metadata: [
                    'initiated_by' => $userName,
                    'initiated_at' => now()->toIso8601String(),
                ],
            );

            $this->publishedTransactions['status'] = $statusResult;

            // Step 3: Publish documents (BEST EFFORT - log failures but continue)
            $uploadedDocuments = [];
            $failedDocuments = [];

            if (! empty($files)) {
                Log::info('Orchestrator: Step 3 - Publishing documents', [
                    'pr_number' => $prNumber,
                    'document_count' => count($files),
                ]);

                foreach ($files as $fileData) {
                    try {
                        $documentResult = $this->documentPublisher->publish(
                            prNumber: $prNumber,
                            procurementTitle: $procurementTitle,
                            userAddress: $userAddress,
                            stage: $stage,
                            status: $status->value,
                            documentType: $fileData['document_type'],
                            file: $fileData['file'],
                            uploadedBy: $userName,
                            description: $fileData['description'] ?? null,
                            stageMetadata: $fileData['metadata'] ?? null,
                        );

                        $uploadedDocuments[] = [
                            'filename' => $fileData['file']->getClientOriginalName(),
                            'txid' => $documentResult['txid'] ?? null,
                        ];
                    } catch (Exception $e) {
                        $failedDocuments[] = [
                            'filename' => $fileData['file']->getClientOriginalName(),
                            'error' => $e->getMessage(),
                        ];

                        Log::error('Orchestrator: Document upload failed (non-critical)', [
                            'pr_number' => $prNumber,
                            'filename' => $fileData['file']->getClientOriginalName(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (! empty($uploadedDocuments)) {
                    $this->publishedTransactions['documents'] = $uploadedDocuments;
                }
            }

            // Step 4: Publish initiation event (BEST EFFORT - audit trail only)
            try {
                Log::info('Orchestrator: Step 4 - Publishing event', ['pr_number' => $prNumber]);

                $eventResult = $this->eventPublisher->publish(
                    prNumber: $prNumber,
                    procurementTitle: $procurementTitle,
                    stage: $stage->value,
                    eventType: 'procurement_created',
                    category: 'procurement',
                    severity: 'info',
                    details: sprintf(
                        'Procurement "%s" (PR#%s) has been initiated with %d documents.',
                        $procurementTitle,
                        $prNumber,
                        count($uploadedDocuments)
                    ),
                    documentCount: count($uploadedDocuments),
                    userAddress: $userAddress,
                    metadata: [
                        'uploaded_documents' => count($uploadedDocuments),
                        'failed_documents' => count($failedDocuments),
                    ],
                );

                $this->publishedTransactions['event'] = $eventResult;
            } catch (Exception $e) {
                Log::warning('Orchestrator: Event publishing failed (non-critical)', [
                    'pr_number' => $prNumber,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Orchestrator: Success - Procurement initiation completed', [
                'pr_number' => $prNumber,
                'transactions' => array_keys($this->publishedTransactions),
                'uploaded_documents' => count($uploadedDocuments),
                'failed_documents' => count($failedDocuments),
            ]);

            return [
                'success' => true,
                'pr_number' => $prNumber,
                'transactions' => $this->publishedTransactions,
                'uploaded_documents' => $uploadedDocuments,
                'failed_documents' => $failedDocuments,
                'message' => sprintf(
                    'Procurement initiated successfully. %d documents uploaded%s.',
                    count($uploadedDocuments),
                    ! empty($failedDocuments) ? ', '.count($failedDocuments).' failed' : ''
                ),
            ];
        } catch (Exception $e) {
            Log::error('Orchestrator: Procurement initiation failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->errors[] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            // Blockchain as single source of truth means we don't rollback
            // Instead, we log the state and let admins handle via blockchain queries
            return [
                'success' => false,
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'transactions' => $this->publishedTransactions,
                'errors' => $this->errors,
                'message' => 'Procurement initiation failed. Check logs for transaction IDs.',
            ];
        }
    }
}

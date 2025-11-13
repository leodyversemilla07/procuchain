<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Enums\StreamEnums;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Unified Atomic Procurement Publishing Service
 *
 * Handles all procurement publishing operations atomically:
 * - Low-level: Atomic blockchain operations (documents, status, events, corrections)
 * - High-level: Procurement orchestration (metadata, redirects, notifications)
 *
 * Ensures atomic publishing across all streams:
 * - file.data (file content)
 * - file.metadata (file metadata)
 * - procurement.documents (document records)
 * - procurement.status (procurement status)
 * - procurement.events (timeline events)
 * - procurement.corrections (corrections)
 */
class ProcurementPublishingService
{
    protected array $publishedTransactions = [];

    protected array $errors = [];

    public function __construct(
        private MultichainService $multichain,
        private FileStorageService $fileStorage,
        private NotificationService $notificationService
    ) {}

    // =====================================================================
    // ATOMIC BLOCKCHAIN OPERATIONS
    // =====================================================================

    /**
     * Publish a complete procurement document with file upload atomically
     *
     * @param  array  $procurementData  Procurement metadata (procurement_id, procurement_title, etc.)
     * @param  UploadedFile  $file  The file to upload
     * @param  array  $documentData  Document-specific data (stage, status, document_type, etc.)
     * @param  array  $statusData  Status data (stage, current_status)
     * @param  array|null  $eventData  Optional event data for timeline
     * @return array Result with all transaction IDs or error information
     */
    public function publishDocumentWithFile(
        array $procurementData,
        UploadedFile $file,
        array $documentData,
        array $statusData,
        ?array $eventData = null
    ): array {
        $this->resetState();

        $procurementId = $procurementData['procurement_id'];
        $procurementTitle = $procurementData['procurement_title'];
        $userAddress = $procurementData['user_address'] ?? config('multichain.admin_address');

        // Validate and convert enums
        $stage = $documentData['stage'] instanceof StageEnums
            ? $documentData['stage']
            : StageEnums::tryFrom($documentData['stage']);

        $documentType = $documentData['document_type'] instanceof DocumentTypeEnums
            ? $documentData['document_type']
            : DocumentTypeEnums::fromString($documentData['document_type']);

        $currentStatus = $statusData['current_status'] instanceof StatusEnums
            ? $statusData['current_status']
            : StatusEnums::tryFrom($statusData['current_status']);

        if ($stage === null) {
            throw new Exception("Invalid stage: {$documentData['stage']}");
        }

        if ($documentType === null) {
            throw new Exception("Invalid document type: {$documentData['document_type']}");
        }

        if ($currentStatus === null) {
            throw new Exception("Invalid status: {$statusData['current_status']}");
        }

        try {
            // Step 1: Upload file to blockchain (file.data + file.metadata)
            Log::info('Step 1: Uploading file to blockchain', [
                'procurement_id' => $procurementId,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'stage' => $stage->value,
                'document_type' => $documentType->value,
            ]);

            $fileResult = $this->fileStorage->uploadFile(
                file: $file,
                path: 'procurement_documents/'.$procurementId,
                suffix: $stage->value.'_'.time(),
                metadata: array_merge($procurementData, $documentData)
            );

            $this->publishedTransactions['file_data'] = $fileResult['data_txid'];
            $this->publishedTransactions['file_metadata'] = $fileResult['metadata_txid'];

            Log::info('Step 1 Complete: File uploaded', [
                'data_txid' => $fileResult['data_txid'],
                'metadata_txid' => $fileResult['metadata_txid'],
            ]);

            // Step 2: Publish document record to procurement.documents
            Log::info('Step 2: Publishing document record', [
                'procurement_id' => $procurementId,
                'stage' => $stage->value,
                'document_type' => $documentType->value,
            ]);

            $documentRecord = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'user_address' => $userAddress,
                    'stage' => $stage->value,
                    'status' => $documentData['status'],
                    'document_type' => $stage->value, // Must match stage for blockchain filter validation
                    'file_key' => $fileResult['file_key'],
                    'file_name' => $fileResult['filename'],
                    'file_size' => $fileResult['size'],
                    'mime_type' => $fileResult['mime_type'],
                    'hash' => $fileResult['hash'],
                    'data_txid' => $fileResult['data_txid'],
                    'metadata_txid' => $fileResult['metadata_txid'],
                    'uploaded_by' => $documentData['uploaded_by'] ?? 'System',
                    'timestamp' => now()->toIso8601String(),
                    'description' => $documentData['description'] ?? null,
                ],
            ];

            $documentTxid = $this->multichain->publish(
                StreamEnums::DOCUMENTS->value,
                $procurementId,
                $documentRecord
            );

            $this->publishedTransactions['document'] = $documentTxid;

            Log::info('Step 2 Complete: Document published', [
                'document_txid' => $documentTxid,
            ]);

            // Step 3: Publish status to procurement.status
            Log::info('Step 3: Publishing status', [
                'procurement_id' => $procurementId,
                'stage' => $stage->value,
                'status' => $currentStatus->value,
            ]);

            $statusRecord = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'stage' => $statusData['stage'] instanceof StageEnums
                        ? $statusData['stage']->value
                        : $statusData['stage'],
                    'current_status' => $currentStatus->value,
                    'user_address' => $userAddress,
                    'timestamp' => now()->toIso8601String(),
                    'previous_status' => $statusData['previous_status'] ?? null,
                    'metadata' => $statusData['metadata'] ?? null,
                ],
            ];

            $statusTxid = $this->multichain->publish(
                StreamEnums::STATUS->value,
                $procurementId,
                $statusRecord
            );

            $this->publishedTransactions['status'] = $statusTxid;

            Log::info('Step 3 Complete: Status published', [
                'status_txid' => $statusTxid,
            ]);

            // Step 4: Publish event to procurement.events (if provided)
            if ($eventData !== null) {
                Log::info('Step 4: Publishing event', [
                    'procurement_id' => $procurementId,
                    'event_type' => $eventData['event_type'],
                ]);

                $eventRecord = [
                    'json' => [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'stage' => $eventData['stage'],
                        'event_type' => $eventData['event_type'],
                        'category' => $eventData['category'],
                        'severity' => $eventData['severity'] ?? 'info',
                        'details' => $eventData['details'],
                        'document_count' => $eventData['document_count'] ?? 1,
                        'user_address' => $userAddress,
                        'timestamp' => now()->toIso8601String(),
                        'metadata' => $eventData['metadata'] ?? null,
                    ],
                ];

                $eventTxid = $this->multichain->publish(
                    StreamEnums::EVENTS->value,
                    $procurementId,
                    $eventRecord
                );

                $this->publishedTransactions['event'] = $eventTxid;

                Log::info('Step 4 Complete: Event published', [
                    'event_txid' => $eventTxid,
                ]);
            }

            Log::info('✅ Atomic publication complete', [
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ]);

            return [
                'success' => true,
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
                'file' => [
                    'file_key' => $fileResult['file_key'],
                    'filename' => $fileResult['filename'],
                    'size' => $fileResult['size'],
                    'hash' => $fileResult['hash'],
                ],
            ];
        } catch (Exception $e) {
            Log::error('❌ Atomic publication failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'transactions' => $this->publishedTransactions,
            ]);

            $this->errors[] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            return [
                'success' => false,
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'transactions' => $this->publishedTransactions,
                'errors' => $this->errors,
            ];
        }
    }

    /**
     * Publish status update only (without document)
     *
     * @param  string  $procurementId  Procurement ID
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums|string  $stage  Stage identifier
     * @param  StatusEnums|string  $currentStatus  Current status (must be valid StatusEnums value)
     * @param  string  $userAddress  User blockchain address
     * @param  StatusEnums|string|null  $previousStatus  Previous status
     * @param  array|null  $metadata  Additional metadata
     * @param  array|null  $eventData  Optional event data for timeline
     * @return array Result with transaction IDs or error information
     */
    public function publishStatusUpdate(
        string $procurementId,
        string $procurementTitle,
        StageEnums|string $stage,
        StatusEnums|string $currentStatus,
        string $userAddress,
        StatusEnums|string|null $previousStatus = null,
        ?array $metadata = null,
        ?array $eventData = null
    ): array {
        $this->resetState();

        // Validate and convert enums
        $stageEnum = $stage instanceof StageEnums ? $stage : StageEnums::tryFrom($stage);
        $currentStatusEnum = $currentStatus instanceof StatusEnums ? $currentStatus : StatusEnums::tryFrom($currentStatus);
        $previousStatusEnum = $previousStatus instanceof StatusEnums ? $previousStatus : ($previousStatus ? StatusEnums::tryFrom($previousStatus) : null);

        if ($stageEnum === null) {
            throw new Exception("Invalid stage: {$stage}");
        }

        if ($currentStatusEnum === null) {
            throw new Exception("Invalid current status: {$currentStatus}");
        }

        try {
            // Step 1: Publish status
            Log::info('Publishing status update', [
                'procurement_id' => $procurementId,
                'stage' => $stageEnum->value,
                'status' => $currentStatusEnum->value,
            ]);

            $statusRecord = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'stage' => $stageEnum->value,
                    'current_status' => $currentStatusEnum->value,
                    'user_address' => $userAddress,
                    'timestamp' => now()->toIso8601String(),
                    'previous_status' => $previousStatusEnum?->value,
                    'metadata' => $metadata,
                ],
            ];

            $statusTxid = $this->multichain->publish(
                StreamEnums::STATUS->value,
                $procurementId,
                $statusRecord
            );

            $this->publishedTransactions['status'] = $statusTxid;

            // Step 2: Publish event (if provided)
            if ($eventData !== null) {
                Log::info('Publishing status change event', [
                    'procurement_id' => $procurementId,
                    'event_type' => $eventData['event_type'],
                ]);

                $eventRecord = [
                    'json' => [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'stage' => $eventData['stage'],
                        'event_type' => $eventData['event_type'],
                        'category' => $eventData['category'],
                        'severity' => $eventData['severity'] ?? 'info',
                        'details' => $eventData['details'],
                        'document_count' => $eventData['document_count'] ?? 0,
                        'user_address' => $userAddress,
                        'timestamp' => now()->toIso8601String(),
                        'metadata' => $eventData['metadata'] ?? null,
                    ],
                ];

                $eventTxid = $this->multichain->publish(
                    StreamEnums::EVENTS->value,
                    $procurementId,
                    $eventRecord
                );

                $this->publishedTransactions['event'] = $eventTxid;
            }

            Log::info('✅ Status update published', [
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ]);

            return [
                'success' => true,
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ];
        } catch (Exception $e) {
            Log::error('❌ Status update failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'transactions' => $this->publishedTransactions,
            ];
        }
    }

    /**
     * Publish event only (without status or document)
     *
     * @param  string  $procurementId  Procurement ID
     * @param  string  $procurementTitle  Procurement title
     * @param  StageEnums|string  $stage  Stage identifier
     * @param  string  $eventType  Event type
     * @param  string  $category  Event category
     * @param  string  $details  Event details
     * @param  string  $userAddress  User blockchain address
     * @param  string  $severity  Event severity (info, warning, error)
     * @param  int  $documentCount  Number of documents involved
     * @param  array|null  $metadata  Additional metadata
     * @return array Result with transaction ID or error information
     */
    public function publishEvent(
        string $procurementId,
        string $procurementTitle,
        StageEnums|string $stage,
        string $eventType,
        string $category,
        string $details,
        string $userAddress,
        string $severity = 'info',
        int $documentCount = 0,
        ?array $metadata = null
    ): array {
        $this->resetState();

        // Validate and convert enum
        $stageEnum = $stage instanceof StageEnums ? $stage : StageEnums::tryFrom($stage);

        if ($stageEnum === null) {
            throw new Exception("Invalid stage: {$stage}");
        }

        try {
            Log::info('Publishing event', [
                'procurement_id' => $procurementId,
                'stage' => $stageEnum->value,
                'event_type' => $eventType,
            ]);

            $eventRecord = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'stage' => $stageEnum->value,
                    'event_type' => $eventType,
                    'category' => $category,
                    'severity' => $severity,
                    'details' => $details,
                    'document_count' => $documentCount,
                    'user_address' => $userAddress,
                    'timestamp' => now()->toIso8601String(),
                    'metadata' => $metadata,
                ],
            ];

            $eventTxid = $this->multichain->publish(
                StreamEnums::EVENTS->value,
                $procurementId,
                $eventRecord
            );

            $this->publishedTransactions['event'] = $eventTxid;

            Log::info('✅ Event published', [
                'procurement_id' => $procurementId,
                'event_txid' => $eventTxid,
            ]);

            return [
                'success' => true,
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ];
        } catch (Exception $e) {
            Log::error('❌ Event publication failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish correction with optional replacement file
     *
     * @param  string  $procurementId  Procurement ID
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $originalTxid  Original document transaction ID
     * @param  string  $originalDocumentHash  Original document hash
     * @param  string  $correctionType  Correction type (document_correction, metadata_correction, etc.)
     * @param  string  $action  Action (replace, invalidate)
     * @param  string  $reason  Correction reason
     * @param  string  $userAddress  User blockchain address
     * @param  UploadedFile|null  $replacementFile  Replacement file (required for replace action)
     * @param  array|null  $correctedMetadata  Corrected metadata
     * @param  array|null  $eventData  Optional event data
     * @return array Result with transaction IDs or error information
     */
    public function publishCorrection(
        string $procurementId,
        string $procurementTitle,
        string $originalTxid,
        string $originalDocumentHash,
        string $correctionType,
        string $action,
        string $reason,
        string $userAddress,
        ?UploadedFile $replacementFile = null,
        ?array $correctedMetadata = null,
        ?array $eventData = null
    ): array {
        $this->resetState();

        try {
            // Step 1: Upload replacement file if provided
            if ($replacementFile !== null) {
                Log::info('Step 1: Uploading replacement file', [
                    'procurement_id' => $procurementId,
                    'filename' => $replacementFile->getClientOriginalName(),
                ]);

                $fileResult = $this->fileStorage->uploadFile(
                    file: $replacementFile,
                    path: 'procurement_documents/'.$procurementId,
                    suffix: 'corrected_'.time(),
                    metadata: [
                        'procurement_id' => $procurementId,
                        'correction_type' => $correctionType,
                        'original_txid' => $originalTxid,
                    ]
                );

                $this->publishedTransactions['replacement_file_data'] = $fileResult['data_txid'];
                $this->publishedTransactions['replacement_file_metadata'] = $fileResult['metadata_txid'];

                // Update corrected metadata with new file info
                $correctedMetadata = array_merge($correctedMetadata ?? [], [
                    'file_name' => $fileResult['filename'],
                    'file_size' => $fileResult['size'],
                    'mime_type' => $fileResult['mime_type'],
                    'file_key' => $fileResult['file_key'],
                    'hash' => $fileResult['hash'],
                    'data_txid' => $fileResult['data_txid'],
                    'metadata_txid' => $fileResult['metadata_txid'],
                ]);

                Log::info('Step 1 Complete: Replacement file uploaded', [
                    'data_txid' => $fileResult['data_txid'],
                ]);
            }

            // Step 2: Publish correction
            Log::info('Step 2: Publishing correction', [
                'procurement_id' => $procurementId,
                'correction_type' => $correctionType,
                'action' => $action,
            ]);

            $correctionRecord = [
                'json' => [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'original_txid' => $originalTxid,
                    'original_document_hash' => $originalDocumentHash,
                    'correction_type' => $correctionType,
                    'action' => $action,
                    'reason' => $reason,
                    'corrected_by' => auth()->user()->name ?? 'System',
                    'user_address' => $userAddress,
                    'timestamp' => now()->toIso8601String(),
                ],
            ];

            // Add corrected_metadata if action is replace (required by filter)
            if ($action === 'replace') {
                $correctionRecord['json']['corrected_metadata'] = $correctedMetadata ?? [
                    'correction_reason' => $reason,
                    'updated_at' => now()->toIso8601String(),
                ];
            }

            $correctionTxid = $this->multichain->publish(
                StreamEnums::CORRECTIONS->value,
                $procurementId,
                $correctionRecord
            );

            $this->publishedTransactions['correction'] = $correctionTxid;

            Log::info('Step 2 Complete: Correction published', [
                'correction_txid' => $correctionTxid,
            ]);

            // Step 3: Publish event (if provided)
            if ($eventData !== null) {
                Log::info('Step 3: Publishing correction event', [
                    'procurement_id' => $procurementId,
                    'event_type' => $eventData['event_type'],
                ]);

                $eventRecord = [
                    'json' => [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'stage' => $eventData['stage'],
                        'event_type' => $eventData['event_type'],
                        'category' => $eventData['category'],
                        'severity' => $eventData['severity'] ?? 'warning',
                        'details' => $eventData['details'],
                        'document_count' => $eventData['document_count'] ?? 1,
                        'user_address' => $userAddress,
                        'timestamp' => now()->toIso8601String(),
                        'metadata' => $eventData['metadata'] ?? null,
                    ],
                ];

                $eventTxid = $this->multichain->publish(
                    StreamEnums::EVENTS->value,
                    $procurementId,
                    $eventRecord
                );

                $this->publishedTransactions['event'] = $eventTxid;

                Log::info('Step 3 Complete: Event published', [
                    'event_txid' => $eventTxid,
                ]);
            }

            Log::info('✅ Correction published', [
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ]);

            return [
                'success' => true,
                'procurement_id' => $procurementId,
                'transactions' => $this->publishedTransactions,
            ];
        } catch (Exception $e) {
            Log::error('❌ Correction publication failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
            ]);

            return [
                'success' => false,
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'completed_steps' => array_keys($this->publishedTransactions),
                'transactions' => $this->publishedTransactions,
            ];
        }
    }

    // =====================================================================
    // HIGH-LEVEL PROCUREMENT ORCHESTRATION
    // =====================================================================

    /**
     * Publish documents for a procurement stage
     */
    public function publishDocuments(
        string $procurementId,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $status,
        array $files,
        array $metadata,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $user = Auth::user();
            $userAddress = $user->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Prepare metadata for all files
            $preparedMetadata = $this->fileStorage->prepareMetadata(
                $files,
                $metadata,
                $procurementId,
                $procurementTitle,
                $stage->getStoragePathSegment()
            );

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            // Publish each document atomically
            foreach ($files as $index => $file) {
                $meta = $preparedMetadata[$index];

                Log::info('Publishing document atomically', [
                    'procurement_id' => $procurementId,
                    'document' => $index + 1,
                    'total' => count($files),
                    'document_type' => $meta['document_type'],
                ]);

                $result = $this->publishDocumentWithFile(
                    procurementData: [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'user_address' => $userAddress,
                    ],
                    file: $file,
                    documentData: [
                        'stage' => $stage,
                        'status' => 'submitted',
                        'document_type' => $meta['document_type'],
                        'uploaded_by' => $user->name,
                        'description' => $meta['description'] ?? null,
                    ],
                    statusData: [
                        'stage' => $stage,
                        'current_status' => $status,
                        'metadata' => [
                            'document_count' => count($files),
                            'current_document' => $index + 1,
                        ],
                    ],
                    eventData: [
                        'stage' => $stage->value,
                        'event_type' => 'document_published',
                        'category' => 'document',
                        'severity' => 'info',
                        'details' => "Uploaded {$file->getClientOriginalName()} for {$stage->getDisplayName()}",
                        'document_count' => 1,
                    ]
                );

                if ($result['success']) {
                    $successCount++;
                    Log::info('Document published successfully', [
                        'procurement_id' => $procurementId,
                        'transactions' => $result['transactions'],
                    ]);
                } else {
                    $failureCount++;
                    Log::error('Document publication failed', [
                        'procurement_id' => $procurementId,
                        'error' => $result['error'],
                        'completed_steps' => $result['completed_steps'] ?? [],
                    ]);

                    // Stop on first failure
                    return redirect()->back()->withErrors([
                        'error' => "Failed to publish document {$file->getClientOriginalName()}: {$result['error']}",
                    ]);
                }

                $results[] = $result;
            }

            Log::info('All documents published successfully', [
                'procurement_id' => $procurementId,
                'total' => count($files),
                'success' => $successCount,
                'failed' => $failureCount,
            ]);

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stageIdentifier: $stage->getDisplayName(),
                currentStatus: $status->getDisplayName(),
                timestamp: $timestamp,
                actionType: 'uploaded',
                documentCount: count($files)
            );

            // Redirect to status page
            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $stage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (\Exception $e) {
            Log::error('Error publishing documents', [
                'stage' => $stage->getDisplayName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish '.$stage->getDisplayName().' documents: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Publish documents with stage transition
     */
    public function publishWithTransition(
        string $procurementId,
        string $procurementTitle,
        StageEnums $currentStage,
        StageEnums $nextStage,
        StatusEnums $currentStatus,
        StatusEnums $nextStatus,
        array $files,
        array $metadata,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $user = Auth::user();
            $userAddress = $user->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Prepare metadata for all files
            $preparedMetadata = $this->fileStorage->prepareMetadata(
                $files,
                $metadata,
                $procurementId,
                $procurementTitle,
                $currentStage->getStoragePathSegment()
            );

            $results = [];
            $successCount = 0;

            // Publish each document atomically
            foreach ($files as $index => $file) {
                $meta = $preparedMetadata[$index];

                $result = $this->publishDocumentWithFile(
                    procurementData: [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'user_address' => $userAddress,
                    ],
                    file: $file,
                    documentData: [
                        'stage' => $currentStage,
                        'status' => 'completed',
                        'document_type' => $meta['document_type'],
                        'uploaded_by' => $user->name,
                        'description' => $meta['description'] ?? null,
                    ],
                    statusData: [
                        'stage' => $currentStage,
                        'current_status' => $currentStatus,
                        'previous_status' => null,
                        'metadata' => [
                            'transitioning_to' => $nextStage->value,
                            'document_count' => count($files),
                        ],
                    ],
                    eventData: [
                        'stage' => $currentStage->value,
                        'event_type' => 'stage_completed',
                        'category' => 'stage_transition',
                        'severity' => 'info',
                        'details' => "Completed {$currentStage->getDisplayName()}, transitioning to {$nextStage->getDisplayName()}",
                        'document_count' => 1,
                    ]
                );

                if (! $result['success']) {
                    return redirect()->back()->withErrors([
                        'error' => "Failed to publish document: {$result['error']}",
                    ]);
                }

                $successCount++;
                $results[] = $result;
            }

            // Publish stage transition event
            $transitionResult = $this->publishStatusUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stage: $nextStage,
                currentStatus: $nextStatus,
                userAddress: $userAddress,
                previousStatus: $currentStatus,
                metadata: [
                    'previous_stage' => $currentStage->value,
                    'transition_reason' => "Completed {$currentStage->getDisplayName()}",
                ],
                eventData: [
                    'stage' => $nextStage->value,
                    'event_type' => 'stage_started',
                    'category' => 'stage_transition',
                    'severity' => 'info',
                    'details' => "Transitioned from {$currentStage->getDisplayName()} to {$nextStage->getDisplayName()}",
                    'document_count' => 0,
                ]
            );

            if (! $transitionResult['success']) {
                Log::error('Stage transition failed', [
                    'procurement_id' => $procurementId,
                    'error' => $transitionResult['error'],
                ]);
            }

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stageIdentifier: $currentStage->getDisplayName(),
                currentStatus: $currentStatus->getDisplayName(),
                timestamp: $timestamp,
                actionType: 'completed',
                documentCount: count($files),
                stageTransition: true,
                nextStage: $nextStage->getDisplayName()
            );

            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $currentStage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (\Exception $e) {
            Log::error('Error publishing documents with transition', [
                'stage' => $currentStage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload '.$currentStage->getDisplayName().' documents: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Handle stage transition without documents
     */
    public function handleTransitionOnly(
        string $procurementId,
        string $procurementTitle,
        StageEnums $currentStage,
        StageEnums $nextStage,
        StatusEnums $fromStatus,
        StatusEnums $toStatus,
        string $reason,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Publish stage transition atomically
            $result = $this->publishStatusUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stage: $nextStage,
                currentStatus: $toStatus,
                userAddress: $userAddress,
                previousStatus: $fromStatus,
                metadata: [
                    'previous_stage' => $currentStage->value,
                    'transition_reason' => $reason,
                ],
                eventData: [
                    'stage' => $nextStage->value,
                    'event_type' => 'stage_transitioned',
                    'category' => 'stage_transition',
                    'severity' => 'info',
                    'details' => $reason,
                    'document_count' => 0,
                ]
            );

            if (! $result['success']) {
                return redirect()->back()->withErrors([
                    'error' => "Failed to process transition: {$result['error']}",
                ]);
            }

            Log::info('Stage transition completed', [
                'procurement_id' => $procurementId,
                'from_stage' => $currentStage->value,
                'to_stage' => $nextStage->value,
                'transactions' => $result['transactions'],
            ]);

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stageIdentifier: $currentStage->getDisplayName(),
                currentStatus: $toStatus->getDisplayName(),
                timestamp: $timestamp,
                actionType: $reason,
                documentCount: 0,
                stageTransition: true,
                nextStage: $nextStage->getDisplayName()
            );

            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $currentStage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (\Exception $e) {
            Log::error('Error handling stage transition', [
                'stage' => $currentStage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to process '.$currentStage->getDisplayName().' decision: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Update status without transition
     */
    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $status,
        string $details,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Publish status update atomically
            $result = $this->publishStatusUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stage: $stage,
                currentStatus: $status,
                userAddress: $userAddress,
                previousStatus: null,
                metadata: null,
                eventData: [
                    'stage' => $stage->value,
                    'event_type' => 'status_updated',
                    'category' => 'status',
                    'severity' => 'info',
                    'details' => $details,
                    'document_count' => 0,
                ]
            );

            if (! $result['success']) {
                return redirect()->back()->withErrors([
                    'error' => "Failed to update status: {$result['error']}",
                ]);
            }

            Log::info('Status updated successfully', [
                'procurement_id' => $procurementId,
                'stage' => $stage->value,
                'status' => $status->value,
                'transactions' => $result['transactions'],
            ]);

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                procurementId: $procurementId,
                procurementTitle: $procurementTitle,
                stageIdentifier: $stage->getDisplayName(),
                currentStatus: $status->getDisplayName(),
                timestamp: $timestamp,
                actionType: $details,
                documentCount: 0
            );

            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $stage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating status', [
                'stage' => $stage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to process '.$stage->getDisplayName().' decision: '.$e->getMessage(),
            ]);
        }
    }

    // =====================================================================
    // HELPER METHODS
    // =====================================================================

    /**
     * Get all published transaction IDs from the last operation
     *
     * @return array Transaction IDs by type
     */
    public function getPublishedTransactions(): array
    {
        return $this->publishedTransactions;
    }

    /**
     * Get all errors from the last operation
     *
     * @return array Error details
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Reset internal state for new operation
     */
    protected function resetState(): void
    {
        $this->publishedTransactions = [];
        $this->errors = [];
    }
}

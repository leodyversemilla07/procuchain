<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Publish document correction records to blockchain.
 *
 * This job handles the immutability challenge by creating correction records
 * that reference the original document while providing corrected information.
 * The original record remains on-chain (immutable), but the correction marks
 * it as superseded and provides the correct data.
 */
class PublishDocumentCorrectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $procurementId,
        public string $procurementTitle,
        public string $originalTxid,
        public string $originalDocumentHash,
        public string $correctionReason,
        public ?array $correctedMetadata,
        public string $correctedBy,
        public string $userAddress,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MultichainService $multiChain,
        StreamKeyService $streamKeyService,
        BlockchainEventLoggerService $eventLoggerService
    ): void {
        try {
            // Validate inputs
            if (empty($this->procurementId) || empty($this->procurementTitle)) {
                throw new Exception('Procurement ID and title are required');
            }

            if (empty($this->originalTxid)) {
                throw new Exception('Original transaction ID is required for correction');
            }

            if (empty($this->correctionReason)) {
                throw new Exception('Correction reason is required');
            }

            if (! $multiChain->validateAddress($this->userAddress)) {
                throw new Exception("Invalid blockchain address: {$this->userAddress}");
            }

            $timestamp = now()->toIso8601String();
            $streamKey = $streamKeyService->generate($this->procurementId, $this->procurementTitle);

            Log::info('Publishing document correction to blockchain', [
                'procurement_id' => $this->procurementId,
                'original_txid' => $this->originalTxid,
                'corrected_by' => $this->correctedBy,
            ]);

            // Build correction record
            $correctionData = [
                'json' => [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'correction_type' => 'document_correction',
                    'original_txid' => $this->originalTxid,
                    'original_document_hash' => $this->originalDocumentHash,
                    'reason' => $this->correctionReason,
                    'corrected_by' => $this->correctedBy,
                    'user_address' => $this->userAddress,
                    'timestamp' => $timestamp,
                ],
            ];

            // Add corrected metadata if provided
            if ($this->correctedMetadata !== null) {
                $correctionData['json']['corrected_metadata'] = $this->correctedMetadata;
                $correctionData['json']['action'] = 'replace'; // Replace with new data
            } else {
                $correctionData['json']['action'] = 'invalidate'; // Just mark as invalid
            }

            // Publish correction to blockchain
            $txid = $multiChain->publishFrom(
                $this->userAddress,
                StreamEnums::CORRECTION->value,
                $streamKey,
                $correctionData
            );

            Log::info('Document correction published successfully', [
                'procurement_id' => $this->procurementId,
                'correction_txid' => $txid,
                'original_txid' => $this->originalTxid,
            ]);

            // Log blockchain event
            $eventLoggerService->logEvent(
                $this->procurementId,
                $this->procurementTitle,
                'correction',
                "Document corrected: {$this->correctionReason}",
                1,
                $this->userAddress,
                'document_corrected',
                'correction',
                'warning',
                now()->toIso8601String()
            );
        } catch (Exception $e) {
            Log::error('Failed to publish document correction', [
                'procurement_id' => $this->procurementId,
                'original_txid' => $this->originalTxid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PublishDocumentCorrectionJob permanently failed', [
            'procurement_id' => $this->procurementId,
            'procurement_title' => $this->procurementTitle,
            'original_txid' => $this->originalTxid,
            'correction_reason' => $this->correctionReason,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Publish Document Correction',
                procurementId: $this->procurementId,
                procurementTitle: $this->procurementTitle,
                errorMessage: $exception->getMessage(),
                attemptNumber: $this->attempts()
            ));
        }
    }
}

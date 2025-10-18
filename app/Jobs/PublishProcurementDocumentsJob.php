<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\BlockchainEventLoggerService;
use App\Services\BlockchainOrchestratorService;
use App\Services\MultichainService;
use App\Services\StatusUpdaterService;
use App\Services\StreamKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PublishProcurementDocumentsJob implements ShouldQueue
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

    protected $procurementId;

    protected $procurementTitle;

    protected $state;

    protected $status;

    protected $metadataArray;

    protected $userAddress;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $procurementId,
        string $procurementTitle,
        string $state,
        string $status,
        array $metadataArray,
        string $userAddress
    ) {
        $this->procurementId = $procurementId;
        $this->procurementTitle = $procurementTitle;
        $this->state = $state;
        $this->status = $status;
        $this->metadataArray = $metadataArray;
        $this->userAddress = $userAddress;
    }

    /**
     * Execute the job.
     *
     * @param  BlockchainOrchestratorService  $blockchainOrchestrator
     * @return void
     */
    public function handle(
        MultichainService $multiChain,
        StreamKeyService $streamKeyService,
        StatusUpdaterService $statusUpdaterService,
        BlockchainEventLoggerService $eventLoggerService
    ) {
        try {
            if (empty($this->procurementId) || empty($this->procurementTitle)) {
                throw new \Exception('Procurement ID and title are required');
            }

            if (empty($this->metadataArray)) {
                throw new \Exception('Document metadata array cannot be empty');
            }

            if (! $multiChain->validateAddress($this->userAddress)) {
                throw new \Exception("Invalid blockchain address: {$this->userAddress}");
            }

            $timestamp = now()->toIso8601String();
            $streamKey = $streamKeyService->generate($this->procurementId, $this->procurementTitle);

            Log::info('Generated stream key', [
                'procurement_id' => $this->procurementId,
                'procurement_title' => $this->procurementTitle,
                'stream_key' => $streamKey,
            ]);

            $documentItems = [];
            foreach ($this->metadataArray as $index => $metadata) {
                $requiredFields = ['document_type', 'hash', 'file_key', 'file_size'];
                foreach ($requiredFields as $field) {
                    if (! isset($metadata[$field])) {
                        throw new \Exception("Missing required metadata field: $field");
                    }
                }

                $docData = [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'stage' => $this->state,
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $this->userAddress,
                    'file_size' => $metadata['file_size'],
                    'stage_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];

                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => ['json' => $docData],
                ];
            }

            Log::info('Publishing documents to blockchain', [
                'procurement_id' => $this->procurementId,
                'document_count' => count($this->metadataArray),
                'user_address' => $this->userAddress,
                'stream_key' => $streamKey,
                'first_document_item' => $documentItems[0] ?? null,
            ]);

            try {
                // Publish to blockchain and capture the transaction ID
                $txid = $multiChain->publishMultiFrom($this->userAddress, StreamEnums::DOCUMENTS->value, $documentItems);

                Log::info('Documents published to blockchain successfully', [
                    'procurement_id' => $this->procurementId,
                    'document_count' => count($this->metadataArray),
                    'blockchain_txid' => $txid,
                ]);

                // Mark documents as confirmed in database with the actual blockchain txid
                $this->markDocumentsAsConfirmed($txid);
            } catch (\Exception $publishException) {
                // Check if this is a smart filter rejection
                if ($this->isFilterRejection($publishException->getMessage())) {
                    Log::error('Smart filter rejected document publication', [
                        'procurement_id' => $this->procurementId,
                        'filter_error' => $publishException->getMessage(),
                        'documents' => $this->metadataArray,
                    ]);

                    // Re-throw with clearer message
                    throw new \Exception(
                        'Document validation failed on blockchain: '.$publishException->getMessage(),
                        0,
                        $publishException
                    );
                }

                // Mark documents as failed in database
                $this->markDocumentsAsFailed($publishException->getMessage());

                // Other blockchain errors
                throw $publishException;
            }

            $statusUpdaterService->updateStatus($this->procurementId, $this->procurementTitle, $this->status, $this->state, $this->userAddress, $timestamp);

            $eventLoggerService->logEvent(
                $this->procurementId,
                $this->procurementTitle,
                $this->state,
                'Uploaded '.count($this->metadataArray)." finalized {$this->state} documents",
                count($this->metadataArray),
                $this->userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

        } catch (\Exception $e) {
            Log::error('Failed to publish procurement documents asynchronously', [
                'error' => $e->getMessage(),
                'procurementId' => $this->procurementId,
                'procurementTitle' => $this->procurementTitle,
                'state' => $this->state,
                'status' => $this->status,
                'metadataArray' => $this->metadataArray,
                'userAddress' => $this->userAddress,
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark documents as failed
            $this->markDocumentsAsFailed($e->getMessage());

            // Optionally: retry, notify, etc.
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Mark procurement documents as confirmed in blockchain
     */
    private function markDocumentsAsConfirmed(string $txid): void
    {
        \App\Models\ProcurementDocument::where('procurement_id', $this->procurementId)
            ->update([
                'blockchain_status' => 'confirmed',
                'blockchain_status_updated_at' => now(),
                'blockchain_txid' => $txid,
                'blockchain_error' => null,
            ]);

        Log::info('Marked documents as blockchain confirmed', [
            'procurement_id' => $this->procurementId,
            'txid' => $txid,
        ]);
    }

    /**
     * Mark procurement documents as failed in blockchain
     */
    private function markDocumentsAsFailed(string $errorMessage): void
    {
        \App\Models\ProcurementDocument::where('procurement_id', $this->procurementId)
            ->update([
                'blockchain_status' => 'failed',
                'blockchain_status_updated_at' => now(),
                'blockchain_error' => $errorMessage,
                'blockchain_retry_count' => \DB::raw('blockchain_retry_count + 1'),
            ]);

        Log::warning('Marked documents as blockchain failed', [
            'procurement_id' => $this->procurementId,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PublishProcurementDocumentsJob permanently failed', [
            'procurement_id' => $this->procurementId,
            'procurement_title' => $this->procurementTitle,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Publish Procurement Documents',
                procurementId: $this->procurementId,
                procurementTitle: $this->procurementTitle,
                errorMessage: $exception->getMessage(),
                attemptNumber: $this->attempts()
            ));
        }
    }

    /**
     * Check if exception message indicates a smart filter rejection
     */
    private function isFilterRejection(string $message): bool
    {
        $filterKeywords = [
            'Invalid document hash',
            'Missing required field',
            'Invalid file size',
            'Invalid document_type',
            'Stage mismatch',
            'Invalid blockchain address',
            'Invalid timestamp',
            'too short',
            'too long',
            'File size exceeds',
        ];

        foreach ($filterKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}

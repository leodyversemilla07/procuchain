<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Services\BlockchainOrchestratorService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use App\Services\StatusUpdaterService;
use App\Services\BlockchainEventLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishProcurementDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $procurementId;
    protected $procurementTitle;
    protected $state;
    protected $status;
    protected $metadataArray;
    protected $userAddress;

    /**
     * Create a new job instance.
     *
     * @param string $procurementId
     * @param string $procurementTitle
     * @param string $state
     * @param string $status
     * @param array $metadataArray
     * @param string $userAddress
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
     * @param BlockchainOrchestratorService $blockchainOrchestrator
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

            $multiChain->publishMultiFrom($this->userAddress, StreamEnums::DOCUMENTS->value, $documentItems);

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
            // Optionally: retry, notify, etc.
        }
    }
}

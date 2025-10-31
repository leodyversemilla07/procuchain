<?php

namespace App\Jobs;

use App\Notifications\BlockchainJobFailedNotification;
use App\Services\SmartContractService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DocumentValidationJob implements ShouldQueue
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

    public function __construct(
        private string $operation,
        private array $data,
        private string $procurementId,
        private string $userAddress
    ) {}

    /**
     * Execute the document validation job
     */
    public function handle(SmartContractService $smartContractService): void
    {
        try {
            Log::info('Processing document validation job', [
                'operation' => $this->operation,
                'procurement_id' => $this->procurementId,
                'user_address' => $this->userAddress,
            ]);

            switch ($this->operation) {
                case 'document_integrity':
                    $this->processDocumentIntegrity($smartContractService);
                    break;

                case 'metadata_compliance':
                    $this->processMetadataCompliance($smartContractService);
                    break;

                case 'storage_consistency':
                    $this->processStorageConsistency($smartContractService);
                    break;

                case 'audit_trail_generation':
                    $this->processAuditTrailGeneration($smartContractService);
                    break;

                default:
                    Log::warning('Unknown document validation operation', [
                        'operation' => $this->operation,
                    ]);
            }

        } catch (\Exception $e) {
            Log::error('Document validation job failed', [
                'operation' => $this->operation,
                'procurement_id' => $this->procurementId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process document integrity validation
     */
    private function processDocumentIntegrity(SmartContractService $smartContractService): void
    {
        if (! isset($this->data['document_hash'])) {
            throw new Exception('Document hash is required for integrity validation');
        }

        $result = $smartContractService->validateDocumentIntegrity(
            $this->procurementId,
            $this->data['document_hash']
        );

        Log::info('Document integrity validation completed', [
            'procurement_id' => $this->procurementId,
            'document_hash' => $this->data['document_hash'],
            'valid' => $result['valid'],
        ]);
    }

    /**
     * Process metadata compliance checking
     */
    private function processMetadataCompliance(SmartContractService $smartContractService): void
    {
        if (! isset($this->data['metadata']) || ! isset($this->data['stage'])) {
            throw new Exception('Metadata and stage are required for compliance checking');
        }

        $result = $smartContractService->checkDocumentMetadataCompliance(
            $this->data['metadata'],
            $this->data['stage']
        );

        Log::info('Metadata compliance check completed', [
            'procurement_id' => $this->procurementId,
            'stage' => $this->data['stage'],
            'compliant' => $result['compliant'],
        ]);
    }

    /**
     * Process storage consistency validation
     */
    private function processStorageConsistency(SmartContractService $smartContractService): void
    {
        $result = $smartContractService->validateDocumentStorageConsistency(
            $this->procurementId
        );

        Log::info('Storage consistency validation completed', [
            'procurement_id' => $this->procurementId,
            'consistent' => $result['consistent'],
            'total_documents' => $result['total_documents'],
            'validated_documents' => $result['validated_documents'],
        ]);
    }

    /**
     * Process audit trail generation
     */
    private function processAuditTrailGeneration(SmartContractService $smartContractService): void
    {
        $result = $smartContractService->getDocumentAuditTrail($this->procurementId);

        Log::info('Audit trail generation completed', [
            'procurement_id' => $result['procurement_id'],
            'total_entries' => $result['total_entries'],
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Document validation job failed permanently', [
            'operation' => $this->operation,
            'procurement_id' => $this->procurementId,
            'user_address' => $this->userAddress,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Document Validation',
                procurementId: $this->procurementId,
                procurementTitle: 'N/A',
                errorMessage: $exception->getMessage(),
                attemptNumber: $this->attempts()
            ));
        }
    }
}

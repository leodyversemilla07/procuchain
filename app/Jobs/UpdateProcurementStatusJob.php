<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UpdateProcurementStatusJob implements ShouldQueue
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

    protected $status;

    protected $stage;

    protected $userAddress;

    protected $timestamp;

    public function __construct(
        string $procurementId,
        string $procurementTitle,
        string $status,
        string $stage,
        string $userAddress,
        string $timestamp
    ) {
        $this->procurementId = $procurementId;
        $this->procurementTitle = $procurementTitle;
        $this->status = $status;
        $this->stage = $stage;
        $this->userAddress = $userAddress;
        $this->timestamp = $timestamp;
    }

    public function handle(MultichainService $multiChain, StreamKeyService $streamKeyService)
    {
        try {
            if (empty($this->procurementId) || empty($this->procurementTitle)) {
                throw new \Exception('Procurement ID and title are required');
            }

            if (empty($this->status) || empty($this->stage)) {
                throw new \Exception('Status and stage are required');
            }

            if (! $multiChain->validateAddress($this->userAddress)) {
                throw new \Exception("Invalid blockchain address: {$this->userAddress}");
            }

            $streamKey = $streamKeyService->generate($this->procurementId, $this->procurementTitle);
            $statusData = [
                'json' => [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'current_status' => $this->status,
                    'stage' => $this->stage,
                    'timestamp' => $this->timestamp,
                    'user_address' => $this->userAddress,
                ],
            ];

            Log::info('Updating procurement status on blockchain', [
                'procurement_id' => $this->procurementId,
                'status' => $this->status,
                'stage' => $this->stage,
            ]);

            // Publish to blockchain and capture the transaction ID
            $txid = $multiChain->publishFrom($this->userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

            Log::info('Procurement status updated successfully', [
                'procurement_id' => $this->procurementId,
                'status' => $this->status,
                'stage' => $this->stage,
                'blockchain_txid' => $txid,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update status on blockchain', [
                'procurement_id' => $this->procurementId,
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
        Log::error('UpdateProcurementStatusJob permanently failed', [
            'procurement_id' => $this->procurementId,
            'procurement_title' => $this->procurementTitle,
            'status' => $this->status,
            'stage' => $this->stage,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Update Procurement Status',
                procurementId: $this->procurementId,
                procurementTitle: $this->procurementTitle,
                errorMessage: $exception->getMessage(),
                attemptNumber: $this->attempts()
            ));
        }
    }
}

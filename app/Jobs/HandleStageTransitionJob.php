<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Notifications\BlockchainJobFailedNotification;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleStageTransitionJob implements ShouldQueue
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

    protected $fromStatus;

    protected $toStatus;

    protected $fromStage;

    protected $toStage;

    protected $userAddress;

    protected $details;

    public function __construct(
        string $procurementId,
        string $procurementTitle,
        string $fromStatus,
        string $toStatus,
        string $fromStage,
        string $toStage,
        string $userAddress,
        string $details
    ) {
        $this->procurementId = $procurementId;
        $this->procurementTitle = $procurementTitle;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->fromStage = $fromStage;
        $this->toStage = $toStage;
        $this->userAddress = $userAddress;
        $this->details = $details;
    }

    public function handle(
        MultichainService $multiChain,
        StreamKeyService $streamKeyService,
        BlockchainEventLoggerService $eventLoggerService
    ) {
        try {
            if (empty($this->procurementId) || empty($this->procurementTitle)) {
                throw new \Exception('Procurement ID and title are required');
            }

            if (empty($this->fromStage) || empty($this->toStage)) {
                throw new \Exception('From and to stages are required');
            }

            if (! $multiChain->validateAddress($this->userAddress)) {
                throw new \Exception("Invalid blockchain address: {$this->userAddress}");
            }

            $timestamp = now()->toIso8601String();
            $streamKey = $streamKeyService->generate($this->procurementId, $this->procurementTitle);

            Log::info('Processing stage transition (job)', [
                'procurement_id' => $this->procurementId,
                'from_stage' => $this->fromStage,
                'to_stage' => $this->toStage,
            ]);

            $statusData = [
                'json' => [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'previous_status' => $this->fromStatus,
                    'current_status' => $this->toStatus,
                    'previous_stage' => $this->fromStage,
                    'stage' => $this->toStage,
                    'timestamp' => $timestamp,
                    'user_address' => $this->userAddress,
                ],
            ];

            try {
                // Publish to blockchain and capture the transaction ID
                $txid = $multiChain->publishFrom($this->userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

                Log::info('Status transition published successfully', [
                    'procurement_id' => $this->procurementId,
                    'from' => "{$this->fromStage}:{$this->fromStatus}",
                    'to' => "{$this->toStage}:{$this->toStatus}",
                    'blockchain_txid' => $txid,
                ]);
            } catch (\Exception $publishException) {
                // Check if this is a smart filter rejection
                if ($this->isFilterRejection($publishException->getMessage())) {
                    Log::error('Smart filter rejected status transition', [
                        'procurement_id' => $this->procurementId,
                        'filter_error' => $publishException->getMessage(),
                        'from_status' => $this->fromStatus,
                        'to_status' => $this->toStatus,
                        'from_stage' => $this->fromStage,
                        'to_stage' => $this->toStage,
                    ]);

                    // Re-throw with clearer message
                    throw new \Exception(
                        'Status transition validation failed on blockchain: '.$publishException->getMessage(),
                        0,
                        $publishException
                    );
                }

                // Other blockchain errors
                throw $publishException;
            }

            $eventLoggerService->logEvent(
                $this->procurementId,
                $this->procurementTitle,
                $this->toStage,
                "{$this->details} (from {$this->fromStage}:{$this->fromStatus} to {$this->toStage}:{$this->toStatus})",
                0,
                $this->userAddress,
                'stage_transition',
                'workflow',
                'info',
                $timestamp
            );
        } catch (\Exception $e) {
            Log::error('Failed to handle stage transition (job)', [
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
        Log::error('HandleStageTransitionJob permanently failed', [
            'procurement_id' => $this->procurementId,
            'procurement_title' => $this->procurementTitle,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'from_stage' => $this->fromStage,
            'to_stage' => $this->toStage,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Handle Stage Transition',
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
            'Invalid status',
            'Invalid stage',
            'Missing required field',
            'not valid for stage',
            'Invalid blockchain address',
            'Invalid timestamp',
            'too short',
            'too long',
        ];

        foreach ($filterKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}

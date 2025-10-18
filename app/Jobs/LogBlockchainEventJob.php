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

class LogBlockchainEventJob implements ShouldQueue
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

    protected $stage;

    protected $details;

    protected $documentCount;

    protected $userAddress;

    protected $eventType;

    protected $category;

    protected $severity;

    protected $timestamp;

    public function __construct(
        string $procurementId,
        string $procurementTitle,
        string $stage,
        string $details,
        int $documentCount,
        string $userAddress,
        string $eventType,
        string $category,
        string $severity,
        string $timestamp
    ) {
        $this->procurementId = $procurementId;
        $this->procurementTitle = $procurementTitle;
        $this->stage = $stage;
        $this->details = $details;
        $this->documentCount = $documentCount;
        $this->userAddress = $userAddress;
        $this->eventType = $eventType;
        $this->category = $category;
        $this->severity = $severity;
        $this->timestamp = $timestamp;
    }

    public function handle(MultichainService $multiChain, StreamKeyService $streamKeyService)
    {
        try {
            if (empty($this->procurementId) || empty($this->procurementTitle)) {
                throw new \Exception('Procurement ID and title are required');
            }

            if (empty($this->details) || empty($this->eventType)) {
                throw new \Exception('Event details and type are required');
            }

            if (! $multiChain->validateAddress($this->userAddress)) {
                throw new \Exception("Invalid blockchain address: {$this->userAddress}");
            }

            if (! in_array($this->severity, ['info', 'warning', 'error'])) {
                throw new \Exception('Invalid severity level. Must be info, warning, or error');
            }

            $streamKey = $streamKeyService->generate($this->procurementId, $this->procurementTitle);
            $eventData = [
                'json' => [
                    'procurement_id' => $this->procurementId,
                    'procurement_title' => $this->procurementTitle,
                    'event_type' => $this->eventType,
                    'stage' => $this->stage,
                    'timestamp' => $this->timestamp,
                    'user_address' => $this->userAddress,
                    'details' => $this->details,
                    'category' => $this->category,
                    'severity' => $this->severity,
                    'document_count' => $this->documentCount,
                ],
            ];

            Log::info('Logging procurement event to blockchain', [
                'procurement_id' => $this->procurementId,
                'event_type' => $this->eventType,
                'severity' => $this->severity,
            ]);

            // Publish to blockchain and capture the transaction ID
            $txid = $multiChain->publishFrom($this->userAddress, StreamEnums::EVENTS->value, $streamKey, $eventData);

            Log::info('Event logged to blockchain successfully', [
                'procurement_id' => $this->procurementId,
                'event_type' => $this->eventType,
                'severity' => $this->severity,
                'blockchain_txid' => $txid,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log event to blockchain', [
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
        Log::error('LogBlockchainEventJob permanently failed', [
            'procurement_id' => $this->procurementId,
            'procurement_title' => $this->procurementTitle,
            'event_type' => $this->eventType,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify administrators about the failure
        $adminUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new BlockchainJobFailedNotification(
                jobName: 'Log Blockchain Event',
                procurementId: $this->procurementId,
                procurementTitle: $this->procurementTitle,
                errorMessage: $exception->getMessage(),
                attemptNumber: $this->attempts()
            ));
        }
    }
}

<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Services\BlockchainEventLoggerService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleStageTransitionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

            $multiChain->publishFrom($this->userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

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
}

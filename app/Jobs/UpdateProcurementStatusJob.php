<?php

namespace App\Jobs;

use App\Enums\StreamEnums;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateProcurementStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

            $multiChain->publishFrom($this->userAddress, StreamEnums::STATUS->value, $streamKey, $statusData);

        } catch (\Exception $e) {
            Log::error('Failed to update status on blockchain', [
                'procurement_id' => $this->procurementId,
                'error' => $e->getMessage(),
            ]);
            // Optionally: rethrow or handle error
        }
    }
}

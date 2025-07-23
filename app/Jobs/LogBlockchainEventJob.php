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

class LogBlockchainEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

            $multiChain->publishFrom($this->userAddress, StreamEnums::EVENTS->value, $streamKey, $eventData);

        } catch (\Exception $e) {
            Log::error('Failed to log event to blockchain', [
                'procurement_id' => $this->procurementId,
                'error' => $e->getMessage(),
            ]);
            // Optionally: rethrow or handle error
        }
    }
}

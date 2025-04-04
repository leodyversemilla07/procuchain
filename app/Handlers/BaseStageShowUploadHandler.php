<?php

namespace App\Handlers;

use App\Services\BlockchainService;
use Exception;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Enums\StreamEnums;

abstract class BaseStageShowUploadHandler
{
    protected $multiChain;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->multiChain = $blockchainService->getClient();
    }

    /**
     * This method must be implemented by all concrete stage handlers
     * 
     * @param string $id
     * @return mixed
     */
    abstract public function handle(string $id);

    /**
     * Get procurement status from blockchain
     * 
     * @param string $id
     * @param string $requiredStatus
     * @param string $requiredStage
     * @return array
     */
    protected function getProcurementStatus(string $id, string $requiredStatus, string $requiredStage): array
    {
        try {
            $allStatuses = $this->multiChain->listStreamItems(StreamEnums::STATUS->value, true, 1000, -1000);

            $procurementStatuses = collect($allStatuses)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                        'timestamp' => $data['timestamp'] ?? '',
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['status'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc('timestamp');

            if ($procurementStatuses->isEmpty()) {
                throw new Exception('Procurement not found');
            }

            $latestStatus = $procurementStatuses->first();

            if (
                $latestStatus['data']['status'] !== $requiredStatus ||
                $latestStatus['data']['stage'] !== $requiredStage
            ) {
                throw new Exception('This procurement is not in the required status and stage');
            }

            return [
                'id' => $id,
                'title' => $latestStatus['data']['procurement_title'] ?? 'Unknown',
                'status' => $latestStatus['data']['status'] ?? '',
                'stage' => $latestStatus['data']['stage'] ?? '',
            ];

        } catch (Exception $e) {
            Log::error('Failed to load procurement status:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Render the upload form view
     * 
     * @param array $procurement
     * @param string $viewPath
     * @return mixed
     */
    protected function renderUploadForm(array $procurement, string $viewPath)
    {
        return Inertia::render($viewPath, [
            'procurement' => $procurement,
        ]);
    }
}

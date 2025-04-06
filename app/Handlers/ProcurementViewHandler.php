<?php

namespace App\Handlers;

use App\Enums\StreamEnums;
use App\Services\BlockchainService;
use App\Services\ProcurementDataTransformerService;
use App\Services\Multichain\StreamQueryOptions;

class ProcurementViewHandler
{
    private $blockchainService;

    private $transformer;

    public function __construct(
        BlockchainService $blockchainService,
        ProcurementDataTransformerService $transformer
    ) {
        $this->blockchainService = $blockchainService;
        $this->transformer = $transformer;
    }

    public function getProcurementsList()
    {
        try {
            $statusOptions = new StreamQueryOptions(
                StreamEnums::STATUS->value,
                true,
                1000,
                -1000
            );

            $allStatuses = $this->blockchainService->getClient()->listStreamItems($statusOptions);

            return $this->transformer->transformProcurementsList($allStatuses);
        } catch (\Exception $e) {
            // Handle exception if necessary
            return [];
        }
    }

    public function getProcurementDetails(string $procurementId)
    {
        try {
            $statusOptions = new StreamQueryOptions(
                StreamEnums::STATUS->value,
                true,
                1000,
                -1000
            );

            $statuses = $this->blockchainService->getClient()->listStreamItems($statusOptions);
            $procurementStatuses = $this->transformer->transformProcurementStatuses($statuses, $procurementId);

            if ($procurementStatuses->isEmpty()) {
                return null;
            }

            $latestStatus = $procurementStatuses->first();
            $procurementTitle = $latestStatus['data']['procurement_title'] ?? '';

            return $this->transformer->buildProcurementData(
                $procurementId,
                $procurementTitle,
                $procurementStatuses,
                $this->blockchainService
            );
        } catch (\Exception $e) {
            // Handle exception if necessary
            return null;
        }
    }
}

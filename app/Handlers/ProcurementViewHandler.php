<?php

namespace App\Handlers;

use App\Enums\StreamEnums;
use App\Services\BlockchainService;
use App\Services\ProcurementDataTransformerService;

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
        $allStatuses = $this->blockchainService->getClient()->listStreamItems(StreamEnums::STATUS->value, true, 1000, -1000);

        return $this->transformer->transformProcurementsList($allStatuses);
    }

    public function getProcurementDetails(string $procurementId)
    {
        $statuses = $this->blockchainService->getClient()->listStreamItems(StreamEnums::STATUS->value, true, 1000, -1000);
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
    }
}

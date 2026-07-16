<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Services\Publishers\ProcurementOrchestrator;
use Exception;

class ProcurementInitiationHandler
{
    public function __construct(
        private readonly ProcurementOrchestrator $orchestrator,
    ) {}

    public function execute(array $data): array
    {
        $result = $this->orchestrator->initiateProcurement(
            procurementData: $data['procurement_data'],
            blockchainFiles: [],
            userName: $data['user_name'],
        );

        if (! $result['success']) {
            throw new Exception($result['message'] ?? 'Orchestrator returned failure during initiation');
        }

        return $result;
    }
}

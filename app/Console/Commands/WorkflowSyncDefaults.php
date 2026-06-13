<?php

namespace App\Console\Commands;

use App\Enums\ProcurementMode;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use App\Services\ProcurementWorkflowService;
use App\Services\StageDocumentConfigService;
use Illuminate\Console\Command;

class WorkflowSyncDefaults extends Command
{
    protected $signature = 'workflow:sync-defaults';

    protected $description = 'Materialize default workflow and document configuration rows without overriding existing records.';

    public function __construct(
        private readonly ProcurementWorkflowService $workflowService,
        private readonly StageDocumentConfigService $documentConfigService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $createdWorkflowConfigs = 0;
        $createdDocumentConfigs = 0;

        foreach (ProcurementMode::cases() as $mode) {
            $existingWorkflowConfig = ProcurementWorkflowConfig::query()
                ->forMode($mode)
                ->first();

            if ($existingWorkflowConfig === null) {
                $this->workflowService->resetToDefaults($mode);
                $createdWorkflowConfigs++;
            }

            foreach ($this->workflowService->getStagesForMode($mode) as $stage) {
                $existingDocumentConfig = StageDocumentConfig::query()
                    ->forMode($mode)
                    ->forStage($stage)
                    ->first();

                if ($existingDocumentConfig !== null) {
                    continue;
                }

                $this->documentConfigService->resetToDefaults($stage, $mode);
                $createdDocumentConfigs++;
            }
        }

        $this->info("Created {$createdWorkflowConfigs} workflow config(s) and {$createdDocumentConfigs} document config(s).");

        return self::SUCCESS;
    }
}

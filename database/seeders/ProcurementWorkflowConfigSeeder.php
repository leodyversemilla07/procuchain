<?php

namespace Database\Seeders;

use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use Illuminate\Database\Seeder;

/**
 * Seeds initial workflow configurations from StageEnums defaults.
 *
 * This seeder populates the procurement_workflow_configs table with
 * the default workflow stages for each procurement mode.
 */
class ProcurementWorkflowConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (ProcurementMode::cases() as $mode) {
            // Get default stages from StageEnums
            $stages = StageEnums::getStagesForMode($mode);
            $optionalStages = StageEnums::getOptionalStagesForMode($mode);

            ProcurementWorkflowConfig::updateOrCreate(
                ['procurement_mode' => $mode->value],
                [
                    'display_name' => $mode->getDisplayName(),
                    'description' => $mode->getDescription(),
                    'stages' => array_map(fn (StageEnums $s) => $s->value, $stages),
                    'optional_stages' => array_map(fn (StageEnums $s) => $s->value, $optionalStages),
                    'is_active' => true,
                    'updated_by' => null,
                ]
            );
        }

        $this->command->info('Seeded workflow configurations for '.count(ProcurementMode::cases()).' procurement modes.');
    }
}

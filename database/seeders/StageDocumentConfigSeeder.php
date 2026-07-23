<?php

namespace Database\Seeders;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\StageDocumentConfig;
use App\Services\ModeAwareDocumentRequirementsService;
use App\Services\StageDocumentRequirementsService;
use App\Support\ModeDocumentRequirements;
use Illuminate\Database\Seeder;

/**
 * Seeds initial stage document configurations from existing service defaults.
 *
 * This seeder populates the stage_document_configs table with
 * the default document requirements for each stage/mode combination.
 */
class StageDocumentConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseRequirements = app(StageDocumentRequirementsService::class);
        $modeAwareRequirements = new ModeAwareDocumentRequirementsService($baseRequirements, new ModeDocumentRequirements);

        $count = 0;

        foreach (ProcurementMode::cases() as $mode) {
            // Get stages for this mode
            $stages = StageEnums::getStagesForMode($mode);

            foreach ($stages as $stage) {
                // Get default documents from ModeAwareDocumentRequirements
                $requiredDocs = $modeAwareRequirements->getRequiredDocuments($stage, $mode);
                $optionalDocs = $modeAwareRequirements->getOptionalDocuments($stage, $mode);

                StageDocumentConfig::updateOrCreate(
                    [
                        'stage' => $stage->value,
                        'procurement_mode' => $mode->value,
                    ],
                    [
                        'stage_display_name' => $stage->getDisplayName(),
                        'required_documents' => array_map(
                            fn (DocumentTypeEnums $d) => $d->value,
                            $requiredDocs
                        ),
                        'optional_documents' => array_map(
                            fn (DocumentTypeEnums $d) => $d->value,
                            $optionalDocs
                        ),
                        'is_active' => true,
                        'updated_by' => null,
                    ]
                );

                $count++;
            }
        }

        $this->command->info("Seeded {$count} stage document configurations.");
    }
}

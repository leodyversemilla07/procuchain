<?php

namespace App\Handlers\Completion;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class CompletionDocumentsShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::MONITORING->value,
                StageEnums::COMPLETION->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/completion-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load Completion Upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Completion Upload form: ' . $e->getMessage());
        }
    }
}

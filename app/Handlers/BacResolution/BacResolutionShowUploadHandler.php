<?php

namespace App\Handlers\BacResolution;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class BacResolutionShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::POST_QUALIFICATION_VERIFIED->value,
                StageEnums::BAC_RESOLUTION->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/bac-resolution-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load BAC Resolution upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading BAC Resolution upload form: ' . $e->getMessage());
        }
    }
}
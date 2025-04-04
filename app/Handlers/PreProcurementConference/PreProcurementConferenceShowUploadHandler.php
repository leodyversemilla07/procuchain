<?php

namespace App\Handlers\PreProcurementConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageShowUploadHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class PreProcurementConferenceShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD->value,
                StageEnums::PRE_PROCUREMENT_CONFERENCE->value
            );

            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/pre-procurement-conference-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load pre-procurement upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading pre-procurement upload form: '.$e->getMessage());
        }
    }
}

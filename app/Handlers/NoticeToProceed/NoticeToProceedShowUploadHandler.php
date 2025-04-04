<?php

namespace App\Handlers\NoticeToProceed;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageShowUploadHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class NoticeToProceedShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED->value,
                StageEnums::NOTICE_TO_PROCEED->value
            );

            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/ntp-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load Notice to Proceed upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice to Proceed upload form: '.$e->getMessage());
        }
    }
}

<?php

namespace App\Handlers\NoticeOfAward;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class NoticeOfAwardShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::RESOLUTION_RECORDED->value,
                StageEnums::NOTICE_OF_AWARD->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/noa-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load Notice of Award upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice of Award upload form: ' . $e->getMessage());
        }
    }
}

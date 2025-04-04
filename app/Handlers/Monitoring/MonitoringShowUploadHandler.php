<?php

namespace App\Handlers\Monitoring;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class MonitoringShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::NTP_RECORDED->value,
                StageEnums::MONITORING->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/monitoring-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load Monitoring upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Monitoring upload form: ' . $e->getMessage());
        }
    }
}

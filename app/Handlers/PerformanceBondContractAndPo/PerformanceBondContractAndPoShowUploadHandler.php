<?php

namespace App\Handlers\PerformanceBondContractAndPo;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StatusEnums;
use App\Enums\StageEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class PerformanceBondContractAndPoShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::AWARDED->value,
                StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/performance-bond-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load Performance Bond upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Performance Bond upload form: ' . $e->getMessage());
        }
    }
}

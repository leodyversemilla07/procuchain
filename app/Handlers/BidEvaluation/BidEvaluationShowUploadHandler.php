<?php

namespace App\Handlers\BidEvaluation;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageShowUploadHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class BidEvaluationShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::BIDS_OPENED->value,
                StageEnums::BID_EVALUATION->value
            );

            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/bid-evaluation-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load bid evaluation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid evaluation upload form: '.$e->getMessage());
        }
    }
}

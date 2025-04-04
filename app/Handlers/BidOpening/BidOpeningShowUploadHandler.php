<?php

namespace App\Handlers\BidOpening;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageShowUploadHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class BidOpeningShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::BIDDING_DOCUMENTS_PUBLISHED->value,
                StageEnums::BID_OPENING->value
            );

            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/bid-submission-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load bid submission upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid submission upload form: '.$e->getMessage());
        }
    }
}

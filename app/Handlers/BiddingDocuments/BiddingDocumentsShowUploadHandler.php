<?php

namespace App\Handlers\BiddingDocuments;

use App\Handlers\BaseStageShowUploadHandler;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Support\Facades\Log;

class BiddingDocumentsShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->value,
                StageEnums::BIDDING_DOCUMENTS->value
            );
            
            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/bid-invitation-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load bid invitation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid invitation upload form: ' . $e->getMessage());
        }
    }
}
<?php

namespace App\Handlers\PostQualification;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageShowUploadHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class PostQualificationShowUploadHandler extends BaseStageShowUploadHandler
{
    public function handle(string $id)
    {
        try {
            $procurement = $this->getProcurementStatus(
                $id,
                StatusEnums::BIDS_EVALUATED->value,
                StageEnums::POST_QUALIFICATION->value
            );

            return $this->renderUploadForm(
                $procurement,
                'bac-secretariat/procurement-stage/post-qualification-upload'
            );
        } catch (Exception $e) {
            Log::error('Failed to load post-qualification upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading post-qualification upload form: '.$e->getMessage());
        }
    }
}

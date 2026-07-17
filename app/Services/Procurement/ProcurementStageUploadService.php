<?php

namespace App\Services\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Jobs\BlockchainWriteJob;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ModeAwareDocumentValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcurementStageUploadService
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ModeAwareDocumentValidationService $modeAwareDocumentValidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{status: int, data: array<string, mixed>}
     */
    public function queueDocumentUpload(
        string $prNumber,
        StageEnums $stage,
        UploadedFile $file,
        DocumentTypeEnums $documentType,
        ?string $description,
        array $metadata,
        User $user,
    ): array {
        $this->procurementSupport->validateStageInWorkflow($prNumber, $stage);

        $existingDocumentEnums = array_values(array_filter(
            array_map(
                fn (string $uploadedDocument): ?DocumentTypeEnums => DocumentTypeEnums::tryFrom($uploadedDocument),
                $this->procurementSupport->getUploadedDocumentTypes($prNumber, $stage),
            ),
        ));
        $validation = $this->modeAwareDocumentValidationService->validateUpload(
            $stage,
            $documentType,
            $existingDocumentEnums,
            $this->procurementSupport->getProcurementMode($prNumber),
        );

        if (! empty($validation['errors'])) {
            return [
                'status' => 422,
                'data' => ['message' => implode(' ', $validation['errors'])],
            ];
        }

        $procurement = $this->resolveProcurementData($prNumber, $stage, $user);
        if ($procurement === null) {
            return [
                'status' => 422,
                'data' => ['message' => 'Procurement not found. Please ensure the procurement has been properly initiated.'],
            ];
        }

        $tempPath = $file->store('temp/blockchain-uploads');
        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('upload_document', [
            'pr_number' => $prNumber,
            'procurement_title' => $procurement->title,
            'user_address' => $user->blockchain_address,
            'stage' => $stage->value,
            'status' => $procurement->current_status,
            'current_status' => (ProcurementStatus::tryFrom($procurement->current_status) ?? $this->procurementSupport->getOngoingStatusForStage($stage))->value,
            'document_type' => $documentType->value,
            'uploaded_by' => $user->name,
            'description' => $description,
            'stage_metadata' => $metadata,
            'temp_file_path' => $tempPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
        ], $jobId, $user->id);

        return [
            'status' => 202,
            'data' => [
                'job_id' => $jobId,
                'status' => 'pending',
                'document_type' => $documentType->getDisplayName(),
            ],
        ];
    }

    private function resolveProcurementData(string $prNumber, StageEnums $stage, User $user): ?Procurement
    {
        $procurement = Procurement::where('pr_number', $prNumber)->first();
        if ($procurement !== null) {
            return $procurement;
        }

        Log::warning('Procurement not found in database, attempting fallback to STATUS stream', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'user' => $user->email,
        ]);

        $statusData = $this->procurementSupport->findProcurementById($prNumber);
        if ($statusData === null) {
            Log::error('Procurement not found in both database and STATUS streams', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'user' => $user->email,
            ]);

            return null;
        }

        return new Procurement([
            'pr_number' => $prNumber,
            'app_reference' => $statusData['app_reference'] ?? null,
            'title' => $statusData['procurement_title'] ?? 'N/A',
            'description' => $statusData['description'] ?? 'N/A',
            'abc_amount' => (float) ($statusData['abc_amount'] ?? 0),
            'fund_source' => $statusData['funding_source'] ?? 'N/A',
            'category' => $statusData['category'] ?? 'goods',
            'procurement_mode' => $this->procurementSupport->getProcurementMode($prNumber)?->value ?? 'competitive_bidding',
            'office' => $statusData['office'] ?? 'N/A',
            'end_user' => $statusData['end_user'] ?? null,
            'delivery_location' => $statusData['delivery_location'] ?? null,
            'delivery_date' => $statusData['delivery_date'] ?? null,
            'delivery_term_days' => isset($statusData['delivery_term_days']) ? (int) $statusData['delivery_term_days'] : null,
            'prepared_by' => $statusData['prepared_by'] ?? null,
            'bac_resolution_number' => $statusData['bac_resolution_number'] ?? null,
            'bac_resolution_date' => $statusData['bac_resolution_date'] ?? null,
            'philgeps_reference' => $statusData['philgeps_reference'] ?? null,
            'philgeps_posting_date' => $statusData['philgeps_posting_date'] ?? null,
            'approved_by' => $statusData['approved_by'] ?? null,
            'approval_date' => $statusData['approval_date'] ?? null,
            'current_status' => ProcurementStatus::tryFrom($statusData['current_status'] ?? '')?->value ?? $this->procurementSupport->getOngoingStatusForStage($stage)->value,
            'user_id' => (string) ($statusData['user_id'] ?? $user->id),
            'initiated_at' => $statusData['created_at'] ?? now(),
        ]);
    }
}

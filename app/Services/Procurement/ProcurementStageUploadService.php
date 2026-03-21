<?php

namespace App\Services\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ModeAwareDocumentValidationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcurementStageUploadService
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ProcurementRepository $procurementRepository,
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

        \App\Jobs\BlockchainWriteJob::dispatch('upload_document', [
            'pr_number' => $prNumber,
            'procurement_title' => $procurement->title,
            'user_address' => $user->blockchain_address,
            'stage' => $stage->value,
            'status' => $procurement->status,
            'current_status' => (StatusEnums::tryFrom($procurement->status) ?? $this->procurementSupport->getOngoingStatusForStage($stage))->value,
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

    private function resolveProcurementData(string $prNumber, StageEnums $stage, User $user): ?ProcurementData
    {
        $procurement = $this->procurementRepository->findByProcurement($prNumber);
        if ($procurement !== null) {
            return $procurement;
        }

        Log::warning('Procurement not found in METADATA stream, attempting fallback to STATUS stream', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
            'user' => $user->email,
        ]);

        $statusData = $this->procurementSupport->findProcurementById($prNumber);
        if ($statusData === null) {
            Log::error('Procurement not found in both METADATA and STATUS streams', [
                'pr_number' => $prNumber,
                'stage' => $stage->value,
                'user' => $user->email,
            ]);

            return null;
        }

        return new ProcurementData(
            prNumber: $prNumber,
            appReference: $statusData['app_reference'] ?? null,
            title: $statusData['procurement_title'] ?? 'N/A',
            description: $statusData['description'] ?? 'N/A',
            abcAmount: (float) ($statusData['abc_amount'] ?? 0),
            fundingSource: $statusData['funding_source'] ?? 'N/A',
            category: ProcurementCategoryEnums::tryFrom($statusData['category'] ?? '') ?? ProcurementCategoryEnums::GOODS,
            procurementMode: $this->procurementSupport->getProcurementMode($prNumber) ?? ProcurementModeEnums::COMPETITIVE_BIDDING,
            office: $statusData['office'] ?? 'N/A',
            endUser: $statusData['end_user'] ?? null,
            deliveryLocation: $statusData['delivery_location'] ?? null,
            deliveryDate: isset($statusData['delivery_date']) ? Carbon::parse($statusData['delivery_date']) : null,
            deliveryTermDays: isset($statusData['delivery_term_days']) ? (int) $statusData['delivery_term_days'] : null,
            preparedBy: $statusData['prepared_by'] ?? null,
            bacResolutionNumber: $statusData['bac_resolution_number'] ?? null,
            bacResolutionDate: isset($statusData['bac_resolution_date']) ? Carbon::parse($statusData['bac_resolution_date']) : null,
            philgepsReference: $statusData['philgeps_reference'] ?? null,
            philgepsPostingDate: isset($statusData['philgeps_posting_date']) ? Carbon::parse($statusData['philgeps_posting_date']) : null,
            approvedBy: $statusData['approved_by'] ?? null,
            approvalDate: isset($statusData['approval_date']) ? Carbon::parse($statusData['approval_date']) : null,
            status: StatusEnums::tryFrom($statusData['current_status'] ?? '')?->value ?? $this->procurementSupport->getOngoingStatusForStage($stage)->value,
            userId: (string) ($statusData['user_id'] ?? $user->id),
            createdAt: isset($statusData['created_at']) ? Carbon::parse($statusData['created_at']) : now(),
        );
    }
}

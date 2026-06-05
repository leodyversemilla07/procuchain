<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Jobs\BlockchainWriteJob;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\Procurement\ProcurementSupportService;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create([
        'blockchain_address' => 'test_address_123',
    ]);
    $this->bacSecretariat->assignRole('bac_secretariat');
});

describe('ProcurementStageController (Phase Pages)', function () {
    it('renders stage pages across all procurement phases', function () {
        actingAs($this->bacSecretariat);
        bindPhasePageStubs(buildPhaseProcurementData($this->bacSecretariat));

        $routes = [
            route('bac-secretariat.procurement.pre-procurement.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]),
            route('bac-secretariat.procurement.pre-procurement.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::BID_EVALUATION->value,
            ]),
            route('bac-secretariat.procurement.bidding.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::BID_EVALUATION->value,
            ]),
            route('bac-secretariat.procurement.bidding.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]),
            route('bac-secretariat.procurement.post-procurement.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
            ]),
            route('bac-secretariat.procurement.post-procurement.show', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::BID_EVALUATION->value,
            ]),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('bac-secretariat/stage-upload')
                ->has('procurement')
                ->has('documentGuide')
            );
        }
    });
});

describe('ProcurementStageController (Actions)', function () {
    it('uploads documents successfully for each phase', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);
        bindUploadStubs(buildPhaseProcurementData($this->bacSecretariat));

        $cases = [
            [
                'route' => route('bac-secretariat.procurement.pre-procurement.upload-document', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                ]),
                'document_type' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
                'filename' => 'minutes.pdf',
            ],
            [
                'route' => route('bac-secretariat.procurement.bidding.upload-document', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::BID_EVALUATION->value,
                ]),
                'document_type' => DocumentTypeEnums::BID_EVALUATION_REPORT->value,
                'filename' => 'evaluation_report.pdf',
            ],
            [
                'route' => route('bac-secretariat.procurement.post-procurement.upload-document', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::NOTICE_OF_AWARD->value,
                ]),
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
                'filename' => 'notice_of_award.pdf',
            ],
            [
                'route' => route('bac-secretariat.procurement.post-procurement.upload-document', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::COMPLETION->value,
                ]),
                'document_type' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION->value,
                'filename' => 'completion_certificate.pdf',
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->withoutMiddleware('throttle:blockchain_writes')
                ->withoutMiddleware(PreventRequestForgery::class)
                ->startSession()
                ->post($case['route'], [
                    'document_file' => UploadedFile::fake()->create($case['filename'], 1000, 'application/pdf'),
                    'document_type' => $case['document_type'],
                ]);

            $response->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'document_type']);
        }

        Queue::assertPushed(BlockchainWriteJob::class, 4);
    });

    it('returns the document guide for a stage', function () {
        actingAs($this->bacSecretariat);
        bindModeAwareGuideStubs(buildPhaseProcurementData($this->bacSecretariat));

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.document-guide', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'stage',
            'stage_display_name',
            'phase',
            'description',
            'required_documents',
            'optional_documents',
            'counts',
        ]);
    });

    it('handles pre-procurement conference decisions', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->startSession()
            ->post(route('bac-secretariat.publish-pre-procurement-conference-decision'), [
                'pr_number' => 'PR-2024-001-0001',
                'procurement_title' => 'Test Procurement Project',
                'conference_held' => true,
            ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status']);
        Queue::assertPushed(BlockchainWriteJob::class);
    });

    it('checks stage completion status', function () {
        actingAs($this->bacSecretariat);
        bindCompletionValidationStubs(buildPhaseProcurementData($this->bacSecretariat), [
            'can_complete' => true,
            'completion_percentage' => 100,
            'missing_documents' => [],
        ]);

        $response = $this->get(route('bac-secretariat.procurement.bidding.check-completion', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertJson([
            'can_complete' => true,
            'completion_percentage' => 100,
            'missing_documents' => [],
        ]);
    });

    it('validates document upload in real time', function () {
        actingAs($this->bacSecretariat);
        bindValidateUploadStubs(buildPhaseProcurementData($this->bacSecretariat));

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->startSession()
            ->post(route('bac-secretariat.procurement.post-procurement.validate-upload', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
            ]), [
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
                'file' => UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf'),
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'valid' => true,
            'errors' => [],
        ]);
    });
});

describe('Authorization', function () {
    it('denies access to non-bac-secretariat users', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertForbidden();
    });

    it('denies access to unauthenticated users', function () {
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertRedirect(route('login'));
    });
});

function buildPhaseProcurementData(User $user): ProcurementData
{
    return new ProcurementData(
        prNumber: 'PR-2024-001-0001',
        appReference: 'APP-2024-001',
        title: 'Test Procurement',
        description: 'Test Description',
        abcAmount: 1000000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategoryEnums::GOODS,
        procurementMode: ProcurementModeEnums::COMPETITIVE_BIDDING,
        office: 'Test Office',
        endUser: 'Test User',
        deliveryLocation: null,
        deliveryDate: null,
        deliveryTermDays: null,
        preparedBy: 'Test Preparer',
        bacResolutionNumber: null,
        bacResolutionDate: null,
        philgepsReference: null,
        philgepsPostingDate: null,
        approvedBy: null,
        approvalDate: null,
        status: 'in_progress',
        userId: (string) $user->id,
        createdAt: now(),
    );
}

function bindProcurementRepositoryStub(ProcurementData $procurementData): void
{
    $repository = mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn($procurementData);

    app()->instance(ProcurementRepository::class, $repository);
}

function bindProcurementDataServiceStub(User $user): void
{
    $dataService = mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->zeroOrMoreTimes()
        ->andReturn(collect([
            [
                'user_address' => $user->blockchain_address,
            ],
        ]));

    app()->instance(ProcurementDataService::class, $dataService);
}

function bindSupportServiceStub(ProcurementData $procurementData): void
{
    $support = mock(ProcurementSupportService::class);
    $support->shouldReceive('stageExistsInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturn(true);
    $support->shouldReceive('validateStageInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $support->shouldReceive('findProcurementById')
        ->zeroOrMoreTimes()
        ->andReturn([
            'procurement_title' => $procurementData->title,
            'current_status' => $procurementData->status,
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]);
    $support->shouldReceive('handleAutoStageTransition')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $support->shouldReceive('getProcurementMode')
        ->zeroOrMoreTimes()
        ->andReturn($procurementData->procurementMode);
    $support->shouldReceive('getWorkflowInfo')
        ->zeroOrMoreTimes()
        ->andReturn([
            'mode' => [
                'value' => $procurementData->procurementMode->value,
            ],
            'workflow' => [
                'stages' => [],
                'total_stages' => 1,
                'current_index' => 0,
                'progress_percentage' => 0,
            ],
        ]);
    $support->shouldReceive('getUploadedDocumentTypes')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $support->shouldReceive('getOngoingStatusForStage')
        ->zeroOrMoreTimes()
        ->andReturn(StatusEnums::PROCUREMENT_SUBMITTED);

    app()->instance(ProcurementSupportService::class, $support);
}

function bindModeAwareValidationStub(array $uploadResult = ['errors' => [], 'warnings' => []], ?array $guide = null): void
{
    $modeAwareValidation = mock(ModeAwareDocumentValidationService::class);
    $modeAwareValidation->shouldReceive('validateUpload')
        ->zeroOrMoreTimes()
        ->andReturn($uploadResult);
    $modeAwareValidation->shouldReceive('getStageDocumentGuide')
        ->zeroOrMoreTimes()
        ->andReturn($guide ?? [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'stage_display_name' => StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
            'phase' => 'pre_procurement',
            'description' => 'Test guide',
            'required_documents' => [],
            'optional_documents' => [],
            'counts' => [
                'required_count' => 0,
                'optional_count' => 0,
            ],
        ]);

    app()->instance(ModeAwareDocumentValidationService::class, $modeAwareValidation);
}

function bindCompletionValidationStub(array $completion = ['can_complete' => false]): void
{
    $validation = mock(ModeAwareDocumentValidationService::class);
    $validation->shouldReceive('validateStageCompletion')
        ->zeroOrMoreTimes()
        ->andReturn(array_merge([
            'required_documents' => [],
            'uploaded_documents' => [],
            'missing_documents' => [],
            'completion_percentage' => 0,
            'mode' => ProcurementModeEnums::COMPETITIVE_BIDDING->value,
            'mode_display_name' => ProcurementModeEnums::COMPETITIVE_BIDDING->getDisplayName(),
            'is_alternative_mode' => false,
        ], $completion));

    app()->instance(ModeAwareDocumentValidationService::class, $validation);
}

function bindPhasePageStubs(ProcurementData $procurementData): void
{
    bindProcurementRepositoryStub($procurementData);
    bindProcurementDataServiceStub(User::findOrFail((int) $procurementData->userId));
    bindSupportServiceStub($procurementData);
    bindModeAwareValidationStub();
}

function bindUploadStubs(ProcurementData $procurementData): void
{
    bindProcurementRepositoryStub($procurementData);
    bindProcurementDataServiceStub(User::findOrFail((int) $procurementData->userId));
    bindSupportServiceStub($procurementData);
    bindModeAwareValidationStub();
}

function bindModeAwareGuideStubs(ProcurementData $procurementData): void
{
    bindProcurementDataServiceStub(User::findOrFail((int) $procurementData->userId));
    bindSupportServiceStub($procurementData);
    bindModeAwareValidationStub(guide: [
        'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        'stage_display_name' => StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
        'phase' => 'pre_procurement',
        'description' => 'Test guide',
        'required_documents' => [
            [
                'value' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
                'display_name' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->getDisplayName(),
            ],
        ],
        'optional_documents' => [
            [
                'value' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->value,
                'display_name' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->getDisplayName(),
            ],
        ],
        'counts' => [
            'required_count' => 1,
            'optional_count' => 1,
        ],
    ]);
}

function bindCompletionValidationStubs(ProcurementData $procurementData, array $completion): void
{
    bindProcurementDataServiceStub(User::findOrFail((int) $procurementData->userId));
    bindSupportServiceStub($procurementData);
    bindCompletionValidationStub($completion);
}

function bindValidateUploadStubs(ProcurementData $procurementData): void
{
    bindProcurementDataServiceStub(User::findOrFail((int) $procurementData->userId));
    bindSupportServiceStub($procurementData);
    bindModeAwareValidationStub(['errors' => [], 'warnings' => []]);
}

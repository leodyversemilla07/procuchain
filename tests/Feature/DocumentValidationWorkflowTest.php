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
        'blockchain_address' => 'test_address_validation',
    ]);
    $this->bacSecretariat->assignRole('bac_secretariat');
});

describe('Document Upload Validation Workflow', function () {
    it('rejects invalid document uploads before dispatching a blockchain job', function () {
        actingAs($this->bacSecretariat);
        bindDocumentWorkflowSupportStubs(buildDocumentWorkflowProcurement($this->bacSecretariat));
        bindModeAwareValidationServiceStub([
            'errors' => ['Document type NOTICE_OF_AWARD is not valid for stage PRE_PROCUREMENT_CONFERENCE'],
            'warnings' => [],
        ]);

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->startSession()
            ->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]), [
                'document_file' => UploadedFile::fake()->create('noa.pdf', 1000, 'application/pdf'),
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
                'description' => 'Invalid document for this stage',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Document type NOTICE_OF_AWARD is not valid for stage PRE_PROCUREMENT_CONFERENCE',
            ]);
    });

    it('returns validation warnings for atypical stage documents', function () {
        actingAs($this->bacSecretariat);
        bindDocumentWorkflowSupportStubs(buildDocumentWorkflowProcurement($this->bacSecretariat));
        bindModeAwareValidationServiceStub([
            'errors' => [],
            'warnings' => ['This document is not typically required for this stage'],
        ]);

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->startSession()
            ->post(route('bac-secretariat.procurement.pre-procurement.validate-upload', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]), [
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
                'file' => UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf'),
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('warnings.0', 'This document is not typically required for this stage');
    });

    it('returns the document guide for a stage', function () {
        actingAs($this->bacSecretariat);
        bindDocumentWorkflowSupportStubs(buildDocumentWorkflowProcurement($this->bacSecretariat));
        bindModeAwareValidationServiceStub(
            guide: [
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'stage_display_name' => 'Pre-Procurement Conference',
                'phase' => 'pre_procurement',
                'description' => 'Documents for Pre-Procurement Conference',
                'required_documents' => [
                    [
                        'value' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
                        'display_name' => 'Meeting Minutes',
                        'description' => 'Official minutes of the pre-procurement conference',
                    ],
                ],
                'optional_documents' => [
                    [
                        'value' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->value,
                        'display_name' => 'Meeting Agenda',
                        'description' => 'Agenda for the pre-procurement conference',
                    ],
                ],
                'counts' => [
                    'required_count' => 1,
                    'optional_count' => 1,
                    'total_count' => 2,
                ],
            ],
        );

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
            'required_documents' => [
                '*' => ['value', 'display_name', 'description'],
            ],
            'optional_documents' => [
                '*' => ['value', 'display_name', 'description'],
            ],
            'counts' => ['required_count', 'optional_count', 'total_count'],
        ]);
    });
});

describe('Progressive Upload Workflow', function () {
    it('uploads a single document progressively', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);
        bindDocumentWorkflowSupportStubs(buildDocumentWorkflowProcurement($this->bacSecretariat));
        bindModeAwareValidationServiceStub();

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->startSession()
            ->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]), [
                'document_file' => UploadedFile::fake()->create('minutes.pdf', 1000, 'application/pdf'),
                'document_type' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
                'description' => 'Meeting minutes for pre-procurement conference',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'document_type']);

        Queue::assertPushed(BlockchainWriteJob::class);
    });

    it('tracks completion progress as documents are uploaded', function () {
        actingAs($this->bacSecretariat);
        bindDocumentWorkflowSupportStubs(buildDocumentWorkflowProcurement($this->bacSecretariat));

        $cases = [
            [
                'completion' => [
                    'can_complete' => false,
                    'completion_percentage' => 0,
                    'missing_documents' => [
                        DocumentTypeEnums::PRE_PROCUREMENT_MINUTES,
                        DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
                    ],
                ],
                'expected_percentage' => 0,
                'expected_ready' => false,
                'expected_missing_count' => 2,
            ],
            [
                'completion' => [
                    'can_complete' => false,
                    'completion_percentage' => 50,
                    'missing_documents' => [
                        DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
                    ],
                ],
                'expected_percentage' => 50,
                'expected_ready' => false,
                'expected_missing_count' => 1,
            ],
            [
                'completion' => [
                    'can_complete' => true,
                    'completion_percentage' => 100,
                    'missing_documents' => [],
                ],
                'expected_percentage' => 100,
                'expected_ready' => true,
                'expected_missing_count' => 0,
            ],
        ];

        $validation = mock(ModeAwareDocumentValidationService::class);
        $validation->shouldReceive('validateStageCompletion')
            ->times(count($cases))
            ->andReturn(...array_map(function (array $case): array {
                return array_merge([
                    'required_documents' => [],
                    'uploaded_documents' => [],
                    'mode' => ProcurementModeEnums::COMPETITIVE_BIDDING->value,
                    'mode_display_name' => ProcurementModeEnums::COMPETITIVE_BIDDING->getDisplayName(),
                    'is_alternative_mode' => false,
                ], $case['completion']);
            }, $cases));
        app()->instance(ModeAwareDocumentValidationService::class, $validation);

        foreach ($cases as $case) {
            $response = $this->get(route('bac-secretariat.procurement.pre-procurement.check-completion', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]));

            $response->assertSuccessful();
            $response->assertJsonPath('completion_percentage', $case['expected_percentage']);
            $response->assertJsonPath('can_complete', $case['expected_ready']);
            expect($response->json('missing_documents'))->toHaveCount($case['expected_missing_count']);
        }
    });
});

function buildDocumentWorkflowProcurement(User $user): ProcurementData
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

function bindDocumentWorkflowSupportStubs(ProcurementData $procurementData): void
{
    $repository = mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn($procurementData);
    app()->instance(ProcurementRepository::class, $repository);

    $dataService = mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->zeroOrMoreTimes()
        ->andReturn(collect([
            [
                'user_address' => User::findOrFail((int) $procurementData->userId)->blockchain_address,
            ],
        ]));
    app()->instance(ProcurementDataService::class, $dataService);

    $support = mock(ProcurementSupportService::class);
    $support->shouldReceive('validateStageInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $support->shouldReceive('getUploadedDocumentTypes')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $support->shouldReceive('getProcurementMode')
        ->zeroOrMoreTimes()
        ->andReturn($procurementData->procurementMode);
    $support->shouldReceive('getOngoingStatusForStage')
        ->zeroOrMoreTimes()
        ->andReturn(StatusEnums::PROCUREMENT_SUBMITTED);
    app()->instance(ProcurementSupportService::class, $support);
}

function bindModeAwareValidationServiceStub(
    array $uploadResult = ['errors' => [], 'warnings' => []],
    ?array $guide = null,
): void {
    $validation = mock(ModeAwareDocumentValidationService::class);
    $validation->shouldReceive('validateUpload')
        ->zeroOrMoreTimes()
        ->andReturn($uploadResult);
    $validation->shouldReceive('getStageDocumentGuide')
        ->zeroOrMoreTimes()
        ->andReturn($guide ?? [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'stage_display_name' => 'Pre-Procurement Conference',
            'phase' => 'pre_procurement',
            'description' => 'Test guide',
            'required_documents' => [],
            'optional_documents' => [],
            'counts' => [
                'required_count' => 0,
                'optional_count' => 0,
                'total_count' => 0,
            ],
        ]);

    app()->instance(ModeAwareDocumentValidationService::class, $validation);
}

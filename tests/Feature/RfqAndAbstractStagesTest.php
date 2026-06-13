<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\NormalizedTableSyncService;
use App\Services\Procurement\ProcurementSupportService;
use App\Services\ProcurementDataService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create([
        'blockchain_address' => 'test_address_123',
    ]);
    $this->bacSecretariat->assignRole('bac_secretariat');

    $this->svpProcurementData = buildSvpProcurementData($this->bacSecretariat);
});

describe('SVP Stage Pages', function () {
    it('renders RFQ and Abstract stage pages with workflow info', function () {
        actingAs($this->bacSecretariat);
        bindSvpStageSupportStubs($this->svpProcurementData);
        bindSvpModeAwareValidationStub();

        $routes = [
            stagePageRoute(StageEnums::REQUEST_FOR_QUOTATION),
            stagePageRoute(StageEnums::ABSTRACT_OF_QUOTATIONS),
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);

            $response->assertSuccessful();
            $response->assertInertia(fn ($page) => $page
                ->component('bac-secretariat/stage-upload')
                ->has('procurement')
                ->has('documentGuide')
                ->has('workflowInfo', fn ($workflow) => $workflow
                    ->has('mode')
                    ->has('workflow')
                    ->has('workflow.stages')
                    ->has('workflow.total_stages')
                    ->has('workflow.current_index')
                    ->has('workflow.progress_percentage')
                )
            );
        }
    });

    it('returns document guides for RFQ and Abstract stages', function () {
        actingAs($this->bacSecretariat);
        bindSvpStageSupportStubs($this->svpProcurementData);

        $guideMap = [
            StageEnums::REQUEST_FOR_QUOTATION->value => [
                'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
                'stage_display_name' => StageEnums::REQUEST_FOR_QUOTATION->getDisplayName(),
                'phase' => 'pre_procurement',
                'description' => 'RFQ guide',
                'required_documents' => [
                    ['value' => DocumentTypeEnums::REQUEST_FOR_QUOTATION->value],
                ],
                'optional_documents' => [],
                'counts' => [
                    'required_count' => 1,
                    'optional_count' => 0,
                    'total_count' => 1,
                ],
            ],
            StageEnums::ABSTRACT_OF_QUOTATIONS->value => [
                'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
                'stage_display_name' => StageEnums::ABSTRACT_OF_QUOTATIONS->getDisplayName(),
                'phase' => 'procurement',
                'description' => 'Abstract guide',
                'required_documents' => [
                    ['value' => DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS->value],
                ],
                'optional_documents' => [],
                'counts' => [
                    'required_count' => 1,
                    'optional_count' => 0,
                    'total_count' => 1,
                ],
            ],
        ];

        bindSvpModeAwareValidationStub($guideMap);

        $cases = [
            [
                'route' => route('bac-secretariat.procurement.pre-procurement.document-guide', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
                ]),
                'expected_stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
            ],
            [
                'route' => route('bac-secretariat.procurement.bidding.document-guide', [
                    'pr_number' => 'PR-2024-001-0001',
                    'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
                ]),
                'expected_stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->get($case['route']);

            $response->assertSuccessful();
            $response->assertJsonPath('stage', $case['expected_stage']);
        }
    });

    it('checks completion status for RFQ and Abstract stages', function () {
        actingAs($this->bacSecretariat);
        bindSvpStageSupportStubs($this->svpProcurementData);

        $validation = mock(ModeAwareDocumentValidationService::class);
        $validation->shouldReceive('validateStageCompletion')
            ->times(2)
            ->andReturn(
                [
                    'can_complete' => false,
                    'required_documents' => [],
                    'uploaded_documents' => [],
                    'completion_percentage' => 0,
                    'missing_documents' => [DocumentTypeEnums::REQUEST_FOR_QUOTATION->value],
                    'mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
                    'mode_display_name' => ProcurementMode::SMALL_VALUE_PROCUREMENT->getDisplayName(),
                    'is_alternative_mode' => true,
                ],
                [
                    'can_complete' => false,
                    'required_documents' => [],
                    'uploaded_documents' => [],
                    'completion_percentage' => 0,
                    'missing_documents' => [DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS->value],
                    'mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
                    'mode_display_name' => ProcurementMode::SMALL_VALUE_PROCUREMENT->getDisplayName(),
                    'is_alternative_mode' => true,
                ],
            );
        app()->instance(ModeAwareDocumentValidationService::class, $validation);

        $cases = [
            route('bac-secretariat.procurement.pre-procurement.check-completion', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
            ]),
            route('bac-secretariat.procurement.bidding.check-completion', [
                'pr_number' => 'PR-2024-001-0001',
                'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
            ]),
        ];

        foreach ($cases as $route) {
            $response = $this->get($route);

            $response->assertSuccessful();
            $response->assertJsonPath('can_complete', false);
            $response->assertJsonPath('completion_percentage', 0);
        }
    });
});

describe('Mode-Aware Stage Navigation', function () {
    it('validates stage inclusion and next-stage mappings for RFQ and Abstract', function () {
        $svpStages = StageEnums::getStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);
        $competitiveStages = StageEnums::getStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);
        $rfqNextStages = StageEnums::REQUEST_FOR_QUOTATION->getNextStagesForMode(
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );
        $abstractNextStages = StageEnums::ABSTRACT_OF_QUOTATIONS->getNextStagesForMode(
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        expect($svpStages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($svpStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
        expect($competitiveStages)->not->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($competitiveStages)->not->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
        expect($rfqNextStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
        expect($abstractNextStages)->toContain(StageEnums::BAC_RESOLUTION);
    });

    it('validates which procurement modes include RFQ and Abstract stages', function () {
        expect(StageEnums::getStagesForMode(ProcurementMode::DIRECT_CONTRACTING))
            ->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect(StageEnums::getStagesForMode(ProcurementMode::REPEAT_ORDER))
            ->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect(StageEnums::getStagesForMode(ProcurementMode::DIRECT_SALES))
            ->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect(StageEnums::getStagesForMode(ProcurementMode::DIRECT_PROCUREMENT_FOR_STI))
            ->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect(StageEnums::getStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT))
            ->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
        expect(StageEnums::getStagesForMode(ProcurementMode::DIRECT_CONTRACTING))
            ->not->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });
});

describe('Stage Display Names and Storage Paths', function () {
    it('returns the expected RFQ and Abstract metadata', function () {
        expect(StageEnums::REQUEST_FOR_QUOTATION->getDisplayName())->toBe('Request for Quotation');
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getDisplayName())->toBe('Abstract of Quotations');
        expect(StageEnums::REQUEST_FOR_QUOTATION->getStoragePathSegment())->toBe('RequestForQuotation');
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getStoragePathSegment())->toBe('AbstractOfQuotations');
        expect(StageEnums::REQUEST_FOR_QUOTATION->getDescription())->toContain('RFQ');
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getDescription())->toContain('quotations');
    });
});

describe('Authorization for New Stages', function () {
    it('denies RFQ and Abstract access to non-bac-secretariat users', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $routes = [
            stagePageRoute(StageEnums::REQUEST_FOR_QUOTATION),
            stagePageRoute(StageEnums::ABSTRACT_OF_QUOTATIONS),
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertForbidden();
        }
    });

    it('denies RFQ and Abstract access to unauthenticated users', function () {
        $routes = [
            stagePageRoute(StageEnums::REQUEST_FOR_QUOTATION),
            stagePageRoute(StageEnums::ABSTRACT_OF_QUOTATIONS),
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }
    });
});

function buildSvpProcurementData(User $user): ProcurementData
{
    return new ProcurementData(
        prNumber: 'PR-2024-001-0001',
        appReference: 'APP-2024-001',
        title: 'Test Procurement',
        description: 'Test Description',
        abcAmount: 100000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategory::GOODS,
        procurementMode: ProcurementMode::SMALL_VALUE_PROCUREMENT,
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

function bindSvpStageSupportStubs(ProcurementData $procurementData): void
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
            ['user_address' => User::findOrFail((int) $procurementData->userId)->blockchain_address],
        ]));
    app()->instance(ProcurementDataService::class, $dataService);

    $support = mock(ProcurementSupportService::class);
    $support->shouldReceive('validateStageInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $support->shouldReceive('stageExistsInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturn(true);
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
                'total_stages' => 2,
                'current_index' => 0,
                'progress_percentage' => 0,
            ],
        ]);
    $support->shouldReceive('getUploadedDocumentTypes')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    $support->shouldReceive('getOngoingStatusForStage')
        ->zeroOrMoreTimes()
        ->andReturn(ProcurementStatus::PROCUREMENT_SUBMITTED);
    app()->instance(ProcurementSupportService::class, $support);

    $sync = mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    app()->instance(NormalizedTableSyncService::class, $sync);
}

function bindSvpModeAwareValidationStub(?array $guideMap = null): void
{
    $validation = mock(ModeAwareDocumentValidationService::class);
    $validation->shouldReceive('getStageDocumentGuide')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (StageEnums $stage) use ($guideMap) {
            if ($guideMap !== null && isset($guideMap[$stage->value])) {
                return $guideMap[$stage->value];
            }

            return [
                'stage' => $stage->value,
                'stage_display_name' => $stage->getDisplayName(),
                'phase' => $stage->isPreProcurement() ? 'pre_procurement' : 'procurement',
                'description' => 'Stage guide',
                'required_documents' => [],
                'optional_documents' => [],
                'counts' => [
                    'required_count' => 0,
                    'optional_count' => 0,
                    'total_count' => 0,
                ],
            ];
        });
    app()->instance(ModeAwareDocumentValidationService::class, $validation);
}

function stagePageRoute(StageEnums $stage): string
{
    if ($stage->isPreProcurement()) {
        return route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => $stage->value,
        ]);
    }

    return route('bac-secretariat.procurement.bidding.show', [
        'pr_number' => 'PR-2024-001-0001',
        'stage' => $stage->value,
    ]);
}

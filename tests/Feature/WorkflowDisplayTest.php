<?php

use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Models\ProcurementWorkflowConfig;
use App\Models\User;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\NormalizedTableSyncService;
use App\Services\Procurement\ProcurementSupportService;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create([
        'blockchain_address' => 'test_address_123',
    ]);
    $this->bacSecretariat->assignRole('bac_secretariat');

    $sync = mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    app()->instance(NormalizedTableSyncService::class, $sync);

    $this->competitiveProcurementData = buildWorkflowProcurementData(
        $this->bacSecretariat,
        ProcurementMode::COMPETITIVE_BIDDING,
        'Test Procurement',
        1000000.00,
    );

    $this->svpProcurementData = buildWorkflowProcurementData(
        $this->bacSecretariat,
        ProcurementMode::SMALL_VALUE_PROCUREMENT,
        'Test SVP Procurement',
        100000.00,
        'PR-2024-001-0002',
    );
});

describe('Public Workflow Page', function () {
    it('uses default workflow definitions when database configs have not been materialized', function () {
        expect(ProcurementWorkflowConfig::query()->count())->toBe(0);

        $response = $this->get(route('workflow'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('workflow')
            ->has('workflows', count(ProcurementMode::cases()))
            ->where('workflows.0.mode', ProcurementMode::COMPETITIVE_BIDDING->value)
            ->where('workflows.0.name', ProcurementMode::COMPETITIVE_BIDDING->getDisplayName())
            ->has('workflows.0.stages', count(StageEnums::getStagesForMode(ProcurementMode::COMPETITIVE_BIDDING)))
            ->where('workflows.0.stages.0.id', StageEnums::PROCUREMENT_INITIATION->value)
        );
    });
});

describe('Workflow Info Structure', function () {
    it('includes the expected workflow info structure for SVP stages', function () {
        actingAs($this->bacSecretariat);
        bindWorkflowSupportStub($this->svpProcurementData);

        $response = $this->get(workflowStagePageRoute(StageEnums::REQUEST_FOR_QUOTATION));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('workflowInfo', fn ($workflow) => $workflow
                ->has('mode')
                ->has('workflow', fn ($wf) => $wf
                    ->has('stages')
                    ->has('total_stages')
                    ->has('current_index')
                    ->has('progress_percentage')
                )
            )
            ->where('workflowInfo.mode', fn ($mode) => $mode === null || (
                isset($mode['value']) &&
                isset($mode['display_name']) &&
                isset($mode['description']) &&
                isset($mode['irr_section'])
            ))
            ->has('workflowInfo.workflow.stages')
        );
    });
});

describe('Stage Pages With Workflow Info', function () {
    it('includes workflow info on representative competitive bidding stage pages', function () {
        actingAs($this->bacSecretariat);
        bindWorkflowSupportStub($this->competitiveProcurementData);
        bindWorkflowDocumentGuideStub();

        $stages = [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BID_OPENING,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::COMPLETION,
        ];

        foreach ($stages as $stage) {
            assertStageUploadPageIncludesWorkflowInfo($this, $stage);
        }
    });

    it('includes workflow info on SVP-only stage pages', function () {
        actingAs($this->bacSecretariat);
        bindWorkflowSupportStub($this->svpProcurementData);
        bindWorkflowDocumentGuideStub();

        foreach ([StageEnums::REQUEST_FOR_QUOTATION, StageEnums::ABSTRACT_OF_QUOTATIONS] as $stage) {
            assertStageUploadPageIncludesWorkflowInfo($this, $stage);
        }
    });
});

describe('Procurement Initiation With Workflow Info', function () {
    it('returns 404 when the procurement does not exist', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.initiation.show', [
            'pr_number' => 'PR-2025-997-0001',
        ]));

        $response->assertNotFound();
    });

    it('includes workflow info on the procurement initiation page', function () {
        actingAs($this->bacSecretariat);
        bindWorkflowSupportStub($this->competitiveProcurementData);
        bindWorkflowDocumentGuideStub();

        $response = $this->get(route('bac-secretariat.procurement.initiation.show', [
            'pr_number' => 'PR-2024-001-0001',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('workflowInfo')
        );
    });
});

describe('Mode-Specific Workflow Validation', function () {
    it('returns workflow info even when the procurement mode is not yet available', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(workflowStagePageRoute(StageEnums::REQUEST_FOR_QUOTATION, 'PR-2025-500-0001'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo')
        );
    });

    it('returns the expected stage mappings for supported procurement modes', function () {
        $svpStages = StageEnums::getStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);
        $svpOptionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);
        $competitiveOptionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);

        expect($svpStages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($svpStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
        expect($svpOptionalStages)->toBeArray();
        expect($competitiveOptionalStages)->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
    });
});

describe('Workflow Progress Calculation', function () {
    it('calculates progress boundaries and stage counts correctly', function () {
        $competitiveStages = StageEnums::getStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);
        $totalStages = count($competitiveStages);

        expect($totalStages)->toBeGreaterThan(0);
        expect((int) round((0 / ($totalStages - 1)) * 100))->toBe(0);
        expect((int) round((($totalStages - 1) / ($totalStages - 1)) * 100))->toBe(100);

        foreach (ProcurementMode::cases() as $mode) {
            $stages = StageEnums::getStagesForMode($mode);

            expect($stages)->toBeArray();
            expect(count($stages))->toBeGreaterThan(0, "Mode {$mode->value} should have at least one stage");
        }
    });
});

describe('Access Control For Stage Pages', function () {
    it('denies access to unauthenticated users', function () {
        $response = $this->get(workflowStagePageRoute(StageEnums::REQUEST_FOR_QUOTATION));

        $response->assertRedirect(route('login'));
    });

    it('denies access to users without the bac_secretariat role', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(workflowStagePageRoute(StageEnums::REQUEST_FOR_QUOTATION));

        $response->assertForbidden();
    });
});

function buildWorkflowProcurementData(
    User $user,
    ProcurementMode $mode,
    string $title,
    float $abcAmount,
    string $prNumber = 'PR-2024-001-0001',
): array {
    Procurement::create([
        'pr_number' => $prNumber,
        'app_reference' => 'APP-2024-001',
        'title' => $title,
        'description' => 'Test Description',
        'abc_amount' => $abcAmount,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => $mode->value,
        'office' => 'Test Office',
        'end_user' => 'Test User',
        'prepared_by' => 'Test Preparer',
        'status' => 'in_progress',
        'user_id' => $user->id,
        'created_at' => now(),
    ]);

    return [
        'pr_number' => $prNumber,
        'app_reference' => 'APP-2024-001',
        'title' => $title,
        'description' => 'Test Description',
        'abc_amount' => $abcAmount,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => $mode->value,
        'office' => 'Test Office',
        'end_user' => 'Test User',
        'prepared_by' => 'Test Preparer',
        'status' => 'in_progress',
        'user_id' => (string) $user->id,
        'created_at' => now(),
    ];
}

function bindWorkflowSupportStub(array $procurementData): void
{
    $support = mock(ProcurementSupportService::class);
    $support->shouldReceive('stageExistsInWorkflow')
        ->zeroOrMoreTimes()
        ->andReturn(true);
    $support->shouldReceive('findProcurementById')
        ->zeroOrMoreTimes()
        ->andReturn([
            'procurement_title' => $procurementData['title'],
            'current_status' => $procurementData['status'],
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
        ]);
    $support->shouldReceive('handleAutoStageTransition')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $support->shouldReceive('getProcurementMode')
        ->zeroOrMoreTimes()
        ->andReturn(ProcurementMode::tryFrom($procurementData['procurement_mode']));
    $support->shouldReceive('getWorkflowInfo')
        ->zeroOrMoreTimes()
        ->andReturn([
            'mode' => [
                'value' => $procurementData['procurement_mode'],
                'display_name' => 'Test Mode',
                'description' => 'Test description',
                'irr_section' => 'Section 10',
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

    app()->instance(ProcurementSupportService::class, $support);

    $sync = mock(NormalizedTableSyncService::class);
    $sync->shouldReceive('syncPr')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    app()->instance(NormalizedTableSyncService::class, $sync);
}

function bindWorkflowDocumentGuideStub(): void
{
    $validation = mock(ModeAwareDocumentValidationService::class);
    $validation->shouldReceive('getStageDocumentGuide')
        ->zeroOrMoreTimes()
        ->andReturn([
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'stage_display_name' => StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
            'phase' => 'pre_procurement',
            'description' => 'Workflow guide fixture',
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

function workflowStagePageRoute(StageEnums $stage, string $prNumber = 'PR-2024-001-0001'): string
{
    if ($stage->isPreProcurement()) {
        return route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
        ]);
    }

    if ($stage->isPostProcurement()) {
        return route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => $prNumber,
            'stage' => $stage->value,
        ]);
    }

    return route('bac-secretariat.procurement.bidding.show', [
        'pr_number' => $prNumber,
        'stage' => $stage->value,
    ]);
}

function assertStageUploadPageIncludesWorkflowInfo(TestCase $testCase, StageEnums $stage): void
{
    $response = $testCase->get(workflowStagePageRoute($stage));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('bac-secretariat/stage-upload')
        ->has('workflowInfo')
        ->has('workflowInfo.workflow')
    );
}

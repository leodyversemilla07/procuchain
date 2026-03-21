<?php

use App\Enums\ProcurementModeEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use App\Services\WorkflowDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materializes missing workflow and document default rows without creating duplicates', function () {
    $service = app(WorkflowDefinitionService::class);
    $expectedWorkflowCount = count(ProcurementModeEnums::cases());
    $expectedDocumentCount = array_reduce(
        ProcurementModeEnums::cases(),
        fn (int $count, ProcurementModeEnums $mode): int => $count + count($service->getStagesForMode($mode)),
        0,
    );

    $this->artisan('workflow:sync-defaults')
        ->expectsOutputToContain('Created')
        ->assertExitCode(0);

    expect(ProcurementWorkflowConfig::query()->count())->toBe($expectedWorkflowCount)
        ->and(StageDocumentConfig::query()->count())->toBe($expectedDocumentCount);

    $this->artisan('workflow:sync-defaults')
        ->expectsOutput('Created 0 workflow config(s) and 0 document config(s).')
        ->assertExitCode(0);

    expect(ProcurementWorkflowConfig::query()->count())->toBe($expectedWorkflowCount)
        ->and(StageDocumentConfig::query()->count())->toBe($expectedDocumentCount);
});

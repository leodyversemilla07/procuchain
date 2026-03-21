<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use App\Services\WorkflowDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(WorkflowDefinitionService::class);
    $this->service->clearCache();
});

it('prefers active workflow and document configuration rows over defaults', function () {
    ProcurementWorkflowConfig::query()->create([
        'procurement_mode' => ProcurementModeEnums::NEGOTIATED_PROCUREMENT->value,
        'display_name' => 'Negotiated Procurement',
        'stages' => [
            StageEnums::PROCUREMENT_INITIATION->value,
            StageEnums::BAC_RESOLUTION->value,
            StageEnums::NOTICE_OF_AWARD->value,
        ],
        'optional_stages' => [StageEnums::BAC_RESOLUTION->value],
        'is_active' => true,
    ]);

    StageDocumentConfig::query()->create([
        'stage' => StageEnums::BAC_RESOLUTION->value,
        'procurement_mode' => ProcurementModeEnums::NEGOTIATED_PROCUREMENT->value,
        'stage_display_name' => StageEnums::BAC_RESOLUTION->getDisplayName(),
        'required_documents' => [DocumentTypeEnums::BAC_RESOLUTION->value],
        'optional_documents' => [DocumentTypeEnums::LOWEST_QUOTATION_CERTIFICATION->value],
        'is_active' => true,
    ]);

    $stages = $this->service->getStagesForMode(ProcurementModeEnums::NEGOTIATED_PROCUREMENT);
    $requiredDocuments = $this->service->getRequiredDocuments(StageEnums::BAC_RESOLUTION, ProcurementModeEnums::NEGOTIATED_PROCUREMENT);
    $optionalDocuments = $this->service->getOptionalDocuments(StageEnums::BAC_RESOLUTION, ProcurementModeEnums::NEGOTIATED_PROCUREMENT);

    expect($stages)->toBe([
        StageEnums::PROCUREMENT_INITIATION,
        StageEnums::BAC_RESOLUTION,
        StageEnums::NOTICE_OF_AWARD,
    ]);

    expect($this->service->isStageOptional(StageEnums::BAC_RESOLUTION, ProcurementModeEnums::NEGOTIATED_PROCUREMENT))->toBeTrue()
        ->and($requiredDocuments)->toBe([DocumentTypeEnums::BAC_RESOLUTION])
        ->and($optionalDocuments)->toBe([DocumentTypeEnums::LOWEST_QUOTATION_CERTIFICATION]);
});

it('falls back to mode-aware defaults when no active configuration exists', function () {
    $defaultStages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);
    $defaultRequiredDocuments = $this->service->getDefaultRequiredDocuments(
        StageEnums::REQUEST_FOR_QUOTATION,
        ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
    );

    expect($this->service->getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT))->toBe($defaultStages)
        ->and($this->service->getRequiredDocuments(
            StageEnums::REQUEST_FOR_QUOTATION,
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
        ))->toBe($defaultRequiredDocuments);
});

it('builds a stage document guide with counts and metadata from the resolved definition', function () {
    $guide = $this->service->getStageDocumentGuide(
        StageEnums::REQUEST_FOR_QUOTATION,
        ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
    );

    expect($guide['stage'])->toBe(StageEnums::REQUEST_FOR_QUOTATION->value)
        ->and($guide['stage_display_name'])->toBe(StageEnums::REQUEST_FOR_QUOTATION->getDisplayName())
        ->and($guide['mode'])->toBe(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT->value)
        ->and($guide['phase'])->toBe(StageEnums::REQUEST_FOR_QUOTATION->getPhase())
        ->and($guide['counts']['required_count'])->toBe(count($guide['required_documents']))
        ->and($guide['counts']['optional_count'])->toBe(count($guide['optional_documents']))
        ->and($guide['counts']['total_count'])->toBe(count($guide['required_documents']) + count($guide['optional_documents']));
});

<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;
use App\Services\ModeAwareDocumentValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->blockchain_address = 'test_address_123';
    $this->bacSecretariat->save();
});

describe('ProcurementStageController (Pre-Procurement Phase)', function () {
    it('shows pre-procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('shows any stage via pre-procurement route (unified controller)', function () {
        actingAs($this->bacSecretariat);

        // The unified controller validates by workflow, not by phase
        // So it should successfully render even for procurement phase stages
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
    });

    it('uploads documents successfully for pre-procurement stage', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        // Mock the repository
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                appReference: 'APP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::COMPETITIVE_BIDDING,
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
                userId: (string) $this->bacSecretariat->id,
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('minutes.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
        ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'document_type']);
        Queue::assertPushed(\App\Jobs\BlockchainWriteJob::class);
    });

    it('provides document guide for stage', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('getStageDocumentGuide')
            ->andReturn([
                'required' => [DocumentTypeEnums::PRE_PROCUREMENT_MINUTES],
                'optional' => [DocumentTypeEnums::PRE_PROCUREMENT_AGENDA],
                'uploaded' => [],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.document-guide', [
            'pr_number' => 'PR-2024-001',
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

    it('handles pre-procurement conference decision', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.publish-pre-procurement-conference-decision'), [
            'pr_number' => 'PR-2024-001',
            'procurement_title' => 'Test Procurement Project',
            'conference_held' => true,
        ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status']);
        Queue::assertPushed(\App\Jobs\BlockchainWriteJob::class);
    });
});

describe('ProcurementStageController (Procurement Phase)', function () {
    it('shows procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('shows any stage via procurement route (unified controller)', function () {
        actingAs($this->bacSecretariat);

        // The unified controller validates by workflow, not by phase
        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
    });

    it('uploads documents successfully for procurement stage', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                appReference: 'APP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::COMPETITIVE_BIDDING,
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
                userId: (string) $this->bacSecretariat->id,
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        // Mock the ModeAwareDocumentValidationService which is used by the unified controller
        $modeAwareValidationService = mock(ModeAwareDocumentValidationService::class);
        $modeAwareValidationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $this->instance(ModeAwareDocumentValidationService::class, $modeAwareValidationService);

        $file = UploadedFile::fake()->create('evaluation_report.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.bidding.upload-document', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::BID_EVALUATION_REPORT->value,
        ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'document_type']);
        Queue::assertPushed(\App\Jobs\BlockchainWriteJob::class);
    });

    it('checks stage completion status', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn([
                'can_complete' => true,
                'completion_percentage' => 100,
                'missing_documents' => [],
            ]);

        $response = $this->get(route('bac-secretariat.procurement.bidding.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertJson([
            'can_complete' => true,
            'completion_percentage' => 100,
            'missing_documents' => [],
        ]);
    });
});

describe('ProcurementStageController (Post-Procurement Phase)', function () {
    it('shows post-procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('shows any stage via post-procurement route (unified controller)', function () {
        actingAs($this->bacSecretariat);

        // The unified controller validates by workflow, not by phase
        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
    });

    it('uploads documents successfully for post-procurement stage', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                appReference: 'APP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::COMPETITIVE_BIDDING,
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
                userId: (string) $this->bacSecretariat->id,
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('notice_of_award.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->startSession()->post(route('bac-secretariat.procurement.post-procurement.upload-document', [
                'pr_number' => 'PR-2024-001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
            ]), [
                'document_file' => $file,
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
            ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'document_type']);
        Queue::assertPushed(\App\Jobs\BlockchainWriteJob::class);
    });

    it('validates document upload in real-time', function () {
        actingAs($this->bacSecretariat);

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->startSession()->post(route('bac-secretariat.procurement.post-procurement.validate-upload', [
                'pr_number' => 'PR-2024-001',
                'stage' => StageEnums::NOTICE_OF_AWARD->value,
            ]), [
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value,
                'file' => $file,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'valid' => true,
            'errors' => [],
        ]);
    });

    it('uploads completion documents successfully', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                appReference: 'APP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::COMPETITIVE_BIDDING,
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
                userId: (string) $this->bacSecretariat->id,
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $procurementDataService->shouldReceive('fetchStatusItems')->andReturn(collect());
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => true]);
        $this->instance(DocumentValidationService::class, $validationService);

        // Mock the ModeAwareDocumentValidationService
        $modeAwareValidationService = mock(ModeAwareDocumentValidationService::class);
        $modeAwareValidationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $this->instance(ModeAwareDocumentValidationService::class, $modeAwareValidationService);

        $file = UploadedFile::fake()->create('completion_certificate.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->startSession()->post(route('bac-secretariat.procurement.post-procurement.upload-document', [
                'pr_number' => 'PR-2024-001',
                'stage' => StageEnums::COMPLETION->value,
            ]), [
                'document_file' => $file,
                'document_type' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION->value,
            ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'document_type']);
        Queue::assertPushed(\App\Jobs\BlockchainWriteJob::class);
    });
});

describe('Authorization', function () {
    it('denies access to non-bac-secretariat users', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertForbidden();
    });

    it('denies access to unauthenticated users', function () {
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertRedirect(route('login'));
    });
});

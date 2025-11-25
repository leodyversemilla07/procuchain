<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->blockchain_address = 'test_address_123';
    $this->bacSecretariat->save();
});

describe('PreProcurementController', function () {
    it('shows pre-procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/pre-procurement-conference-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('rejects non-pre-procurement stages', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value, // Procurement phase stage
        ]));

        $response->assertForbidden();
    });

    it('uploads documents successfully for pre-procurement stage', function () {
        actingAs($this->bacSecretariat);

        // Mock the repository
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        // Mock the orchestrator
        $orchestrator = mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')->once()->andReturn([
            'success' => true,
            'document_txid' => 'doc123',
            'status_txid' => 'status123',
            'event_txid' => 'event123',
        ]);
        $this->instance(ProcurementOrchestrator::class, $orchestrator);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('minutes.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.pre-procurement.upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'pr_number' => 'PR-2024-001',
            'minutes_file' => $file,
            'meeting_date' => '2024-01-15',
            'participants' => 'John Doe, Jane Smith',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
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
        $response->assertJson([
            'required' => [DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value],
            'optional' => [DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->value],
            'uploaded' => [],
        ]);
    });

    it('handles pre-procurement conference decision', function () {
        actingAs($this->bacSecretariat);

        $eventPublisher = mock(EventPublisher::class);
        $eventPublisher->shouldReceive('publish')->once();
        $this->instance(EventPublisher::class, $eventPublisher);

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.publish-pre-procurement-conference-decision'), [
            'pr_number' => 'PR-2024-001',
            'procurement_title' => 'Test Procurement Project',
            'conference_held' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    });
});

describe('ProcurementController', function () {
    it('shows procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/bid-evaluation-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('rejects non-procurement stages', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value, // Pre-procurement phase stage
        ]));

        $response->assertForbidden();
    });

    it('uploads documents successfully for procurement stage', function () {
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $orchestrator = mock(ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')->once()->andReturn([
            'success' => true,
            'document_txid' => 'doc123',
            'status_txid' => 'status123',
            'event_txid' => 'event123',
        ]);
        $this->instance(ProcurementOrchestrator::class, $orchestrator);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('evaluation_report.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.bidding.upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]), [
            'pr_number' => 'PR-2024-001',
            'evaluation_report_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
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

describe('PostProcurementController', function () {
    it('shows post-procurement stage page for authorized users', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/noa-upload')
            ->has('procurement')
            ->has('documentGuide')
        );
    });

    it('rejects non-post-procurement stages', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value, // Procurement phase stage
        ]));

        $response->assertForbidden();
    });

    it('uploads documents successfully for post-procurement stage', function () {
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        // Mock publishers first
        $documentPublisher = mock(\App\Services\Publishers\DocumentPublisher::class);
        $statusPublisher = mock(\App\Services\Publishers\StatusPublisher::class);
        $eventPublisher = mock(\App\Services\Publishers\EventPublisher::class);

        $orchestrator = mock(ProcurementOrchestrator::class);
        $orchestrator->documentPublisher = $documentPublisher;
        $orchestrator->statusPublisher = $statusPublisher;
        $orchestrator->eventPublisher = $eventPublisher;
        $orchestrator->shouldReceive('publishDocumentWorkflow')->once()->andReturn([
            'success' => true,
            'document_txid' => 'doc123',
            'status_txid' => 'status123',
            'event_txid' => 'event123',
        ]);
        $this->instance(ProcurementOrchestrator::class, $orchestrator);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('notice_of_award.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.post-procurement.upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
        ]), [
            'pr_number' => 'PR-2024-001',
            'notice_of_award_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    });

    it('validates document upload in real-time', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn([
                'errors' => [],
                'warnings' => ['This document type is optional'],
            ]);

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.post-procurement.validate-upload', [
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
            'warnings' => ['This document type is optional'],
        ]);
    });

    it('marks procurement as completed when acceptance turnover is done', function () {
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        // Mock publishers first
        $documentPublisher = mock(\App\Services\Publishers\DocumentPublisher::class);
        $statusPublisher = mock(\App\Services\Publishers\StatusPublisher::class);
        $statusPublisher->shouldReceive('publish')->once()->andReturn([
            'success' => true,
            'txid' => 'status_publish_123',
        ]);
        $statusPublisher->shouldReceive('publishTransition')->once()->andReturn([
            'success' => true,
            'txid' => 'status_transition_123',
        ]);
        $this->instance(\App\Services\Publishers\StatusPublisher::class, $statusPublisher);

        $eventPublisher = mock(\App\Services\Publishers\EventPublisher::class);
        $eventPublisher->shouldReceive('publishStageTransition')->once()->andReturn([
            'success' => true,
            'txid' => 'event123',
        ]);
        $this->instance(\App\Services\Publishers\EventPublisher::class, $eventPublisher);

        $orchestrator = mock(ProcurementOrchestrator::class);
        $orchestrator->documentPublisher = $documentPublisher;
        $orchestrator->statusPublisher = $statusPublisher;
        $orchestrator->eventPublisher = $eventPublisher;
        $orchestrator->shouldReceive('publishDocumentWorkflow')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'success' => true,
                'document_txid' => 'doc123',
                'status_txid' => 'status123',
                'event_txid' => 'event123',
            ]);
        $this->instance(ProcurementOrchestrator::class, $orchestrator);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $procurementDataService->shouldReceive('fetchStatusItems')->andReturn(collect());
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => true]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('completion_certificate.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')->startSession()->post(route('bac-secretariat.procurement.post-procurement.upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::COMPLETION->value,
        ]), [
            'pr_number' => 'PR-2024-001',
            'completion_certificate_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
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

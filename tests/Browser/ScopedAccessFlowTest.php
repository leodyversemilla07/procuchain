<?php

declare(strict_types=1);

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\ProcurementData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementRepository;
use App\Services\PdfViewerService;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\ProcurementDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\SeedsPermissions;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Scoped Access Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'secretariat-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('allows bac secretariat to open an accessible procurement detail page', function () {
        browserBindProcurementDetailMocks(
            prNumber: 'PR-2025-993-0001',
            user: $this->bacSecretariat,
            accessible: true,
        );

        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-2025-993-0001']));

        $page->assertSee('Accessible Procurement')
            ->assertSee('Workflow Progress')
            ->assertSee('Procurement ID: PR-2025-993-0001')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('blocks bac secretariat from opening a procurement they do not own or touch', function () {
        browserBindProcurementDetailMocks(
            prNumber: 'PR-2025-993-0002',
            user: $this->bacSecretariat,
            accessible: false,
        );

        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.show', ['pr_number' => 'PR-2025-993-0002']));

        $page->assertSee('403: Forbidden')
            ->assertSee('Sorry, you are forbidden from accessing this page.')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('allows bac secretariat to open an accessible PDF viewer page', function () {
        browserBindPdfViewerMocks(
            fileKey: 'open-document.pdf',
            prNumber: 'PR-2025-993-0001',
            user: $this->bacSecretariat,
            accessible: true,
        );

        $this->actingAs($this->bacSecretariat);

        $page = visit(route('pdf.viewer', ['fileKey' => 'open-document.pdf']));

        $page->assertSee('Request for Quotation Document (PDF)')
            ->assertSee('Accessible Procurement')
            ->assertSee('Download');
    });

    it('blocks bac secretariat from opening a locked PDF viewer page', function () {
        browserBindPdfViewerMocks(
            fileKey: 'locked-document.pdf',
            prNumber: 'PR-2025-993-0002',
            user: $this->bacSecretariat,
            accessible: false,
        );

        $this->actingAs($this->bacSecretariat);

        $page = visit(route('pdf.viewer', ['fileKey' => 'locked-document.pdf']));

        $page->assertSee('403: Forbidden')
            ->assertSee('Sorry, you are forbidden from accessing this page.')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });
});

function browserBindProcurementDetailMocks(string $prNumber, User $user, bool $accessible): void
{
    $title = $accessible ? 'Accessible Procurement' : 'Locked Procurement';

    $procurementRepository = mock(ProcurementRepository::class);
    $procurementRepository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn(browserProcurementFixture(
            prNumber: $prNumber,
            userId: $accessible ? (string) $user->id : '999',
            title: $title,
        ));

    $dataService = mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn(collect([
            [
                'user_address' => $accessible ? $user->blockchain_address : 'different-address',
                'stage' => 'procurement_initiation',
                'current_status' => 'draft',
            ],
        ]));
    $dataService->shouldReceive('fetchAndProcessAllDocuments')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn([]);
    $dataService->shouldReceive('fetchAndProcessEvents')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn([]);
    $dataService->shouldReceive('preloadUserNames')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $dataService->shouldReceive('buildProcurementData')
        ->zeroOrMoreTimes()
        ->with($prNumber, Mockery::any(), [], [], Mockery::any())
        ->andReturn(browserProcurementViewData(
            prNumber: $prNumber,
            title: $title,
        ));

    $correctionRepository = mock(ProcurementCorrectionRepositoryInterface::class);
    $correctionRepository->shouldReceive('hasCorrections')
        ->zeroOrMoreTimes()
        ->andReturn(false);
    $correctionRepository->shouldReceive('getLatest')
        ->zeroOrMoreTimes()
        ->andReturnNull();
    $correctionRepository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn([]);

    $detailService = new ProcurementDetailService(
        $dataService,
        $procurementRepository,
        $correctionRepository,
    );

    app()->instance(ProcurementRepository::class, $procurementRepository);
    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(ProcurementDetailService::class, $detailService);
    app()->instance(ProcurementCorrectionRepositoryInterface::class, $correctionRepository);
}

function browserBindPdfViewerMocks(string $fileKey, string $prNumber, User $user, bool $accessible): void
{
    $procurementTitle = $accessible ? 'Accessible Procurement' : 'Locked Procurement';
    $userAddress = $accessible ? $user->blockchain_address : 'different-address';

    $documentRepository = mock(DocumentRepository::class);
    $documentRepository->shouldReceive('findByfileKey')
        ->zeroOrMoreTimes()
        ->with($fileKey)
        ->andReturn(browserDocumentFixture(
            fileKey: $fileKey,
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            userAddress: $userAddress,
        ));
    $documentRepository->shouldReceive('findByTxid')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $procurementRepository = mock(ProcurementRepository::class);
    $procurementRepository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn(browserProcurementFixture(
            prNumber: $prNumber,
            userId: $accessible ? (string) $user->id : '999',
            title: $procurementTitle,
        ));

    $dataService = mock(ProcurementDataService::class);
    $dataService->shouldReceive('fetchStatusItems')
        ->zeroOrMoreTimes()
        ->with($prNumber)
        ->andReturn(collect([
            [
                'user_address' => $userAddress,
                'stage' => 'request_for_quotation',
                'current_status' => 'draft',
            ],
        ]));
    $dataService->shouldReceive('validateDocumentExistsInBlockchain')
        ->zeroOrMoreTimes()
        ->with($fileKey)
        ->andReturn([
            'pr_number' => $prNumber,
            'procurement_title' => $procurementTitle,
            'document_type' => 'request_for_quotation',
            'stage' => 'request_for_quotation',
            'file_key' => $fileKey,
            'file_name' => $fileKey,
            'data_txid' => 'browser-data-txid',
        ]);

    $pdfViewerService = mock(PdfViewerService::class);
    $pdfViewerService->shouldReceive('prepareDocumentData')
        ->zeroOrMoreTimes()
        ->with($fileKey, Mockery::type(Request::class))
        ->andReturn([
            'id' => 'browser-document',
            'pr_number' => $prNumber,
            'procurement_title' => $procurementTitle,
            'document_type' => 'request_for_quotation',
            'document_type_display' => 'Request for Quotation Document (PDF)',
            'stage' => 'request_for_quotation',
            'stage_display' => 'Request for Quotation',
            'current_status' => 'draft',
            'phase_display_name' => 'Pre-Procurement',
            'hash' => 'browser-hash',
            'blockchain_txid' => 'browser-data-txid',
        ]);
    $pdfViewerService->shouldReceive('getBlockchainFileViewStats')
        ->zeroOrMoreTimes()
        ->with($fileKey)
        ->andReturn([
            'total_views' => 1,
            'unique_viewers' => 1,
            'today_views' => 1,
            'week_views' => 1,
            'month_views' => 1,
            'views_by_role' => collect(['bac_secretariat' => 1]),
            'views_by_day' => collect([today()->toDateString() => 1]),
            'first_viewed' => now()->format('M j, Y g:i A'),
            'last_viewed' => now()->format('M j, Y g:i A'),
        ]);

    app()->instance(DocumentRepository::class, $documentRepository);
    app()->instance(ProcurementRepository::class, $procurementRepository);
    app()->instance(ProcurementDataService::class, $dataService);
    app()->instance(PdfViewerService::class, $pdfViewerService);
}

function browserProcurementFixture(string $prNumber, string $userId, string $title): ProcurementData
{
    return ProcurementData::fromArray([
        'pr_number' => $prNumber,
        'title' => $title,
        'description' => 'Browser fixture',
        'abc_amount' => 1000,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'BAC Office',
        'status' => 'draft',
        'user_id' => $userId,
        'created_at' => now()->toIso8601String(),
    ]);
}

function browserProcurementViewData(string $prNumber, string $title): array
{
    return [
        'id' => $prNumber,
        'title' => $title,
        'status' => [
            'stage' => 'procurement_initiation',
            'stage_formatted' => 'Procurement Initiation',
            'current_status' => 'draft',
            'status_formatted' => 'Draft',
            'timestamp' => now()->toIso8601String(),
            'formatted_date' => now()->format('M j, Y g:i A'),
            'formatted_date_only' => now()->format('M j, Y'),
            'progress' => 10,
            'total_stages' => 12,
            'phase' => 'pre-procurement',
            'phase_display_name' => 'Pre-Procurement',
        ],
        'documents' => [],
        'events' => [],
        'timeline' => [],
        'is_archived' => false,
        'has_corrections' => false,
        'details' => [
            'pr_number' => $prNumber,
            'title' => $title,
            'description' => 'Browser fixture description',
            'abc_amount' => 1000,
            'abc_amount_formatted' => 'PHP 1,000.00',
            'funding_source' => 'General Fund',
            'category' => 'goods',
            'category_label' => 'Goods',
            'procurement_mode' => 'competitive_bidding',
            'procurement_mode_label' => 'Competitive Bidding',
            'office' => 'BAC Office',
            'created_at' => now()->toIso8601String(),
            'created_at_formatted' => now()->format('M j, Y g:i A'),
        ],
    ];
}

function browserDocumentFixture(
    string $fileKey,
    string $prNumber,
    string $procurementTitle,
    string $userAddress,
): DocumentData {
    return new DocumentData(
        prNumber: $prNumber,
        procurementTitle: $procurementTitle,
        userAddress: $userAddress,
        stage: 'request_for_quotation',
        status: 'draft',
        documentType: 'request_for_quotation',
        fileKey: $fileKey,
        filename: $fileKey,
        fileSize: 1024,
        mimeType: 'application/pdf',
        hash: 'browser-hash',
        dataTxid: 'browser-data-txid',
        metadataTxid: 'browser-metadata-txid',
        uploadedBy: 'Browser Tester',
        timestamp: Carbon::now(),
    );
}

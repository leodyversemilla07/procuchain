<?php

use App\Models\DocumentView;
use App\Repositories\DocumentRepository;
use App\Services\PdfViewerService;
use App\Services\ProcurementDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Log::spy();

    $this->procurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->documentRepository = Mockery::mock(DocumentRepository::class);

    $this->service = new PdfViewerService(
        $this->procurementDataService,
        $this->documentRepository,
    );
});

describe('PdfViewerService', function () {
    describe('prepareDocumentData', function () {
        it('returns correct structure with all fields when document exists', function () {
            $fileKey = 'PR-2025-001/pre-procurement/stage-01/purchase_request.pdf';
            $request = Request::create('/pdf-viewer/'.$fileKey);

            $this->procurementDataService
                ->shouldReceive('getDocumentDataByFileKey')
                ->with($fileKey)
                ->once()
                ->andReturn([
                    'pr_number' => 'PR-2025-001',
                    'procurement_title' => 'Test Procurement',
                    'document_type' => 'purchase_request',
                    'stage' => 'procurement_initiation',
                    'file_size' => 1024,
                    'timestamp' => now()->toIso8601String(),
                    'hash' => 'abc123hash',
                    'data_txid' => 'txid_123',
                ]);

            $this->procurementDataService
                ->shouldReceive('validateDocumentExistsInBlockchain')
                ->with($fileKey)
                ->once()
                ->andReturn(['exists' => true]);

            $this->procurementDataService
                ->shouldReceive('getCurrentProcurementStatus')
                ->with('PR-2025-001')
                ->once()
                ->andReturn([
                    'current_status' => 'procurement_submitted',
                    'timestamp' => now()->toIso8601String(),
                    'phase' => 'pre_procurement',
                    'phase_display_name' => 'Pre-Procurement',
                ]);

            $this->actingAs(createUserWithRole('bac_secretariat'));

            $result = $this->service->prepareDocumentData($fileKey, $request);

            expect($result)
                ->toHaveKeys(['pr_number', 'procurement_title', 'document_type', 'stage', 'file_size', 'hash', 'blockchain_txid'])
                ->and($result['pr_number'])->toBe('PR-2025-001')
                ->and($result['blockchain_txid'])->toBe('txid_123')
                ->and($result['stage_display'])->toBeString()
                ->and($result['document_type_display'])->toBeString()
                ->and($result['current_status'])->toBe('procurement_submitted');
        });

        it('handles document found without status data', function () {
            $fileKey = 'PR-2025-001/pre-procurement/stage-01/doc.pdf';
            $request = Request::create('/pdf-viewer/'.$fileKey);

            $this->procurementDataService
                ->shouldReceive('getDocumentDataByFileKey')
                ->once()
                ->andReturn([
                    'pr_number' => 'PR-2025-001',
                    'procurement_title' => 'Test',
                    'document_type' => 'purchase_request',
                    'stage' => 'procurement_initiation',
                    'file_size' => 512,
                    'timestamp' => now()->toIso8601String(),
                    'hash' => 'hash123',
                ]);

            $this->procurementDataService
                ->shouldReceive('getCurrentProcurementStatus')
                ->once()
                ->andReturn(null);

            $this->actingAs(createUserWithRole('bac_secretariat'));

            $result = $this->service->prepareDocumentData($fileKey, $request);

            expect($result)
                ->toHaveKeys(['pr_number', 'document_type', 'stage', 'hash'])
                ->and($result['pr_number'])->toBe('PR-2025-001')
                ->and($result)->not->toHaveKey('blockchain_txid');
        });
    });

    describe('getFileViewStats', function () {
        it('returns correct statistics structure', function () {
            $fileKey = 'PR-2025-001/pre-procurement/stage-01/test.pdf';

            $user = createUserWithRole('bac_secretariat');

            DocumentView::create([
                'user_id' => $user->id,
                'file_key' => $fileKey,
                'pr_number' => 'PR-2025-001',
                'procurement_title' => 'Test',
                'document_type' => 'purchase_request',
                'stage' => 'procurement_initiation',
                'viewed_at' => now(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
            ]);

            $result = $this->service->getFileViewStats($fileKey);

            expect($result)
                ->toHaveKeys([
                    'total_views',
                    'unique_viewers',
                    'today_views',
                    'week_views',
                    'month_views',
                    'views_by_role',
                    'views_by_day',
                    'first_viewed',
                    'last_viewed',
                ])
                ->and($result['total_views'])->toBeGreaterThanOrEqual(1)
                ->and($result['unique_viewers'])->toBeGreaterThanOrEqual(1);
        });

        it('returns zero counts when no views exist', function () {
            $result = $this->service->getFileViewStats('nonexistent/file/key');

            expect($result['total_views'])->toBe(0)
                ->and($result['unique_viewers'])->toBe(0)
                ->and($result['today_views'])->toBe(0);
        });
    });
});

<?php

use App\Jobs\DocumentValidationJob;
use App\Services\SmartContractService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->procurementId = 'PROC-001';
    $this->userAddress = '1ABC123XYZ';

    $this->smartContractService = mock(SmartContractService::class);
});

describe('DocumentValidationJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched', function () {
            Queue::fake();

            DocumentValidationJob::dispatch(
                'document_integrity',
                ['document_hash' => 'hash123'],
                $this->procurementId,
                $this->userAddress
            );

            Queue::assertPushed(DocumentValidationJob::class);
        });

        it('implements ShouldQueue interface', function () {
            $job = new DocumentValidationJob(
                'document_integrity',
                ['document_hash' => 'hash123'],
                $this->procurementId,
                $this->userAddress
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });
    });

    describe('document_integrity operation', function () {
        it('validates document integrity successfully', function () {
            $documentHash = 'abc123hash';
            $data = ['document_hash' => $documentHash];

            $this->smartContractService
                ->shouldReceive('validateDocumentIntegrity')
                ->once()
                ->with($this->procurementId, $documentHash)
                ->andReturn(['valid' => true]);

            Log::shouldReceive('info')
                ->twice(); // Once at start, once at completion

            $job = new DocumentValidationJob(
                'document_integrity',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            $job->handle($this->smartContractService);
        });

        it('throws exception when document hash is missing', function () {
            $job = new DocumentValidationJob(
                'document_integrity',
                [], // Missing document_hash
                $this->procurementId,
                $this->userAddress
            );

            expect(fn () => $job->handle($this->smartContractService))
                ->toThrow(Exception::class, 'Document hash is required for integrity validation');
        });

        it('logs integrity validation result', function () {
            $documentHash = 'abc123hash';
            $data = ['document_hash' => $documentHash];

            $this->smartContractService
                ->shouldReceive('validateDocumentIntegrity')
                ->andReturn(['valid' => true]);

            Log::shouldReceive('info')
                ->with('Processing document validation job', \Mockery::any())
                ->once();

            Log::shouldReceive('info')
                ->with('Document integrity validation completed', [
                    'procurement_id' => $this->procurementId,
                    'document_hash' => $documentHash,
                    'valid' => true,
                ])
                ->once();

            $job = new DocumentValidationJob('document_integrity', $data, $this->procurementId, $this->userAddress);
            $job->handle($this->smartContractService);
        });
    });

    describe('metadata_compliance operation', function () {
        it('validates metadata compliance successfully', function () {
            $data = [
                'metadata' => ['type' => 'bid'],
                'stage' => 'submission',
            ];

            $this->smartContractService
                ->shouldReceive('checkDocumentMetadataCompliance')
                ->once()
                ->with($data['metadata'], $data['stage'])
                ->andReturn(['compliant' => true]);

            Log::shouldReceive('info')
                ->with('Processing document validation job', \Mockery::any())
                ->once();

            Log::shouldReceive('info')
                ->with('Metadata compliance check completed', \Mockery::any())
                ->once();

            $job = new DocumentValidationJob(
                'metadata_compliance',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            $job->handle($this->smartContractService);
        });

        it('throws exception when metadata is missing', function () {
            $data = ['stage' => 'submission']; // Missing metadata

            Log::shouldReceive('info')
                ->with('Processing document validation job', \Mockery::any())
                ->once();

            Log::shouldReceive('error')
                ->with('Document validation job failed', \Mockery::any())
                ->once();

            $job = new DocumentValidationJob(
                'metadata_compliance',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            expect(fn () => $job->handle($this->smartContractService))
                ->toThrow(Exception::class, 'Metadata and stage are required');
        });

        it('throws exception when stage is missing', function () {
            $data = ['metadata' => ['type' => 'bid']]; // Missing stage

            $job = new DocumentValidationJob(
                'metadata_compliance',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            expect(fn () => $job->handle($this->smartContractService))
                ->toThrow(Exception::class, 'Metadata and stage are required');
        });
    });

    describe('storage_consistency operation', function () {
        it('validates storage consistency successfully', function () {
            $data = ['file_key' => 'file123'];

            $this->smartContractService
                ->shouldReceive('validateDocumentStorageConsistency')
                ->once()
                ->with($this->procurementId)
                ->andReturn([
                    'consistent' => true,
                    'total_documents' => 10,
                    'validated_documents' => 10,
                ]);

            Log::shouldReceive('info')
                ->with('Processing document validation job', \Mockery::any())
                ->once();

            Log::shouldReceive('info')
                ->with('Storage consistency validation completed', \Mockery::any())
                ->once();

            $job = new DocumentValidationJob(
                'storage_consistency',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            $job->handle($this->smartContractService);
        });
    });

    describe('audit_trail_generation operation', function () {
        it('generates audit trail successfully', function () {
            $data = ['actions' => ['upload', 'verify']];

            $this->smartContractService
                ->shouldReceive('getDocumentAuditTrail')
                ->once()
                ->with($this->procurementId)
                ->andReturn([
                    'procurement_id' => $this->procurementId,
                    'total_entries' => 5,
                ]);

            Log::shouldReceive('info')
                ->with('Processing document validation job', \Mockery::any())
                ->once();

            Log::shouldReceive('info')
                ->with('Audit trail generation completed', \Mockery::any())
                ->once();

            $job = new DocumentValidationJob(
                'audit_trail_generation',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            $job->handle($this->smartContractService);
        });
    });

    describe('unknown operation handling', function () {
        it('logs warning for unknown operation', function () {
            Log::shouldReceive('info')->once(); // Processing log
            Log::shouldReceive('warning')
                ->with('Unknown document validation operation', [
                    'operation' => 'invalid_operation',
                ])
                ->once();

            $job = new DocumentValidationJob(
                'invalid_operation',
                [],
                $this->procurementId,
                $this->userAddress
            );

            $job->handle($this->smartContractService);
        });
    });

    describe('error handling', function () {
        it('logs and rethrows exceptions', function () {
            $data = ['document_hash' => 'hash123'];
            $exception = new Exception('Service unavailable');

            $this->smartContractService
                ->shouldReceive('validateDocumentIntegrity')
                ->andThrow($exception);

            Log::shouldReceive('info')->once(); // Processing log
            Log::shouldReceive('error')
                ->with('Document validation job failed', [
                    'operation' => 'document_integrity',
                    'procurement_id' => $this->procurementId,
                    'error' => 'Service unavailable',
                ])
                ->once();

            $job = new DocumentValidationJob(
                'document_integrity',
                $data,
                $this->procurementId,
                $this->userAddress
            );

            expect(fn () => $job->handle($this->smartContractService))
                ->toThrow(Exception::class, 'Service unavailable');
        });
    });
});

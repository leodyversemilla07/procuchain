<?php

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ProcurementDocument Model - Advanced Relationships', function () {
    test('belongs to procurement relationship', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-REL-001',
            'title' => 'Test Procurement',
        ]);

        $document = ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-REL-001',
        ]);

        expect($document->procurement)->toBeInstanceOf(Procurement::class);
        expect($document->procurement->id)->toBe('PR-REL-001');
        expect($document->procurement->title)->toBe('Test Procurement');
    });

    test('can eager load procurement relationship', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-EAGER-001']);

        $created = ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-EAGER-001',
        ]);

        $document = ProcurementDocument::with('procurement')->where('id', $created->id)->first();

        expect($document->relationLoaded('procurement'))->toBeTrue();
        expect($document->procurement->id)->toBe('PR-EAGER-001');
    });

    test('procurement relationship can be accessed', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-ACCESS-001']);

        $document = ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-ACCESS-001',
        ]);

        expect($document->procurement)->not->toBeNull();
        expect($document->procurement)->toBeInstanceOf(Procurement::class);
        expect($document->procurement_id)->toBe('PR-ACCESS-001');
    });

    test('multiple documents can belong to same procurement', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-MULTI-001']);

        ProcurementDocument::factory()->count(5)->create([
            'procurement_id' => 'PR-MULTI-001',
        ]);

        $documents = ProcurementDocument::where('procurement_id', 'PR-MULTI-001')->get();

        expect($documents)->toHaveCount(5);
        expect($documents->first()->procurement->id)->toBe('PR-MULTI-001');
    });
});

describe('ProcurementDocument Model - Advanced Queries', function () {
    test('can filter by stage', function () {
        ProcurementDocument::factory()->create([
            'stage' => 'Procurement Initiation',
            'file_name' => 'initiation.pdf',
        ]);

        ProcurementDocument::factory()->create([
            'stage' => 'Bid Evaluation',
            'file_name' => 'evaluation.pdf',
        ]);

        $initiation = ProcurementDocument::where('stage', 'Procurement Initiation')->get();

        expect($initiation)->toHaveCount(1);
        expect($initiation->first()->file_name)->toBe('initiation.pdf');
    });

    test('can filter by document_type', function () {
        ProcurementDocument::factory()->create([
            'document_type' => 'Purchase Request',
        ]);

        ProcurementDocument::factory()->create([
            'document_type' => 'Bidding Documents',
        ]);

        ProcurementDocument::factory()->create([
            'document_type' => 'Purchase Request',
        ]);

        $purchaseRequests = ProcurementDocument::where('document_type', 'Purchase Request')->get();

        expect($purchaseRequests)->toHaveCount(2);
    });

    test('can query documents with failed status and retries available', function () {
        ProcurementDocument::factory()->failed()->create([
            'blockchain_retry_count' => 2,
        ]);

        ProcurementDocument::factory()->failed()->create([
            'blockchain_retry_count' => 5,
        ]);

        ProcurementDocument::factory()->confirmed()->create();

        $retryable = ProcurementDocument::where('blockchain_status', 'failed')
            ->where('blockchain_retry_count', '<', 5)
            ->get();

        expect($retryable)->toHaveCount(1);
        expect($retryable->first()->blockchain_retry_count)->toBe(2);
    });

    test('can query documents by date range', function () {
        ProcurementDocument::factory()->create([
            'created_at' => now()->subMonths(3),
        ]);

        ProcurementDocument::factory()->create([
            'created_at' => now()->subDays(5),
        ]);

        ProcurementDocument::factory()->create([
            'created_at' => now(),
        ]);

        $recent = ProcurementDocument::where('created_at', '>=', now()->subWeek())->get();

        expect($recent)->toHaveCount(2);
    });

    test('can group documents by stage', function () {
        ProcurementDocument::factory()->count(3)->create(['stage' => 'Procurement Initiation']);
        ProcurementDocument::factory()->count(2)->create(['stage' => 'Bid Evaluation']);
        ProcurementDocument::factory()->count(1)->create(['stage' => 'Completion']);

        $grouped = ProcurementDocument::all()->groupBy('stage');

        expect($grouped)->toHaveKey('Procurement Initiation');
        expect($grouped)->toHaveKey('Bid Evaluation');
        expect($grouped)->toHaveKey('Completion');
        expect($grouped['Procurement Initiation'])->toHaveCount(3);
        expect($grouped['Bid Evaluation'])->toHaveCount(2);
    });

    test('can count documents per procurement', function () {
        $proc1 = Procurement::factory()->create(['id' => 'PR-COUNT-1']);
        $proc2 = Procurement::factory()->create(['id' => 'PR-COUNT-2']);

        ProcurementDocument::factory()->count(4)->create(['procurement_id' => 'PR-COUNT-1']);
        ProcurementDocument::factory()->count(7)->create(['procurement_id' => 'PR-COUNT-2']);

        $count1 = ProcurementDocument::where('procurement_id', 'PR-COUNT-1')->count();
        $count2 = ProcurementDocument::where('procurement_id', 'PR-COUNT-2')->count();

        expect($count1)->toBe(4);
        expect($count2)->toBe(7);
    });
});

describe('ProcurementDocument Model - Blockchain Transitions', function () {
    test('transitions from pending to confirmed', function () {
        $document = ProcurementDocument::factory()->pending()->create();

        expect($document->blockchain_status)->toBe('pending');
        expect($document->blockchain_txid)->toBeNull();

        $document->update([
            'blockchain_status' => 'confirmed',
            'blockchain_txid' => 'txid-success-123',
            'blockchain_status_updated_at' => now(),
        ]);

        $document->refresh();

        expect($document->blockchain_status)->toBe('confirmed');
        expect($document->blockchain_txid)->toBe('txid-success-123');
        expect($document->blockchain_status_updated_at)->not->toBeNull();
    });

    test('transitions from pending to failed', function () {
        $document = ProcurementDocument::factory()->pending()->create();

        $document->update([
            'blockchain_status' => 'failed',
            'blockchain_error' => 'Network timeout',
            'blockchain_retry_count' => 1,
        ]);

        $document->refresh();

        expect($document->blockchain_status)->toBe('failed');
        expect($document->blockchain_error)->toBe('Network timeout');
        expect($document->blockchain_retry_count)->toBe(1);
    });

    test('increments retry count on failures', function () {
        $document = ProcurementDocument::factory()->failed()->create([
            'blockchain_retry_count' => 1,
        ]);

        $document->update([
            'blockchain_status' => 'pending',
        ]);

        $document->update([
            'blockchain_status' => 'failed',
            'blockchain_error' => 'Second failure',
            'blockchain_retry_count' => 2,
        ]);

        expect($document->fresh()->blockchain_retry_count)->toBe(2);
    });

    test('clears error when transitioning to pending', function () {
        $document = ProcurementDocument::factory()->failed()->create([
            'blockchain_error' => 'Previous error message',
        ]);

        $document->update([
            'blockchain_status' => 'pending',
            'blockchain_error' => null,
        ]);

        expect($document->fresh()->blockchain_status)->toBe('pending');
        expect($document->fresh()->blockchain_error)->toBeNull();
    });
});

describe('ProcurementDocument Model - Correction Workflow', function () {
    test('marks document as corrected with full details', function () {
        $document = ProcurementDocument::factory()->confirmed()->create([
            'is_corrected' => false,
        ]);

        $document->update([
            'is_corrected' => true,
            'correction_reason' => 'Missing required signature',
            'corrected_at' => now(),
            'corrected_by' => '1AdminAddress123',
            'correction_txid' => 'correction-tx-abc',
        ]);

        $document->refresh();

        expect($document->is_corrected)->toBeTrue();
        expect($document->correction_reason)->toBe('Missing required signature');
        expect($document->corrected_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($document->corrected_by)->toBe('1AdminAddress123');
        expect($document->correction_txid)->toBe('correction-tx-abc');
    });

    test('can query only corrected documents', function () {
        ProcurementDocument::factory()->corrected()->count(3)->create();
        ProcurementDocument::factory()->count(5)->create(['is_corrected' => false]);

        $corrected = ProcurementDocument::where('is_corrected', true)->get();

        expect($corrected)->toHaveCount(3);
    });

    test('correction fields are nullable for non-corrected documents', function () {
        $document = ProcurementDocument::factory()->create([
            'is_corrected' => false,
            'correction_reason' => null,
            'corrected_at' => null,
            'corrected_by' => null,
            'correction_txid' => null,
        ]);

        expect($document->correction_reason)->toBeNull();
        expect($document->corrected_at)->toBeNull();
        expect($document->corrected_by)->toBeNull();
        expect($document->correction_txid)->toBeNull();
    });
});

describe('ProcurementDocument Model - Metadata Operations', function () {
    test('handles empty metadata', function () {
        $document = ProcurementDocument::factory()->create([
            'metadata' => [],
        ]);

        expect($document->metadata)->toBeArray();
        expect($document->metadata)->toBeEmpty();
    });

    test('handles null metadata gracefully', function () {
        $document = ProcurementDocument::factory()->create([
            'metadata' => null,
        ]);

        expect($document->metadata)->toBeNull();
    });

    test('updates metadata preserving existing keys', function () {
        $document = ProcurementDocument::factory()->create([
            'metadata' => [
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
            ],
        ]);

        $metadata = $document->metadata;
        $metadata['hash'] = 'abc123';

        $document->update(['metadata' => $metadata]);

        $document->refresh();

        expect($document->metadata)->toHaveKey('file_size');
        expect($document->metadata)->toHaveKey('mime_type');
        expect($document->metadata)->toHaveKey('hash');
        expect($document->metadata['hash'])->toBe('abc123');
    });

    test('can store deeply nested metadata', function () {
        $metadata = [
            'file_info' => [
                'size' => 2048,
                'type' => 'pdf',
                'properties' => [
                    'pages' => 10,
                    'security' => [
                        'encrypted' => false,
                        'permissions' => ['read', 'print'],
                    ],
                ],
            ],
        ];

        $document = ProcurementDocument::factory()->create([
            'metadata' => $metadata,
        ]);

        $retrieved = $document->fresh()->metadata;

        expect($retrieved['file_info']['properties']['pages'])->toBe(10);
        expect($retrieved['file_info']['properties']['security']['permissions'])->toBe(['read', 'print']);
    });
});

describe('ProcurementDocument Model - Factory States', function () {
    test('pending state creates correct attributes', function () {
        $document = ProcurementDocument::factory()->pending()->create();

        expect($document->blockchain_status)->toBe('pending');
        expect($document->blockchain_txid)->toBeNull();
        expect($document->blockchain_error)->toBeNull();
        expect($document->blockchain_retry_count)->toBe(0);
    });

    test('confirmed state creates correct attributes', function () {
        $document = ProcurementDocument::factory()->confirmed()->create();

        expect($document->blockchain_status)->toBe('confirmed');
        expect($document->blockchain_txid)->not->toBeNull();
        expect($document->blockchain_status_updated_at)->not->toBeNull();
        expect($document->blockchain_error)->toBeNull();
    });

    test('failed state creates correct attributes', function () {
        $document = ProcurementDocument::factory()->failed()->create();

        expect($document->blockchain_status)->toBe('failed');
        expect($document->blockchain_error)->not->toBeNull();
        expect($document->blockchain_retry_count)->toBeGreaterThan(0);
    });

    test('corrected state creates correct attributes', function () {
        $document = ProcurementDocument::factory()->corrected()->create();

        expect($document->is_corrected)->toBeTrue();
        expect($document->correction_reason)->not->toBeNull();
        expect($document->corrected_at)->not->toBeNull();
        expect($document->corrected_by)->not->toBeNull();
        expect($document->correction_txid)->not->toBeNull();
    });
});

describe('ProcurementDocument Model - Data Integrity', function () {
    test('requires procurement_id', function () {
        expect(fn () => ProcurementDocument::create([
            'file_key' => 'test.pdf',
            'file_name' => 'test.pdf',
            'document_type' => 'Test',
            'stage' => 'Procurement Initiation',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('accepts valid procurement_id', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-VALID-001']);

        $document = ProcurementDocument::create([
            'procurement_id' => 'PR-VALID-001',
            'file_key' => 'docs/test.pdf',
            'file_name' => 'test.pdf',
            'document_type' => 'Purchase Request',
            'stage' => 'Procurement Initiation',
        ]);

        expect($document->procurement_id)->toBe('PR-VALID-001');
    });

    test('nullable fields work correctly', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-NULL-001']);

        $document = ProcurementDocument::create([
            'procurement_id' => 'PR-NULL-001',
            'file_key' => 'minimal.pdf',
            'file_name' => 'minimal.pdf',
            'document_type' => 'Basic Doc',
            'stage' => 'Procurement Initiation',
            // All blockchain and correction fields are nullable
        ]);

        expect($document->blockchain_txid)->toBeNull();
        expect($document->blockchain_error)->toBeNull();
        expect($document->correction_reason)->toBeNull();
        expect($document->corrected_by)->toBeNull();
    });

    test('timestamps are automatically managed', function () {
        $document = ProcurementDocument::factory()->create();

        expect($document->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($document->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);

        $oldUpdatedAt = $document->updated_at;

        sleep(1);

        $document->update(['file_name' => 'updated.pdf']);

        expect($document->fresh()->updated_at->isAfter($oldUpdatedAt))->toBeTrue();
    });
});

describe('ProcurementDocument Model - Complex Scenarios', function () {
    test('can query documents by multiple blockchain statuses', function () {
        ProcurementDocument::factory()->pending()->create();
        ProcurementDocument::factory()->confirmed()->create();
        ProcurementDocument::factory()->failed()->create();
        ProcurementDocument::factory()->pending()->create();

        $pendingOrFailed = ProcurementDocument::whereIn('blockchain_status', ['pending', 'failed'])->get();

        expect($pendingOrFailed)->toHaveCount(3);
    });

    test('can order by multiple columns', function () {
        $procurement = Procurement::factory()->create(['id' => 'PR-ORDER-001']);

        ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-ORDER-001',
            'stage' => 'Procurement Initiation',
            'created_at' => now()->subDays(2),
        ]);

        ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-ORDER-001',
            'stage' => 'Bid Evaluation',
            'created_at' => now()->subDays(1),
        ]);

        ProcurementDocument::factory()->create([
            'procurement_id' => 'PR-ORDER-001',
            'stage' => 'Procurement Initiation',
            'created_at' => now(),
        ]);

        $ordered = ProcurementDocument::orderBy('stage', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        expect($ordered->first()->stage)->toBe('Bid Evaluation');
    });

    test('can filter documents needing attention', function () {
        // Failed with retries available
        ProcurementDocument::factory()->failed()->create([
            'blockchain_retry_count' => 2,
        ]);

        // Corrected but not yet republished
        ProcurementDocument::factory()->corrected()->create([
            'correction_txid' => null,
        ]);

        // Successful - no attention needed
        ProcurementDocument::factory()->confirmed()->create();

        $needsAttention = ProcurementDocument::where(function ($query) {
            $query->where(function ($q) {
                $q->where('blockchain_status', 'failed')
                    ->where('blockchain_retry_count', '<', 5);
            })->orWhere(function ($q) {
                $q->where('is_corrected', true)
                    ->whereNull('correction_txid');
            });
        })->get();

        expect($needsAttention)->toHaveCount(2);
    });
});

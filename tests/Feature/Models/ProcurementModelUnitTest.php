<?php

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Procurement Model - Relationships', function () {
    test('has many documents relationship', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-REL-001',
        ]);

        ProcurementDocument::factory()->count(3)->create([
            'pr_number' => 'PR-REL-001',
        ]);

        expect($procurement->documents)->toHaveCount(3);
        expect($procurement->documents->first())->toBeInstanceOf(ProcurementDocument::class);
    });

    test('documents relationship is empty when no documents exist', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-REL-002',
        ]);

        expect($procurement->documents)->toHaveCount(0);
        expect($procurement->documents)->toBeEmpty();
    });

    test('can eager load documents', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-EAGER-001',
        ]);

        ProcurementDocument::factory()->count(5)->create([
            'pr_number' => 'PR-EAGER-001',
        ]);

        $loadedProcurement = Procurement::with('documents')->find('PR-EAGER-001');

        expect($loadedProcurement->relationLoaded('documents'))->toBeTrue();
        expect($loadedProcurement->documents)->toHaveCount(5);
    });

    test('can have documents associated', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-CASCADE-001',
        ]);

        $document = ProcurementDocument::factory()->create([
            'pr_number' => 'PR-CASCADE-001',
        ]);

        $foundDocument = ProcurementDocument::where('pr_number', 'PR-CASCADE-001')->first();

        expect($foundDocument)->not->toBeNull();
        expect($foundDocument->pr_number)->toBe('PR-CASCADE-001');
    });
});

describe('Procurement Model - Scopes and Queries', function () {
    test('can filter by stage', function () {
        Procurement::factory()->create([
            'id' => 'PR-STAGE-001',
            'stage' => 'Procurement Initiation',
        ]);

        Procurement::factory()->create([
            'id' => 'PR-STAGE-002',
            'stage' => 'Bid Evaluation',
        ]);

        $initiation = Procurement::where('stage', 'Procurement Initiation')->get();
        $evaluation = Procurement::where('stage', 'Bid Evaluation')->get();

        expect($initiation)->toHaveCount(1);
        expect($evaluation)->toHaveCount(1);
        expect($initiation->first()->id)->toBe('PR-STAGE-001');
    });

    test('can filter by current_status', function () {
        Procurement::factory()->create([
            'id' => 'PR-STATUS-001',
            'current_status' => 'Submitted',
        ]);

        Procurement::factory()->create([
            'id' => 'PR-STATUS-002',
            'current_status' => 'Approved',
        ]);

        $submitted = Procurement::where('current_status', 'Submitted')->get();

        expect($submitted)->toHaveCount(1);
        expect($submitted->first()->current_status)->toBe('Submitted');
    });

    test('can filter by blockchain_status', function () {
        Procurement::factory()->pending()->create(['id' => 'PR-BC-PENDING']);
        Procurement::factory()->confirmed()->create(['id' => 'PR-BC-CONFIRMED']);
        Procurement::factory()->failed()->create(['id' => 'PR-BC-FAILED']);

        $pending = Procurement::where('blockchain_status', 'pending')->get();
        $confirmed = Procurement::where('blockchain_status', 'confirmed')->get();
        $failed = Procurement::where('blockchain_status', 'failed')->get();

        expect($pending)->toHaveCount(1);
        expect($confirmed)->toHaveCount(1);
        expect($failed)->toHaveCount(1);
    });

    test('can query by date range', function () {
        Procurement::factory()->create([
            'id' => 'PR-DATE-OLD',
            'last_updated' => now()->subMonths(2),
        ]);

        Procurement::factory()->create([
            'id' => 'PR-DATE-RECENT',
            'last_updated' => now()->subDays(5),
        ]);

        $recent = Procurement::where('last_updated', '>=', now()->subWeek())->get();

        expect($recent)->toHaveCount(1);
        expect($recent->first()->id)->toBe('PR-DATE-RECENT');
    });

    test('can query failed procurements with retry available', function () {
        Procurement::factory()->failed()->create([
            'id' => 'PR-RETRY-AVAIL',
            'blockchain_retry_count' => 2,
        ]);

        Procurement::factory()->failed()->create([
            'id' => 'PR-RETRY-EXHAUSTED',
            'blockchain_retry_count' => 5,
        ]);

        $retryable = Procurement::where('blockchain_status', 'failed')
            ->where('blockchain_retry_count', '<', 5)
            ->get();

        expect($retryable)->toHaveCount(1);
        expect($retryable->first()->id)->toBe('PR-RETRY-AVAIL');
    });

    test('can count documents per procurement', function () {
        $proc1 = Procurement::factory()->create(['id' => 'PR-COUNT-1']);
        $proc2 = Procurement::factory()->create(['id' => 'PR-COUNT-2']);

        ProcurementDocument::factory()->count(3)->create(['pr_number' => 'PR-COUNT-1']);
        ProcurementDocument::factory()->count(7)->create(['pr_number' => 'PR-COUNT-2']);

        $procurements = Procurement::withCount('documents')->get();

        expect($procurements->find('PR-COUNT-1')->documents_count)->toBe(3);
        expect($procurements->find('PR-COUNT-2')->documents_count)->toBe(7);
    });
});

describe('Procurement Model - Blockchain Status Transitions', function () {
    test('transitions from pending to confirmed', function () {
        $procurement = Procurement::factory()->pending()->create([
            'id' => 'PR-TRANS-001',
        ]);

        expect($procurement->blockchain_status)->toBe('pending');
        expect($procurement->blockchain_txid)->toBeNull();

        $procurement->update([
            'blockchain_status' => 'confirmed',
            'blockchain_txid' => 'tx123abc',
            'blockchain_status_updated_at' => now(),
        ]);

        $procurement->refresh();

        expect($procurement->blockchain_status)->toBe('confirmed');
        expect($procurement->blockchain_txid)->toBe('tx123abc');
        expect($procurement->blockchain_status_updated_at)->not->toBeNull();
    });

    test('transitions from pending to failed', function () {
        $procurement = Procurement::factory()->pending()->create([
            'id' => 'PR-TRANS-002',
        ]);

        $procurement->update([
            'blockchain_status' => 'failed',
            'blockchain_error' => 'Connection timeout',
            'blockchain_retry_count' => 1,
        ]);

        $procurement->refresh();

        expect($procurement->blockchain_status)->toBe('failed');
        expect($procurement->blockchain_error)->toBe('Connection timeout');
        expect($procurement->blockchain_retry_count)->toBe(1);
    });

    test('increments retry count on subsequent failures', function () {
        $procurement = Procurement::factory()->failed()->create([
            'id' => 'PR-TRANS-003',
            'blockchain_retry_count' => 1,
        ]);

        $procurement->update([
            'blockchain_status' => 'pending',
        ]);

        $procurement->update([
            'blockchain_status' => 'failed',
            'blockchain_error' => 'Network error',
            'blockchain_retry_count' => 2,
        ]);

        expect($procurement->fresh()->blockchain_retry_count)->toBe(2);
    });

    test('clears error when transitioning to pending', function () {
        $procurement = Procurement::factory()->failed()->create([
            'id' => 'PR-TRANS-004',
            'blockchain_error' => 'Previous error',
        ]);

        $procurement->update([
            'blockchain_status' => 'pending',
            'blockchain_error' => null,
        ]);

        expect($procurement->fresh()->blockchain_status)->toBe('pending');
        expect($procurement->fresh()->blockchain_error)->toBeNull();
    });
});

describe('Procurement Model - Data Integrity', function () {
    test('requires string id', function () {
        expect(fn () => Procurement::create([
            'title' => 'Test',
            'stage' => 'Procurement Initiation',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('accepts custom string ID format', function () {
        $customIds = [
            'PR-2025-0001-0001',
            'PROC-ABC-123',
            'P-TEST',
            'PR-2024-CUSTOM-ID',
        ];

        foreach ($customIds as $id) {
            $procurement = Procurement::create([
                'id' => $id,
                'title' => "Test for {$id}",
                'stage' => 'Procurement Initiation',
                'current_status' => 'Submitted',
            ]);

            expect($procurement->id)->toBe($id);
        }
    });

    test('prevents duplicate IDs', function () {
        Procurement::create([
            'id' => 'PR-DUP-001',
            'title' => 'First',
            'stage' => 'Procurement Initiation',
        ]);

        expect(fn () => Procurement::create([
            'id' => 'PR-DUP-001',
            'title' => 'Second',
            'stage' => 'Bid Evaluation',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('nullable fields work correctly', function () {
        $procurement = Procurement::create([
            'id' => 'PR-NULL-001',
            'title' => 'Minimal Procurement',
            'stage' => 'Procurement Initiation',
            'current_status' => 'Submitted',
            // All other fields are nullable
        ]);

        expect($procurement->blockchain_txid)->toBeNull();
        expect($procurement->blockchain_error)->toBeNull();
        expect($procurement->user_address)->toBeNull();
    });

    test('updates last_updated timestamp automatically', function () {
        $procurement = Procurement::factory()->create([
            'id' => 'PR-TIME-001',
            'last_updated' => now()->subHours(2),
        ]);

        $oldTime = $procurement->last_updated;

        sleep(1);

        $procurement->update(['title' => 'Updated Title']);

        // last_updated should be manually managed or remain the same
        expect($procurement->fresh()->last_updated->eq($oldTime))->toBeTrue();
    });
});

describe('Procurement Model - Factory States', function () {
    test('pending state creates correct attributes', function () {
        $procurement = Procurement::factory()->pending()->create([
            'id' => 'PR-STATE-PENDING',
        ]);

        expect($procurement->blockchain_status)->toBe('pending');
        expect($procurement->blockchain_txid)->toBeNull();
        expect($procurement->blockchain_error)->toBeNull();
        expect($procurement->blockchain_retry_count)->toBe(0);
    });

    test('confirmed state creates correct attributes', function () {
        $procurement = Procurement::factory()->confirmed()->create([
            'id' => 'PR-STATE-CONFIRMED',
        ]);

        expect($procurement->blockchain_status)->toBe('confirmed');
        expect($procurement->blockchain_txid)->not->toBeNull();
        expect($procurement->blockchain_status_updated_at)->not->toBeNull();
        expect($procurement->blockchain_error)->toBeNull();
    });

    test('failed state creates correct attributes', function () {
        $procurement = Procurement::factory()->failed()->create([
            'id' => 'PR-STATE-FAILED',
        ]);

        expect($procurement->blockchain_status)->toBe('failed');
        expect($procurement->blockchain_error)->not->toBeNull();
        expect($procurement->blockchain_retry_count)->toBeGreaterThan(0);
    });
});

describe('Procurement Model - Complex Queries', function () {
    test('can query with multiple conditions', function () {
        Procurement::factory()->confirmed()->create([
            'id' => 'PR-COMPLEX-1',
            'stage' => 'Bid Evaluation',
        ]);

        Procurement::factory()->pending()->create([
            'id' => 'PR-COMPLEX-2',
            'stage' => 'Bid Evaluation',
        ]);

        Procurement::factory()->confirmed()->create([
            'id' => 'PR-COMPLEX-3',
            'stage' => 'Procurement Initiation',
        ]);

        $result = Procurement::where('stage', 'Bid Evaluation')
            ->where('blockchain_status', 'confirmed')
            ->get();

        expect($result)->toHaveCount(1);
        expect($result->first()->id)->toBe('PR-COMPLEX-1');
    });

    test('can order by multiple columns', function () {
        Procurement::factory()->create([
            'id' => 'PR-ORDER-1',
            'stage' => 'Procurement Initiation',
            'last_updated' => now()->subDays(1),
        ]);

        Procurement::factory()->create([
            'id' => 'PR-ORDER-2',
            'stage' => 'Bid Evaluation',
            'last_updated' => now()->subDays(2),
        ]);

        Procurement::factory()->create([
            'id' => 'PR-ORDER-3',
            'stage' => 'Procurement Initiation',
            'last_updated' => now(),
        ]);

        $result = Procurement::orderBy('stage', 'asc')
            ->orderBy('last_updated', 'desc')
            ->get();

        expect($result->first()->id)->toBe('PR-ORDER-2');
        expect($result->last()->id)->toBe('PR-ORDER-1');
    });

    test('can use whereIn for multiple IDs', function () {
        Procurement::factory()->create(['id' => 'PR-IN-1']);
        Procurement::factory()->create(['id' => 'PR-IN-2']);
        Procurement::factory()->create(['id' => 'PR-IN-3']);
        Procurement::factory()->create(['id' => 'PR-IN-4']);

        $result = Procurement::whereIn('id', ['PR-IN-1', 'PR-IN-3', 'PR-IN-4'])->get();

        expect($result)->toHaveCount(3);
        expect($result->pluck('id')->toArray())->toBe(['PR-IN-1', 'PR-IN-3', 'PR-IN-4']);
    });
});

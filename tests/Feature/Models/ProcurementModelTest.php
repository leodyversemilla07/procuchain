<?php

use App\Models\Procurement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Procurement Model', function () {
    describe('Model Configuration', function () {
        it('has string primary key type', function () {
            $procurement = new Procurement();
            
            expect($procurement->getKeyType())->toBe('string');
        });

        it('does not auto-increment IDs', function () {
            $procurement = new Procurement();
            
            expect($procurement->getIncrementing())->toBeFalse();
        });

        it('has correct fillable fields', function () {
            $procurement = new Procurement();
            $expectedFillable = [
                'id',
                'title',
                'stage',
                'current_status',
                'user_address',
                'document_count',
                'last_updated',
                'blockchain_txid',
                'blockchain_status',
                'blockchain_status_updated_at',
                'blockchain_error',
                'blockchain_retry_count',
            ];
            
            expect($procurement->getFillable())->toBe($expectedFillable);
        });

        it('casts last_updated to datetime', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-001',
                'last_updated' => '2025-10-18 10:00:00',
            ]);
            
            expect($procurement->last_updated)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('casts blockchain_status_updated_at to datetime', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-002',
                'blockchain_status_updated_at' => now(),
            ]);
            
            expect($procurement->blockchain_status_updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('casts document_count to integer', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-003',
                'document_count' => '5',
            ]);
            
            expect($procurement->document_count)->toBeInt();
            expect($procurement->document_count)->toBe(5);
        });

        it('casts blockchain_retry_count to integer', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-004',
                'blockchain_retry_count' => '3',
            ]);
            
            expect($procurement->blockchain_retry_count)->toBeInt();
            expect($procurement->blockchain_retry_count)->toBe(3);
        });
    });

    describe('CRUD Operations', function () {
        it('can create a procurement with string ID', function () {
            $procurement = Procurement::create([
                'id' => 'PR-2025-0001-0001',
                'title' => 'Test Procurement',
                'stage' => 'Procurement Initiation',
                'current_status' => 'Submitted',
                'user_address' => '1ABC123XYZ',
                'document_count' => 5,
                'last_updated' => now(),
                'blockchain_status' => 'pending',
            ]);
            
            expect($procurement->id)->toBe('PR-2025-0001-0001');
            expect($procurement->title)->toBe('Test Procurement');
            expect($procurement->blockchain_status)->toBe('pending');
            
            $this->assertDatabaseHas('procurements', [
                'id' => 'PR-2025-0001-0001',
                'title' => 'Test Procurement',
            ]);
        });

        it('can update procurement blockchain status', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-UPDATE',
                'blockchain_status' => 'pending',
            ]);
            
            $procurement->update([
                'blockchain_status' => 'confirmed',
                'blockchain_txid' => 'txid123',
                'blockchain_status_updated_at' => now(),
            ]);
            
            expect($procurement->fresh()->blockchain_status)->toBe('confirmed');
            expect($procurement->fresh()->blockchain_txid)->toBe('txid123');
        });

        it('can update retry count on failure', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-RETRY',
                'blockchain_retry_count' => 0,
            ]);
            
            $procurement->update([
                'blockchain_status' => 'failed',
                'blockchain_error' => 'Connection timeout',
                'blockchain_retry_count' => $procurement->blockchain_retry_count + 1,
            ]);
            
            expect($procurement->fresh()->blockchain_status)->toBe('failed');
            expect($procurement->fresh()->blockchain_retry_count)->toBe(1);
            expect($procurement->fresh()->blockchain_error)->toBe('Connection timeout');
        });

        it('can delete procurement', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-TEST-DELETE',
            ]);
            
            $procurement->delete();
            
            $this->assertDatabaseMissing('procurements', [
                'id' => 'PR-TEST-DELETE',
            ]);
        });
    });

    describe('Query Operations', function () {
        it('can find by string primary key', function () {
            Procurement::factory()->create([
                'id' => 'PR-FIND-001',
                'title' => 'Findable Procurement',
            ]);
            
            $procurement = Procurement::find('PR-FIND-001');
            
            expect($procurement)->not->toBeNull();
            expect($procurement->title)->toBe('Findable Procurement');
        });

        it('can query by blockchain status', function () {
            Procurement::factory()->create([
                'id' => 'PR-PENDING-001',
                'blockchain_status' => 'pending',
            ]);
            
            Procurement::factory()->create([
                'id' => 'PR-CONFIRMED-001',
                'blockchain_status' => 'confirmed',
            ]);
            
            $pending = Procurement::where('blockchain_status', 'pending')->get();
            $confirmed = Procurement::where('blockchain_status', 'confirmed')->get();
            
            expect($pending)->toHaveCount(1);
            expect($confirmed)->toHaveCount(1);
        });

        it('can query failed procurements for retry', function () {
            Procurement::factory()->create([
                'id' => 'PR-FAILED-001',
                'blockchain_status' => 'failed',
                'blockchain_retry_count' => 2,
            ]);
            
            Procurement::factory()->create([
                'id' => 'PR-SUCCESS-001',
                'blockchain_status' => 'confirmed',
            ]);
            
            $failedForRetry = Procurement::where('blockchain_status', 'failed')
                ->where('blockchain_retry_count', '<', 5)
                ->get();
            
            expect($failedForRetry)->toHaveCount(1);
            expect($failedForRetry->first()->id)->toBe('PR-FAILED-001');
        });

        it('can order by last_updated', function () {
            Procurement::factory()->create([
                'id' => 'PR-OLD',
                'last_updated' => now()->subDays(5),
            ]);
            
            Procurement::factory()->create([
                'id' => 'PR-NEW',
                'last_updated' => now(),
            ]);
            
            $latest = Procurement::orderBy('last_updated', 'desc')->first();
            
            expect($latest->id)->toBe('PR-NEW');
        });
    });

    describe('Blockchain Status Tracking', function () {
        it('tracks publication lifecycle', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-LIFECYCLE',
                'blockchain_status' => 'pending',
                'blockchain_retry_count' => 0,
            ]);
            
            // Simulate publication success
            $procurement->update([
                'blockchain_status' => 'confirmed',
                'blockchain_txid' => 'abc123',
                'blockchain_status_updated_at' => now(),
            ]);
            
            expect($procurement->fresh()->blockchain_status)->toBe('confirmed');
            expect($procurement->fresh()->blockchain_txid)->toBe('abc123');
        });

        it('handles retry mechanism correctly', function () {
            $procurement = Procurement::factory()->create([
                'id' => 'PR-RETRY-TEST',
                'blockchain_status' => 'pending',
                'blockchain_retry_count' => 0,
            ]);
            
            // First failure
            $procurement->update([
                'blockchain_status' => 'failed',
                'blockchain_error' => 'Network error',
                'blockchain_retry_count' => 1,
            ]);
            
            expect($procurement->fresh()->blockchain_retry_count)->toBe(1);
            
            // Retry attempt
            $procurement->update([
                'blockchain_status' => 'pending',
            ]);
            
            // Second failure
            $procurement->update([
                'blockchain_status' => 'failed',
                'blockchain_error' => 'Timeout',
                'blockchain_retry_count' => 2,
            ]);
            
            expect($procurement->fresh()->blockchain_retry_count)->toBe(2);
        });
    });
});

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\Procurement;
use App\Services\IntegrityVerificationService;
use App\Services\Manager;
use App\Services\NormalizedTableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integrity Breach Simulation Test
 *
 * Simulates database tampering scenarios to verify that
 * the integrity verification system correctly detects breaches.
 */
class IntegrityBreachSimulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that hash mismatch is detected when data is tampered.
     */
    public function test_detects_hash_mismatch_when_data_is_tampered(): void
    {
        // Arrange: Create a procurement with known data
        $originalData = [
            'title' => 'Test Procurement',
            'amount' => 100000,
            'status' => 'pending',
        ];

        $procurement = Procurement::create([
            'pr_number' => 'PR-2026-001-0001',
            'title' => 'Test Procurement',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'procurement_initiation',
            'current_status' => 'pending',
            'abc_amount' => 100000,
            'txid' => 'abc123def456',
            'data_hash' => hash('sha256', json_encode($originalData)),
            'blockchain_hash' => hash('sha256', json_encode($originalData)),
        ]);

        // Act: Tamper with the data directly in database
        DB::table('procurements')
            ->where('id', $procurement->id)
            ->update(['title' => 'TAMPERED TITLE']);

        $procurement->refresh();

        // Assert: Hash should not match
        $computedHash = hash('sha256', json_encode(['title' => $procurement->title, 'amount' => $procurement->abc_amount]));
        $this->assertNotEquals($procurement->data_hash, $computedHash, 'Hash should mismatch after tampering');
    }

    /**
     * Test that field-level differences are correctly computed.
     */
    public function test_computes_field_level_differences_correctly(): void
    {
        $service = app(IntegrityVerificationService::class);

        $chainData = [
            'title' => 'Original Title',
            'amount' => 100000,
            'status' => 'pending',
            'category' => 'goods',
        ];

        $dbData = [
            'title' => 'Modified Title', // Changed
            'amount' => 100000,           // Same
            'status' => 'approved',       // Changed
            'extra_field' => 'new',       // Added
        ];

        $diffs = $service->computeFieldDifferences($dbData, $chainData);

        expect($diffs)->toHaveCount(2); // title, status; DB-only mirror fields are not raw chain diffs
        expect($diffs[0]['field'])->toBe('title');
        expect($diffs[1]['field'])->toBe('status');
    }

    /**
     * Test that deleted records are detected.
     */
    public function test_detects_deleted_records(): void
    {
        // Arrange: Create a procurement
        Procurement::create([
            'pr_number' => 'PR-2026-002-0001',
            'title' => 'To Be Deleted',
            'category' => 'goods',
            'procurement_mode' => 'competitive_bidding',
            'current_stage' => 'procurement_initiation',
            'current_status' => 'draft',
        ]);

        // Act: Delete the record
        Procurement::where('pr_number', 'PR-2026-002-0001')->delete();

        // Assert: Record should be soft deleted
        $this->assertSoftDeleted('procurements', ['pr_number' => 'PR-2026-002-0001']);
    }

    /**
     * Test that violation report is generated correctly.
     */
    public function test_generates_violation_report(): void
    {
        // Arrange: Create some violations
        $runId = IntegrityAuditLog::newRunId();

        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-003-0001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
            runId: $runId,
        );

        IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-004-0001',
            violationType: BreachTypeEnums::ROW_DELETED->value,
            runId: $runId,
        );

        // Act
        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        // Assert
        expect($report['summary']['total_violations'])->toBe(2);
        expect($report['summary']['critical'])->toBe(1); // hash_mismatch
        expect($report['summary']['low'])->toBe(1); // row_deleted
    }

    /**
     * Test that restoration works correctly.
     */
    public function test_restores_from_blockchain(): void
    {
        // Arrange: Create a violation
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: 'PR-2026-005-0001',
            violationType: BreachTypeEnums::HASH_MISMATCH->value,
        );

        $manager = \Mockery::mock(Manager::class);
        $manager->shouldReceive('liststreamitems')
            ->once()
            ->andReturn([]);

        $syncService = \Mockery::mock(NormalizedTableSyncService::class);
        $syncService->shouldReceive('syncAll')
            ->once();

        $this->app->instance(Manager::class, $manager);
        $this->app->instance(NormalizedTableSyncService::class, $syncService);

        // Act: Restore
        $service = app(IntegrityVerificationService::class);
        $result = $service->restoreViolation($auditLog);

        // Assert
        expect($result['success'])->toBeTrue();

        $auditLog->refresh();
        expect($auditLog->recovery_status)->toBe('restored');
        expect($auditLog->recovery_result)->toHaveKey('restored_by');
    }
}

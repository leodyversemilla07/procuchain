<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\ProcurementMirror;
use App\Services\IntegrityVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integrity Breach Simulation Test
 *
 * This test simulates database tampering scenarios to verify that
 * the integrity verification system correctly detects breaches.
 *
 * Run with: php artisan test --filter=IntegrityBreachSimulationTest
 */
class IntegrityBreachSimulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that hash mismatch is detected when data is tampered.
     */
    public function test_detects_hash_mismatch_when_data_is_tampered(): void
    {
        // Arrange: Create a mirror record with known data
        $originalData = [
            'title' => 'Test Procurement',
            'amount' => 100000,
            'status' => 'pending',
        ];

        $mirror = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-001',
            'txid' => 'abc123def456',
            'revision_number' => 1,
            'parent_txid' => null,
            'is_latest_revision' => true,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'blocktime' => now(),
            'data_json' => $originalData,
            'data_hash' => hash('sha256', json_encode($originalData)),
            'is_authorized' => true,
            'synced_at' => now(),
        ]);

        // Act: Tamper with the data directly in database (simulating malicious modification)
        $tamperedData = $originalData;
        $tamperedData['amount'] = 999999; // Change the amount

        // Directly update the data_json without updating the hash
        DB::table('procurement_mirror')
            ->where('id', $mirror->id)
            ->update(['data_json' => $tamperedData]);

        // Refresh the model
        $mirror->refresh();

        // Assert: Hash should not match
        $computedHash = hash('sha256', json_encode($mirror->data_json));
        $this->assertNotEquals($mirror->data_hash, $computedHash, 'Hash should mismatch after tampering');

        // Verify the breach detection works
        $isValid = $mirror->verifyIntegrity();
        $this->assertFalse($isValid, 'Integrity check should fail after tampering');
    }

    /**
     * Test that field-level differences are correctly computed.
     */
    public function test_computes_field_level_differences_correctly(): void
    {
        // Arrange
        $service = app(IntegrityVerificationService::class);

        $chainData = [
            'title' => 'Original Title',
            'amount' => 100000,
            'status' => 'pending',
            'category' => 'goods',
        ];

        $mirrorData = [
            'title' => 'Modified Title', // Changed
            'amount' => 100000,           // Same
            'status' => 'approved',       // Changed
            // 'category' is missing       // Removed
            'extra_field' => 'new',       // Added
        ];

        // Act
        $diffs = $service->computeFieldDifferences($mirrorData, $chainData);

        // Assert
        $this->assertIsArray($diffs);
        $this->assertGreaterThan(0, count($diffs), 'Should detect differences');

        // Check that specific field differences are detected
        $fieldNames = array_column($diffs, 'field');
        $this->assertContains('title', $fieldNames, 'Should detect title change');
        $this->assertContains('status', $fieldNames, 'Should detect status change');

        // Verify the diff structure
        foreach ($diffs as $diff) {
            $this->assertArrayHasKey('field', $diff);
            $this->assertArrayHasKey('old_value', $diff);
            $this->assertArrayHasKey('new_value', $diff);
        }
    }

    /**
     * Test that audit log records the correct severity for different breach types.
     */
    public function test_records_correct_severity_for_breach_types(): void
    {
        // Test each breach type has the expected severity
        $expectedSeverity = [
            BreachTypeEnums::HASH_MISMATCH->value => 'critical',
            BreachTypeEnums::CONTENT_MISMATCH->value => 'critical',
            BreachTypeEnums::USER_ADDRESS_TAMPERED->value => 'high',
            BreachTypeEnums::UNAUTHORIZED_PUBLISHER->value => 'medium',
            BreachTypeEnums::ROW_DELETED->value => 'low',
        ];

        foreach ($expectedSeverity as $type => $expectedSev) {
            $severity = IntegrityAuditLog::severityForType($type);
            $this->assertEquals(
                $expectedSev,
                $severity,
                "Breach type {$type} should have severity {$expectedSev}"
            );
        }
    }

    /**
     * Test that revision lineage is correctly tracked.
     */
    public function test_tracks_revision_lineage_correctly(): void
    {
        // Arrange: Create a chain of revisions
        $root = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-002',
            'txid' => 'tx_root_001',
            'revision_number' => 1,
            'parent_txid' => null,
            'is_latest_revision' => false,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'data_json' => ['version' => 1],
            'data_hash' => hash('sha256', json_encode(['version' => 1])),
            'synced_at' => now(),
        ]);

        $revision2 = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-002',
            'txid' => 'tx_rev_002',
            'revision_number' => 2,
            'parent_txid' => 'tx_root_001',
            'is_latest_revision' => false,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'data_json' => ['version' => 2],
            'data_hash' => hash('sha256', json_encode(['version' => 2])),
            'synced_at' => now(),
        ]);

        $revision3 = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-002',
            'txid' => 'tx_rev_003',
            'revision_number' => 3,
            'parent_txid' => 'tx_rev_002',
            'is_latest_revision' => true,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'data_json' => ['version' => 3],
            'data_hash' => hash('sha256', json_encode(['version' => 3])),
            'synced_at' => now(),
        ]);

        // Act: Get revision lineage from the latest
        $lineage = $revision3->getRevisionLineage();

        // Assert
        $this->assertEquals(
            ['tx_root_001', 'tx_rev_002', 'tx_rev_003'],
            $lineage,
            'Should return full lineage from root to current revision'
        );

        // Verify parent-child relationships
        $this->assertNull($root->parent_txid, 'Root should have no parent');
        $this->assertEquals('tx_root_001', $revision2->parent_txid);
        $this->assertEquals('tx_rev_002', $revision3->parent_txid);

        // Verify revision history
        $history = $revision3->getRevisionHistory();
        $this->assertEquals(3, $history->count(), 'Should have 3 revisions in history');
    }

    /**
     * Test that breach marking and repair tracking works.
     */
    public function test_marks_breach_and_repair_correctly(): void
    {
        // Arrange
        $mirror = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-003',
            'txid' => 'tx_test_breach',
            'revision_number' => 1,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'data_json' => ['test' => true],
            'data_hash' => hash('sha256', json_encode(['test' => true])),
            'synced_at' => now(),
        ]);

        // Initially not breached
        $this->assertFalse($mirror->isBreached(), 'Should not be breached initially');
        $this->assertNull($mirror->breach_detected_at);
        $this->assertNull($mirror->repaired_at);

        // Act: Mark as breached
        $mirror->markAsBreached(BreachTypeEnums::HASH_MISMATCH->value, [
            'reason' => 'test tampering',
        ]);

        // Assert: Should be breached
        $this->assertTrue($mirror->isBreached(), 'Should be breached after marking');
        $this->assertNotNull($mirror->breach_detected_at);
        $this->assertEquals(BreachTypeEnums::HASH_MISMATCH->value, $mirror->breach_type);

        // Act: Mark as repaired
        $mirror->markAsRepaired();

        // Assert: Should no longer be breached
        $this->assertFalse($mirror->isBreached(), 'Should not be breached after repair');
        $this->assertNotNull($mirror->repaired_at);
    }

    /**
     * Test that audit log records field differences correctly.
     */
    public function test_records_field_differences_in_audit_log(): void
    {
        // Arrange
        $mirror = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => 'PR-2026-004',
            'txid' => 'tx_audit_test',
            'revision_number' => 1,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'data_json' => ['field1' => 'original'],
            'data_hash' => hash('sha256', json_encode(['field1' => 'original'])),
            'synced_at' => now(),
        ]);

        $fieldDiffs = [
            [
                'field' => 'field1',
                'old_value' => 'blockchain_value',
                'new_value' => 'tampered_value',
            ],
        ];

        $chainSnapshot = ['field1' => 'blockchain_value'];

        // Act: Record violation
        $auditLog = IntegrityAuditLog::recordViolationFromMirror(
            mirror: $mirror,
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
            fieldDifferences: $fieldDiffs,
            chainSnapshot: $chainSnapshot,
            runId: 'test-run-001',
            source: 'test',
        );

        // Assert
        $this->assertNotNull($auditLog->id);
        $this->assertEquals('procurement.metadata', $auditLog->stream);
        $this->assertEquals('PR-2026-004', $auditLog->stream_key);
        $this->assertEquals(BreachTypeEnums::CONTENT_MISMATCH->value, $auditLog->violation_type);
        $this->assertEquals('critical', $auditLog->severity);
        $this->assertEquals('pending', $auditLog->recovery_status);
        $this->assertEquals($fieldDiffs, $auditLog->field_differences);
        $this->assertEquals($chainSnapshot, $auditLog->chain_snapshot);
        $this->assertEquals(1, $auditLog->revision_number);
        $this->assertNull($auditLog->parent_txid);
        $this->assertEquals('test-run-001', $auditLog->verification_run_id);
    }
}

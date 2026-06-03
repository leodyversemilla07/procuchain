<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\ProcurementMirror;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity System Demonstration Command
 *
 * Demonstrates the data integrity and audit-tracking system by:
 * 1. Showing current mirror records
 * 2. Simulating a database tampering attack
 * 3. Running integrity verification
 * 4. Showing detected breaches and audit logs
 *
 * Usage: php artisan integrity:demo
 */
class IntegrityDemo extends Command
{
    protected $signature = 'integrity:demo {--restore : Restore the demo record after demonstration}';

    protected $description = 'Demonstrate the integrity verification system with a simulated tampering attack';

    private string $demoKey = 'DEMO-PR-2026-TEST';

    public function handle(): int
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║      DATA INTEGRITY & AUDIT-TRACKING SYSTEM DEMO          ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($this->option('restore')) {
            return $this->restoreDemoRecord();
        }

        // Step 1: Create or show demo record
        $this->step(1, 'Creating Demo Mirror Record');

        $originalData = [
            'pr_number' => $this->demoKey,
            'title' => 'Test Procurement for Integrity Demo',
            'amount' => 150000.00,
            'status' => 'pending',
            'category' => 'goods',
            'created_by' => 'admin@procuchain.tech',
            'blockchain_verified' => true,
        ];

        $mirror = ProcurementMirror::updateOrCreate(
            [
                'stream' => 'procurement.metadata',
                'stream_key' => $this->demoKey,
                'txid' => 'demo_txid_001',
            ],
            [
                'revision_number' => 1,
                'parent_txid' => null,
                'is_latest_revision' => true,
                'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                'blocktime' => now(),
                'data_json' => $originalData,
                'data_hash' => hash('sha256', json_encode($originalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'is_authorized' => true,
                'synced_at' => now(),
            ]
        );

        $this->info("  ✓ Created mirror record (ID: {$mirror->id})");
        $this->info("  ✓ Stream: procurement.metadata");
        $this->info("  ✓ Key: {$this->demoKey}");
        $this->info("  ✓ Hash: " . substr($mirror->data_hash, 0, 16) . '...');
        $this->newLine();

        // Step 2: Verify initial integrity
        $this->step(2, 'Initial Integrity Check (Before Tampering)');

        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $hashValid = $computedHash === $mirror->data_hash;

        $this->info("  Computed Hash: " . substr($computedHash, 0, 16) . '...');
        $this->info("  Stored Hash:   " . substr($mirror->data_hash, 0, 16) . '...');
        $this->info("  Hash Match: " . ($hashValid ? '✓ YES' : '✗ NO'));
        $this->info("  Integrity Status: " . ($mirror->isBreached() ? '✗ BREACHED' : '✓ VALID'));
        $this->newLine();

        // Step 3: Simulate tampering attack
        $this->step(3, 'Simulating Database Tampering Attack');

        $this->warn('  ⚠ Attacker modifies data directly in database...');
        $this->warn('  ⚠ Bypassing application logic and blockchain...');

        $tamperedData = $originalData;
        $tamperedData['amount'] = 999999.99; // Drastically change amount
        $tamperedData['status'] = 'approved'; // Change status
        $tamperedData['title'] = 'HACKED - Procurement Modified';

        // Direct database update (simulating SQL injection or compromised DB)
        DB::table('procurement_mirror')
            ->where('id', $mirror->id)
            ->update(['data_json' => $tamperedData]);

        $mirror->refresh();

        $this->info("  ✓ Amount changed: {$originalData['amount']} → {$tamperedData['amount']}");
        $this->info("  ✓ Status changed: {$originalData['status']} → {$tamperedData['status']}");
        $this->info("  ✓ Title changed: '{$originalData['title']}' → '{$tamperedData['title']}'");
        $this->newLine();

        // Step 4: Verify integrity after tampering
        $this->step(4, 'Integrity Check After Tampering');

        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $hashValid = $computedHash === $mirror->data_hash;

        $this->info("  Computed Hash: " . substr($computedHash, 0, 16) . '...');
        $this->info("  Stored Hash:   " . substr($mirror->data_hash, 0, 16) . '...');

        if (! $hashValid) {
            $this->error("  ✗ Hash MISMATCH detected!");
        } else {
            $this->warn("  ⚠ Hash matches (data was updated with new hash)");
        }

        // Step 5: Field-level diff
        $this->step(5, 'Field-Level Difference Analysis');

        $service = app(\App\Services\IntegrityVerificationService::class);
        $fieldDiffs = $service->computeFieldDifferences($tamperedData, $originalData);

        $this->info("  Detected " . count($fieldDiffs) . " field difference(s):");
        $this->newLine();

        $this->table(
            ['Field', 'Original (Chain)', 'Tampered (DB)'],
            array_map(fn ($diff) => [
                $diff['field'],
                is_array($diff['old_value']) ? json_encode($diff['old_value']) : (string) $diff['old_value'],
                is_array($diff['new_value']) ? json_encode($diff['new_value']) : (string) $diff['new_value'],
            ], $fieldDiffs)
        );

        // Step 6: Record audit log
        $this->step(6, 'Recording Audit Log (Append-Only)');

        $auditLog = IntegrityAuditLog::recordViolationFromMirror(
            mirror: $mirror,
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
            fieldDifferences: $fieldDiffs,
            chainSnapshot: $originalData,
            runId: 'demo-run-' . time(),
            source: 'demo',
        );

        $this->info("  ✓ Audit Log ID: {$auditLog->id}");
        $this->info("  ✓ Violation Type: {$auditLog->violation_type}");
        $this->info("  ✓ Severity: {$auditLog->severity}");
        $this->info("  ✓ Recovery Status: {$auditLog->recovery_status}");
        $this->info("  ✓ Revision Number: {$auditLog->revision_number}");
        $this->newLine();

        // Step 7: Mark mirror as breached
        $this->step(7, 'Updating Mirror Record with Breach Status');

        $mirror->markAsBreached(BreachTypeEnums::CONTENT_MISMATCH->value, [
            'detected_at' => now()->toIso8601String(),
            'field_differences' => $fieldDiffs,
        ]);

        $this->info("  ✓ Breach Detected At: {$mirror->breach_detected_at}");
        $this->info("  ✓ Breach Type: {$mirror->breach_type}");
        $this->info("  ✓ Is Breached: " . ($mirror->isBreached() ? 'YES' : 'NO'));
        $this->newLine();

        // Step 8: Show recovery options
        $this->step(8, 'Recovery Options');

        $this->info('  To restore from blockchain, run:');
        $this->info('  php artisan blockchain:repair --pr=' . $this->demoKey);
        $this->newLine();

        $this->info('  Or restore this demo record:');
        $this->info('  php artisan integrity:demo --restore');
        $this->newLine();

        // Summary
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                      DEMO COMPLETE                         ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('Summary:');
        $this->info('  • Database tampering was simulated');
        $this->info('  • Field-level differences were detected');
        $this->info('  • Audit log was created (append-only)');
        $this->info('  • Mirror record was marked as breached');
        $this->info('  • Blockchain remains the source of truth');
        $this->newLine();

        $this->info('Key Architecture Points:');
        $this->info('  1. Blockchain = Immutable Source of Truth');
        $this->info('  2. Database = Mutable Mirror/Cache');
        $this->info('  3. Integrity Service = Continuous Verification');
        $this->info('  4. Audit Log = Permanent Forensic Record');
        $this->newLine();

        return self::SUCCESS;
    }

    private function restoreDemoRecord(): int
    {
        $this->info('Restoring demo record...');

        $mirror = ProcurementMirror::where('stream_key', $this->demoKey)->first();

        if (! $mirror) {
            $this->error('Demo record not found.');

            return self::FAILURE;
        }

        $restoredData = [
            'pr_number' => $this->demoKey,
            'title' => 'Test Procurement for Integrity Demo',
            'amount' => 150000.00,
            'status' => 'pending',
            'category' => 'goods',
            'created_by' => 'admin@procuchain.tech',
            'blockchain_verified' => true,
        ];

        $mirror->update([
            'data_json' => $restoredData,
            'data_hash' => hash('sha256', json_encode($restoredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        $mirror->markAsRepaired();

        $this->info('✓ Demo record restored successfully.');
        $this->info('  Hash: ' . substr($mirror->data_hash, 0, 16) . '...');
        $this->info('  Repaired At: ' . $mirror->repaired_at);

        return self::SUCCESS;
    }

    private function step(int $number, string $title): void
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Step {$number}: {$title}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();
    }
}

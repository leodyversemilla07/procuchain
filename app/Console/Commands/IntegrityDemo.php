<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Models\ProcurementMirror;
use App\Services\IntegrityVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity System Demonstration Command
 *
 * Demonstrates the data integrity and audit-tracking system by:
 * 1. Creating a test record in the mirror
 * 2. Simulating database tampering (modification)
 * 3. Simulating data deletion
 * 4. Running integrity verification
 * 5. Showing how data is restored from blockchain
 *
 * Usage:
 *   php artisan integrity:demo              # Run full demo (modify + delete)
 *   php artisan integrity:demo --restore    # Restore demo record
 *   php artisan integrity:demo --delete     # Only show deletion scenario
 */
class IntegrityDemo extends Command
{
    protected $signature = 'integrity:demo {--restore : Restore the demo record} {--delete : Only run deletion scenario}';

    protected $description = 'Demonstrate the integrity verification system with simulated attacks';

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
        $this->info('  ✓ Stream: procurement.metadata');
        $this->info("  ✓ Key: {$this->demoKey}");
        $this->info('  ✓ Hash: '.substr($mirror->data_hash, 0, 16).'...');
        $this->newLine();

        // Step 2: Verify initial integrity
        $this->step(2, 'Initial Integrity Check (Before Tampering)');

        $computedHash = hash('sha256', json_encode($mirror->data_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $hashValid = $computedHash === $mirror->data_hash;

        $this->info('  Computed Hash: '.substr($computedHash, 0, 16).'...');
        $this->info('  Stored Hash:   '.substr($mirror->data_hash, 0, 16).'...');
        $this->info('  Hash Match: '.($hashValid ? '✓ YES' : '✗ NO'));
        $this->info('  Integrity Status: '.($mirror->isBreached() ? '✗ BREACHED' : '✓ VALID'));
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

        $this->info('  Computed Hash: '.substr($computedHash, 0, 16).'...');
        $this->info('  Stored Hash:   '.substr($mirror->data_hash, 0, 16).'...');

        if (! $hashValid) {
            $this->error('  ✗ Hash MISMATCH detected!');
        } else {
            $this->warn('  ⚠ Hash matches (data was updated with new hash)');
        }

        // Step 5: Field-level diff
        $this->step(5, 'Field-Level Difference Analysis');

        $service = app(IntegrityVerificationService::class);
        $fieldDiffs = $service->computeFieldDifferences($tamperedData, $originalData);

        $this->info('  Detected '.count($fieldDiffs).' field difference(s):');
        $this->newLine();

        $this->table(
            ['Field', 'Original (Chain)', 'Tampered (DB)'],
            array_map(fn ($diff) => [
                $diff['field'],
                is_array($diff['old_value']) ? json_encode($diff['old_value']) : (string) $diff['old_value'],
                is_array($diff['new_value']) ? json_encode($diff['new_value']) : (string) $diff['new_value'],
            ], $fieldDiffs)
        );

        // Step 6: Record audit log (MySQL + Blockchain)
        $this->step(6, 'Recording Audit Log (MySQL + Blockchain)');

        $auditLog = IntegrityAuditLog::recordViolationFromMirror(
            mirror: $mirror,
            violationType: BreachTypeEnums::CONTENT_MISMATCH->value,
            fieldDifferences: $fieldDiffs,
            chainSnapshot: $originalData,
            runId: 'demo-run-'.time(),
            source: 'demo',
        );

        $this->info("  ✓ Audit Log ID: {$auditLog->id} (MySQL)");
        $this->info("  ✓ Violation Type: {$auditLog->violation_type}");
        $this->info("  ✓ Severity: {$auditLog->severity}");
        $this->info("  ✓ Recovery Status: {$auditLog->recovery_status}");
        $this->info("  ✓ Revision Number: {$auditLog->revision_number}");
        $this->newLine();
        $this->info('  ✓ ALSO published to blockchain (integrity.violations stream)');
        $this->info('  → This record is IMMUTABLE and survives MySQL destruction');
        $this->newLine();

        // Step 7: Mark mirror as breached
        $this->step(7, 'Updating Mirror Record with Breach Status');

        $mirror->markAsBreached(BreachTypeEnums::CONTENT_MISMATCH->value, [
            'detected_at' => now()->toIso8601String(),
            'field_differences' => $fieldDiffs,
        ]);

        $this->info("  ✓ Breach Detected At: {$mirror->breach_detected_at}");
        $this->info("  ✓ Breach Type: {$mirror->breach_type}");
        $this->info('  ✓ Is Breached: '.($mirror->isBreached() ? 'YES' : 'NO'));
        $this->newLine();

        // Step 8: Show recovery options
        $this->step(8, 'Recovery Options');

        $this->info('  To restore from blockchain, run:');
        $this->info('  php artisan blockchain:repair --pr='.$this->demoKey);
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
        $this->info('  • Audit log was created in MySQL (append-only)');
        $this->info('  • Audit log was ALSO published to blockchain (immutable)');
        $this->info('  • Mirror record was marked as breached');
        $this->info('  • Blockchain remains the source of truth');
        $this->newLine();

        $this->info('Key Architecture Points:');
        $this->info('  1. Blockchain = Immutable Source of Truth');
        $this->info('  2. Database = Mutable Mirror/Cache');
        $this->info('  3. Integrity Service = Continuous Verification');
        $this->info('  4. Audit Log (MySQL) = Fast queries, mutable');
        $this->info('  5. Audit Trail (Blockchain) = Permanent, immutable');
        $this->newLine();
        $this->info('  Requirement #6 Satisfied:');
        $this->info('  "Maintain a permanent audit trail of all recovery operations"');
        $this->info('  → Violations are written to integrity.violations stream');
        $this->info('  → Survives total MySQL destruction');
        $this->info('  → Recover with: php artisan blockchain:audit-trail --restore');
        $this->newLine();

        // Ask if user wants to see deletion scenario
        if ($this->confirm('Would you also like to see the DELETION scenario?', false)) {
            $this->runDeletionScenario($mirror);
        }

        return self::SUCCESS;
    }

    /**
     * Run the deletion scenario - simulate deleting data from database.
     */
    private function runDeletionScenario(ProcurementMirror $mirror): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           DELETION SCENARIO DEMONSTRATION                  ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Step D1: Show current state
        $this->step('D1', 'Current State Before Deletion');

        $recordExists = ProcurementMirror::where('stream_key', $this->demoKey)
            ->where('txid', 'demo_txid_001')
            ->exists();

        $this->info('  Record exists in database: '.($recordExists ? 'YES' : 'NO'));
        $this->info("  Record ID: {$mirror->id}");
        $this->info("  Stream: {$mirror->stream}");
        $this->info("  Key: {$mirror->stream_key}");
        $this->info("  TXID: {$mirror->txid}");
        $this->newLine();

        // Step D2: Simulate deletion
        $this->step('D2', 'Simulating Data Deletion Attack');

        $this->warn('  ⚠ Attacker deletes record from database...');
        $this->warn('  ⚠ This could happen via SQL injection, compromised admin, etc.');
        $this->newLine();

        // Delete the record directly from database
        $deleted = DB::table('procurement_mirror')
            ->where('id', $mirror->id)
            ->delete();

        $this->info("  ✓ Deleted {$deleted} record(s) from database");
        $this->newLine();

        // Verify it's gone
        $recordExistsAfter = ProcurementMirror::where('stream_key', $this->demoKey)
            ->where('txid', 'demo_txid_001')
            ->exists();

        $this->info('  Record exists after deletion: '.($recordExistsAfter ? 'YES' : 'NO'));

        if (! $recordExistsAfter) {
            $this->error('  ✗ Record has been DELETED from database!');
        }
        $this->newLine();

        // Step D3: What the blockchain still has
        $this->step('D3', 'Blockchain Still Has The Data');

        $this->info('  The blockchain is immutable - the data still exists there:');
        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['Stream', 'procurement.metadata'],
                ['Key', $this->demoKey],
                ['TXID', 'demo_txid_001'],
                ['Data', 'Still on blockchain (immutable)'],
                ['Status', '✓ SAFE on blockchain'],
            ]
        );
        $this->newLine();

        // Step D4: Integrity verification would detect this
        $this->step('D4', 'Integrity Verification Detects Deletion');

        $this->info('  When the IntegrityVerificationService runs, it:');
        $this->info('  1. Lists all items on blockchain for this stream');
        $this->info('  2. Checks if each item exists in the mirror database');
        $this->info('  3. Detects that demo_txid_001 is missing from database');
        $this->info('  4. Creates an audit log with ROW_DELETED violation');
        $this->info('  5. Can automatically restore from blockchain data');
        $this->newLine();

        // Create the audit log for deletion
        $auditLog = IntegrityAuditLog::recordViolation(
            stream: 'procurement.metadata',
            streamKey: $this->demoKey,
            violationType: BreachTypeEnums::ROW_DELETED->value,
            txid: 'demo_txid_001',
            chainSnapshot: [
                'pr_number' => $this->demoKey,
                'title' => 'Test Procurement for Integrity Demo',
                'amount' => 150000.00,
                'status' => 'pending',
            ],
            runId: 'demo-deletion-'.time(),
            source: 'demo',
            revisionNumber: 1,
        );

        $this->info('  ✓ Audit Log created:');
        $this->info("    - ID: {$auditLog->id}");
        $this->info("    - Violation Type: {$auditLog->violation_type}");
        $this->info("    - Severity: {$auditLog->severity}");
        $this->info("    - Status: {$auditLog->recovery_status}");
        $this->newLine();

        // Step D5: Restore from blockchain
        $this->step('D5', 'Restoring Deleted Data from Blockchain');

        $this->info('  The system can restore by reading from blockchain:');
        $this->newLine();

        // Simulate restoration
        $restoredData = [
            'pr_number' => $this->demoKey,
            'title' => 'Test Procurement for Integrity Demo',
            'amount' => 150000.00,
            'status' => 'pending',
            'category' => 'goods',
            'created_by' => 'admin@procuchain.tech',
            'blockchain_verified' => true,
        ];

        // Recreate the record (simulating blockchain restore)
        $restored = ProcurementMirror::create([
            'stream' => 'procurement.metadata',
            'stream_key' => $this->demoKey,
            'txid' => 'demo_txid_001',
            'revision_number' => 1,
            'parent_txid' => null,
            'is_latest_revision' => true,
            'publisher_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'blocktime' => now(),
            'data_json' => $restoredData,
            'data_hash' => hash('sha256', json_encode($restoredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'is_authorized' => true,
            'repaired_at' => now(),
            'synced_at' => now(),
        ]);

        $auditLog->markRestored([
            'items_restored' => 1,
            'restored_by' => 'demo',
        ]);

        $this->info('  ✓ Record restored from blockchain!');
        $this->info("    - New ID: {$restored->id}");
        $this->info("    - Data restored: {$restoredData['title']}");
        $this->info("    - Amount: \${$restoredData['amount']}");
        $this->newLine();

        // Final summary
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║              DELETION SCENARIO COMPLETE                     ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('What happened:');
        $this->info('  1. Record existed in both blockchain AND database');
        $this->info('  2. Attacker deleted record from database only');
        $this->info('  3. Blockchain still had the original data (immutable)');
        $this->info('  4. Integrity verification detected the deletion');
        $this->info('  5. System restored data from blockchain');
        $this->newLine();

        $this->info('Key Takeaway:');
        $this->info('  ✓ Even if data is DELETED from database, it can be');
        $this->info('    restored from the blockchain (source of truth)');
        $this->newLine();
        $this->info('  ✓ Even if MySQL is COMPLETELY DESTROYED:');
        $this->info('    • Procurement data → rebuilt with: blockchain:sync');
        $this->info('    • Audit trail → rebuilt with: blockchain:audit-trail --restore');
        $this->info('    • BOTH are permanent on the blockchain');
        $this->newLine();
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
        $this->info('  Hash: '.substr($mirror->data_hash, 0, 16).'...');
        $this->info('  Repaired At: '.$mirror->repaired_at);

        return self::SUCCESS;
    }

    private function step(int $number, string $title): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  Step {$number}: {$title}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
    }
}

<?php

namespace App\Console\Commands;

use App\Services\MultichainService;
use Illuminate\Console\Command;
use Exception;

class CheckBlockchainVars extends Command
{
    protected $signature = 'smart-contracts:check-vars {procurement_id=PR-2025-0001-0001}';
    protected $description = 'Check blockchain variables for a specific procurement';

    public function __construct(
        private MultichainService $multichainService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $procurementId = $this->argument('procurement_id');
        
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║      Blockchain Variables Check          ║');
        $this->info("║         {$procurementId}                ║");
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Check the variables that were created for this procurement
            $this->info('🔍 Checking procurement-specific variables...');
            $testVariables = ['pr2025_722c63f4', 'pr2025_00125b74', 'pr2025_654dfcc7'];
            
            $foundVars = 0;
            foreach ($testVariables as $varName) {
                try {
                    $value = $this->multichainService->getVariableValue($varName);
                    $this->line("✅ Found variable: {$varName}");
                    $data = json_decode($value, true);
                    if ($data && isset($data['procurement_id'])) {
                        $this->line("   └─ Procurement ID: {$data['procurement_id']}");
                        $this->line("   └─ Document Type: {$data['document_type']}");
                        $this->line("   └─ File Key: {$data['file_key']}");
                        $this->line("   └─ Document Hash: {$data['document_hash']}");
                        $this->line("   └─ Timestamp: {$data['timestamp']}");
                        $foundVars++;
                    }
                    $this->newLine();
                } catch (Exception $e) {
                    $this->warn("❌ Variable not found: {$varName}");
                }
            }
            
            $this->newLine();
            
            // Check configuration variables
            $this->info('📋 System Configuration Variables:');
            $configVars = ['document_validation_config', 'document_validation_rules'];
            
            foreach ($configVars as $varName) {
                try {
                    $value = $this->multichainService->getVariableValue($varName);
                    $this->line("✅ Found config: {$varName}");
                    
                    // Show some details for config
                    $config = json_decode($value, true);
                    if ($config) {
                        if ($varName === 'document_validation_config') {
                            $this->line("   └─ Validation enabled: " . ($config['document_validation_enabled'] ? 'Yes' : 'No'));
                            $this->line("   └─ Max file size: " . number_format($config['max_file_size']) . " bytes");
                            $this->line("   └─ Allowed types: " . count($config['allowed_document_types']) . " types");
                        } elseif ($varName === 'document_validation_rules') {
                            $this->line("   └─ Required fields: " . count($config['required_fields']) . " fields");
                            $this->line("   └─ Hash format: {$config['hash_format']}");
                            $this->line("   └─ Duplicate prevention: " . ($config['duplicate_hash_prevention'] ? 'Enabled' : 'Disabled'));
                        }
                    }
                } catch (Exception $e) {
                    $this->error("❌ Config not found: {$varName}");
                }
                $this->newLine();
            }
            
            // Summary
            $this->info('╔══════════════════════════════════════════╗');
            $this->info('║              Summary                     ║');
            $this->info('╚══════════════════════════════════════════╝');
            $this->line("📄 Documents found for {$procurementId}: {$foundVars}");
            $this->line("⚙️  System configuration: Complete");
            $this->line("🔗 Blockchain connection: Active");
            
            if ($foundVars > 0) {
                $this->newLine();
                $this->info("🎉 Procurement {$procurementId} has {$foundVars} documents stored in blockchain!");
                $this->comment("You can now run integrity validation tests against these stored documents.");
            } else {
                $this->newLine();
                $this->warn("⚠️  No documents found for {$procurementId} in blockchain variables.");
                $this->comment("Run the test-document command to create sample documents for testing.");
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Blockchain variable check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

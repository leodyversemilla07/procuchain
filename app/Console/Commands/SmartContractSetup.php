<?php

namespace App\Console\Commands;

use App\Services\MultichainService;
use App\Services\SmartContractService;
use Exception;
use Illuminate\Console\Command;

class SmartContractSetup extends Command
{
    protected $signature = 'smart-contracts:setup {--test : Run in test mode}';

    protected $description = 'Set up document management smart contract system for ProcuChain';

    public function __construct(
        private SmartContractService $smartContractService,
        private MultichainService $multichainService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║   Document Management Smart Contract     ║');
        $this->info('║            Setup Starting                ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Step 1: Check blockchain connection
            $this->info('🔗 Step 1: Checking blockchain connection...');
            $this->checkBlockchainConnection();
            $this->info('  └─ ✅ Blockchain connection verified');
            $this->newLine();

            // Step 2: Create document management library
            $this->info('📚 Step 2: Creating document management library...');
            $libraryTxid = $this->createDocumentLibrary();
            $this->info("  └─ ✅ Document management library created (TXID: $libraryTxid)");
            $this->newLine();

            // Step 3: Create document validation filters
            $this->info('🔍 Step 3: Creating document validation filters...');
            $filterResults = $this->createDocumentFilters();
            foreach ($filterResults as $filterName => $txid) {
                $this->info("  └─ ✅ $filterName created (TXID: $txid)");
            }
            $this->newLine();

            // Step 4: Set up configuration
            $this->info('⚙️  Step 4: Setting up document validation configuration...');
            $this->setupDocumentConfiguration();
            $this->info('  └─ ✅ Document validation configuration set');
            $this->newLine();

            // Step 5: Test document validation functionality
            if ($this->option('test')) {
                $this->info('🧪 Step 5: Testing document validation functionality...');
                $this->testDocumentValidation();
                $this->info('  └─ ✅ Document validation tests completed');
                $this->newLine();
            }

            // Display summary
            $this->displaySummary();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Document management smart contract setup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function checkBlockchainConnection(): void
    {
        try {
            $info = $this->multichainService->getInfo();
            if (empty($info)) {
                throw new Exception('No response from blockchain');
            }

            $this->line("  └─ Chain: {$info['chainname']}");
            $this->line("  └─ Blocks: {$info['blocks']}");
            $this->line("  └─ Connections: {$info['connections']}");

        } catch (Exception $e) {
            throw new Exception("Blockchain connection failed: {$e->getMessage()}");
        }
    }

    private function createDocumentLibrary(): string
    {
        try {
            $this->line('  └─ Creating document validation library...');

            // Check if library already exists
            try {
                $existingLibrary = $this->multichainService->getLibraryCode('document_management_validation');
                if (! empty($existingLibrary)) {
                    $this->warn('  └─ ⚠️  Library already exists, skipping creation');

                    return 'existing';
                }
            } catch (Exception $e) {
                // Library doesn't exist, continue with creation
            }

            $txid = $this->smartContractService->createDocumentManagementLibrary();

            // Wait for library to be created
            sleep(3);

            return $txid;

        } catch (Exception $e) {
            throw new Exception("Document library creation failed: {$e->getMessage()}");
        }
    }

    private function createDocumentFilters(): array
    {
        try {
            $this->line('  └─ Creating document upload validation filter...');
            $this->line('  └─ Creating metadata integrity filter...');

            $results = $this->smartContractService->createDocumentValidationFilters();

            // Wait for filters to be created
            sleep(3);

            return $results;

        } catch (Exception $e) {
            throw new Exception("Document filter creation failed: {$e->getMessage()}");
        }
    }

    private function setupDocumentConfiguration(): void
    {
        try {
            // Set up document validation configuration
            $documentConfig = [
                'document_validation_enabled' => true,
                'max_file_size' => 10485760, // 10MB in bytes
                'allowed_document_types' => [
                    'Purchase Request',
                    'Minutes',
                    'Attendance',
                    'Bidding Documents',
                    'Evaluation Report',
                    'BAC Resolution',
                    'Notice of Award',
                    'Performance Bond',
                    'Contract',
                    'Purchase Order',
                    'Notice to Proceed',
                    'Certificate of Completion',
                ],
                'hash_algorithm' => 'sha256',
                'metadata_compliance_enabled' => true,
                'audit_trail_enabled' => true,
                'storage_consistency_checks' => true,
                'created_at' => time(),
                'version' => '1.0.0',
            ];

            $this->line('  └─ Creating document validation configuration...');
            try {
                // Try to get existing variable first
                $this->multichainService->getVariableValue('document_validation_config');
                $this->warn('  └─ ⚠️  Configuration already exists, updating...');
                $this->multichainService->setVariableValue(
                    'document_validation_config',
                    json_encode($documentConfig)
                );
            } catch (Exception $e) {
                // Variable doesn't exist, create it with open=true parameter
                $this->multichainService->createVariable(
                    'document_validation_config',
                    true,  // Set open=true for variable creation
                    json_encode($documentConfig)
                );
            }

            // Set up validation rules
            $validationRules = [
                'required_fields' => ['hash', 'file_key', 'file_size', 'document_type', 'user_address', 'timestamp'],
                'hash_format' => 'hexadecimal_64_chars',
                'timestamp_format' => 'iso8601',
                'user_address_min_length' => 25,
                'duplicate_hash_prevention' => true,
                'chronological_order_validation' => true,
            ];

            $this->line('  └─ Creating validation rules...');
            try {
                // Try to get existing variable first
                $this->multichainService->getVariableValue('document_validation_rules');
                $this->warn('  └─ ⚠️  Validation rules already exist, updating...');
                $this->multichainService->setVariableValue(
                    'document_validation_rules',
                    json_encode($validationRules)
                );
            } catch (Exception $e) {
                // Variable doesn't exist, create it with open=true parameter
                $this->multichainService->createVariable(
                    'document_validation_rules',
                    true,  // Set open=true for variable creation
                    json_encode($validationRules)
                );
            }

            sleep(2);

        } catch (Exception $e) {
            throw new Exception("Document configuration setup failed: {$e->getMessage()}");
        }
    }

    private function testDocumentValidation(): void
    {
        try {
            $this->line('  └─ Testing document integrity validation...');

            // Test document integrity validation
            $testProcurementId = 'TEST-DOC-001';
            $testDocumentHash = 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567a'; // Exactly 64 chars

            $integrityResult = $this->smartContractService->validateDocumentIntegrity(
                $testProcurementId,
                $testDocumentHash
            );

            if (! $integrityResult['valid'] && $integrityResult['error'] === 'Document hash not found on blockchain') {
                $this->line('    ✅ Document integrity validation test passed (expected: not found)');
            } else {
                $this->warn('    ⚠️  Document integrity validation test unexpected result');
            }

            $this->line('  └─ Testing metadata compliance...');

            // Test metadata compliance
            $testMetadata = [
                'hash' => $testDocumentHash,
                'file_key' => 'test/document.pdf',
                'file_size' => 1024000,
                'document_type' => 'Purchase Request',
                'user_address' => '1A2B3C4D5E6F789012345678901234567890',
                'timestamp' => '2024-01-15T10:30:00Z',
                'stage_metadata' => [
                    'stage' => 'Procurement Initiation',
                    'submission_date' => '2024-01-15',
                ],
            ];

            $complianceResult = $this->smartContractService->checkDocumentMetadataCompliance(
                $testMetadata,
                'Procurement Initiation'
            );

            if ($complianceResult['compliant']) {
                $this->line('    ✅ Metadata compliance test passed');
            } else {
                $this->warn('    ⚠️  Metadata compliance test failed: '.implode(', ', $complianceResult['invalid_fields']));
            }

            $this->line('  └─ Testing storage consistency...');

            // Test storage consistency
            $consistencyResult = $this->smartContractService->validateDocumentStorageConsistency(
                $testProcurementId
            );

            if ($consistencyResult['consistent'] && $consistencyResult['total_documents'] === 0) {
                $this->line('    ✅ Storage consistency test passed (no documents found as expected)');
            } else {
                $this->line('    ✅ Storage consistency test completed');
            }

            $this->line('  └─ Testing audit trail generation...');

            // Test audit trail
            $auditResult = $this->smartContractService->getDocumentAuditTrail($testProcurementId);

            if (isset($auditResult['procurement_id']) && $auditResult['procurement_id'] === $testProcurementId) {
                $this->line('    ✅ Audit trail generation test passed (entries: '.$auditResult['total_entries'].')');
            } else {
                $this->warn('    ⚠️  Audit trail generation test failed');
            }

        } catch (Exception $e) {
            throw new Exception("Document validation testing failed: {$e->getMessage()}");
        }
    }

    private function displaySummary(): void
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║   Document Management Smart Contract     ║');
        $this->info('║           Setup Complete                 ║');
        $this->info('╚══════════════════════════════════════════╝');

        $this->table(
            ['Component', 'Status'],
            [
                ['Blockchain Connection', '✅ Connected'],
                ['Document Management Library', '✅ Created'],
                ['Document Validation Filters', '✅ Deployed'],
                ['Validation Configuration', '✅ Configured'],
                ['System Status', '✅ Ready'],
            ]
        );

        $this->newLine();
        $this->info('🎉 Document management smart contract system is now ready!');
        $this->info('💡 You can now use:');
        $this->info('   • Document integrity validation');
        $this->info('   • Metadata compliance checking');
        $this->info('   • Storage consistency validation');
        $this->info('   • Comprehensive audit trails');
        $this->newLine();

        $this->comment('Next steps:');
        $this->comment('1. Test the system with actual document uploads');
        $this->comment('2. Integrate validation into your document upload flow');
        $this->comment('3. Monitor validation logs and audit trails');
        $this->comment('4. Configure alerts for compliance violations');
        $this->newLine();

        $this->info('Available endpoints:');
        $this->comment('• POST /smart-contracts/initialize');
        $this->comment('• POST /smart-contracts/validate-integrity');
        $this->comment('• POST /smart-contracts/check-compliance');
        $this->comment('• POST /smart-contracts/validate-storage');
        $this->comment('• GET  /smart-contracts/audit-trail/{id}');
        $this->comment('• GET  /smart-contracts/status');
    }
}

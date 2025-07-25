<?php

namespace App\Console\Commands;

use App\Services\SmartContractService;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use App\Enums\StreamEnums;
use Illuminate\Console\Command;
use Exception;

class ValidateRealDocuments extends Command
{
    protected $signature = 'smart-contracts:validate-real {procurement_id=PR-2025-0001-0001} {--title=Office Supplies Procurement}';
    protected $description = 'Validate integrity of real documents stored in blockchain streams';

    public function __construct(
        private SmartContractService $smartContractService,
        private MultichainService $multichainService,
        private StreamKeyService $streamKeyService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $procurementId = $this->argument('procurement_id');
        $procurementTitle = $this->option('title') ?? 'Office Supplies Procurement';
        
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     Real Document Integrity Validation  ║');
        $this->info("║         {$procurementId}                ║");
        $this->info("║         {$procurementTitle}              ║");
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Generate the stream key using the StreamKeyService
            $streamKey = $this->streamKeyService->generate($procurementId, $procurementTitle);
            $this->info("🔑 Generated stream key: {$streamKey}");
            $this->newLine();

            // Dynamically discover all documents for this procurement from blockchain streams
            $this->info('🔍 Discovering all documents for this procurement...');
            $realDocuments = $this->discoverBlockchainDocuments($procurementId, $streamKey);
            
            if (empty($realDocuments)) {
                $this->warn("No documents found for procurement {$procurementId} with stream key {$streamKey}");
                $this->comment("Trying fallback search methods...");
                
                // Fallback to variable-based search
                $realDocuments = $this->discoverBlockchainVariables($procurementId);
                
                if (empty($realDocuments)) {
                    $this->error("No documents found for procurement {$procurementId} in streams or variables");
                    return Command::FAILURE;
                } else {
                    $this->info("Found documents in blockchain variables instead");
                }
            }

            $this->info("🔍 Found " . count($realDocuments) . " documents in blockchain for {$procurementId}");
            $this->newLine();

            $validCount = 0;
            foreach ($realDocuments as $index => $document) {
                $this->info("📄 Validating Document #" . ($index + 1) . ":");
                $this->line("  └─ Type: {$document['type']}");
                $this->line("  └─ Hash: {$document['hash']}");
                $this->line("  └─ File: {$document['file_key']}");
                $this->line("  └─ Source: {$document['source']}");
                if (isset($document['txid'])) {
                    $this->line("  └─ TXID: {$document['txid']}");
                } elseif (isset($document['variable_name'])) {
                    $this->line("  └─ Variable: {$document['variable_name']}");
                }
                
                // Test document integrity validation
                $this->info('  🔐 Testing integrity validation...');
                $integrityResult = $this->smartContractService->validateDocumentIntegrity(
                    $procurementId,
                    $document['hash']
                );
                
                if ($integrityResult['valid']) {
                    $this->line('    ✅ Document integrity VALID - hash found in blockchain');
                    $this->line("    └─ Validation timestamp: {$integrityResult['validation_timestamp']}");
                    if (isset($integrityResult['blockchain_timestamp'])) {
                        $this->line("    └─ Stored timestamp: {$integrityResult['blockchain_timestamp']}");
                    }
                    $validCount++;
                } else {
                    $this->warn('    ❌ Document integrity INVALID');
                    $this->line("    └─ Error: {$integrityResult['error']}");
                }
                
                $this->newLine();
            }

            // Test storage consistency for the entire procurement
            $this->info('💾 Testing storage consistency for entire procurement...');
            $consistencyResult = $this->smartContractService->validateDocumentStorageConsistency($procurementId);
            
            $this->line("  └─ Total documents found: {$consistencyResult['total_documents']}");
            $this->line('  └─ Storage consistent: ' . ($consistencyResult['consistent'] ? '✅ Yes' : '❌ No'));
            
            if (!empty($consistencyResult['inconsistencies'])) {
                $this->warn('  └─ Inconsistencies found:');
                foreach ($consistencyResult['inconsistencies'] as $inconsistency) {
                    $this->line("    • {$inconsistency}");
                }
            }
            $this->newLine();

            // Test audit trail
            $this->info('📋 Testing audit trail generation...');
            $auditResult = $this->smartContractService->getDocumentAuditTrail($procurementId);
            
            $this->line("  └─ Procurement ID: {$auditResult['procurement_id']}");
            $this->line("  └─ Total audit entries: {$auditResult['total_entries']}");
            $this->line("  └─ Generated at: {$auditResult['generated_at']}");
            
            if (!empty($auditResult['audit_entries'])) {
                $this->line('  └─ Recent audit entries:');
                foreach (array_slice($auditResult['audit_entries'], 0, 5) as $entry) {
                    $this->line("    • {$entry['timestamp']}: {$entry['action']}");
                }
            }
            $this->newLine();

            // Final summary
            $this->info('╔══════════════════════════════════════════╗');
            $this->info('║           Validation Results             ║');
            $this->info('╚══════════════════════════════════════════╝');
            
            $totalDocs = count($realDocuments);
            $this->table(
                ['Metric', 'Result'],
                [
                    ['Procurement ID', $procurementId],
                    ['Documents Tested', $totalDocs],
                    ['Valid Documents', $validCount],
                    ['Invalid Documents', $totalDocs - $validCount],
                    ['Storage Consistency', $consistencyResult['consistent'] ? '✅ Consistent' : '❌ Inconsistent'],
                    ['Audit Trail Entries', $auditResult['total_entries']],
                    ['Validation Status', $validCount === $totalDocs ? '✅ ALL VALID' : '⚠️ SOME INVALID']
                ]
            );

            if ($validCount === $totalDocs) {
                $this->info('🎉 ALL DOCUMENTS VALIDATED SUCCESSFULLY!');
                $this->info("✅ Procurement {$procurementId} has {$validCount}/{$totalDocs} valid documents in blockchain");
                $this->comment('The smart contract system is working perfectly with real data!');
            } else {
                $this->warn("⚠️ {$validCount}/{$totalDocs} documents validated successfully");
                $this->comment('Some documents may need re-validation or re-storage');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Real document validation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Discover all documents for a procurement stored in blockchain streams
     * Uses the same approach as ViewProcurementsController
     */
    private function discoverBlockchainDocuments(string $procurementId, string $streamKey): array
    {
        $documents = [];
        
        try {
            $this->line('  └─ Searching blockchain streams using controller approach...');
            
            // Use the same approach as ViewProcurementsController
            $this->line("    └─ Fetching all documents from stream: " . StreamEnums::DOCUMENTS->value);
            
            // Fetch all document items from the stream (same as controller)
            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true, // Verbose
                10000, // Large page size like controller
                0, // Start from beginning
                false // Don't fetch local order
            );
            
            if ($allDocumentItems === null) {
                $this->warn("    └─ Failed to retrieve document stream items");
                return [];
            }
            
            $this->line("    └─ Found " . count($allDocumentItems) . " total documents in stream");
            
            // Filter documents by procurement_id in PHP (same as controller)
            $filteredItems = collect($allDocumentItems)
                ->filter(function ($item) use ($procurementId) {
                    // Check if the necessary keys exist before accessing them
                    return isset($item['data']['json']['procurement_id']) &&
                        $item['data']['json']['procurement_id'] === $procurementId;
                });
            
            $this->line("    └─ Found " . $filteredItems->count() . " documents for procurement {$procurementId}");
            
            // Map to our document format
            foreach ($filteredItems as $item) {
                $data = $item['data']['json'] ?? [];
                
                $documents[] = [
                    'hash' => $data['hash'] ?? 'unknown',
                    'type' => $data['document_type'] ?? 'Unknown',
                    'file_key' => $data['file_key'] ?? 'unknown',
                    'timestamp' => $data['timestamp'] ?? 'unknown',
                    'txid' => $item['txid'] ?? 'unknown',
                    'file_size' => $data['file_size'] ?? 0,
                    'user_address' => $data['user_address'] ?? 'unknown',
                    'stage' => $data['stage'] ?? 'unknown',
                    'stage_metadata' => $data['stage_metadata'] ?? null,
                    'procurement_title' => $data['procurement_title'] ?? 'unknown',
                    'source' => 'blockchain_stream'
                ];
                
                $this->line("    ✅ Found document: {$data['document_type']} - {$data['hash']}");
            }
            
        } catch (Exception $e) {
            $this->error("Error discovering documents in streams: {$e->getMessage()}");
        }
        
        // Sort documents by timestamp (newest first, like controller)
        usort($documents, function($a, $b) {
            $timeA = strtotime($a['timestamp']);
            $timeB = strtotime($b['timestamp']);
            return $timeB <=> $timeA; // Descending order
        });
        
        return $documents;
    }

    /**
     * Fallback: Discover documents stored in blockchain variables
     */
    private function discoverBlockchainVariables(string $procurementId): array
    {
        $documents = [];
        
        try {
            $this->line('  └─ Searching blockchain variables...');
            
            // Try to get all variables from the blockchain
            try {
                $allVariables = $this->multichainService->listVariables();
                $this->line("    └─ Found " . count($allVariables) . " total variables in blockchain");
                
                foreach ($allVariables as $variable) {
                    $varName = $variable['name'] ?? $variable;
                    
                    // Check if this variable might contain our procurement documents
                    if (str_contains($varName, 'pr2025_') || str_contains($varName, strtolower($procurementId))) {
                        try {
                            $value = $this->multichainService->getVariableValue($varName);
                            $data = json_decode($value, true);
                            
                            if ($data && isset($data['procurement_id']) && $data['procurement_id'] === $procurementId) {
                                $documents[] = [
                                    'hash' => $data['document_hash'] ?? $data['hash'] ?? 'unknown',
                                    'type' => $data['document_type'] ?? 'Unknown',
                                    'file_key' => $data['file_key'] ?? 'unknown',
                                    'timestamp' => $data['timestamp'] ?? 'unknown',
                                    'variable_name' => $varName,
                                    'file_size' => $data['file_size'] ?? 0,
                                    'user_address' => $data['user_address'] ?? 'unknown',
                                    'source' => 'blockchain_variable'
                                ];
                                $this->line("    ✅ Found document: {$data['document_type']} ({$varName})");
                            }
                        } catch (Exception $e) {
                            // Variable might not be JSON or accessible, continue
                        }
                    }
                }
            } catch (Exception $e) {
                $this->warn("  └─ Could not list all variables, trying known patterns...");
                
                // Fallback to known patterns
                $knownVariables = [
                    'pr2025_722c63f4', 'pr2025_00125b74', 'pr2025_654dfcc7'
                ];
                
                // Generate more potential variable names
                for ($i = 0; $i < 50; $i++) {
                    $hash = md5($procurementId . '_document_' . $i);
                    $knownVariables[] = 'pr2025_' . substr($hash, 0, 8);
                }
                
                foreach ($knownVariables as $varName) {
                    try {
                        $value = $this->multichainService->getVariableValue($varName);
                        $data = json_decode($value, true);
                        
                        if ($data && isset($data['procurement_id']) && $data['procurement_id'] === $procurementId) {
                            // Check if we already have this document
                            $exists = false;
                            foreach ($documents as $existingDoc) {
                                if ($existingDoc['hash'] === ($data['document_hash'] ?? $data['hash'])) {
                                    $exists = true;
                                    break;
                                }
                            }
                            
                            if (!$exists) {
                                $documents[] = [
                                    'hash' => $data['document_hash'] ?? $data['hash'] ?? 'unknown',
                                    'type' => $data['document_type'] ?? 'Unknown',
                                    'file_key' => $data['file_key'] ?? 'unknown',
                                    'timestamp' => $data['timestamp'] ?? 'unknown',
                                    'variable_name' => $varName,
                                    'file_size' => $data['file_size'] ?? 0,
                                    'user_address' => $data['user_address'] ?? 'unknown',
                                    'source' => 'blockchain_variable'
                                ];
                                $this->line("    ✅ Found document: {$data['document_type']} ({$varName})");
                            }
                        }
                    } catch (Exception $e) {
                        // Variable doesn't exist, continue
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->error("Error discovering documents in variables: {$e->getMessage()}");
        }
        
        // Sort documents by timestamp if available
        usort($documents, function($a, $b) {
            $timeA = strtotime($a['timestamp']);
            $timeB = strtotime($b['timestamp']);
            return $timeA <=> $timeB;
        });
        
        return $documents;
    }
}

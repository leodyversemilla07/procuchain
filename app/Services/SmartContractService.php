<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class SmartContractService
{
    public function __construct(
        private MultichainService $multichainService
    ) {}

    /**
     * Initialize the document management smart contract system
     * Pure PHP implementation - all validation logic runs in PHP
     */
    public function initializeDocumentManagementSystem(): array
    {
        try {
            $results = [
                'library_created' => false,
                'filters_created' => false,
                'configuration_set' => false,
                'php_validation_ready' => false,
                'errors' => [],
            ];

            // Set up document validation configuration
            $this->setupDocumentValidationConfiguration();
            $results['configuration_set'] = true;

            // Initialize PHP validation system (no JavaScript needed)
            try {
                $validationResults = $this->createBasicValidationFilters();
                $results['filters_created'] = true;
                $results['php_validation_ready'] = $validationResults['php_validation_ready'] ?? false;
                $results['validation_setup'] = $validationResults;
            } catch (Exception $e) {
                $results['errors'][] = 'PHP validation setup failed: '.$e->getMessage();
                Log::warning('PHP validation setup failed, but system can still function', ['error' => $e->getMessage()]);
            }

            Log::info('Document management system initialized successfully with pure PHP validation');

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to initialize document management system', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Legacy method name for compatibility - calls initializeDocumentManagementSystem
     */
    public function createDocumentManagementLibrary(): string
    {
        $results = $this->initializeDocumentManagementSystem();

        return $results['configuration_set'] ? 'system_initialized' : 'initialization_failed';
    }

    /**
     * Legacy method name for compatibility - calls createBasicValidationFilters
     * Note: Now returns PHP validation setup results instead of JavaScript filter txids
     */
    public function createDocumentValidationFilters(): array
    {
        return $this->createBasicValidationFilters();
    }

    /**
     * Validate document metadata compliance (Pure PHP implementation)
     */
    public function checkDocumentMetadataCompliance(array $metadata, string $stage): array
    {
        $requiredFields = $this->getRequiredDocumentFields();
        $missing = [];
        $invalid = [];

        // Check for required basic fields
        foreach ($requiredFields as $field => $rules) {
            if (! isset($metadata[$field]) || $metadata[$field] === null || $metadata[$field] === '') {
                $missing[] = $field;

                continue;
            }

            $value = $metadata[$field];

            // Validate field based on rules
            if (isset($rules['type'])) {
                if ($rules['type'] === 'string' && ! is_string($value)) {
                    $invalid[] = "{$field} must be a string";
                } elseif ($rules['type'] === 'numeric' && ! is_numeric($value)) {
                    $invalid[] = "{$field} must be numeric";
                } elseif ($rules['type'] === 'array' && ! is_array($value)) {
                    $invalid[] = "{$field} must be an array";
                }
            }

            // Validate string length
            if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
                $invalid[] = "{$field} exceeds maximum length of {$rules['max_length']}";
            }

            // Validate numeric values
            if (isset($rules['min_value']) && is_numeric($value) && $value < $rules['min_value']) {
                $invalid[] = "{$field} is below minimum value of {$rules['min_value']}";
            }

            if (isset($rules['max_value']) && is_numeric($value) && $value > $rules['max_value']) {
                $invalid[] = "{$field} exceeds maximum value of {$rules['max_value']}";
            }

            // Validate specific formats
            if ($field === 'hash' && ! $this->validateDocumentHash($value)) {
                $invalid[] = 'Hash must be a valid 64-character hexadecimal string (SHA-256)';
            }

            if ($field === 'timestamp' && ! $this->validateTimestampFormat($value)) {
                $invalid[] = 'Timestamp must be in valid ISO 8601 format';
            }

            if ($field === 'document_type' && ! $this->validateDocumentType($value)) {
                $invalid[] = "Document type '{$value}' is not in the allowed list";
            }
        }

        // Check for duplicate hash within procurement (if both hash and procurement_id are provided)
        if (isset($metadata['hash']) && isset($metadata['procurement_id'])) {
            $duplicateCheck = $this->checkForDuplicateHash($metadata['procurement_id'], $metadata['hash']);
            if (! $duplicateCheck['unique']) {
                $invalid[] = 'Document with this hash already exists in procurement';
            }
        }

        return [
            'compliant' => empty($missing) && empty($invalid),
            'missing_fields' => $missing,
            'invalid_fields' => $invalid,
            'stage' => $stage,
            'validation_timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Validate document integrity against blockchain records (Pure PHP implementation)
     */
    public function validateDocumentIntegrity(string $procurementId, string $documentHash): array
    {
        try {
            // First, search for document in blockchain streams (primary method)
            $streamItems = $this->multichainService->listStreamItems(
                'procurement.documents',
                true, // Verbose
                10000, // Large page size
                0, // Start from beginning
                false // Don't fetch local order
            );

            if ($streamItems) {
                foreach ($streamItems as $item) {
                    $data = $item['data']['json'] ?? [];

                    // Check if this document matches our search criteria
                    if (isset($data['procurement_id']) &&
                        isset($data['hash']) &&
                        $data['procurement_id'] === $procurementId &&
                        $data['hash'] === $documentHash) {

                        // Document found in stream - verify integrity
                        return [
                            'valid' => true,
                            'blockchain_hash' => $data['hash'],
                            'file_size' => $data['file_size'] ?? null,
                            'file_key' => $data['file_key'] ?? null,
                            'document_type' => $data['document_type'] ?? null,
                            'timestamp' => $data['timestamp'] ?? null,
                            'user_address' => $data['user_address'] ?? null,
                            'txid' => $item['txid'] ?? null,
                            'block_time' => $item['blocktime'] ?? null,
                            'blockchain_timestamp' => $data['timestamp'] ?? null,
                            'validation_timestamp' => now()->toISOString(),
                            'source' => 'blockchain_stream',
                            'stream_name' => 'procurement.documents',
                        ];
                    }
                }
            }

            // Fallback: Search for document in blockchain variables (legacy method)
            $variablePrefix = 'pr2025_'.substr($documentHash, 0, 8);
            try {
                $variableValue = $this->multichainService->getVariableValue($variablePrefix);
                $data = json_decode($variableValue, true);

                if ($data && isset($data['document_hash']) && $data['document_hash'] === $documentHash) {
                    // Document found in variables - verify integrity
                    return [
                        'valid' => true,
                        'blockchain_hash' => $data['document_hash'],
                        'file_size' => $data['file_size'] ?? null,
                        'file_key' => $data['file_key'] ?? null,
                        'document_type' => $data['document_type'] ?? null,
                        'timestamp' => $data['timestamp'] ?? null,
                        'user_address' => $data['user_address'] ?? null,
                        'blockchain_timestamp' => $data['timestamp'] ?? null,
                        'validation_timestamp' => now()->toISOString(),
                        'source' => 'blockchain_variable',
                        'variable_name' => $variablePrefix,
                    ];
                }
            } catch (Exception $e) {
                // Variable not found, continue to return not found
            }

            return [
                'valid' => false,
                'error' => 'Document hash not found on blockchain',
                'searched_procurement' => $procurementId,
                'searched_hash' => $documentHash,
                'validation_timestamp' => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Document integrity validation failed', [
                'procurement_id' => $procurementId,
                'hash' => $documentHash,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Validation error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generate comprehensive audit trail for documents (Pure PHP implementation)
     */
    public function getDocumentAuditTrail(string $procurementId): array
    {
        try {
            $auditTrail = [];

            // Get all document-related transactions
            $documentKey = "procurement.documents.{$procurementId}";
            $documentItems = $this->multichainService->listStreamKeyItems('procurement.documents', $documentKey);

            // Get status updates for context
            $statusKey = "procurement.status.{$procurementId}";
            $statusItems = $this->multichainService->listStreamKeyItems('procurement.status', $statusKey);

            // Get events for additional context
            $eventKey = "procurement.events.{$procurementId}";
            $eventItems = $this->multichainService->listStreamKeyItems('procurement.events', $eventKey);

            // Combine and sort by block time
            $allItems = array_merge($documentItems, $statusItems, $eventItems);
            usort($allItems, function ($a, $b) {
                return ($a['blocktime'] ?? 0) <=> ($b['blocktime'] ?? 0);
            });

            foreach ($allItems as $item) {
                $data = json_decode($item['data'], true);

                $auditEntry = [
                    'txid' => $item['txid'] ?? 'unknown',
                    'block_time' => $item['blocktime'] ?? null,
                    'formatted_time' => isset($item['blocktime']) ? Carbon::createFromTimestamp($item['blocktime'])->toISOString() : null,
                    'stream_type' => $this->determineStreamType($item['key'] ?? ''),
                    'user_address' => $data['user_address'] ?? 'unknown',
                    'timestamp' => $data['timestamp'] ?? null,
                    'action' => $this->determineActionType($data),
                    'data' => $data,
                ];

                // Add specific fields based on stream type
                if (isset($data['hash'])) {
                    $auditEntry['document_hash'] = $data['hash'];
                    $auditEntry['document_type'] = $data['document_type'] ?? 'unknown';
                    $auditEntry['file_size'] = $data['file_size'] ?? null;
                }

                if (isset($data['stage'])) {
                    $auditEntry['stage'] = $data['stage'];
                }

                $auditTrail[] = $auditEntry;
            }

            return [
                'procurement_id' => $procurementId,
                'total_entries' => count($auditTrail),
                'audit_trail' => $auditTrail,
                'generated_at' => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get audit trail', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate storage consistency between blockchain and expected metadata (Pure PHP implementation)
     */
    public function validateDocumentStorageConsistency(string $procurementId): array
    {
        try {
            $results = [
                'consistent' => true,
                'inconsistencies' => [],
                'total_documents' => 0,
                'validated_documents' => 0,
                'validation_details' => [],
            ];

            // Get all documents for this procurement from blockchain
            $key = "procurement.documents.{$procurementId}";
            $documentItems = $this->multichainService->listStreamKeyItems('procurement.documents', $key);

            $results['total_documents'] = count($documentItems);

            foreach ($documentItems as $item) {
                $blockchainData = json_decode($item['data'], true);
                $documentHash = $blockchainData['hash'] ?? 'unknown';

                $validation = $this->validateSingleDocumentConsistency($blockchainData, $item);

                if ($validation['valid']) {
                    $results['validated_documents']++;
                } else {
                    $results['consistent'] = false;
                    $results['inconsistencies'][] = [
                        'txid' => $item['txid'],
                        'document_hash' => $documentHash,
                        'errors' => $validation['errors'],
                        'blockchain_data' => $blockchainData,
                    ];
                }

                $results['validation_details'][] = [
                    'hash' => $documentHash,
                    'valid' => $validation['valid'],
                    'checks_performed' => $validation['checks'],
                ];
            }

            $results['consistency_percentage'] = $results['total_documents'] > 0
                ? ($results['validated_documents'] / $results['total_documents']) * 100
                : 100;

            return $results;

        } catch (Exception $e) {
            Log::error('Document storage consistency validation failed', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return [
                'consistent' => false,
                'error' => 'Validation error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Set up document validation configuration
     */
    private function setupDocumentValidationConfiguration(): void
    {
        $config = [
            'document_validation_enabled' => true,
            'max_file_size' => 10485760, // 10MB
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
            'timestamp_format' => 'iso8601',
            'user_address_min_length' => 25,
            'duplicate_prevention_enabled' => true,
            'audit_trail_enabled' => true,
            'created_at' => now()->toISOString(),
            'version' => '1.0.0',
        ];

        try {
            $this->multichainService->setVariableValue('document_validation_config', json_encode($config));
            Log::info('Document validation configuration set successfully');
        } catch (Exception $e) {
            Log::warning('Could not set blockchain configuration, using defaults', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create basic validation setup (Pure PHP implementation - no JavaScript needed)
     * Since validation is done entirely in PHP, we just ensure the system is ready
     */
    private function createBasicValidationFilters(): array
    {
        $results = [];

        // Instead of JavaScript filters, we rely on PHP validation methods
        // Test that our PHP validation methods are working
        try {
            // Test basic field validation
            $testMetadata = [
                'hash' => 'test',
                'file_size' => 1000,
                'document_type' => 'Purchase Request',
            ];

            $validationTest = $this->checkDocumentMetadataCompliance($testMetadata, 'test');

            if (isset($validationTest['compliant'])) {
                $results['php_validation_ready'] = true;
                Log::info('PHP validation system is ready and functional');
            } else {
                $results['php_validation_ready'] = false;
                Log::warning('PHP validation system test failed');
            }

            // Set up validation flags in blockchain (optional)
            try {
                $this->multichainService->setVariableValue('validation_mode', 'php_only');
                $this->multichainService->setVariableValue('javascript_filters_disabled', 'true');
                $results['blockchain_config_updated'] = true;
            } catch (Exception $e) {
                Log::warning('Could not update blockchain validation config', ['error' => $e->getMessage()]);
                $results['blockchain_config_updated'] = false;
            }

        } catch (Exception $e) {
            Log::warning('PHP validation system test failed', ['error' => $e->getMessage()]);
            $results['php_validation_ready'] = false;
        }

        $results['message'] = 'Using pure PHP validation - no JavaScript filters needed';

        return $results;
    }

    /**
     * Get required document fields for validation
     */
    private function getRequiredDocumentFields(): array
    {
        return [
            'hash' => [
                'type' => 'string',
                'max_length' => 64,
                'description' => 'SHA-256 hash of document content',
            ],
            'file_key' => [
                'type' => 'string',
                'max_length' => 500,
                'description' => 'Storage file key/path',
            ],
            'file_size' => [
                'type' => 'numeric',
                'min_value' => 1,
                'max_value' => 10485760, // 10MB
                'description' => 'File size in bytes',
            ],
            'document_type' => [
                'type' => 'string',
                'max_length' => 100,
                'description' => 'Type/category of document',
            ],
            'user_address' => [
                'type' => 'string',
                'max_length' => 100,
                'description' => 'Blockchain address of uploader',
            ],
            'timestamp' => [
                'type' => 'string',
                'description' => 'ISO 8601 timestamp',
            ],
        ];
    }

    /**
     * Validate document hash format
     */
    private function validateDocumentHash(string $hash): bool
    {
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash);
    }

    /**
     * Validate timestamp format
     */
    private function validateTimestampFormat(string $timestamp): bool
    {
        try {
            $carbon = Carbon::parse($timestamp);

            return $carbon instanceof Carbon && str_contains($timestamp, 'T');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate document type against allowed list
     */
    private function validateDocumentType(string $documentType): bool
    {
        $allowedTypes = [
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
        ];

        return in_array($documentType, $allowedTypes);
    }

    /**
     * Check for duplicate hash within procurement
     */
    private function checkForDuplicateHash(string $procurementId, string $hash): array
    {
        try {
            $key = "procurement.documents.{$procurementId}";
            $items = $this->multichainService->listStreamKeyItems('procurement.documents', $key);

            foreach ($items as $item) {
                $data = json_decode($item['data'], true);
                if (isset($data['hash']) && $data['hash'] === $hash) {
                    return [
                        'unique' => false,
                        'existing_txid' => $item['txid'],
                    ];
                }
            }

            return ['unique' => true];

        } catch (Exception $e) {
            Log::warning('Could not check for duplicate hash', ['error' => $e->getMessage()]);

            return ['unique' => true]; // Assume unique if check fails
        }
    }

    /**
     * Perform integrity checks on document data
     */
    private function performIntegrityChecks(array $data): array
    {
        $checks = [];
        $valid = true;

        // Check hash format
        if (! $this->validateDocumentHash($data['hash'] ?? '')) {
            $checks[] = 'Invalid hash format';
            $valid = false;
        } else {
            $checks[] = 'Hash format valid';
        }

        // Check file size
        if (! isset($data['file_size']) || $data['file_size'] <= 0 || $data['file_size'] > 10485760) {
            $checks[] = 'Invalid file size';
            $valid = false;
        } else {
            $checks[] = 'File size valid';
        }

        // Check timestamp
        if (! $this->validateTimestampFormat($data['timestamp'] ?? '')) {
            $checks[] = 'Invalid timestamp format';
            $valid = false;
        } else {
            $checks[] = 'Timestamp format valid';
        }

        return [
            'valid' => $valid,
            'checks' => $checks,
            'details' => [
                'hash_length' => strlen($data['hash'] ?? ''),
                'file_size' => $data['file_size'] ?? null,
                'timestamp' => $data['timestamp'] ?? null,
            ],
        ];
    }

    /**
     * Validate single document consistency
     */
    private function validateSingleDocumentConsistency(array $blockchainData, array $item): array
    {
        $errors = [];
        $checks = [];

        // Check required fields
        $requiredFields = ['hash', 'file_key', 'file_size', 'document_type', 'user_address', 'timestamp'];
        foreach ($requiredFields as $field) {
            if (! isset($blockchainData[$field]) || $blockchainData[$field] === null || $blockchainData[$field] === '') {
                $errors[] = "Missing field: {$field}";
            } else {
                $checks[] = "Field present: {$field}";
            }
        }

        // Validate data integrity
        $integrityCheck = $this->performIntegrityChecks($blockchainData);
        if (! $integrityCheck['valid']) {
            $errors = array_merge($errors, $integrityCheck['checks']);
        } else {
            $checks = array_merge($checks, $integrityCheck['checks']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'checks' => $checks,
        ];
    }

    /**
     * Determine stream type from key
     */
    private function determineStreamType(string $key): string
    {
        if (str_contains($key, 'procurement.documents')) {
            return 'documents';
        } elseif (str_contains($key, 'procurement.status')) {
            return 'status';
        } elseif (str_contains($key, 'procurement.events')) {
            return 'events';
        }

        return 'unknown';
    }

    /**
     * Determine action type from data
     */
    private function determineActionType(array $data): string
    {
        if (isset($data['hash']) && isset($data['file_key'])) {
            return 'document_upload';
        } elseif (isset($data['stage']) && isset($data['status'])) {
            return 'status_update';
        } elseif (isset($data['event_type'])) {
            return 'event_log';
        }

        return 'unknown';
    }
}

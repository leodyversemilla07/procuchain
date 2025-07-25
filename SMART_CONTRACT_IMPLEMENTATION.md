# ProcuChain Smart Contract Implementation Guide

## Overview

The ProcuChain smart contract implementation is specifically designed for **document management only** - focusing on document validation, integrity checking, audit trail management, and compliance verification. This is NOT a procurement workflow automation system.

**🔥 IMPORTANT: This implementation is now 100% PHP-based with NO JavaScript dependencies for validation logic.**

## What the Smart Contract Does

### 1. Document Validation (Pure PHP Implementation)
- **Hash Validation**: Ensures all documents have valid SHA-256 hashes using PHP validation
- **File Size Validation**: Enforces 10MB maximum file size limit through PHP checks
- **Metadata Validation**: Validates required document metadata fields using PHP logic
- **Storage Consistency**: Verifies blockchain data matches storage metadata with PHP algorithms

### 2. Document Integrity & Security (PHP-Powered)
- **Duplicate Detection**: Prevents duplicate document hashes within a procurement using PHP database checks
- **Audit Trail Validation**: Ensures chronological order and data integrity through PHP sorting and validation
- **Timestamp Validation**: Validates ISO 8601 timestamp formats using Carbon library in PHP
- **User Address Validation**: Verifies blockchain address formats using PHP regex

### 3. Compliance Reporting (PHP Generated)
- **Document Compliance Reports**: Generates reports on document validation status using PHP
- **Storage Consistency Checks**: Validates blockchain vs storage consistency through PHP algorithms
- **Audit Trail Generation**: Creates comprehensive audit trails for documents using PHP data processing

## API Endpoints

Your updated SmartContractController now provides these document management endpoints:

### 1. Initialize Smart Contract System (Pure PHP)
```
POST /api/smart-contract/initialize
```
Initializes the document management system with pure PHP validation - no JavaScript dependencies.

**Response:**
```json
{
    "success": true,
    "library_created": false,
    "filters_created": true,
    "configuration_set": true,
    "php_validation_ready": true,
    "validation_setup": {
        "message": "Using pure PHP validation - no JavaScript filters needed",
        "php_validation_ready": true,
        "blockchain_config_updated": true
    }
}
```

### 2. Validate Document Integrity (Pure PHP Logic)
```
POST /api/smart-contract/validate-integrity
Content-Type: application/json

{
    "procurement_id": "PR-2024-001",
    "document_hash": "a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890"
}
```

**Response:**
```json
{
    "valid": true,
    "blockchain_hash": "a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890",
    "file_size": 1024000,
    "file_key": "procurement/2024/doc.pdf",
    "document_type": "Purchase Request",
    "timestamp": "2024-01-15T10:30:00Z",
    "user_address": "1A2B3C4D5E6F...",
    "txid": "abc123...",
    "block_time": 1705315800,
    "integrity_details": {
        "hash_length": 64,
        "file_size": 1024000,
        "timestamp": "2024-01-15T10:30:00Z"
    }
}
```

### 3. Check Metadata Compliance (PHP Validation Engine)
```
POST /api/smart-contract/check-compliance
Content-Type: application/json

{
    "metadata": {
        "hash": "a1b2c3d4e5f6...",
        "file_key": "procurement/2024/doc.pdf",
        "file_size": 1024000,
        "document_type": "Purchase Request",
        "user_address": "1A2B3C4D5E6F...",
        "timestamp": "2024-01-15T10:30:00Z"
    },
    "stage": "Procurement Initiation"
}
```

**Response:**
```json
{
    "compliant": true,
    "missing_fields": [],
    "invalid_fields": [],
    "stage": "Procurement Initiation",
    "validation_timestamp": "2024-01-15T10:35:00Z"
}
```

**Note:** All validation logic now runs in PHP using:
- PHP regex for hash validation
- PHP numeric checks for file size
- PHP array validation for required fields
- Carbon library for timestamp validation

### 4. Validate Storage Consistency
```
POST /api/smart-contract/validate-storage
Content-Type: application/json

{
    "procurement_id": "PR-2024-001"
}
```

### 5. Get Document Audit Trail
```
GET /api/smart-contract/audit-trail/{procurement_id}
```

### 6. Get System Status
```
GET /api/smart-contract/status
```

## Implementation Examples

### 1. Basic Usage in Your Controllers (Pure PHP)

```php
<?php

use App\Services\SmartContractService;

// In your ProcurementController
public function validateDocumentBeforeUpload(Request $request)
{
    $smartContract = app(SmartContractService::class);
    
    // Example document metadata
    $documentData = [
        'hash' => hash_file('sha256', $request->file('document')->path()),
        'file_size' => $request->file('document')->getSize(),
        'file_key' => 'procurement/2024/doc_123.pdf',
        'document_type' => 'Purchase Request',
        'timestamp' => now()->toISOString(),
        'user_address' => auth()->user()->blockchain_address,
        'stage_metadata' => [
            'stage' => 'Procurement Initiation',
            'submission_date' => now()->toDateString()
        ]
    ];
    
    // Validate using pure PHP smart contract logic - NO JavaScript involved
    $validation = $smartContract->checkDocumentMetadataCompliance($documentData, 'Procurement Initiation');
    
    if (!$validation['compliant']) {
        return response()->json([
            'error' => 'Document validation failed (PHP validation)',
            'missing_fields' => $validation['missing_fields'],
            'invalid_fields' => $validation['invalid_fields']
        ], 422);
    }
    
    // Proceed with upload...
}
```

### 2. Document Integrity Verification (PHP-Based)

```php
public function verifyDocumentIntegrity(string $procurementId, string $documentHash)
{
    $smartContract = app(SmartContractService::class);
    
    // Pure PHP integrity verification - no JavaScript filters needed
    $verification = $smartContract->validateDocumentIntegrity($procurementId, $documentHash);
    
    if ($verification['valid']) {
        return response()->json([
            'message' => 'Document verified successfully using PHP validation',
            'blockchain_data' => $verification,
            'validation_method' => 'Pure PHP - No JavaScript'
        ]);
    } else {
        return response()->json([
            'error' => 'Document verification failed (PHP validation)',
            'message' => $verification['error']
        ], 404);
    }
}
```

### 3. Generate Audit Trail

```php
public function getDocumentAuditTrail(string $procurementId)
{
    $smartContract = app(SmartContractService::class);
    
    try {
        $auditTrail = $smartContract->getDocumentAuditTrail($procurementId);
        
        return response()->json([
            'procurement_id' => $auditTrail['procurement_id'],
            'total_entries' => $auditTrail['total_entries'],
            'audit_trail' => $auditTrail['audit_trail']
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Failed to retrieve audit trail',
            'message' => $e->getMessage()
        ], 500);
    }
}
```

### 4. Storage Consistency Check

```php
public function checkStorageConsistency(string $procurementId)
{
    $smartContract = app(SmartContractService::class);
    
    $consistencyCheck = $smartContract->validateDocumentStorageConsistency($procurementId);
    
    return response()->json([
        'consistent' => $consistencyCheck['consistent'],
        'total_documents' => $consistencyCheck['total_documents'],
        'validated_documents' => $consistencyCheck['validated_documents'],
        'inconsistencies' => $consistencyCheck['inconsistencies'] ?? []
    ]);
}
```

## Deployment Steps

### 1. Deploy Smart Contract System (Pure PHP Implementation)

```php
// In a deployment script or command
public function deploySmartContractLibrary()
{
    $smartContract = app(SmartContractService::class);
    
    try {
        // Initialize pure PHP document management system
        $initResults = $smartContract->initializeDocumentManagementSystem();
        
        Log::info('Pure PHP smart contract system initialized', [
            'configuration_set' => $initResults['configuration_set'],
            'php_validation_ready' => $initResults['php_validation_ready'],
            'validation_method' => 'Pure PHP - No JavaScript'
        ]);
        
        return [
            'success' => true,
            'system_initialized' => $initResults['configuration_set'],
            'php_validation_ready' => $initResults['php_validation_ready'],
            'validation_setup' => $initResults['validation_setup'],
            'validation_method' => 'Pure PHP - No JavaScript dependencies'
        ];
        
    } catch (Exception $e) {
        Log::error('PHP smart contract system initialization failed', ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

### 2. Integration with Existing Services

You can integrate the smart contract validation into your existing services:

**In DocumentUploadService.php (Pure PHP Validation):**
```php
public function uploadAndPrepare(array $files, array $metadata, string $procurementId, string $procurementTitle, string $stageFolder): array
{
    $smartContract = app(SmartContractService::class);
    $metadataArray = [];
    
    foreach ($files as $index => $file) {
        // ... existing upload logic ...
        
        $documentData = [
            'hash' => $hash,
            'file_size' => $file->getSize(),
            'file_key' => $fileKey,
            'document_type' => $metadata[$index]['document_type'],
            'timestamp' => now()->toISOString(),
            'user_address' => auth()->user()->blockchain_address,
            'stage_metadata' => $metadata[$index]
        ];
        
        // Validate with pure PHP smart contract - NO JavaScript involved
        $validation = $smartContract->checkDocumentMetadataCompliance($documentData, $stageFolder);
        
        if (!$validation['compliant']) {
            throw new Exception('Document validation failed (PHP): ' . implode(', ', $validation['invalid_fields']));
        }
        
        $metadataArray[] = $documentData;
    }
    
    return $metadataArray;
}
```

### 3. Create a Validation Middleware

```php
<?php

namespace App\Http\Middleware;

use App\Services\SmartContractService;
use Closure;
use Illuminate\Http\Request;

class ValidateDocumentUpload
{
    public function __construct(
        private SmartContractService $smartContract
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            
            // Basic validation using smart contract rules
            if ($file->getSize() > 10485760) { // 10MB
                return response()->json(['error' => 'File size exceeds 10MB limit'], 422);
            }
            
            // Additional smart contract validations can be added here
        }
        
        return $next($request);
    }
}
```

## Key Benefits

### 1. Document Security (PHP-Powered)
- **Immutable Validation Rules**: Configuration stored on blockchain, validation logic runs in PHP
- **Hash Integrity**: Ensures document content hasn't been modified using PHP algorithms
- **Audit Trail**: Complete blockchain-based audit trail processed by PHP logic

### 2. Compliance & Transparency (Pure PHP Implementation)
- **Automated Compliance Checks**: Validates documents against predefined rules using PHP
- **Transparent Validation**: All validation logic is visible and maintainable in PHP
- **Regulatory Compliance**: Maintains immutable records with PHP-generated audit trails

### 3. System Integration (PHP-Native)
- **Non-Intrusive**: Works alongside your existing Laravel application flow
- **Pure PHP**: No JavaScript dependencies for validation - everything runs in PHP
- **Performance**: Native PHP validation is faster and more reliable than mixed-language approaches
- **Maintainable**: All validation logic in one language (PHP) for easier debugging and updates

## 🔥 PHP-Only Implementation Highlights

### ✅ What's Now 100% PHP:
- ✅ **Document hash validation** - PHP regex patterns
- ✅ **File size validation** - PHP numeric checks  
- ✅ **Metadata compliance** - PHP array validation
- ✅ **Timestamp validation** - Carbon library in PHP
- ✅ **Document type validation** - PHP array membership checks
- ✅ **Duplicate detection** - PHP database queries
- ✅ **Audit trail generation** - PHP data processing
- ✅ **Storage consistency checks** - PHP algorithms

### ❌ What's NO LONGER Used:
- ❌ **JavaScript filters** - Removed completely
- ❌ **Mixed language validation** - Pure PHP only
- ❌ **Client-side dependencies** - Server-side PHP validation
- ❌ **Complex blockchain scripts** - Simple configuration variables only

## Important Notes

1. **Scope Limitation**: This smart contract is ONLY for document management - it does not automate procurement workflows, milestone payments, or contract execution.

2. **Data Privacy**: Only document metadata (hash, file_key, file_size, etc.) is stored on blockchain - actual document content remains in DigitalOcean Spaces.

3. **Validation Focus**: The smart contract validates data integrity, format compliance, and audit trail consistency - not business logic or procurement rules.

4. **Blockchain Integration**: Works with your existing MultiChain setup using the `procurement.documents`, `procurement.status`, and `procurement.events` streams.

## Testing & Validation

### 1. Setup and Deployment Testing
```bash
# Deploy and test the complete system
php artisan smart-contracts:setup --test

# This command will:
# - Verify blockchain connectivity
# - Deploy document management library
# - Create validation filters
# - Set up configuration
# - Run comprehensive tests
```

### 2. Manual API Testing

#### Test Document Integrity Validation
```bash
curl -X POST http://your-domain/smart-contracts/validate-integrity \
  -H "Content-Type: application/json" \
  -d '{
    "procurement_id": "TEST-001",
    "document_hash": "a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890"
  }'
```

#### Test Metadata Compliance
```bash
curl -X POST http://your-domain/smart-contracts/check-compliance \
  -H "Content-Type: application/json" \
  -d '{
    "metadata": {
      "hash": "a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890",
      "file_key": "test/document.pdf",
      "file_size": 1024000,
      "document_type": "Purchase Request",
      "user_address": "1A2B3C4D5E6F789012345678901234567890",
      "timestamp": "2024-01-15T10:30:00Z"
    },
    "stage": "Procurement Initiation"
  }'
```

#### Test Storage Consistency
```bash
curl -X POST http://your-domain/smart-contracts/validate-storage \
  -H "Content-Type: application/json" \
  -d '{
    "procurement_id": "TEST-001"
  }'
```

#### Test Audit Trail
```bash
curl -X GET http://your-domain/smart-contracts/audit-trail/TEST-001
```

### 3. Integration Testing with DocumentValidationJob

```php
// In a test file or console command
use App\Jobs\DocumentValidationJob;

// Test background validation job
$testMetadata = [
    'hash' => 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890',
    'file_key' => 'test/document.pdf',
    'file_size' => 1024000,
    'document_type' => 'Purchase Request',
    'user_address' => '1A2B3C4D5E6F789012345678901234567890',
    'timestamp' => now()->toISOString(),
    'stage_metadata' => [
        'stage' => 'Procurement Initiation',
        'submission_date' => now()->toDateString()
    ]
];

DocumentValidationJob::dispatch($testMetadata, 'TEST-001');
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Library Creation Fails
**Error**: `Library creation failed: Permission denied`
**Solution**:
```bash
# Check MultiChain permissions
php artisan multichain:info

# Verify you have create permissions
# If not, contact your blockchain administrator
```

#### 2. Document Hash Not Found
**Error**: `Document hash not found on blockchain`
**Cause**: Document hasn't been published to blockchain yet
**Solution**: Ensure documents are published to `procurement.documents` stream before validation

#### 3. Metadata Compliance Failure
**Error**: `Document validation failed: missing required fields`
**Solution**: Check that all required fields are present:
- `hash` (64-character hexadecimal)
- `file_key` (string)
- `file_size` (integer, max 10MB)
- `document_type` (must be from allowed list)
- `user_address` (min 25 characters)
- `timestamp` (ISO 8601 format)

#### 4. Storage Consistency Issues
**Error**: `Storage validation failed: file not found`
**Solution**: 
- Verify file exists in DigitalOcean Spaces
- Check file key format matches blockchain record
- Ensure proper access permissions to storage

### Debug Mode

Enable debug logging in `.env`:
```env
LOG_LEVEL=debug
SMART_CONTRACT_DEBUG=true
```

Check logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

## Performance Optimization

### 1. Caching Validation Rules
```php
// Cache validation configuration to reduce blockchain queries
Cache::remember('document_validation_config', 3600, function () {
    return $this->multichainService->getVariableValue('document_validation_config');
});
```

### 2. Batch Validation
```php
// Validate multiple documents in a single operation
public function validateMultipleDocuments(array $documentHashes, string $procurementId)
{
    $results = [];
    foreach ($documentHashes as $hash) {
        $results[$hash] = $this->validateDocumentIntegrity($procurementId, $hash);
    }
    return $results;
}
```

### 3. Asynchronous Processing
```php
// Use DocumentValidationJob for large validation operations
foreach ($documents as $document) {
    DocumentValidationJob::dispatch($document['metadata'], $procurementId);
}
```

## Security Considerations

### 1. Input Validation
- All inputs are validated before blockchain operations
- Hash format verification (64-character hexadecimal)
- File size limits enforced
- User address format validation

### 2. Access Control
- Operations require proper authentication
- Blockchain address verification
- Role-based permission checking

### 3. Data Privacy
- Only metadata stored on blockchain
- Actual document content remains in secure storage
- No sensitive information exposed

## Monitoring and Alerts

### 1. Set Up Monitoring
```php
// Monitor validation failures
if (!$validation['compliant']) {
    Log::warning('Document validation failed', [
        'procurement_id' => $procurementId,
        'missing_fields' => $validation['missing_fields'],
        'invalid_fields' => $validation['invalid_fields']
    ]);
    
    // Send alert notification
    // Mail::to($admin)->send(new ValidationFailureAlert($validation));
}
```

### 2. Performance Metrics
```php
// Track validation performance
$startTime = microtime(true);
$result = $this->validateDocumentIntegrity($procurementId, $hash);
$executionTime = microtime(true) - $startTime;

Log::info('Document validation completed', [
    'execution_time' => $executionTime,
    'result' => $result['valid']
]);
```

## Next Steps

1. **Deploy the System**: Run `php artisan smart-contracts:setup --test` to deploy and test
2. **Integration**: Add validation calls to your existing document upload flow
3. **Monitoring**: Set up logging and alerting for validation failures
4. **Testing**: Thoroughly test with real document data
5. **Documentation**: Update your API documentation to include validation endpoints
6. **Training**: Train users on new validation features and error handling

This implementation ensures your document management system has robust validation, integrity checking, and audit capabilities while staying strictly within the scope of document management rather than procurement process automation.

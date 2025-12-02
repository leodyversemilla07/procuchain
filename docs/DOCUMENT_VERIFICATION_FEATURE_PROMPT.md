# Detailed Prompt: Document Verification Feature for ProcuChain

**Created**: December 2, 2025  
**Purpose**: Development prompt for implementing document verification feature  
**Based on**: Anthropic Prompt Engineering Best Practices

---

## Table of Contents

1. [System Context](#system-context)
2. [Feature Requirements](#feature-requirements)
3. [Existing Codebase Context](#existing-codebase-context)
4. [Implementation Instructions](#implementation-instructions)
5. [Code Examples & Patterns](#code-examples--patterns)
6. [Constraints & Guidelines](#constraints--guidelines)
7. [Expected Outputs](#expected-outputs)
8. [Additional Context](#additional-context)

---

## System Context

```xml
<system_context>
You are an expert Laravel developer working on ProcuChain, a blockchain-backed procurement document management system for Philippine government (BAC) operations. The system follows RA 9184 (Government Procurement Reform Act) and RA 12009 (New Government Procurement Act).

### Technology Stack
- **Backend**: PHP 8.3, Laravel 12.38.1, MySQL 8.0+
- **Frontend**: React 19, Inertia.js 2.x, TypeScript, Tailwind CSS 4.x
- **Blockchain**: MultiChain 2.3.3+ (permissioned, on-chain storage)
- **Auth**: Laravel Fortify with 2FA
- **Testing**: Pest 4.x

### Architecture Overview
- Documents are stored directly on blockchain with SHA-256 hash verification
- 15-stage procurement workflow with 150+ document types
- Role-based access: `bac_secretariat`, `bac_chairman`, `hope`, `admin`
- All files stored on-chain for immutability and audit trails
</system_context>
```

---

## Feature Requirements

```xml
<feature_requirements>
### Primary Goal
Develop a comprehensive document verification system that validates uploaded procurement documents for authenticity, completeness, and compliance with RA 9184/RA 12009 requirements.

### Functional Requirements

1. **Hash-Based Integrity Verification**
   - Verify document integrity using SHA-256 hash comparison
   - Compare uploaded document hash against blockchain-stored hash
   - Detect any modifications or tampering attempts
   - Support re-verification at any time during procurement lifecycle

2. **Document Completeness Validation**
   - Validate required fields/signatures presence in PDF documents
   - Check for mandatory document sections (e.g., signatories, dates, approvals)
   - Ensure document meets stage-specific requirements from `StageDocumentRequirements`
   - Generate completeness reports with missing element details

3. **Cross-Reference Verification**
   - Verify PR numbers match across related documents
   - Validate budget amounts consistency (ABC, CAF, PR)
   - Check timeline/date consistency across documents
   - Ensure signatory authority matches role requirements

4. **Authenticity Verification**
   - Verify document metadata (creation date, modification date)
   - Check digital signature validity if present
   - Validate PhilGEPS posting receipts against external API (if available)
   - Flag suspicious patterns (backdated documents, unusual file sizes)

5. **Compliance Verification**
   - Validate against RA 9184/RA 12009 document requirements
   - Check stage-appropriate document types using `DocumentTypeEnums`
   - Ensure procurement mode-specific requirements are met
   - Verify timeline compliance (posting periods, deadlines)

### Non-Functional Requirements
- Verification must complete within 30 seconds for single documents
- Batch verification for all stage documents
- Detailed audit logging of all verification attempts
- User-friendly verification status display
- Support for offline verification reports
</feature_requirements>
```

---

## Existing Codebase Context

```xml
<existing_code>
### Key Services to Extend/Use

1. **DocumentValidationService** (`app/Services/DocumentValidationService.php`)
   - Already validates document uploads and stage completion
   - Has `validateUpload()`, `validateStageCompletion()`, `calculateCompletionPercentage()`
   - Uses `StageDocumentRequirements` for document mapping

2. **BlockchainStorageService** (`app/Services/BlockchainStorageService.php`)
   - Handles on-chain file storage with SHA-256 hashing
   - `uploadFile()` generates hash: `$fileHash = hash('sha256', $fileContent)`
   - `retrieveFile()` retrieves files from blockchain

3. **DocumentRepository** (`app/Repositories/DocumentRepository.php`)
   - Manages `procurement.documents` stream
   - `findByProcurement()`, `findByTxid()`, `getHistory()`

4. **DocumentData DTO** (`app/DataTransferObjects/DocumentData.php`)
   - Contains: `hash`, `prNumber`, `stage`, `documentType`, `fileKey`, `dataTxid`, `metadataTxid`

5. **DocumentPublisher** (`app/Services/Publishers/DocumentPublisher.php`)
   - Publishes documents to blockchain with metadata

### Key Enums

1. **DocumentTypeEnums** (`app/Enums/DocumentTypeEnums.php`)
   - 150+ document types across 15 stages
   - Methods: `getDisplayName()`, `getDescription()`, `isRequiredForInitiation()`

2. **StageEnums** (`app/Enums/StageEnums.php`)
   - 15 procurement stages from PROCUREMENT_INITIATION to COMPLETED
   - Methods: `getDisplayName()`, `getPhase()`, `getId()`

### Existing Validation Pattern
```php
// From UploadSingleDocumentRequest.php
'document_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
'document_type' => ['required', 'string', Rule::in(DocumentTypeEnums::cases())],
```

### File Organization
```
app/
├── Services/
│   ├── DocumentValidationService.php  ← Extend this
│   ├── BlockchainStorageService.php   ← Use for hash verification
│   └── Publishers/DocumentPublisher.php
├── Repositories/
│   └── DocumentRepository.php
├── DataTransferObjects/
│   └── DocumentData.php
├── Http/
│   ├── Controllers/Procurement/
│   └── Requests/Procurement/
```
</existing_code>
```

---

## Implementation Instructions

```xml
<implementation_steps>
### Step 1: Create DocumentVerificationService
Create a new service class that orchestrates all verification logic:

```
Location: app/Services/DocumentVerificationService.php
```

**Required Methods:**
- `verifyIntegrity(string $fileKey, string $dataTxid): VerificationResult`
- `verifyCompleteness(string $prNumber, StageEnums $stage): CompletenessResult`
- `verifyCrossReferences(string $prNumber): CrossReferenceResult`
- `verifyCompliance(string $prNumber, StageEnums $stage): ComplianceResult`
- `generateVerificationReport(string $prNumber): VerificationReportDTO`

### Step 2: Create Verification DTOs
Create immutable DTOs for verification results:

```
Location: app/DataTransferObjects/Verification/
```

**DTOs to Create:**
- `VerificationResult.php` - Single document verification
- `CompletenessResult.php` - Stage completeness check
- `CrossReferenceResult.php` - Cross-document validation
- `ComplianceResult.php` - Regulatory compliance
- `VerificationReportDTO.php` - Full verification report

### Step 3: Create Verification Controller
Create controller for verification endpoints:

```
Location: app/Http/Controllers/DocumentVerificationController.php
```

**Endpoints:**
- `POST /procurement/{pr_number}/verify` - Full verification
- `POST /procurement/{pr_number}/verify/integrity` - Hash verification only
- `GET /procurement/{pr_number}/verification-report` - Get report
- `POST /documents/{file_key}/verify` - Single document verify

### Step 4: Create Frontend Components
Create React components for verification UI:

```
Location: resources/js/components/verification/
```

**Components:**
- `VerificationStatus.tsx` - Status badge/indicator
- `VerificationReport.tsx` - Full report display
- `VerificationDialog.tsx` - Verification trigger modal
- `IntegrityCheck.tsx` - Hash verification display

### Step 5: Create Database Migration (if needed)
For caching verification results:

```
Location: database/migrations/
```

**Table: `document_verifications`**
- `id`, `file_key`, `pr_number`, `verification_type`, `result`, `verified_at`, `verified_by`

### Step 6: Create Tests
Create comprehensive tests:

```
Location: tests/Feature/DocumentVerificationTest.php
Location: tests/Unit/Services/DocumentVerificationServiceTest.php
```

**Test Cases:**
- Hash match verification passes
- Hash mismatch detection
- Missing required documents detection
- Cross-reference inconsistency detection
- Compliance violation detection
- Verification report generation
</implementation_steps>
```

---

## Code Examples & Patterns

```xml
<code_examples>
### Example: Verification Result DTO
```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Verification;

final class VerificationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $verificationType,
        public readonly string $fileKey,
        public readonly ?string $expectedHash,
        public readonly ?string $actualHash,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly \Carbon\Carbon $verifiedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'verification_type' => $this->verificationType,
            'file_key' => $this->fileKey,
            'hash_match' => $this->expectedHash === $this->actualHash,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'verified_at' => $this->verifiedAt->toIso8601String(),
        ];
    }
}
```

### Example: Integrity Verification Method
```php
public function verifyIntegrity(string $fileKey, string $dataTxid): VerificationResult
{
    // 1. Retrieve document from blockchain
    $fileData = $this->blockchainStorage->retrieveFile($fileKey, $dataTxid);
    
    // 2. Get stored hash from metadata
    $storedHash = $fileData['metadata']['hash'] ?? null;
    
    // 3. Recalculate hash from content
    $actualHash = hash('sha256', $fileData['content']);
    
    // 4. Compare and return result
    $isValid = $storedHash === $actualHash;
    
    return new VerificationResult(
        isValid: $isValid,
        verificationType: 'integrity',
        fileKey: $fileKey,
        expectedHash: $storedHash,
        actualHash: $actualHash,
        errors: $isValid ? [] : ['Document integrity compromised: hash mismatch'],
        warnings: [],
        verifiedAt: now(),
    );
}
```

### Example: Frontend Verification Status Component
```tsx
interface VerificationStatusProps {
    status: 'verified' | 'failed' | 'pending' | 'not_verified';
    lastVerified?: string;
    onClick?: () => void;
}

const VerificationStatus: React.FC<VerificationStatusProps> = ({
    status,
    lastVerified,
    onClick,
}) => {
    const statusConfig = {
        verified: { icon: CheckCircle, color: 'text-green-600', label: 'Verified' },
        failed: { icon: XCircle, color: 'text-red-600', label: 'Failed' },
        pending: { icon: Clock, color: 'text-yellow-600', label: 'Pending' },
        not_verified: { icon: AlertCircle, color: 'text-gray-400', label: 'Not Verified' },
    };

    const config = statusConfig[status];
    const Icon = config.icon;

    return (
        <button
            onClick={onClick}
            className={cn('flex items-center gap-2', config.color)}
        >
            <Icon className="h-5 w-5" />
            <span className="text-sm font-medium">{config.label}</span>
            {lastVerified && (
                <span className="text-xs text-muted-foreground">
                    {formatDistanceToNow(new Date(lastVerified), { addSuffix: true })}
                </span>
            )}
        </button>
    );
};
```
</code_examples>
```

---

## Constraints & Guidelines

```xml
<constraints>
### MUST Follow
1. Use existing `BlockchainStorageService` for hash retrieval
2. Follow existing DTO patterns in `app/DataTransferObjects/`
3. Use `StageDocumentRequirements` for requirement mapping
4. Create Form Request classes for validation
5. Write Pest tests for all new functionality
6. Use Wayfinder for route type generation
7. Run `vendor/bin/pint --dirty` before committing
8. Follow existing error handling patterns with proper logging

### MUST NOT Do
1. Store verification results only in database (must be blockchain-auditable)
2. Modify existing `DocumentValidationService` core methods
3. Create new database tables without discussing schema first
4. Skip tests for verification logic
5. Use raw database queries - use Eloquent/repositories

### Security Considerations
1. Verify user has permission to access document before verification
2. Log all verification attempts with user ID and IP
3. Rate limit verification endpoints to prevent abuse
4. Sanitize all file paths and keys before blockchain queries
5. Never expose internal transaction IDs to unauthorized users
</constraints>
```

---

## Expected Outputs

```xml
<expected_outputs>
### Thinking Process
Before implementing, analyze:
1. How existing `DocumentValidationService` can be extended vs. new service
2. Which verification types need blockchain interaction vs. local
3. Performance implications of batch verification
4. Caching strategy for repeated verifications
5. Error handling for blockchain connection failures

### Deliverables
1. `DocumentVerificationService.php` - Main verification orchestrator
2. Verification DTOs in `app/DataTransferObjects/Verification/`
3. `DocumentVerificationController.php` with Form Requests
4. React components in `resources/js/components/verification/`
5. Pest tests with >90% coverage
6. Migration for verification cache table (optional)
7. Routes in `routes/web.php` with proper middleware

### Success Criteria
- [ ] Single document integrity verification works
- [ ] Batch verification for all stage documents works
- [ ] Verification report can be generated and exported
- [ ] UI shows clear verification status
- [ ] All verification attempts are logged
- [ ] Tests pass with high coverage
- [ ] Performance meets 30-second requirement
</expected_outputs>
```

---

## Additional Context

```xml
<additional_context>
### Document Requirements Reference
See `docs/PROCUREMENT_DOCUMENT_REQUIREMENTS.md` for complete list of required documents per stage based on RA 9184 & RA 12009.

### Key Document Properties for Verification
```php
// From DocumentData DTO
- prNumber: string        // Must match across documents
- hash: string            // SHA-256 for integrity
- documentType: string    // Must be valid for stage
- stage: string           // Must match procurement stage
- fileSize: int           // Reasonable size validation
- mimeType: string        // Must be PDF
- timestamp: Carbon       // Must be within valid range
- dataTxid: string        // Blockchain transaction ID
```

### Example Verification Scenarios
1. **Purchase Request (PR)** - Verify signatures, amounts match ABC
2. **Abstract of Bids** - Verify all bidder entries match bid documents
3. **BAC Resolution** - Verify all required members signed
4. **Notice of Award** - Verify amount matches evaluated bid
5. **Contract** - Verify performance bond amount = 5-30% of contract
</additional_context>
```

---

## Prompt Engineering Techniques Applied

This prompt follows **Anthropic's Prompt Engineering Best Practices**:

| Technique | Application |
|-----------|-------------|
| **Be Clear & Direct** | Explicit requirements with numbered steps and specific file paths |
| **Use XML Tags** | Structured sections (`<system_context>`, `<feature_requirements>`, etc.) for clarity |
| **Chain of Thought** | Includes "Thinking Process" section for expected analysis steps |
| **Provide Examples** | Code patterns from existing codebase to ensure consistency |
| **Give Context** | Full technology stack, existing services, and file organization |
| **Set Constraints** | Clear "MUST" and "MUST NOT" guidelines |
| **Define Success Criteria** | Checkbox-style deliverables for validation |

---

## Related Documentation

- [Architecture Documentation](./ARCHITECTURE.md)
- [Procurement Document Requirements](./PROCUREMENT_DOCUMENT_REQUIREMENTS.md)
- [Database Schema](./DATABASE_SCHEMA.md)
- [Developer Guide](./DEVELOPER_GUIDE.md)

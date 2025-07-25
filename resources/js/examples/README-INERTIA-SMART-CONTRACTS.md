# Smart Contract Integration with Inertia.js

This guide shows how to use Inertia.js for smart contract validation instead of raw `fetch()` calls, providing better integration with the Laravel backend.

## Overview

The smart contract validation system has been updated to use Inertia.js router for seamless integration with Laravel. This provides:

✅ **Automatic CSRF token handling**  
✅ **Better error handling with Laravel validation**  
✅ **Preserves page state during requests**  
✅ **No manual fetch headers management**  
✅ **Built-in loading states**  
✅ **Type-safe requests**

## Migration Guide

### Before (Raw Fetch)

```typescript
// ❌ Old approach - manual fetch with headers
const [validationStatus, setValidationStatus] = useState('pending');

const handleFileUpload = async (file) => {
    const validation = await fetch('/smart-contracts/check-compliance', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: JSON.stringify(documentMetadata)
    });
    
    if (validation.ok) {
        const result = await validation.json();
        if (result.compliant) {
            setValidationStatus('✅ Valid - Ready to upload');
        } else {
            setValidationStatus('❌ Invalid - Fix required fields');
        }
    }
};
```

### After (Inertia.js)

```typescript
// ✅ New approach - clean Inertia.js integration
import { useSmartContractValidation } from '@/hooks/useSmartContractValidation';

const [validationStatus, setValidationStatus] = useState('pending');
const { validateMetadata, isLoading } = useSmartContractValidation();

const handleFileUpload = async (file: File) => {
    try {
        setValidationStatus('🔄 Validating...');
        
        const documentMetadata: DocumentMetadata = {
            hash: 'temp_hash_' + Date.now(),
            file_key: `docs/${file.name}`,
            file_size: file.size,
            document_type: 'Bid Evaluation',
            user_address: 'user_123',
            timestamp: new Date().toISOString(),
            procurement_id: 'PR-2025-0001-0001'
        };

        // Clean, type-safe API call
        const validation = await validateMetadata(documentMetadata, 'bid-evaluation');
        
        if (validation.compliant) {
            setValidationStatus('✅ Valid - Ready to upload');
            // Proceed with upload...
        } else {
            setValidationStatus('❌ Invalid - Fix required fields');
            // Show validation errors...
        }
        
    } catch (error) {
        setValidationStatus('⚠️ Validation Error');
        console.error('Validation failed:', error);
    }
};
```

## Available Hooks

### useSmartContractValidation

The main hook for smart contract operations:

```typescript
const {
    validateMetadata,      // Document compliance validation
    validateIntegrity,     // Document integrity check
    validateStorage,       // Storage consistency check
    getAuditTrail,        // Get audit trail
    getSystemStatus,      // Get system status
    isLoading,            // Loading state
    error                 // Error state
} = useSmartContractValidation();
```

## API Methods

### 1. Document Compliance Validation

```typescript
const result = await validateMetadata(documentMetadata, stage);

// Returns: SmartContractValidationResult
{
    compliant: boolean;
    missing_fields: string[];
    invalid_fields: string[];
    stage: string;
    validation_timestamp: string;
}
```

### 2. Document Integrity Check

```typescript
const result = await validateIntegrity(procurementId, documentHash);

// Returns: DocumentIntegrityResult
{
    valid: boolean;
    blockchain_hash?: string;
    file_size?: number;
    timestamp?: string;
    error?: string;
}
```

### 3. Storage Consistency Validation

```typescript
const result = await validateStorage(procurementId);

// Returns: StorageConsistencyResult
{
    consistent: boolean;
    total_documents: number;
    validated_documents: number;
    consistency_percentage: number;
    inconsistencies: Array<...>;
}
```

### 4. Audit Trail

```typescript
const result = await getAuditTrail(procurementId);

// Returns: AuditTrailResult
{
    procurement_id: string;
    total_entries: number;
    audit_entries: Array<...>;
    generated_at: string;
}
```

## Component Integration Example

```typescript
import React, { useState } from 'react';
import { useSmartContractValidation } from '@/hooks/useSmartContractValidation';
import { Button } from '@/components/ui/button';

const DocumentUploadWithValidation: React.FC = () => {
    const [file, setFile] = useState<File | null>(null);
    const [validationResult, setValidationResult] = useState(null);
    const { validateMetadata, isLoading } = useSmartContractValidation();

    const handleFileUpload = async () => {
        if (!file) return;

        try {
            const metadata: DocumentMetadata = {
                hash: await calculateFileHash(file),
                file_key: `uploads/${file.name}`,
                file_size: file.size,
                document_type: 'Bid Document',
                user_address: currentUser.address,
                timestamp: new Date().toISOString(),
                procurement_id: procurementId
            };

            const result = await validateMetadata(metadata, currentStage);
            setValidationResult(result);

            if (result.compliant) {
                // Proceed with file upload
                await uploadFile(file);
            }
        } catch (error) {
            console.error('Validation failed:', error);
        }
    };

    return (
        <div>
            <input 
                type="file" 
                onChange={(e) => setFile(e.target.files?.[0] || null)} 
            />
            
            <Button 
                onClick={handleFileUpload}
                disabled={!file || isLoading}
            >
                {isLoading ? '🔄 Validating...' : '📤 Upload & Validate'}
            </Button>

            {validationResult && (
                <div className={validationResult.compliant ? 'text-green-600' : 'text-red-600'}>
                    {validationResult.compliant ? '✅ Document Valid' : '❌ Validation Failed'}
                </div>
            )}
        </div>
    );
};
```

## Error Handling

Inertia.js integration provides better error handling:

```typescript
try {
    const result = await validateMetadata(metadata, stage);
    // Handle success
} catch (error) {
    // Automatic error toasts are shown
    // Error state is managed in the hook
    console.error('Validation failed:', error);
}
```

## Loading States

Loading states are automatically managed:

```typescript
const { validateMetadata, isLoading } = useSmartContractValidation();

// Use isLoading to show spinners, disable buttons, etc.
<Button disabled={isLoading}>
    {isLoading ? '🔄 Validating...' : '✅ Validate Document'}
</Button>
```

## Backend Requirements

Ensure your Laravel controllers return proper JSON responses:

```php
// SmartContractController.php
public function checkCompliance(CheckComplianceRequest $request)
{
    $result = $this->smartContractService->validateDocumentCompliance(
        $request->metadata,
        $request->stage
    );

    return response()->json([
        'data' => $result
    ]);
}
```

## Testing Example

See `resources/js/examples/inertia-smart-contract-example.tsx` for a complete working example that demonstrates all the features.

## Benefits Summary

1. **Cleaner Code**: No manual header management or CSRF tokens
2. **Better UX**: Automatic loading states and error handling
3. **Type Safety**: Full TypeScript support with proper interfaces
4. **Laravel Integration**: Seamless with Laravel validation and responses
5. **State Management**: Preserves page state during API calls
6. **Error Handling**: Built-in error toasts and state management

This integration makes smart contract validation much more maintainable and provides a better developer experience while ensuring all validation remains server-side in pure PHP.

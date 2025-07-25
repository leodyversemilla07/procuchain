import { useState, useCallback } from 'react';
import { toast } from 'sonner';
import {
    SmartContractValidationResult,
    DocumentIntegrityResult,
    StorageConsistencyResult,
    AuditTrailResult,
    SmartContractSystemStatus,
    DocumentMetadata,
    UseSmartContractValidation,
    ValidateIntegrityRequest,
    CheckComplianceRequest,
    ValidateStorageRequest
} from '@/types/smart-contracts';

/**
 * Custom hook for Smart Contract validation operations
 * Uses fetch() for JSON API endpoints - this is the correct approach for API calls
 * while Inertia.js handles page navigation and form submissions
 */
export const useSmartContractValidation = (): UseSmartContractValidation => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleApiCall = useCallback(async <T>(
        url: string,
        method: 'GET' | 'POST' = 'GET',
        requestData?: unknown
    ): Promise<T> => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: requestData ? JSON.stringify(requestData) : undefined
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: 'Unknown error occurred' }));
                throw new Error(errorData.message || `HTTP ${response.status}`);
            }

            const result = await response.json();
            
            // Handle both direct data and wrapped responses
            return result.data || result;

        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
            setError(errorMessage);
            toast.error('Smart Contract Error', { description: errorMessage });
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const validateMetadata = useCallback(async (
        metadata: DocumentMetadata,
        stage: string
    ): Promise<SmartContractValidationResult> => {
        const request: CheckComplianceRequest = { metadata, stage };
        
        const result = await handleApiCall<SmartContractValidationResult>(
            '/smart-contracts/check-compliance',
            'POST',
            request
        );

        if (result.compliant) {
            toast.success('Document Validation', { 
                description: 'Document metadata is compliant with smart contract rules' 
            });
        } else {
            toast.warning('Validation Issues Found', {
                description: `${result.missing_fields.length + result.invalid_fields.length} validation issues detected`
            });
        }

        return result;
    }, [handleApiCall]);

    const validateIntegrity = useCallback(async (
        procurementId: string,
        documentHash: string
    ): Promise<DocumentIntegrityResult> => {
        const request: ValidateIntegrityRequest = { procurement_id: procurementId, document_hash: documentHash };
        
        const result = await handleApiCall<DocumentIntegrityResult>(
            '/smart-contracts/validate-integrity',
            'POST',
            request
        );

        if (result.valid) {
            toast.success('Document Integrity Verified', { 
                description: 'Document found on blockchain and integrity confirmed' 
            });
        } else {
            toast.error('Integrity Validation Failed', {
                description: result.error || 'Document not found or integrity compromised'
            });
        }

        return result;
    }, [handleApiCall]);

    const validateStorage = useCallback(async (
        procurementId: string
    ): Promise<StorageConsistencyResult> => {
        const request: ValidateStorageRequest = { procurement_id: procurementId };
        
        const result = await handleApiCall<StorageConsistencyResult>(
            '/smart-contracts/validate-storage',
            'POST',
            request
        );

        if (result.consistent) {
            toast.success('Storage Consistency Verified', { 
                description: `All ${result.total_documents} documents are consistent` 
            });
        } else {
            toast.warning('Storage Inconsistencies Found', {
                description: `${result.inconsistencies.length} inconsistencies detected`
            });
        }

        return result;
    }, [handleApiCall]);

    const getAuditTrail = useCallback(async (
        procurementId: string
    ): Promise<AuditTrailResult> => {
        const result = await handleApiCall<AuditTrailResult>(
            `/smart-contracts/audit-trail/${procurementId}`,
            'GET'
        );

        toast.success('Audit Trail Retrieved', { 
            description: `Found ${result.total_entries} audit trail entries` 
        });

        return result;
    }, [handleApiCall]);

    const getSystemStatus = useCallback(async (): Promise<SmartContractSystemStatus> => {
        const result = await handleApiCall<SmartContractSystemStatus>(
            '/smart-contracts/status',
            'GET'
        );

        return result;
    }, [handleApiCall]);

    return {
        validateMetadata,
        validateIntegrity,
        validateStorage,
        getAuditTrail,
        getSystemStatus,
        isLoading,
        error
    };
};

/**
 * Hook for real-time document validation during file upload
 */
export const useDocumentUploadValidation = () => {
    const { validateMetadata, isLoading } = useSmartContractValidation();
    const [validationResults, setValidationResults] = useState<Map<string, SmartContractValidationResult>>(new Map());

    const validateFileMetadata = useCallback(async (
        file: File,
        documentType: string,
        stage: string,
        procurementId?: string
    ): Promise<SmartContractValidationResult> => {
        // Generate file hash (simplified for demo - in production, use proper hashing)
        const arrayBuffer = await file.arrayBuffer();
        const hashArray = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', arrayBuffer)));
        const hash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

        const metadata: DocumentMetadata = {
            hash,
            file_key: `${procurementId || 'temp'}/${file.name}`,
            file_size: file.size,
            document_type: documentType,
            user_address: 'temp_address', // Will be set by backend
            timestamp: new Date().toISOString(),
            procurement_id: procurementId
        };

        const result = await validateMetadata(metadata, stage);
        
        // Cache the result
        setValidationResults(prev => new Map(prev.set(file.name, result)));
        
        return result;
    }, [validateMetadata]);

    const getValidationResult = useCallback((fileName: string): SmartContractValidationResult | undefined => {
        return validationResults.get(fileName);
    }, [validationResults]);

    const clearValidationResults = useCallback(() => {
        setValidationResults(new Map());
    }, []);

    return {
        validateFileMetadata,
        getValidationResult,
        clearValidationResults,
        isValidating: isLoading,
        validationResults: Array.from(validationResults.entries())
    };
};

/**
 * Hook for batch validation operations
 */
export const useBatchValidation = () => {
    const { validateIntegrity, validateStorage } = useSmartContractValidation();
    const [batchResults, setBatchResults] = useState<Map<string, DocumentIntegrityResult | StorageConsistencyResult>>(new Map());
    const [isProcessing, setIsProcessing] = useState(false);

    const validateMultipleDocuments = useCallback(async (
        procurementId: string,
        documentHashes: string[]
    ) => {
        setIsProcessing(true);
        const results = new Map();

        try {
            // Validate each document integrity
            for (const hash of documentHashes) {
                try {
                    const result = await validateIntegrity(procurementId, hash);
                    results.set(hash, result);
                } catch (error) {
                    results.set(hash, { valid: false, error: error instanceof Error ? error.message : 'Unknown error' });
                }
            }

            // Also run storage consistency check
            try {
                const storageResult = await validateStorage(procurementId);
                results.set('storage_consistency', storageResult);
            } catch (error) {
                results.set('storage_consistency', { consistent: false, error: error instanceof Error ? error.message : 'Unknown error' });
            }

            setBatchResults(results);
            return results;

        } finally {
            setIsProcessing(false);
        }
    }, [validateIntegrity, validateStorage]);

    return {
        validateMultipleDocuments,
        batchResults: Array.from(batchResults.entries()),
        isProcessing,
        clearResults: () => setBatchResults(new Map())
    };
};

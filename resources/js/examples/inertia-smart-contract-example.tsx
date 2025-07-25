import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { useSmartContractValidation } from '@/hooks/use-smart-contract-validation';
import { DocumentMetadata } from '@/types/smart-contracts';

/**
 * Example showing how to use Smart Contract validation with Inertia.js
 * instead of raw fetch() calls
 */
const InertiaSmartContractExample: React.FC = () => {
    const [validationStatus, setValidationStatus] = useState('pending');
    const { validateMetadata, validateIntegrity, isLoading } = useSmartContractValidation();

    // Example 1: Document compliance validation using Inertia.js
    const handleFileUpload = async (file: File) => {
        try {
            setValidationStatus('🔄 Validating...');
            
            // Prepare document metadata
            const documentMetadata: DocumentMetadata = {
                hash: 'temp_hash_' + Date.now(), // Would be calculated from file
                file_key: `docs/${file.name}`,
                file_size: file.size,
                document_type: 'Bid Evaluation',
                user_address: 'user_123',
                timestamp: new Date().toISOString(),
                procurement_id: 'PR-2025-0001-0001',
                stage_metadata: {
                    stage: 'bid-evaluation',
                    user_id: 1
                }
            };

            // Instead of raw fetch, use Inertia.js integration
            const validation = await validateMetadata(documentMetadata, 'bid-evaluation');
            
            if (validation.compliant) {
                setValidationStatus('✅ Valid - Ready to upload');
                toast.success('Document Valid', {
                    description: 'Smart contract validation passed. Document can be uploaded.'
                });
                // Proceed with upload...
            } else {
                setValidationStatus('❌ Invalid - Fix required fields');
                toast.error('Validation Failed', {
                    description: `${validation.missing_fields.length + validation.invalid_fields.length} issues found`
                });
                // Show validation errors...
            }
            
        } catch (error) {
            setValidationStatus('⚠️ Validation Error');
            console.error('Validation failed:', error);
        }
    };

    // Example 2: Document integrity check using Inertia.js
    const handleIntegrityCheck = async () => {
        try {
            // Instead of raw fetch, use Inertia.js integration
            const result = await validateIntegrity(
                'PR-2025-0001-0001',
                'a1b2c3d4e5f6789012345678901234567890abcdef'
            );
            
            if (result.valid) {
                toast.success('Document Integrity Verified', {
                    description: 'Document found on blockchain and hash matches'
                });
            } else {
                toast.warning('Integrity Issue', {
                    description: result.error || 'Document not found or hash mismatch'
                });
            }
        } catch (error) {
            console.error('Integrity check failed:', error);
        }
    };

    return (
        <Card className="w-full max-w-2xl mx-auto">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    🔗 Inertia.js Smart Contract Integration
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Validation Status */}
                <div className="p-4 bg-gray-50 rounded-lg">
                    <h3 className="font-medium mb-2">Validation Status:</h3>
                    <p className="text-sm text-gray-600">{validationStatus}</p>
                </div>

                {/* Example Actions */}
                <div className="space-y-4">
                    <Button 
                        onClick={() => {
                            // Simulate file upload
                            const mockFile = new File(['test content'], 'test-document.pdf', {
                                type: 'application/pdf'
                            });
                            handleFileUpload(mockFile);
                        }}
                        disabled={isLoading}
                        className="w-full"
                    >
                        {isLoading ? '🔄 Validating...' : '📄 Test Document Validation'}
                    </Button>

                    <Button 
                        onClick={handleIntegrityCheck}
                        disabled={isLoading}
                        variant="outline"
                        className="w-full"
                    >
                        {isLoading ? '🔄 Checking...' : '🔐 Test Integrity Check'}
                    </Button>
                </div>

                {/* Key Benefits */}
                <div className="space-y-2 text-sm text-gray-600">
                    <h4 className="font-medium text-gray-800">✅ Benefits of Inertia.js Integration:</h4>
                    <ul className="list-disc list-inside space-y-1 ml-4">
                        <li>Automatic CSRF token handling</li>
                        <li>Better error handling with Laravel validation</li>
                        <li>Preserves page state during requests</li>
                        <li>No need to manually manage fetch headers</li>
                        <li>Seamless integration with Laravel backend</li>
                        <li>Built-in loading states and error management</li>
                    </ul>
                </div>

                {/* Code Example */}
                <div className="bg-gray-900 text-gray-100 p-4 rounded-lg text-sm">
                    <h4 className="text-white font-medium mb-2">📝 Before (Raw Fetch):</h4>
                    <pre className="whitespace-pre-wrap">{`const validation = await fetch('/smart-contracts/check-compliance', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify(data)
});`}</pre>
                    
                    <h4 className="text-white font-medium mb-2 mt-4">✨ After (Inertia.js):</h4>
                    <pre className="whitespace-pre-wrap">{`const validation = await validateMetadata(
    documentMetadata, 
    'bid-evaluation'
);`}</pre>
                </div>
            </CardContent>
        </Card>
    );
};

export default InertiaSmartContractExample;

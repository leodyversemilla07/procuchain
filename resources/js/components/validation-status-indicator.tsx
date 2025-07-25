import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { CheckCircle, AlertCircle, XCircle, Loader2, Shield } from 'lucide-react';
import { SmartContractValidationResult, DocumentIntegrityResult } from '@/types/smart-contracts';

interface ValidationStatusIndicatorProps {
    validationResult?: SmartContractValidationResult;
    integrityResult?: DocumentIntegrityResult;
    isValidating?: boolean;
    size?: 'sm' | 'md' | 'lg';
    showTooltip?: boolean;
    showText?: boolean;
}

const ValidationStatusIndicator: React.FC<ValidationStatusIndicatorProps> = ({
    validationResult,
    integrityResult,
    isValidating = false,
    size = 'md',
    showTooltip = true,
    showText = true
}) => {
    const getIconSize = () => {
        switch (size) {
            case 'sm': return 'w-3 h-3';
            case 'lg': return 'w-6 h-6';
            default: return 'w-4 h-4';
        }
    };

    const getStatus = () => {
        if (isValidating) {
            return {
                icon: <Loader2 className={`${getIconSize()} animate-spin`} />,
                text: 'Validating...',
                variant: 'secondary' as const,
                color: 'text-blue-600'
            };
        }

        if (!validationResult && !integrityResult) {
            return {
                icon: <Shield className={getIconSize()} />,
                text: 'Ready',
                variant: 'outline' as const,
                color: 'text-gray-600'
            };
        }

        // Check validation result first
        if (validationResult) {
            if (validationResult.compliant) {
                return {
                    icon: <CheckCircle className={getIconSize()} />,
                    text: 'Valid',
                    variant: 'default' as const,
                    color: 'text-green-600',
                    bgColor: 'bg-green-600'
                };
            } else {
                const errorCount = validationResult.missing_fields.length + validationResult.invalid_fields.length;
                return {
                    icon: <XCircle className={getIconSize()} />,
                    text: `${errorCount} Issue${errorCount > 1 ? 's' : ''}`,
                    variant: 'destructive' as const,
                    color: 'text-red-600'
                };
            }
        }

        // Check integrity result
        if (integrityResult) {
            if (integrityResult.valid) {
                return {
                    icon: <CheckCircle className={getIconSize()} />,
                    text: 'Verified',
                    variant: 'default' as const,
                    color: 'text-green-600',
                    bgColor: 'bg-green-600'
                };
            } else {
                return {
                    icon: <AlertCircle className={getIconSize()} />,
                    text: 'Not Found',
                    variant: 'destructive' as const,
                    color: 'text-red-600'
                };
            }
        }

        return {
            icon: <Shield className={getIconSize()} />,
            text: 'Unknown',
            variant: 'outline' as const,
            color: 'text-gray-600'
        };
    };

    const status = getStatus();

    const getTooltipContent = () => {
        if (isValidating) return 'Smart contract validation in progress...';
        
        if (validationResult) {
            if (validationResult.compliant) {
                return 'Document metadata passed all smart contract validation rules';
            } else {
                const details = [
                    ...validationResult.missing_fields.map(field => `Missing: ${field}`),
                    ...validationResult.invalid_fields
                ];
                return (
                    <div className="space-y-1">
                        <div className="font-medium">Validation Issues:</div>
                        {details.map((detail, index) => (
                            <div key={index} className="text-xs">• {detail}</div>
                        ))}
                    </div>
                );
            }
        }

        if (integrityResult) {
            if (integrityResult.valid) {
                return (
                    <div className="space-y-1">
                        <div className="font-medium">Document Verified</div>
                        <div className="text-xs">Hash: {integrityResult.blockchain_hash}</div>
                        <div className="text-xs">Type: {integrityResult.document_type}</div>
                        <div className="text-xs">Size: {integrityResult.file_size ? (integrityResult.file_size / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'}</div>
                    </div>
                );
            } else {
                return `Integrity check failed: ${integrityResult.error || 'Document not found on blockchain'}`;
            }
        }

        return 'No validation performed yet';
    };

    const indicator = (
        <div className="inline-flex items-center gap-2">
            <div className={status.color}>
                {status.icon}
            </div>
            {showText && (
                <Badge 
                    variant={status.variant}
                    className={status.bgColor ? `${status.bgColor} text-white` : ''}
                >
                    {status.text}
                </Badge>
            )}
        </div>
    );

    if (showTooltip) {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        {indicator}
                    </TooltipTrigger>
                    <TooltipContent>
                        {getTooltipContent()}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return indicator;
};

export default ValidationStatusIndicator;

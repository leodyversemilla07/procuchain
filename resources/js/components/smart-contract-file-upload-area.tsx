import React, { useState, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { FileUp, X, FileText, CheckCircle, AlertCircle, Shield, XCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useDocumentUploadValidation } from '@/hooks/use-smart-contract-validation';
import { SmartContractValidationResult, AllowedDocumentType, VALIDATION_CONSTRAINTS } from '@/types/smart-contracts';

interface SmartContractFileUploadAreaProps {
    label: string;
    file: File | null;
    error?: string;
    isDragging: boolean;
    onFileChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onDragEnter: (e: React.DragEvent) => void;
    onDragLeave: (e: React.DragEvent) => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: (e: React.DragEvent) => void;
    onRemove: () => void;
    inputId: string;
    accept?: string;
    required?: boolean;
    labelClassName?: string;
    errorClassName?: string;
    
    // Smart contract specific props
    documentType: AllowedDocumentType;
    stage: string;
    procurementId?: string;
    enableSmartValidation?: boolean;
    showValidationDetails?: boolean;
    onValidationComplete?: (result: SmartContractValidationResult) => void;
}

const SmartContractFileUploadArea: React.FC<SmartContractFileUploadAreaProps> = ({
    label,
    file,
    error,
    isDragging,
    onFileChange,
    onDragEnter,
    onDragLeave,
    onDragOver,
    onDrop,
    onRemove,
    inputId,
    accept = 'application/pdf',
    required = false,
    labelClassName,
    errorClassName,
    documentType,
    stage,
    procurementId,
    enableSmartValidation = true,
    showValidationDetails = true,
    onValidationComplete
}) => {
    const { validateFileMetadata } = useDocumentUploadValidation();
    const [validationResult, setValidationResult] = useState<SmartContractValidationResult | null>(null);
    const [isValidating, setIsValidating] = useState(false);
    const [validationProgress, setValidationProgress] = useState(0);

    const handleFileChange = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
        onFileChange(e);
        
        if (enableSmartValidation && e.target.files && e.target.files[0]) {
            const selectedFile = e.target.files[0];
            
            setIsValidating(true);
            setValidationProgress(0);
            setValidationResult(null);
            
            try {
                // Progress simulation for better UX
                setValidationProgress(20);
                
                // Basic file validation first
                if (selectedFile.size > VALIDATION_CONSTRAINTS.MAX_FILE_SIZE) {
                    throw new Error(`File size exceeds ${VALIDATION_CONSTRAINTS.MAX_FILE_SIZE / 1024 / 1024}MB limit`);
                }
                
                setValidationProgress(40);
                
                // Smart contract validation
                const result = await validateFileMetadata(selectedFile, documentType, stage, procurementId);
                
                setValidationProgress(80);
                
                // Simulate final processing
                await new Promise(resolve => setTimeout(resolve, 500));
                setValidationProgress(100);
                
                setValidationResult(result);
                
                if (onValidationComplete) {
                    onValidationComplete(result);
                }
                
            } catch (err) {
                const errorMessage = err instanceof Error ? err.message : 'Validation failed';
                const errorResult = {
                    compliant: false,
                    missing_fields: [],
                    invalid_fields: [errorMessage],
                    stage,
                    validation_timestamp: new Date().toISOString()
                };
                
                setValidationProgress(100);
                setValidationResult(errorResult);
                
                if (onValidationComplete) {
                    onValidationComplete(errorResult);
                }
            } finally {
                setIsValidating(false);
            }
        }
    }, [onFileChange, enableSmartValidation, validateFileMetadata, documentType, stage, procurementId, onValidationComplete]);

    const handleDrop = useCallback(async (e: React.DragEvent) => {
        onDrop(e);
        
        // Reset validation state when new file is dropped
        setValidationResult(null);
        
        if (enableSmartValidation && e.dataTransfer.files && e.dataTransfer.files[0]) {
            const droppedFile = e.dataTransfer.files[0];
            
            setIsValidating(true);
            setValidationProgress(0);
            
            try {
                // Progress simulation for better UX
                setValidationProgress(20);
                
                // Basic file validation first
                if (droppedFile.size > VALIDATION_CONSTRAINTS.MAX_FILE_SIZE) {
                    throw new Error(`File size exceeds ${VALIDATION_CONSTRAINTS.MAX_FILE_SIZE / 1024 / 1024}MB limit`);
                }
                
                setValidationProgress(40);
                
                // Smart contract validation
                const result = await validateFileMetadata(droppedFile, documentType, stage, procurementId);
                
                setValidationProgress(80);
                
                // Simulate final processing
                await new Promise(resolve => setTimeout(resolve, 500));
                setValidationProgress(100);
                
                setValidationResult(result);
                
                if (onValidationComplete) {
                    onValidationComplete(result);
                }
                
            } catch (err) {
                const errorMessage = err instanceof Error ? err.message : 'Validation failed';
                const errorResult = {
                    compliant: false,
                    missing_fields: [],
                    invalid_fields: [errorMessage],
                    stage,
                    validation_timestamp: new Date().toISOString()
                };
                
                setValidationProgress(100);
                setValidationResult(errorResult);
                
                if (onValidationComplete) {
                    onValidationComplete(errorResult);
                }
            } finally {
                setIsValidating(false);
            }
        }
    }, [onDrop, enableSmartValidation, validateFileMetadata, documentType, stage, procurementId, onValidationComplete]);

    const handleRemove = useCallback(() => {
        onRemove();
        // Reset validation state when file is removed
        setValidationResult(null);
        setIsValidating(false);
        setValidationProgress(0);
    }, [onRemove]);

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={inputId} className={labelClassName}>
                {label}
                {required ? (
                    <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                        *
                    </span>
                ) : null}
            </Label>
            <div
                className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDragging
                    ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                    : file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : error
                            ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                            : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                } cursor-pointer group`}
                onDragEnter={onDragEnter}
                onDragLeave={onDragLeave}
                onDragOver={onDragOver}
                onDrop={handleDrop}
                onClick={() => document.getElementById(inputId)?.click()}
            >
                {!file ? (
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                            <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                            Drag and drop your file here
                        </p>
                        <p className="text-sm text-muted-foreground/70 mb-5">
                            Only PDF files are supported
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="group-hover:bg-primary/5 transition-colors"
                            onClick={e => {
                                e.stopPropagation();
                                document.getElementById(inputId)?.click();
                            }}
                        >
                            Browse Files
                        </Button>
                        <Input
                            id={inputId}
                            type="file"
                            accept={accept}
                            className="hidden"
                            onChange={handleFileChange}
                        />
                    </div>
                ) : (
                    <div className="flex items-center justify-between">
                        <div className="flex items-center">
                            <div className="rounded-full bg-primary/10 p-3 mr-4">
                                <FileText className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <p className="font-medium">{file.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {(file.size / 1024).toFixed(2)} KB • PDF
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="rounded-full transition-colors self-end sm:self-auto flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-destructive/60 focus:ring-offset-2 hover:bg-destructive hover:text-white dark:hover:bg-destructive dark:hover:text-white cursor-pointer"
                            onClick={e => {
                                e.stopPropagation();
                                handleRemove();
                            }}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>

            {/* Validation Progress */}
            {isValidating && (
                <div className="mt-4 space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Validating document...</span>
                        <span className="text-muted-foreground">{validationProgress}%</span>
                    </div>
                    <Progress value={validationProgress} className="h-2" />
                </div>
            )}

            {/* Smart Contract Validation Details */}
            {showValidationDetails !== false && validationResult && (
                <Card className="p-4 mt-4">
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="font-medium flex items-center gap-2">
                                <Shield className="h-4 w-4 text-primary" />
                                Smart Contract Validation
                            </h4>
                            <Badge 
                                variant={validationResult.compliant ? "default" : "destructive"}
                            >
                                {validationResult.compliant ? "Compliant" : "Non-Compliant"}
                            </Badge>
                        </div>

                        <div className="text-sm text-muted-foreground">
                            <div className="flex justify-between">
                                <span>Stage:</span>
                                <span className="font-medium text-foreground">{validationResult.stage}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Validated:</span>
                                <span className="font-medium text-foreground">{new Date(validationResult.validation_timestamp).toLocaleString()}</span>
                            </div>
                        </div>

                        {validationResult.missing_fields && validationResult.missing_fields.length > 0 && (
                            <div className="space-y-2">
                                <div className="text-sm font-medium text-destructive">Missing Fields:</div>
                                <div className="space-y-1">
                                    {validationResult.missing_fields.map((field, index) => (
                                        <div key={index} className="flex items-start gap-2 text-sm text-destructive">
                                            <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                                            <span>{field}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {validationResult.invalid_fields && validationResult.invalid_fields.length > 0 && (
                            <div className="space-y-2">
                                <div className="text-sm font-medium text-amber-600">Invalid Fields:</div>
                                <div className="space-y-1">
                                    {validationResult.invalid_fields.map((field, index) => (
                                        <div key={index} className="flex items-start gap-2 text-sm text-amber-600">
                                            <XCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                                            <span>{field}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {validationResult.compliant && (
                            <div className="flex items-center gap-2 text-sm text-green-600">
                                <CheckCircle className="h-4 w-4" />
                                <span>All validation checks passed successfully</span>
                            </div>
                        )}
                    </div>
                </Card>
            )}

            {error && <InputError message={error} className={errorClassName} />}
        </div>
    );
};

export default SmartContractFileUploadArea;

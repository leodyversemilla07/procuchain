import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import { FileText, Upload, AlertCircle, CheckCircle } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import SmartContractFileUploadArea from '@/components/smart-contract-file-upload-area';
import ValidationStatusIndicator from '@/components/validation-status-indicator';
import SmartContractDashboard from '@/components/smart-contract-dashboard';

import { SmartContractValidationResult } from '@/types/smart-contracts';

interface SmartContractDocumentUploadPageProps {
    procurement_id: string;
    user?: {
        id: number;
        name: string;
        role: string;
    };
}

const SmartContractDocumentUploadPage: React.FC<SmartContractDocumentUploadPageProps> = ({ procurement_id }) => {
    const [summaryFile, setSummaryFile] = useState<File | null>(null);
    const [summaryDragging, setSummaryDragging] = useState(false);
    const [abstractFile, setAbstractFile] = useState<File | null>(null);
    const [abstractDragging, setAbstractDragging] = useState(false);
    
    const [summaryValidation, setSummaryValidation] = useState<SmartContractValidationResult | null>(null);
    const [abstractValidation, setAbstractValidation] = useState<SmartContractValidationResult | null>(null);
    const [showDashboard, setShowDashboard] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        summary_file: null as File | null,
        abstract_file: null as File | null,
        evaluator_names: '',
        summary_description: '',
        abstract_description: ''
    });

    const handleDragEvents = (
        setDragging: (dragging: boolean) => void
    ) => ({
        onDragEnter: (e: React.DragEvent) => {
            e.preventDefault();
            setDragging(true);
        },
        onDragLeave: (e: React.DragEvent) => {
            e.preventDefault();
            setDragging(false);
        },
        onDragOver: (e: React.DragEvent) => {
            e.preventDefault();
        },
        onDrop: (e: React.DragEvent) => {
            e.preventDefault();
            setDragging(false);
        },
    });

    const handleSummaryFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setSummaryFile(file);
        setData('summary_file', file);
    };

    const handleAbstractFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setAbstractFile(file);
        setData('abstract_file', file);
    };

    const handleSummaryValidation = (result: SmartContractValidationResult) => {
        setSummaryValidation(result);
        if (!result.compliant) {
            toast.warning('Summary validation issues found', {
                description: `${result.missing_fields.length + result.invalid_fields.length} issues detected`
            });
        }
    };

    const handleAbstractValidation = (result: SmartContractValidationResult) => {
        setAbstractValidation(result);
        if (!result.compliant) {
            toast.warning('Abstract validation issues found', {
                description: `${result.missing_fields.length + result.invalid_fields.length} issues detected`
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Client-side validation with smart contract results
        if (!summaryFile) {
            toast.error('Missing summary file', { description: 'Please upload the evaluation summary PDF.' });
            return;
        }

        if (!abstractFile) {
            toast.error('Missing abstract file', { description: 'Please upload the bid abstract PDF.' });
            return;
        }

        // Check smart contract validation results
        if (summaryValidation && !summaryValidation.compliant) {
            toast.error('Summary file validation failed', { 
                description: 'Please fix validation issues before submitting.' 
            });
            return;
        }

        if (abstractValidation && !abstractValidation.compliant) {
            toast.error('Abstract file validation failed', { 
                description: 'Please fix validation issues before submitting.' 
            });
            return;
        }

        // Submit with smart contract validation passed
        post('/bac-secretariat/procurement-stage/bid-evaluation-upload', {
            onSuccess: () => {
                toast.success('Documents uploaded successfully', {
                    description: 'Smart contract validation passed and documents are now on blockchain.'
                });
            },
            onError: () => {
                toast.error('Upload failed', {
                    description: 'Please check your files and try again.'
                });
            }
        });
    };

    const canSubmit = summaryFile && abstractFile && 
                     summaryValidation?.compliant && 
                     abstractValidation?.compliant && 
                     !processing;

    return (
        <AppLayout>
            <Head title={`Smart Contract Upload Demo - ${procurement_id}`} />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header with Smart Contract Status */}
                <div className="border-b pb-4 mb-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Smart Contract Document Upload</h1>
                            <p className="text-muted-foreground mt-1 text-sm md:text-base">
                                Upload documents with real-time smart contract validation
                            </p>
                        </div>
                        <div className="flex items-center gap-4">
                            <ValidationStatusIndicator
                                validationResult={summaryValidation || undefined}
                                showTooltip={true}
                                showText={true}
                            />
                            <Button
                                variant="outline"
                                onClick={() => setShowDashboard(!showDashboard)}
                                className="w-full sm:w-auto"
                            >
                                {showDashboard ? 'Hide' : 'Show'} Dashboard
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Smart Contract Dashboard (Collapsible) */}
                {showDashboard && (
                    <SmartContractDashboard
                        procurementId={procurement_id}
                        autoRefresh={true}
                        refreshInterval={30000}
                    />
                )}

                {/* Upload Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <SmartContractFileUploadArea
                                    label="Upload Evaluation Summary PDF"
                                    file={summaryFile}
                                    error={errors.summary_file}
                                    isDragging={summaryDragging}
                                    onFileChange={handleSummaryFileChange}
                                    {...handleDragEvents(setSummaryDragging)}
                                    onRemove={() => {
                                        setSummaryFile(null);
                                        setData('summary_file', null);
                                        setSummaryValidation(null);
                                    }}
                                    inputId="summary-file"
                                    required={true}
                                    documentType="Evaluation Report"
                                    stage="Bid Evaluation"
                                    procurementId={procurement_id}
                                    enableSmartValidation={true}
                                    showValidationDetails={true}
                                    onValidationComplete={handleSummaryValidation}
                                />
                   

                        {/* Abstract File Upload with Smart Contract Validation */}
                        <Card className="border-l-4 border-l-green-500">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="w-5 h-5" />
                                    Bid Abstract
                                    <ValidationStatusIndicator
                                        validationResult={abstractValidation || undefined}
                                        size="sm"
                                        showText={false}
                                    />
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <SmartContractFileUploadArea
                                    label="Upload Bid Abstract PDF"
                                    file={abstractFile}
                                    error={errors.abstract_file}
                                    isDragging={abstractDragging}
                                    onFileChange={handleAbstractFileChange}
                                    {...handleDragEvents(setAbstractDragging)}
                                    onRemove={() => {
                                        setAbstractFile(null);
                                        setData('abstract_file', null);
                                        setAbstractValidation(null);
                                    }}
                                    inputId="abstract-file"
                                    required={true}
                                    documentType="Evaluation Report"
                                    stage="Bid Evaluation"
                                    procurementId={procurement_id}
                                    enableSmartValidation={true}
                                    showValidationDetails={true}
                                    onValidationComplete={handleAbstractValidation}
                                />

                                <div>
                                    <Label htmlFor="abstract-description">Description (Optional)</Label>
                                    <Textarea
                                        id="abstract-description"
                                        placeholder="Brief description of the bid abstract..."
                                        value={data.abstract_description}
                                        onChange={(e) => setData('abstract_description', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Evaluator Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Evaluation Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div>
                                <Label htmlFor="evaluator-names">Evaluator Names *</Label>
                                <Textarea
                                    id="evaluator-names"
                                    placeholder="Enter the names of all evaluators involved in this evaluation..."
                                    value={data.evaluator_names}
                                    onChange={(e) => setData('evaluator_names', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                                {errors.evaluator_names && (
                                    <p className="text-sm text-destructive mt-1">{errors.evaluator_names}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Section */}
                    <Card className="bg-muted/50">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-4">
                                    {canSubmit ? (
                                        <div className="flex items-center gap-2 text-green-600">
                                            <CheckCircle className="w-5 h-5" />
                                            <span className="font-medium">Ready to submit</span>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-2 text-amber-600">
                                            <AlertCircle className="w-5 h-5" />
                                            <span className="font-medium">
                                                {!summaryFile || !abstractFile 
                                                    ? 'Upload required files' 
                                                    : 'Complete smart contract validation'
                                                }
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={!canSubmit}
                                    className="min-w-[120px]"
                                >
                                    {processing ? (
                                        <>
                                            <Upload className="w-4 h-4 mr-2 animate-pulse" />
                                            Uploading...
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="w-4 h-4 mr-2" />
                                            Upload Documents
                                        </>
                                    )}
                                </Button>
                            </div>

                            {/* Smart Contract Validation Summary */}
                            <div className="mt-4 p-4 bg-background rounded-lg border">
                                <h4 className="font-medium text-sm mb-2">Smart Contract Validation Summary:</h4>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span>Summary File:</span>
                                        <ValidationStatusIndicator
                                            validationResult={summaryValidation || undefined}
                                            size="sm"
                                        />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Abstract File:</span>
                                        <ValidationStatusIndicator
                                            validationResult={abstractValidation || undefined}
                                            size="sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
};

export default SmartContractDocumentUploadPage;

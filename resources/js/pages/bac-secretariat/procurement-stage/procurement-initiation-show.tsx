import { Head, router } from '@inertiajs/react';
import React, { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { type BreadcrumbItem } from '@/types';
import type { DocumentGuide, DocumentItem } from '@/types/document-guide';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

import AppLayout from '@/layouts/app-layout';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import FileUploadArea from '@/components/file-upload-area';
import { uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';

import { FileText, Info, AlertTriangle, CheckCircle2, Plus, X, Send, Upload, CheckCheck } from 'lucide-react';

interface ProcurementInitiationShowProps {
    pr_number: string;
    documentGuide: DocumentGuide;
    uploadedDocuments: string[];
    currentStage?: string;
    currentStatus?: string;
    isStageComplete?: boolean;
}

export default function ProcurementInitiationShow({
    pr_number,
    documentGuide,
    uploadedDocuments,
    isStageComplete = false,
}: ProcurementInitiationShowProps) {
    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
        { title: 'Upload Documents', href: '#' },
    ]);

    // Track which optional documents have been added by the user
    const [addedOptionalDocs, setAddedOptionalDocs] = useState<string[]>([]);
    const [selectedOptionalDocType, setSelectedOptionalDocType] = useState<string>('');
    const [showUploadDialog, setShowUploadDialog] = useState(false);
    const [showCompleteDialog, setShowCompleteDialog] = useState(false);
    const [uploadDialogData, setUploadDialogData] = useState<{
        docValue: string;
        docName: string;
        isRequired: boolean;
    } | null>(null);
    
    // Track drag states for file uploads
    const [dragStates, setDragStates] = useState<Record<string, boolean>>({});
    
    // Track file selections (before upload)
    const [selectedFiles, setSelectedFiles] = useState<Record<string, File | null>>({});

    // Calculate mandatory document progress
    const mandatoryProgress = useMemo(() => {
        const uploadedMandatory = documentGuide.required_documents.filter((doc) =>
            uploadedDocuments.includes(doc.value)
        ).length;
        const totalMandatory = documentGuide.required_documents.length;
        const isComplete = uploadedMandatory === totalMandatory;
        const percentage = totalMandatory > 0 ? Math.round((uploadedMandatory / totalMandatory) * 100) : 100;
        
        return {
            uploaded: uploadedMandatory,
            total: totalMandatory,
            isComplete,
            percentage,
        };
    }, [documentGuide.required_documents, uploadedDocuments]);

    // Calculate optional document progress
    const optionalProgress = useMemo(() => {
        const uploadedOptional = addedOptionalDocs.filter((docValue) =>
            uploadedDocuments.includes(docValue)
        ).length;
        
        return {
            uploaded: uploadedOptional,
            total: addedOptionalDocs.length,
        };
    }, [addedOptionalDocs, uploadedDocuments]);

    // Available optional documents (not yet added)
    const availableOptionalDocs = useMemo(() => {
        return documentGuide.optional_documents.filter(
            (doc) => !addedOptionalDocs.includes(doc.value)
        );
    }, [documentGuide.optional_documents, addedOptionalDocs]);

    const addOptionalDocument = useCallback(() => {
        if (!selectedOptionalDocType) return;
        
        setAddedOptionalDocs((prev) => [...prev, selectedOptionalDocType]);
        setSelectedOptionalDocType('');
    }, [selectedOptionalDocType]);

    const removeOptionalDocument = useCallback((docValue: string) => {
        setAddedOptionalDocs((prev) => prev.filter((value) => value !== docValue));
    }, []);

    const getDocumentItem = useCallback(
        (docValue: string): DocumentItem | undefined => {
            return documentGuide.optional_documents.find((doc) => doc.value === docValue);
        },
        [documentGuide.optional_documents]
    );

    const validateFile = useCallback((file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            toast.error('File too large', {
                description: 'File size must not exceed 10MB.',
            });
            return false;
        }
        if (file.type && file.type !== 'application/pdf') {
            toast.error('Invalid file type', {
                description: 'Only PDF files are allowed.',
            });
            return false;
        }
        if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
            toast.error('Invalid file type', {
                description: 'Only PDF files are allowed.',
            });
            return false;
        }
        return true;
    }, []);

    const handleFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>, docValue: string) => {
            const newFile = e.target.files?.[0] || null;

            if (newFile && !validateFile(newFile)) {
                e.target.value = '';
                return;
            }

            setSelectedFiles((prev) => ({ ...prev, [docValue]: newFile }));
        },
        [validateFile]
    );

    const handleDragEnter = useCallback(
        (e: React.DragEvent, docValue: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [docValue]: true }));
        },
        []
    );

    const handleDragLeave = useCallback(
        (e: React.DragEvent, docValue: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [docValue]: false }));
        },
        []
    );

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent, docValue: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [docValue]: false }));

            const file = e.dataTransfer.files[0];
            if (file && validateFile(file)) {
                setSelectedFiles((prev) => ({ ...prev, [docValue]: file }));
            }
        },
        [validateFile]
    );

    const handleRemoveFile = useCallback((docValue: string) => {
        setSelectedFiles((prev) => {
            const updated = { ...prev };
            delete updated[docValue];
            return updated;
        });
    }, []);

    const openUploadDialog = useCallback((docValue: string, docName: string, isRequired: boolean) => {
        const file = selectedFiles[docValue];
        
        if (!file) {
            toast.error('No file selected', {
                description: 'Please select a file to upload.',
            });
            return;
        }

        setUploadDialogData({ docValue, docName, isRequired });
        setShowUploadDialog(true);
    }, [selectedFiles]);

    const handleUploadClick = useCallback(
        async (documentValue: string, documentName: string, isRequired: boolean) => {
            const file = selectedFiles[documentValue];
            
            if (!file) {
                toast.error('No file selected', {
                    description: 'Please select a file to upload.',
                });
                return;
            }

            const uploadToast = toast.loading(`Uploading ${isRequired ? 'mandatory' : 'optional'} document...`);
            
            try {
                // Use Inertia router.post for file upload
                router.post(
                    uploadSingleDocument(pr_number).url,
                    {
                        document_file: file,
                        document_type: documentValue,
                        description: documentName,
                    },
                    {
                        onSuccess: () => {
                            toast.success(
                                `${isRequired ? 'Mandatory' : 'Optional'} document uploaded successfully!`,
                                {
                                    id: uploadToast,
                                    description: `${documentName} has been uploaded.`,
                                }
                            );
                            // Clear the selected file after successful upload
                            handleRemoveFile(documentValue);
                            setShowUploadDialog(false);
                            setUploadDialogData(null);
                        },
                        onError: (errors) => {
                            const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to upload document';
                            toast.error('Upload failed', {
                                id: uploadToast,
                                description: errorMessage,
                            });
                        },
                        preserveScroll: true,
                        only: ['uploadedDocuments'],
                    }
                );
                
            } catch (error: unknown) {
                const axiosError = error as { response?: { data?: { message?: string } } };
                const errorMessage = axiosError.response?.data?.message || 'Failed to upload document';
                toast.error('Upload failed', {
                    id: uploadToast,
                    description: errorMessage,
                });
            }
        },
        [pr_number, selectedFiles, handleRemoveFile],
    );

    const confirmUpload = useCallback(() => {
        if (uploadDialogData) {
            handleUploadClick(
                uploadDialogData.docValue,
                uploadDialogData.docName,
                uploadDialogData.isRequired
            );
        }
    }, [uploadDialogData, handleUploadClick]);

    const handleMarkStageComplete = useCallback(() => {
        setShowCompleteDialog(true);
    }, []);

    const confirmMarkStageComplete = useCallback(() => {
        const completeToast = toast.loading('Marking stage as complete...');

        router.post(
            `/bac-secretariat/procurement-initiation/${pr_number}/complete`,
            {},
            {
                onSuccess: () => {
                    toast.success('Stage marked as complete!', {
                        id: completeToast,
                        description: 'The Procurement Initiation stage has been completed.',
                    });
                    setShowCompleteDialog(false);
                },
                onError: (errors) => {
                    const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to mark stage as complete';
                    toast.error('Failed to complete stage', {
                        id: completeToast,
                        description: errorMessage,
                    });
                },
            }
        );
    }, [pr_number]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Upload Documents - ${pr_number}`} />

            <div
                className="w-full space-y-4 p-3 sm:space-y-6 sm:p-4 md:p-6 lg:p-8"
                role="main"
                aria-labelledby="page-title"
            >
                {/* Header Section */}
                <HeroCard
                    icon={FileText}
                    title="Upload Procurement Documents"
                    description="Upload mandatory and optional documents for this procurement. You can upload them progressively and return later to complete."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary transition-colors duration-200 hover:bg-primary/20 md:px-3 md:py-1.5 md:text-sm">
                                {pr_number}
                            </Badge>
                            <Badge className="rounded-md bg-chart-2/10 px-2 py-1 text-xs text-chart-2 dark:bg-chart-2/20 dark:text-chart-2 md:px-3 md:py-1.5 md:text-sm">
                                Document Upload
                            </Badge>
                            <Badge 
                                variant={mandatoryProgress.isComplete ? "default" : "destructive"}
                                className="rounded-md px-2 py-1 text-xs md:px-3 md:py-1.5 md:text-sm"
                            >
                                {mandatoryProgress.uploaded} / {mandatoryProgress.total} Required
                            </Badge>
                        </div>
                    }
                />

                {/* Mandatory Document Alert */}
                {!mandatoryProgress.isComplete && (
                    <Alert variant="destructive" className="border-2">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Mandatory Documents Required:</strong> You must upload all{' '}
                            {mandatoryProgress.total} mandatory documents before this procurement can proceed.
                            Currently {mandatoryProgress.uploaded} of {mandatoryProgress.total} uploaded (
                            {mandatoryProgress.percentage}% complete).
                        </AlertDescription>
                    </Alert>
                )}

                {/* Success Alert */}
                {mandatoryProgress.isComplete && !isStageComplete && (
                    <Alert className="border-2 border-green-500/50 bg-green-50/50 dark:bg-green-950/20">
                        <Info className="h-4 w-4 text-green-600 dark:text-green-500" />
                        <AlertDescription className="text-green-700 dark:text-green-400">
                            <strong>All Mandatory Documents Uploaded:</strong> You have successfully uploaded
                            all required documents. You can now proceed with optional documents or mark this stage as complete.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Stage Complete Alert */}
                {isStageComplete && (
                    <Alert className="border-2 border-green-500/50 bg-green-50/50 dark:bg-green-950/20">
                        <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-500" />
                        <AlertDescription className="text-green-700 dark:text-green-400">
                            <strong>Stage Complete:</strong> The Procurement Initiation stage has been marked as complete.
                            You can proceed to the Pre-Procurement Conference stage.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Mark Stage as Complete Button */}
                {mandatoryProgress.isComplete && !isStageComplete && (
                    <Card className="border-2 border-green-500/50 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                        <CardContent className="p-4 sm:p-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-lg bg-green-600/10 p-2 dark:bg-green-500/10">
                                        <CheckCheck className="h-5 w-5 text-green-600 dark:text-green-500 sm:h-6 sm:w-6" />
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="text-base font-semibold text-green-700 dark:text-green-400 sm:text-lg">
                                            Ready to Complete Stage
                                        </h3>
                                        <p className="mt-1 text-sm text-green-600/80 dark:text-green-400/80">
                                            All mandatory documents have been uploaded. Click the button to mark this stage as complete and proceed to the next stage.
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    onClick={handleMarkStageComplete}
                                    className="w-full gap-2 bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700 sm:w-auto"
                                    size="lg"
                                >
                                    <CheckCheck className="h-5 w-5" />
                                    Mark Stage as Complete
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Mandatory Documents Section */}
                <Card className="border-2 border-primary/20 bg-primary/5">
                    <CardHeader className="p-4 sm:p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                            <div className="rounded-lg bg-primary/10 p-3">
                                <Upload className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                            </div>
                            <div className="flex-1">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 className="text-base font-semibold sm:text-lg">Required Documents</h3>
                                    <Badge
                                        variant={
                                            mandatoryProgress.isComplete ? 'default' : 'outline'
                                        }
                                        className="w-fit"
                                    >
                                        {mandatoryProgress.uploaded} / {mandatoryProgress.total} Uploaded
                                    </Badge>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                    Upload all mandatory documents required by RA 9184. Files can be uploaded
                                    progressively.
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Mandatory Document Upload Areas */}
                <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {documentGuide.required_documents.map((doc) => {
                        const isUploaded = uploadedDocuments.includes(doc.value);
                        const file = selectedFiles[doc.value];
                        const isDragging = dragStates[doc.value] || false;

                        return (
                            <Card key={doc.value}>
                                <CardHeader className="p-4 pb-3 sm:p-6 sm:pb-4">
                                    <div className="flex items-start gap-3">
                                        <FileText className="mt-0.5 h-5 w-5 text-primary" />
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-medium">{doc.display_name}</h4>
                                                <Badge variant="destructive" className="text-xs">
                                                    Required
                                                </Badge>
                                                {isUploaded && (
                                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {doc.description}
                                            </p>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                                    {isUploaded && !file ? (
                                        <div className="rounded-lg border border-green-200 bg-green-50/50 p-4 dark:border-green-900/50 dark:bg-green-950/20">
                                            <div className="flex items-center gap-2">
                                                <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-500" />
                                                <div className="flex-1">
                                                    <p className="font-medium text-green-700 dark:text-green-400">
                                                        Document Uploaded
                                                    </p>
                                                    <p className="text-xs text-green-600/80 dark:text-green-400/80">
                                                        This document has been successfully uploaded to the blockchain
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <FileUploadArea
                                            label=""
                                            file={file}
                                            isDragging={isDragging}
                                            onFileChange={(e) => handleFileChange(e, doc.value)}
                                            onDragEnter={(e) => handleDragEnter(e, doc.value)}
                                            onDragLeave={(e) => handleDragLeave(e, doc.value)}
                                            onDragOver={handleDragOver}
                                            onDrop={(e) => handleDrop(e, doc.value)}
                                            onRemove={() => handleRemoveFile(doc.value)}
                                            inputId={`file-${doc.value}`}
                                            required={!isUploaded}
                                        />
                                    )}
                                </CardContent>
                                {file && (
                                    <CardFooter className="p-4 pt-0 sm:p-6 sm:pt-0">
                                        <Button
                                            type="button"
                                            onClick={() => openUploadDialog(doc.value, doc.display_name, true)}
                                            className="w-full gap-2"
                                        >
                                            <Send className="h-4 w-4" />
                                            {isUploaded ? 'Replace' : 'Upload'}
                                        </Button>
                                    </CardFooter>
                                )}
                            </Card>
                        );
                    })}
                </div>

                {/* Optional Documents Section */}
                <Card className="border-2 border-primary/20 bg-primary/5">
                    <CardHeader className="p-4 sm:p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                            <div className="rounded-lg bg-primary/10 p-3">
                                <FileText className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                            </div>
                            <div className="flex-1">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 className="text-base font-semibold sm:text-lg">
                                        Optional Supporting Documents
                                    </h3>
                                    <Badge variant="outline" className="w-fit">
                                        {optionalProgress.uploaded} / {optionalProgress.total} Uploaded
                                    </Badge>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                    Add any additional supporting documents that may strengthen your
                                    procurement request.
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Optional Document Upload Areas */}
                <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {/* Add Optional Document Selector */}
                    {availableOptionalDocs.length > 0 && (
                        <Card>
                            <CardContent className="p-4 pt-4 sm:p-6 sm:pt-6">
                                <div className="flex flex-col gap-3">
                                    <div className="flex-1">
                                        <Select
                                            value={selectedOptionalDocType}
                                            onValueChange={setSelectedOptionalDocType}
                                        >
                                            <SelectTrigger className="h-auto min-h-10">
                                                <SelectValue placeholder="Select document type to add" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableOptionalDocs.map((doc) => (
                                                    <SelectItem
                                                        key={doc.value}
                                                        value={doc.value}
                                                        className="py-3"
                                                    >
                                                        <div className="flex flex-col gap-1">
                                                            <span className="font-medium">
                                                                {doc.display_name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground line-clamp-2">
                                                                {doc.description}
                                                            </span>
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={addOptionalDocument}
                                        disabled={!selectedOptionalDocType}
                                        className="w-full gap-2"
                                    >
                                        <Plus className="h-4 w-4" />
                                        Add Document
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Added Optional Documents */}
                    {addedOptionalDocs.map((docValue) => {
                        const doc = getDocumentItem(docValue);
                        if (!doc) return null;

                        const isUploaded = uploadedDocuments.includes(docValue);
                        const file = selectedFiles[docValue];
                        const isDragging = dragStates[docValue] || false;

                        return (
                            <Card key={docValue}>
                                <CardHeader className="p-4 pb-3 sm:p-6 sm:pb-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <FileText className="mt-0.5 h-5 w-5 text-primary" />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="font-medium">{doc.display_name}</h4>
                                                    <Badge variant="outline" className="text-xs">
                                                        Optional
                                                    </Badge>
                                                    {isUploaded && (
                                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {doc.description}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeOptionalDocument(docValue)}
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                                    {isUploaded && !file ? (
                                        <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                                            <div className="flex items-center gap-2">
                                                <CheckCircle2 className="h-5 w-5 text-blue-600 dark:text-blue-500" />
                                                <div className="flex-1">
                                                    <p className="font-medium text-blue-700 dark:text-blue-400">
                                                        Document Uploaded
                                                    </p>
                                                    <p className="text-xs text-blue-600/80 dark:text-blue-400/80">
                                                        This document has been successfully uploaded to the blockchain
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <FileUploadArea
                                            label=""
                                            file={file}
                                            isDragging={isDragging}
                                            onFileChange={(e) => handleFileChange(e, docValue)}
                                            onDragEnter={(e) => handleDragEnter(e, docValue)}
                                            onDragLeave={(e) => handleDragLeave(e, docValue)}
                                            onDragOver={handleDragOver}
                                            onDrop={(e) => handleDrop(e, docValue)}
                                            onRemove={() => handleRemoveFile(docValue)}
                                            inputId={`file-optional-${docValue}`}
                                            required={false}
                                        />
                                    )}
                                </CardContent>
                                {file && (
                                    <CardFooter className="p-4 pt-0 sm:p-6 sm:pt-0">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => openUploadDialog(docValue, doc.display_name, false)}
                                            className="w-full gap-2"
                                            size="sm"
                                        >
                                            <Send className="h-4 w-4" />
                                            {isUploaded ? 'Replace' : 'Upload'}
                                        </Button>
                                    </CardFooter>
                                )}
                            </Card>
                        );
                    })}

                    {/* Empty State */}
                    {addedOptionalDocs.length === 0 && availableOptionalDocs.length === 0 && (
                        <Card className="md:col-span-2 lg:col-span-3">
                            <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="mb-3 h-12 w-12 text-muted-foreground/50" />
                                <p className="text-sm font-medium text-muted-foreground">
                                    All optional document types have been added
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Info Alerts */}
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        <strong>RA 9184 Compliance:</strong> All mandatory documents must be uploaded in accordance
                        with the Government Procurement Reform Act. All documents must be in PDF format. 
                        Maximum file size: 10MB.
                    </AlertDescription>
                </Alert>

                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        <strong>Optional Documents:</strong> While not required, additional documents can help
                        expedite the procurement review process. Select document types from the dropdown to add them.
                    </AlertDescription>
                </Alert>

                {/* Upload Confirmation Dialog */}
                <AlertDialog open={showUploadDialog} onOpenChange={setShowUploadDialog}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Confirm Document Upload</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to upload this {uploadDialogData?.isRequired ? 'mandatory' : 'optional'} document?
                                
                                {uploadDialogData && selectedFiles[uploadDialogData.docValue] && (
                                    <div className="mt-4 space-y-2 rounded-lg bg-muted p-3">
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">Document Type:</span>
                                            <span className="text-right">{uploadDialogData.docName}</span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Name:</span>
                                            <span className="truncate text-right ml-2">
                                                {selectedFiles[uploadDialogData.docValue]?.name}
                                            </span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Size:</span>
                                            <span>
                                                {((selectedFiles[uploadDialogData.docValue]?.size || 0) / 1024 / 1024).toFixed(2)} MB
                                            </span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">Type:</span>
                                            <Badge variant={uploadDialogData.isRequired ? "destructive" : "outline"} className="text-xs">
                                                {uploadDialogData.isRequired ? 'Required' : 'Optional'}
                                            </Badge>
                                        </div>
                                    </div>
                                )}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmUpload}>
                                <Send className="mr-2 h-4 w-4" />
                                Upload Document
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Mark Stage Complete Confirmation Dialog */}
                <AlertDialog open={showCompleteDialog} onOpenChange={setShowCompleteDialog}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Mark Stage as Complete</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to mark the Procurement Initiation stage as complete?
                                
                                <div className="mt-4 space-y-2 rounded-lg bg-muted p-3">
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">PR Number:</span>
                                        <span>{pr_number}</span>
                                    </div>
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">Required Documents:</span>
                                        <span className="text-green-600 dark:text-green-400 font-medium">
                                            {mandatoryProgress.uploaded} / {mandatoryProgress.total} Uploaded ✓
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">Optional Documents:</span>
                                        <span>{optionalProgress.uploaded} / {optionalProgress.total} Uploaded</span>
                                    </div>
                                </div>

                                <p className="mt-3 text-sm font-medium">
                                    After completing this stage, you can proceed to the Pre-Procurement Conference stage.
                                </p>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmMarkStageComplete} className="bg-green-600 hover:bg-green-700">
                                <CheckCheck className="mr-2 h-4 w-4" />
                                Mark as Complete
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
}

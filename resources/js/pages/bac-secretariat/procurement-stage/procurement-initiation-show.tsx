import { Head, router } from '@inertiajs/react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { type BreadcrumbItem } from '@/types';
import type { DocumentGuide, DocumentItem } from '@/types/document-guide';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
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
import { uploadSingleDocument, markStageComplete } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';

import { FileText, CheckCircle2, Plus, X, AlertCircle } from 'lucide-react';

interface ProcurementInitiationShowProps {
    procurement?: {
        pr_number: string;
        title: string;
        status?: string;
        stage?: string;
    };
    documentGuide: DocumentGuide;
    uploadedDocuments: string[];
    currentStage?: string;
    currentStatus?: string;
    isStageComplete?: boolean;
}

export default function ProcurementInitiationShow({
    procurement,
    documentGuide,
    uploadedDocuments,
    currentStage,
    isStageComplete = false,
}: ProcurementInitiationShowProps) {
    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Documents - ${procurement?.pr_number || 'Unknown'}${procurement?.title ? ': ' + procurement.title : ''}`, href: '#' },
    ]);

    // Determine if the stage is effectively complete (either marked complete or moved to next stage)
    const isStageFinished = isStageComplete || (currentStage && currentStage !== 'procurement_initiation');

    // Track which optional documents have been added by the user
    const [addedOptionalDocs, setAddedOptionalDocs] = useState<string[]>([]);
    const [selectedOptionalDocType, setSelectedOptionalDocType] = useState<string>('');
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
        isRequired: boolean;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
        isRequired: false,
    });
    const [showCompleteDialog, setShowCompleteDialog] = useState(false);
    
    // Track drag states for file uploads
    const [dragStates, setDragStates] = useState<Record<string, boolean>>({});
    
    // Track file selections (before upload)
    const [selectedFiles, setSelectedFiles] = useState<Record<string, File | null>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);

    // Initialize addedOptionalDocs with already uploaded optional documents
    useEffect(() => {
        const optionalDocValues = documentGuide.optional_documents.map(doc => doc.value);
        const uploadedOptionalDocs = uploadedDocuments.filter(docValue => 
            optionalDocValues.includes(docValue)
        );
        setAddedOptionalDocs(uploadedOptionalDocs);
    }, [documentGuide.optional_documents, uploadedDocuments]);

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

        setConfirmDialog({ open: true, documentValue: docValue, documentName: docName, isRequired });
    }, [selectedFiles]);

    const handleUploadClick = useCallback(() => {
        const file = selectedFiles[confirmDialog.documentValue];
        
        if (!file) {
            toast.error('No file selected', {
                description: 'Please select a file to upload.',
            });
            return;
        }

        if (!procurement?.pr_number) {
            toast.error('Procurement data missing', {
                description: 'Unable to upload document. Please refresh the page.',
            });
            return;
        }

        const uploadToast = toast.loading('Uploading document...');
        setIsUploading(true);
        
        // Use Inertia router.post for file upload with Wayfinder
        router.post(
            uploadSingleDocument.url(procurement.pr_number),
            {
                document_file: file,
                document_type: confirmDialog.documentValue,
                description: confirmDialog.documentName,
            },
            {
                onSuccess: () => {
                    toast.success('Document uploaded successfully!', {
                        id: uploadToast,
                        description: `${confirmDialog.documentName} has been uploaded.`,
                    });
                    // Clear the selected file after successful upload
                    handleRemoveFile(confirmDialog.documentValue);
                    setConfirmDialog({ open: false, documentValue: '', documentName: '', isRequired: false });
                    setIsUploading(false);
                },
                onError: (errors) => {
                    const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to upload document';
                    toast.error('Upload failed', {
                        id: uploadToast,
                        description: errorMessage,
                    });
                    setIsUploading(false);
                },
                preserveScroll: true,
                only: ['uploadedDocuments'],
                forceFormData: true,
            }
        );
    }, [procurement?.pr_number, selectedFiles, confirmDialog, handleRemoveFile]);

    const handleMarkStageComplete = useCallback(() => {
        setShowCompleteDialog(true);
    }, []);

    const confirmMarkStageComplete = useCallback(() => {
        if (!procurement?.pr_number) {
            toast.error('Procurement data missing', {
                description: 'Unable to mark stage complete. Please refresh the page.',
            });
            return;
        }

        const completeToast = toast.loading('Marking stage as complete...');
        setIsMarkingComplete(true);

        router.post(
            markStageComplete.url(procurement.pr_number),
            {},
            {
                onSuccess: () => {
                    toast.success('Stage marked as complete!', {
                        id: completeToast,
                        description: 'The Procurement Initiation stage has been completed.',
                    });
                    setShowCompleteDialog(false);
                    setIsMarkingComplete(false);
                },
                onError: (errors) => {
                    const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to mark stage as complete';
                    toast.error('Failed to complete stage', {
                        id: completeToast,
                        description: errorMessage,
                    });
                    setIsMarkingComplete(false);
                },
                preserveScroll: true,
            }
        );
    }, [procurement?.pr_number]);

    const uploadedRequiredCount = documentGuide
        ? documentGuide.required_documents.filter((doc) => uploadedDocuments.includes(doc.value)).length
        : 0;

    const calculatedPercentage =
        documentGuide && documentGuide.counts.required_count > 0
            ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100)
            : 100;

    const allRequiredUploaded = documentGuide && uploadedRequiredCount === documentGuide.counts.required_count;

    if (!procurement) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Upload Documents" />
                <div className="flex h-full items-center justify-center">
                    <p className="text-muted-foreground">Loading procurement data...</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Upload Documents - ${procurement.pr_number}`} />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <FileText className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Procurement Initiation</h1>
                    </div>
                    <p className="text-muted-foreground text-sm sm:text-base">
                        Upload documents for procurement
                        <span className="text-foreground font-medium"> #{procurement?.pr_number || 'Unknown'}</span>
                        {procurement?.title && (
                            <>
                                :<span className="text-foreground font-medium italic"> {procurement.title}</span>
                            </>
                        )}
                    </p>
                </div>

                <div className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        {/* Document Upload Progress */}
                        {documentGuide && (
                            <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                                <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                    <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                        <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                        Upload Progress
                                    </CardTitle>
                                    <CardDescription className="text-sm">Track your document upload progress</CardDescription>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    {/* Progress Overview */}
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Completion</span>
                                            <span className="font-semibold">
                                                {uploadedRequiredCount}/{documentGuide.counts.required_count} required
                                            </span>
                                        </div>
                                        <Progress value={calculatedPercentage} className="h-2" />
                                        <p className="text-xs text-muted-foreground">
                                            {allRequiredUploaded ? (
                                                <span className="text-green-600 dark:text-green-500 flex items-center gap-1">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    All required documents uploaded
                                                </span>
                                            ) : (
                                                <span className="text-amber-600 dark:text-amber-500 flex items-center gap-1">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {documentGuide.counts.required_count - uploadedRequiredCount} required document
                                                    {documentGuide.counts.required_count - uploadedRequiredCount !== 1 ? 's' : ''} remaining
                                                </span>
                                            )}
                                        </p>
                                    </div>

                                    {/* Stage Info */}
                                    <div className="rounded-lg bg-muted/50 p-3 text-xs space-y-1">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Stage:</span>
                                            <span className="font-medium">{documentGuide.stage_display_name}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Phase:</span>
                                            <span className="font-medium capitalize">{documentGuide.phase.replace('_', ' ')}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Required:</span>
                                            <Badge variant="secondary" className="text-xs">
                                                {documentGuide.counts.required_count}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Optional:</span>
                                            <Badge variant="outline" className="text-xs">
                                                {documentGuide.counts.optional_count}
                                            </Badge>
                                        </div>
                                    </div>

                                </CardContent>
                            </Card>
                        )}

                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Document Upload
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Upload required and optional documents for Procurement Initiation. Files will be permanently saved.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-6">
                                {/* Required Documents */}
                                {documentGuide && documentGuide.required_documents.length > 0 && (
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-semibold">Required Documents</h3>
                                            <Badge variant="secondary" className="text-xs">
                                                {documentGuide.counts.required_count}
                                            </Badge>
                                        </div>
                                        <div className="space-y-4">
                                            {documentGuide.required_documents.map((doc) => {
                                                const isUploaded = uploadedDocuments.includes(doc.value);
                                                const file = selectedFiles[doc.value];
                                                const isDragging = dragStates[doc.value] || false;

                                                return (
                                                    <div key={doc.value} className="space-y-2">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="flex-1">
                                                                <p className="text-sm font-medium">{doc.display_name}</p>
                                                                {doc.description && (
                                                                    <p className="text-xs text-muted-foreground">{doc.description}</p>
                                                                )}
                                                            </div>
                                                            {isUploaded && (
                                                                <Badge variant="outline" className="text-xs text-green-600 dark:text-green-500">
                                                                    <CheckCircle2 className="h-3 w-3 mr-1" />
                                                                    Uploaded
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {!isUploaded && (
                                                            <div className="flex gap-2">
                                                                <div className="flex-1">
                                                                    <FileUploadArea
                                                                        label=""
                                                                        file={file || null}
                                                                        isDragging={isDragging}
                                                                        onFileChange={(e) => handleFileChange(e, doc.value)}
                                                                        onDragEnter={(e) => handleDragEnter(e, doc.value)}
                                                                        onDragLeave={(e) => handleDragLeave(e, doc.value)}
                                                                        onDragOver={handleDragOver}
                                                                        onDrop={(e) => handleDrop(e, doc.value)}
                                                                        onRemove={() => handleRemoveFile(doc.value)}
                                                                        inputId={`file-${doc.value}`}
                                                                        required
                                                                    />
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    onClick={() => openUploadDialog(doc.value, doc.display_name, true)}
                                                                    disabled={!file || isUploading}
                                                                    className="self-start mt-0 h-[120px]"
                                                                >
                                                                    Upload
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* Optional Documents */}
                                {documentGuide && documentGuide.optional_documents.length > 0 && (
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-semibold">Optional Documents</h3>
                                            <Badge variant="outline" className="text-xs">
                                                {documentGuide.counts.optional_count}
                                            </Badge>
                                        </div>

                                        {/* Add Optional Document Selector */}
                                        {availableOptionalDocs.length > 0 && (
                                            <div className="flex gap-2">
                                                <Select
                                                    value={selectedOptionalDocType}
                                                    onValueChange={setSelectedOptionalDocType}
                                                >
                                                    <SelectTrigger className="flex-1">
                                                        <SelectValue placeholder="Select document type to add" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {availableOptionalDocs.map((doc) => (
                                                            <SelectItem key={doc.value} value={doc.value}>
                                                                {doc.display_name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    type="button"
                                                    onClick={addOptionalDocument}
                                                    disabled={!selectedOptionalDocType}
                                                    variant="outline"
                                                >
                                                    <Plus className="h-4 w-4 mr-2" />
                                                    Add
                                                </Button>
                                            </div>
                                        )}

                                        {/* Added Optional Documents */}
                                        <div className="space-y-4">
                                            {addedOptionalDocs.map((docValue) => {
                                                const doc = getDocumentItem(docValue);
                                                if (!doc) return null;

                                                const isUploaded = uploadedDocuments.includes(docValue);
                                                const file = selectedFiles[docValue];
                                                const isDragging = dragStates[docValue] || false;

                                                return (
                                                    <div key={docValue} className="space-y-2">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="flex-1">
                                                                <p className="text-sm font-medium">{doc.display_name}</p>
                                                                {doc.description && (
                                                                    <p className="text-xs text-muted-foreground">{doc.description}</p>
                                                                )}
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                {isUploaded && (
                                                                    <Badge variant="outline" className="text-xs text-green-600 dark:text-green-500">
                                                                        <CheckCircle2 className="h-3 w-3 mr-1" />
                                                                        Uploaded
                                                                    </Badge>
                                                                )}
                                                                {!isUploaded && (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => removeOptionalDocument(docValue)}
                                                                    >
                                                                        <X className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </div>
                                                        {!isUploaded && (
                                                            <div className="flex gap-2">
                                                                <div className="flex-1">
                                                                    <FileUploadArea
                                                                        label=""
                                                                        file={file || null}
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
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    variant="secondary"
                                                                    onClick={() => openUploadDialog(docValue, doc.display_name, false)}
                                                                    disabled={!file || isUploading}
                                                                    className="self-start mt-0 h-[120px]"
                                                                >
                                                                    Upload
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </CardContent>

                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                {isStageFinished ? (
                                    <div className="w-full rounded-lg bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 p-4">
                                        <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                                            <CheckCircle2 className="h-5 w-5" />
                                            <div>
                                                <p className="font-semibold">Stage Completed</p>
                                                <p className="text-sm">This stage has been marked as complete.</p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <Button
                                        type="button"
                                        disabled={!allRequiredUploaded || isUploading || isMarkingComplete}
                                        onClick={handleMarkStageComplete}
                                        className="flex h-10 w-full items-center gap-2 text-sm sm:h-11 sm:text-base"
                                    >
                                        {isMarkingComplete ? (
                                            <div className="flex items-center gap-2">
                                                <Spinner className="h-4 w-4" />
                                                Marking Complete...
                                            </div>
                                        ) : isUploading ? (
                                            <div className="flex items-center gap-2">
                                                <Spinner className="h-4 w-4" />
                                                Uploading...
                                            </div>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="h-4 w-4" />
                                                Mark Stage as Complete
                                            </>
                                        )}
                                    </Button>
                                )}
                            </CardFooter>
                        </Card>
                    </div>
                </div>

                {/* Upload Confirmation Dialog */}
                <AlertDialog open={confirmDialog.open} onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, open })}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Confirm Document Upload</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to upload this {confirmDialog.isRequired ? 'required' : 'optional'} document?
                                
                                {selectedFiles[confirmDialog.documentValue] && (
                                    <div className="mt-4 space-y-2 rounded-lg bg-muted p-3">
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">Document Type:</span>
                                            <span className="text-right">{confirmDialog.documentName}</span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Name:</span>
                                            <span className="truncate text-right ml-2">
                                                {selectedFiles[confirmDialog.documentValue]?.name}
                                            </span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Size:</span>
                                            <span>
                                                {((selectedFiles[confirmDialog.documentValue]?.size || 0) / 1024 / 1024).toFixed(2)} MB
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={isUploading}>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={handleUploadClick} disabled={isUploading}>
                                {isUploading ? 'Uploading...' : 'Upload Document'}
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
                                        <span>{procurement.pr_number}</span>
                                    </div>
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">Required Documents:</span>
                                        <span className="text-green-600 dark:text-green-400 font-medium">
                                            {uploadedRequiredCount} / {documentGuide.counts.required_count} Uploaded ✓
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">Optional Documents:</span>
                                        <span>{optionalProgress.uploaded} / {optionalProgress.total} Uploaded</span>
                                    </div>
                                </div>

                                <p className="mt-3 text-sm font-medium">
                                    After completing this stage, you can proceed to the next procurement phase.
                                </p>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={isMarkingComplete}>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmMarkStageComplete} disabled={isMarkingComplete}>
                                {isMarkingComplete ? 'Marking Complete...' : 'Mark as Complete'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
}

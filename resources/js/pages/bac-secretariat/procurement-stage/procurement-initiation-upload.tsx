import { Head, router } from '@inertiajs/react';
import React, { useCallback, useState } from 'react';
import { toast } from 'sonner';

import { type BreadcrumbItem, type WorkflowInfo } from '@/types';
import type { DocumentGuide } from '@/types/document-guide';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';

import { markStageComplete, uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import FileUploadArea from '@/components/file-upload-area';
import { ModeBadge, WorkflowProgressIndicator } from '@/components/procurement/workflow-progress-indicator';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';

import { AlertCircle, CheckCircle2, FileText, Upload } from 'lucide-react';

interface ProcurementInitiationShowProps {
    procurement?: {
        pr_number: string;
        title: string;
        status?: string;
        stage?: string;
    };
    workflowInfo?: WorkflowInfo;
    documentGuide: DocumentGuide;
    uploadedDocuments: string[];
    currentStage?: string;
    currentStatus?: string;
    isStageComplete?: boolean;
}

// The document type value for procurement initiation
const DOCUMENT_TYPE = 'procurement_initiation_document';
const DOCUMENT_NAME = 'Procurement Initiation Document';

export default function ProcurementInitiationShow({
    procurement,
    workflowInfo,
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

    // Check if the document has been uploaded
    const isDocumentUploaded = uploadedDocuments.includes(DOCUMENT_TYPE);

    // State management
    const [isDragging, setIsDragging] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);
    const [showUploadDialog, setShowUploadDialog] = useState(false);
    const [showCompleteDialog, setShowCompleteDialog] = useState(false);

    const validateFile = useCallback((file: File): boolean => {
        // Allow up to 50MB for document
        if (file.size > 50 * 1024 * 1024) {
            toast.error('File too large', {
                description: 'File size must not exceed 50MB.',
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
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const newFile = e.target.files?.[0] || null;

            if (newFile && !validateFile(newFile)) {
                e.target.value = '';
                return;
            }

            setSelectedFile(newFile);
        },
        [validateFile],
    );

    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(false);

            const file = e.dataTransfer.files[0];
            if (file && validateFile(file)) {
                setSelectedFile(file);
            }
        },
        [validateFile],
    );

    const handleRemoveFile = useCallback(() => {
        setSelectedFile(null);
    }, []);

    const openUploadDialog = useCallback(() => {
        if (!selectedFile) {
            toast.error('No file selected', {
                description: 'Please select a PDF file to upload.',
            });
            return;
        }
        setShowUploadDialog(true);
    }, [selectedFile]);

    const handleUploadClick = useCallback(() => {
        if (!selectedFile) {
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

        router.post(
            uploadSingleDocument.url(procurement.pr_number),
            {
                document_file: selectedFile,
                document_type: DOCUMENT_TYPE,
                description: DOCUMENT_NAME,
            },
            {
                onSuccess: () => {
                    toast.success('Document uploaded successfully!', {
                        id: uploadToast,
                        description: 'Your procurement initiation document has been uploaded.',
                    });
                    setSelectedFile(null);
                    setShowUploadDialog(false);
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
            },
        );
    }, [procurement?.pr_number, selectedFile]);

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
            },
        );
    }, [procurement?.pr_number]);

    const calculatedPercentage = isDocumentUploaded ? 100 : 0;

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
                {/* Workflow Progress Indicator */}
                {workflowInfo && <WorkflowProgressIndicator workflowInfo={workflowInfo} />}

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
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
                    {workflowInfo && <ModeBadge workflowInfo={workflowInfo} />}
                </div>

                <div className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        {/* Upload Progress Card */}
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
                                        <span className="font-semibold">{isDocumentUploaded ? '1/1' : '0/1'} required</span>
                                    </div>
                                    <Progress value={calculatedPercentage} className="h-2" />
                                    <p className="text-muted-foreground text-xs">
                                        {isDocumentUploaded ? (
                                            <span className="flex items-center gap-1 text-green-600 dark:text-green-500">
                                                <CheckCircle2 className="h-3 w-3" />
                                                Document uploaded
                                            </span>
                                        ) : (
                                            <span className="flex items-center gap-1 text-amber-600 dark:text-amber-500">
                                                <AlertCircle className="h-3 w-3" />
                                                Upload PDF to continue
                                            </span>
                                        )}
                                    </p>
                                </div>

                                {/* Stage Info */}
                                <div className="bg-muted/50 space-y-1 rounded-lg p-3 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Stage:</span>
                                        <span className="font-medium">{documentGuide?.stage_display_name || 'Procurement Initiation'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Phase:</span>
                                        <span className="font-medium capitalize">{documentGuide?.phase?.replace('_', ' ') || 'Pre-Procurement'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Document:</span>
                                        <Badge variant="secondary" className="text-xs">
                                            PDF
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Main Upload Card */}
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <Upload className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Upload Document
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Upload the procurement initiation document in PDF format.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-6">

                                {/* Upload Status or Upload Area */}
                                {isDocumentUploaded ? (
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950/20">
                                        <div className="flex items-center gap-3">
                                            <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
                                            <div>
                                                <p className="font-semibold text-green-700 dark:text-green-300">Document Uploaded</p>
                                                <p className="text-sm text-green-600 dark:text-green-400">
                                                    Your procurement initiation document has been successfully uploaded.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">Procurement Initiation Document</p>
                                            <p className="text-muted-foreground text-xs">
                                                Upload the procurement initiation document (PDF, max 50MB).
                                            </p>
                                        </div>
                                        <div className="flex flex-col gap-3 sm:flex-row">
                                            <div className="flex-1">
                                                <FileUploadArea
                                                    label=""
                                                    file={selectedFile}
                                                    isDragging={isDragging}
                                                    onFileChange={handleFileChange}
                                                    onDragEnter={handleDragEnter}
                                                    onDragLeave={handleDragLeave}
                                                    onDragOver={handleDragOver}
                                                    onDrop={handleDrop}
                                                    onRemove={handleRemoveFile}
                                                    inputId="document-upload"
                                                    required
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                onClick={openUploadDialog}
                                                disabled={!selectedFile || isUploading}
                                                className="mt-0 h-12 w-full self-start sm:h-[120px] sm:w-auto"
                                            >
                                                {isUploading ? (
                                                    <div className="flex items-center gap-2">
                                                        <Spinner className="h-4 w-4" />
                                                        Uploading...
                                                    </div>
                                                ) : (
                                                    <>
                                                        <Upload className="mr-2 h-4 w-4" />
                                                        Upload
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>

                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                {isStageFinished ? (
                                    <div className="w-full rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/20">
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
                                        disabled={!isDocumentUploaded || isUploading || isMarkingComplete}
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
                <AlertDialog open={showUploadDialog} onOpenChange={setShowUploadDialog}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Confirm Document Upload</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to upload this procurement initiation document?
                                {selectedFile && (
                                    <div className="bg-muted mt-4 space-y-2 rounded-lg p-3">
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">Document Type:</span>
                                            <span className="text-right">Procurement Initiation</span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Name:</span>
                                            <span className="ml-2 truncate text-right">{selectedFile.name}</span>
                                        </div>
                                        <div className="flex items-start justify-between text-sm">
                                            <span className="font-medium">File Size:</span>
                                            <span>{(selectedFile.size / 1024 / 1024).toFixed(2)} MB</span>
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
                                <div className="bg-muted mt-4 space-y-2 rounded-lg p-3">
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">PR Number:</span>
                                        <span>{procurement.pr_number}</span>
                                    </div>
                                    <div className="flex items-start justify-between text-sm">
                                        <span className="font-medium">Document:</span>
                                        <span className="font-medium text-green-600 dark:text-green-400">Uploaded ✓</span>
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

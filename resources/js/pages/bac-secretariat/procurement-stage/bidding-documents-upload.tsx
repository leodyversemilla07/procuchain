import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, FileText } from 'lucide-react';
import React, { useState, useCallback } from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import FileUploadArea from '@/components/file-upload-area';
import type { DocumentGuide } from '@/types/document-guide';
import { markStageComplete, uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/PreProcurementController';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { AlertCircle } from 'lucide-react';
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

interface BiddingDocumentsUploadProps {
    procurement: {
        pr_number: string;
        title: string;
        status?: string;
        stage?: string;
        stage_value?: string;
        current_stage?: string;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function BiddingDocumentsUpload({ procurement, documentGuide, uploadedDocuments = [] }: BiddingDocumentsUploadProps) {
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [dragging, setDragging] = useState<Record<string, boolean>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Bidding Documents - ${procurement?.pr_number || 'Unknown'}${procurement?.title ? ': ' + procurement.title : ''}`, href: '#' },
    ]);

    const handleMarkComplete = () => {
        setIsMarkingComplete(true);
        router.post(
            markStageComplete({ pr_number: procurement.pr_number, stage: 'bidding_documents' }).url,
            {},
            {
                onSuccess: (page) => {
                    const flash = (page.props as Record<string, unknown>).flash as Record<string, unknown> | undefined;
                    const response = flash?.success;
                    if (typeof response === 'object' && response && 'blockchain' in response) {
                        const { message, blockchain } = response as { message: string; blockchain: { status_txid?: string; event_txid?: string } };
                        toast.success(message, {
                            description: (
                                <div className="space-y-1 text-xs">
                                    {blockchain.status_txid && (
                                        <p>Status TX: {blockchain.status_txid}</p>
                                    )}
                                    {blockchain.event_txid && (
                                        <p>Event TX: {blockchain.event_txid}</p>
                                    )}
                                </div>
                            ),
                        });
                    } else {
                        toast.success('Stage marked as complete!', {
                            description: 'All required documents have been uploaded.',
                        });
                    }
                },
                onError: () => {
                    toast.error('Failed to mark stage as complete', {
                        description: 'Please try again or contact support.',
                    });
                },
                onFinish: () => {
                    setIsMarkingComplete(false);
                },
                preserveScroll: true,
            },
        );
    };

    const uploadedRequiredCount = documentGuide
        ? documentGuide.required_documents.filter((doc) => uploadedDocuments.includes(doc.value)).length
        : 0;

    const calculatedPercentage =
        documentGuide && documentGuide.counts.required_count > 0
            ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100)
            : 100;

    const allRequiredUploaded = documentGuide && uploadedRequiredCount === documentGuide.counts.required_count;
    
    // Stage is completed if the current stage of the procurement is different from this stage (meaning we moved past it)
    // OR if the status explicitly indicates completion (fallback)
    const isStageCompleted = procurement.current_stage && procurement.stage_value
        ? procurement.current_stage !== procurement.stage_value
        : (procurement.status?.includes('bidding_documents') === false || procurement.stage !== 'bidding_documents');

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

    const handleFileChange = (documentValue: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file && validateFile(file)) {
            setFiles((prev) => ({ ...prev, [documentValue]: file }));
        } else if (file) {
            e.target.value = '';
        }
    };

    const handleDragEnter = (documentValue: string) => (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragging((prev) => ({ ...prev, [documentValue]: true }));
    };

    const handleDragLeave = (documentValue: string) => (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragging((prev) => ({ ...prev, [documentValue]: false }));
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (documentValue: string) => (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragging((prev) => ({ ...prev, [documentValue]: false }));

        const file = e.dataTransfer.files?.[0];
        if (file && validateFile(file)) {
            setFiles((prev) => ({ ...prev, [documentValue]: file }));
        }
    };

    const handleRemove = (documentValue: string) => () => {
        setFiles((prev) => ({ ...prev, [documentValue]: null }));
    };

    const handleUploadClick = (documentValue: string, documentName: string) => {
        const file = files[documentValue];
        if (!file) {
            toast.error('No file selected', {
                description: 'Please select a file to upload.',
            });
            return;
        }

        setConfirmDialog({
            open: true,
            documentValue,
            documentName,
        });
    };

    const handleConfirmUpload = useCallback(() => {
        const file = files[confirmDialog.documentValue];
        
        if (!file) {
            toast.error('No file selected', {
                description: 'Please select a file to upload.',
            });
            return;
        }

        const uploadToast = toast.loading('Uploading document...');
        setIsUploading(true);

        router.post(
            uploadSingleDocument({ pr_number: procurement.pr_number, stage: 'bidding_documents' }).url,
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
                    setFiles((prev) => ({ ...prev, [confirmDialog.documentValue]: null }));
                    setConfirmDialog({ open: false, documentValue: '', documentName: '' });
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
    }, [confirmDialog, files, procurement.pr_number]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Bidding Documents" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <FileText className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Bidding Documents</h1>
                    </div>
                    <p className="text-muted-foreground text-sm sm:text-base">
                        Upload the bidding documents for procurement
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
                                    Upload required and optional documents for Bidding Documents. Files will be permanently saved.
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
                                                            <div className="flex flex-col sm:flex-row gap-2">
                                                                <div className="flex-1">
                                                                    <FileUploadArea
                                                                        label=""
                                                                        file={files[doc.value] || null}
                                                                        isDragging={dragging[doc.value] || false}
                                                                        onFileChange={handleFileChange(doc.value)}
                                                                        onDragEnter={handleDragEnter(doc.value)}
                                                                        onDragLeave={handleDragLeave(doc.value)}
                                                                        onDragOver={handleDragOver}
                                                                        onDrop={handleDrop(doc.value)}
                                                                        onRemove={handleRemove(doc.value)}
                                                                        inputId={`file-${doc.value}`}
                                                                        required
                                                                    />
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    onClick={() => handleUploadClick(doc.value, doc.display_name)}
                                                                    disabled={!files[doc.value] || isUploading}
                                                                    className="self-start mt-0 h-12 sm:h-[120px] w-full sm:w-auto"
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
                                        <div className="space-y-4">
                                            {documentGuide.optional_documents.map((doc) => {
                                                const isUploaded = uploadedDocuments.includes(doc.value);
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
                                                                <Badge variant="outline" className="text-xs text-blue-600 dark:text-blue-500">
                                                                    <CheckCircle2 className="h-3 w-3 mr-1" />
                                                                    Uploaded
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {!isUploaded && (
                                                            <div className="flex flex-col sm:flex-row gap-2">
                                                                <div className="flex-1">
                                                                    <FileUploadArea
                                                                        label=""
                                                                        file={files[doc.value] || null}
                                                                        isDragging={dragging[doc.value] || false}
                                                                        onFileChange={handleFileChange(doc.value)}
                                                                        onDragEnter={handleDragEnter(doc.value)}
                                                                        onDragLeave={handleDragLeave(doc.value)}
                                                                        onDragOver={handleDragOver}
                                                                        onDrop={handleDrop(doc.value)}
                                                                        onRemove={handleRemove(doc.value)}
                                                                        inputId={`file-${doc.value}`}
                                                                    />
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    onClick={() => handleUploadClick(doc.value, doc.display_name)}
                                                                    disabled={!files[doc.value] || isUploading}
                                                                    variant="secondary"
                                                                    className="self-start mt-0 h-12 sm:h-[120px] w-full sm:w-auto"
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
                                {isStageCompleted ? (
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
                                        onClick={handleMarkComplete}
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
            </div>

            <AlertDialog open={confirmDialog.open} onOpenChange={(open) => !open && setConfirmDialog({ open: false, documentValue: '', documentName: '' })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Upload Document?</AlertDialogTitle>
                        <AlertDialogDescription className="space-y-2">
                            <p>You are about to upload the following document:</p>
                            <p className="font-semibold text-foreground">{confirmDialog.documentName}</p>
                            <p className="text-sm">
                                Once uploaded, this document will be permanently recorded and cannot be deleted.
                            </p>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmUpload}>Upload Document</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

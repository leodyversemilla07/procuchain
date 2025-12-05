import { markStageComplete, uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/PreProcurementController';
import FileUploadArea from '@/components/file-upload-area';
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
import { BreadcrumbItem } from '@/types';
import type { DocumentGuide } from '@/types/document-guide';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, FileText, Users } from 'lucide-react';
import React, { useCallback, useState } from 'react';
import { toast } from 'sonner';

interface PreProcurementConferenceUploadProps {
    procurement: {
        pr_number: string;
        title: string;
        status?: string;
        stage?: string;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function PreProcurementConferenceUpload({ procurement, documentGuide, uploadedDocuments = [] }: PreProcurementConferenceUploadProps) {
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
        {
            title: `Upload Pre-Procurement Conference - ${procurement?.pr_number || 'Unknown'}${procurement?.title ? ': ' + procurement.title : ''}`,
            href: '#',
        },
    ]);

    const handleMarkComplete = () => {
        setIsMarkingComplete(true);
        router.post(
            markStageComplete({ pr_number: procurement.pr_number, stage: 'pre_procurement_conference' }).url,
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
                                    {blockchain.status_txid && <p>Status TX: {blockchain.status_txid}</p>}
                                    {blockchain.event_txid && <p>Event TX: {blockchain.event_txid}</p>}
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

    const uploadedRequiredCount = documentGuide ? documentGuide.required_documents.filter((doc) => uploadedDocuments.includes(doc.value)).length : 0;

    const calculatedPercentage =
        documentGuide && documentGuide.counts.required_count > 0
            ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100)
            : 100;

    const allRequiredUploaded = documentGuide && uploadedRequiredCount === documentGuide.counts.required_count;

    // Stage is completed only if status explicitly shows completed or skipped
    const isStageCompleted =
        procurement.status === 'pre_procurement_conference_completed' || procurement.status === 'pre_procurement_conference_skipped';

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
            uploadSingleDocument({ pr_number: procurement.pr_number, stage: 'pre_procurement_conference' }).url,
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
            },
        );
    }, [confirmDialog, files, procurement.pr_number]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Pre-Procurement Conference" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Page Header */}
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                    <CardContent className="flex flex-col gap-2 p-4 sm:p-6">
                        <div className="text-primary flex items-center gap-2">
                            <Users className="h-5 w-5 sm:h-6 sm:w-6" />
                            <h1 className="text-xl font-bold sm:text-2xl">Pre-Procurement Conference</h1>
                        </div>
                        <p className="text-muted-foreground text-sm sm:text-base">
                            Upload the Pre-Procurement Conference for procurement
                            <span className="text-foreground font-medium"> #{procurement?.pr_number || 'Unknown'}</span>
                            {procurement?.title && (
                                <>
                                    :<span className="text-foreground font-medium italic"> {procurement.title}</span>
                                </>
                            )}
                        </p>
                    </CardContent>
                </Card>

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
                                        <p className="text-muted-foreground text-xs">
                                            {allRequiredUploaded ? (
                                                <span className="flex items-center gap-1 text-green-600 dark:text-green-500">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    All required documents uploaded
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-1 text-amber-600 dark:text-amber-500">
                                                    <AlertCircle className="h-3 w-3" />
                                                    {documentGuide.counts.required_count - uploadedRequiredCount} required document
                                                    {documentGuide.counts.required_count - uploadedRequiredCount !== 1 ? 's' : ''} remaining
                                                </span>
                                            )}
                                        </p>
                                    </div>

                                    {/* Stage Info */}
                                    <div className="bg-muted/50 space-y-1 rounded-lg p-3 text-xs">
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
                                    <Users className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Document Upload
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Upload required and optional documents for Pre-Procurement Conference. Files will be permanently saved.
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
                                                                    <p className="text-muted-foreground text-xs">{doc.description}</p>
                                                                )}
                                                            </div>
                                                            {isUploaded && (
                                                                <Badge variant="outline" className="text-xs text-green-600 dark:text-green-500">
                                                                    <CheckCircle2 className="mr-1 h-3 w-3" />
                                                                    Uploaded
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {!isUploaded && (
                                                            <div className="flex flex-col gap-2 sm:flex-row">
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
                                                                    className="mt-0 h-12 w-full self-start sm:h-[120px] sm:w-auto"
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
                                                                    <p className="text-muted-foreground text-xs">{doc.description}</p>
                                                                )}
                                                            </div>
                                                            {isUploaded && (
                                                                <Badge variant="outline" className="text-xs text-blue-600 dark:text-blue-500">
                                                                    <CheckCircle2 className="mr-1 h-3 w-3" />
                                                                    Uploaded
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {!isUploaded && (
                                                            <div className="flex flex-col gap-2 sm:flex-row">
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
                                                                    className="mt-0 h-12 w-full self-start sm:h-[120px] sm:w-auto"
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

            <AlertDialog
                open={confirmDialog.open}
                onOpenChange={(open) => !open && setConfirmDialog({ open: false, documentValue: '', documentName: '' })}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Upload Document?</AlertDialogTitle>
                        <AlertDialogDescription className="space-y-2">
                            <p>You are about to upload the following document:</p>
                            <p className="text-foreground font-semibold">{confirmDialog.documentName}</p>
                            <p className="text-sm">Once uploaded, this document will be permanently recorded and cannot be deleted.</p>
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

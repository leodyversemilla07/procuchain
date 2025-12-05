import { markStageComplete, updateDeliveryDetails, uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/PostProcurementController';
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
import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import type { DocumentGuide } from '@/types/document-guide';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, MapPin, Calendar, Clock, Send } from 'lucide-react';
import React, { useCallback, useState } from 'react';
import { toast } from 'sonner';

interface NtpUploadProps {
    procurement: {
        pr_number: string;
        title: string;
        status?: string;
        stage_value?: string;
        current_stage?: string;
        // Delivery details per NGPA IRR Section 71
        delivery_location?: string;
        delivery_date?: string;
        delivery_date_formatted?: string;
        delivery_term_days?: number;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function NtpUpload({ procurement, documentGuide, uploadedDocuments = [] }: NtpUploadProps) {
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [dragging, setDragging] = useState<Record<string, boolean>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);
    const [isSavingDelivery, setIsSavingDelivery] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
    });

    // Delivery details form state
    const [deliveryForm, setDeliveryForm] = useState({
        delivery_location: procurement.delivery_location || '',
        delivery_date: procurement.delivery_date || '',
        delivery_term_days: procurement.delivery_term_days?.toString() || '',
    });

    const hasDeliveryDetails = Boolean(procurement.delivery_location && procurement.delivery_date && procurement.delivery_term_days);

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        {
            title: `Upload Notice to Proceed - ${procurement?.pr_number || 'Unknown'}${procurement?.title ? ': ' + procurement.title : ''}`,
            href: '#',
        },
    ]);

    const validateFile = useCallback((file: File) => {
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            toast.error('File too large', {
                description: 'File size must be less than 10MB',
            });
            return false;
        }
        return true;
    }, []);

    // Handle saving delivery details
    const handleSaveDeliveryDetails = useCallback(() => {
        // Validate required fields
        if (!deliveryForm.delivery_location.trim()) {
            toast.error('Delivery location is required');
            return;
        }
        if (!deliveryForm.delivery_date) {
            toast.error('Delivery date is required');
            return;
        }
        if (!deliveryForm.delivery_term_days || parseInt(deliveryForm.delivery_term_days) < 1) {
            toast.error('Delivery term must be at least 1 day');
            return;
        }

        setIsSavingDelivery(true);

        router.post(
            updateDeliveryDetails({ pr_number: procurement.pr_number }).url,
            {
                delivery_location: deliveryForm.delivery_location,
                delivery_date: deliveryForm.delivery_date,
                delivery_term_days: parseInt(deliveryForm.delivery_term_days),
            },
            {
                onSuccess: () => {
                    toast.success('Delivery details saved successfully', {
                        description: 'Delivery information has been recorded on the blockchain.',
                    });
                },
                onError: (errors) => {
                    const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to save delivery details';
                    toast.error('Failed to save delivery details', {
                        description: errorMessage,
                    });
                },
                onFinish: () => {
                    setIsSavingDelivery(false);
                },
                preserveScroll: true,
            },
        );
    }, [deliveryForm, procurement.pr_number]);

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
            uploadSingleDocument({ pr_number: procurement.pr_number, stage: 'notice_to_proceed' }).url,
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
            },
        );
    }, [confirmDialog, files, procurement.pr_number]);

    const handleMarkComplete = () => {
        setIsMarkingComplete(true);
        router.post(
            markStageComplete({ pr_number: procurement.pr_number, stage: 'notice_to_proceed' }).url,
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
    const isStageCompleted = procurement.current_stage !== procurement.stage_value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Notice to Proceed" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Page Header */}
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                    <CardContent className="flex flex-col gap-2 p-4 sm:p-6">
                        <div className="text-primary flex items-center gap-2">
                            <Send className="h-5 w-5 sm:h-6 sm:w-6" />
                            <h1 className="text-xl font-bold sm:text-2xl">Notice to Proceed</h1>
                        </div>
                        <p className="text-muted-foreground text-sm sm:text-base">
                            Upload the Notice to Proceed for procurement
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
                                        <Send className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                        Upload Progress
                                    </CardTitle>
                                    <CardDescription className="text-muted-foreground text-sm">Track your document upload progress</CardDescription>
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

                        {/* Delivery Details Card - Per NGPA IRR Section 71 */}
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md lg:col-span-3">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <MapPin className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Delivery Details
                                    {hasDeliveryDetails && (
                                        <Badge variant="outline" className="ml-2 text-xs text-green-600 dark:text-green-500">
                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                            Saved
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription className="text-muted-foreground text-sm">
                                    Per NGPA IRR Section 71, delivery details must be specified before contract implementation.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="delivery_location" className="flex items-center gap-1.5">
                                            <MapPin className="h-3.5 w-3.5" />
                                            Delivery Location
                                        </Label>
                                        <Input
                                            id="delivery_location"
                                            value={deliveryForm.delivery_location}
                                            onChange={(e) => setDeliveryForm((prev) => ({ ...prev, delivery_location: e.target.value }))}
                                            placeholder="e.g., Municipal Hall, Brgy. Centro"
                                            disabled={isStageCompleted}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="delivery_date" className="flex items-center gap-1.5">
                                            <Calendar className="h-3.5 w-3.5" />
                                            Delivery Date
                                        </Label>
                                        <DatePickerInput
                                            id="delivery_date"
                                            value={deliveryForm.delivery_date}
                                            onChange={(value) => setDeliveryForm((prev) => ({ ...prev, delivery_date: value }))}
                                            placeholder="Select delivery date"
                                            disabled={isStageCompleted}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="delivery_term_days" className="flex items-center gap-1.5">
                                            <Clock className="h-3.5 w-3.5" />
                                            Delivery Term (Days)
                                        </Label>
                                        <Input
                                            id="delivery_term_days"
                                            type="number"
                                            min="1"
                                            max="365"
                                            value={deliveryForm.delivery_term_days}
                                            onChange={(e) => setDeliveryForm((prev) => ({ ...prev, delivery_term_days: e.target.value }))}
                                            placeholder="e.g., 30"
                                            disabled={isStageCompleted}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                            <CardFooter className="border-t pt-4">
                                {isStageCompleted ? (
                                    <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-500">
                                        <CheckCircle2 className="h-4 w-4" />
                                        Delivery details have been recorded
                                    </div>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={handleSaveDeliveryDetails}
                                        disabled={isSavingDelivery || !deliveryForm.delivery_location || !deliveryForm.delivery_date || !deliveryForm.delivery_term_days}
                                        className="w-full sm:w-auto"
                                    >
                                        {isSavingDelivery ? (
                                            <>
                                                <Spinner className="mr-2 h-4 w-4" />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                                Save Delivery Details
                                            </>
                                        )}
                                    </Button>
                                )}
                            </CardFooter>
                        </Card>
                    </div>

                    {/* Document Upload Section */}
                    <div className="grid grid-cols-1 gap-4 sm:gap-6">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <Send className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Document Upload
                                </CardTitle>
                                <p className="text-muted-foreground text-sm">
                                    Upload required and optional documents for Notice to Proceed. Files will be permanently saved.
                                </p>
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

            <AlertDialog open={confirmDialog.open} onOpenChange={(open) => !isUploading && setConfirmDialog({ ...confirmDialog, open })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Upload</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to upload <strong>{confirmDialog.documentName}</strong>? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isUploading}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmUpload} disabled={isUploading}>
                            {isUploading ? (
                                <>
                                    <Spinner className="mr-2 size-4" />
                                    Uploading...
                                </>
                            ) : (
                                'Confirm Upload'
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

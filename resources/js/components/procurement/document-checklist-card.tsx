import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { CheckCircle2, Circle, FileText, AlertCircle, Upload, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import type { DocumentGuide } from '@/types/document-guide';

interface DocumentChecklistCardProps {
    documentGuide: DocumentGuide;
    uploadedDocuments?: string[]; // Array of document type values that have been uploaded
    completionPercentage?: number;
    className?: string;
    onUploadClick?: (documentValue: string, documentName: string, isRequired: boolean) => void;
    canUpload?: boolean; // Whether upload functionality is enabled
}

export function DocumentChecklistCard({
    documentGuide,
    uploadedDocuments = [],
    completionPercentage,
    className,
    onUploadClick,
    canUpload = false,
}: DocumentChecklistCardProps) {
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
        isRequired: boolean;
        isReplace: boolean;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
        isRequired: false,
        isReplace: false,
    });

    const isDocumentUploaded = (docValue: string) => uploadedDocuments.includes(docValue);

    const uploadedRequiredCount = documentGuide.required_documents.filter((doc) => isDocumentUploaded(doc.value)).length;

    const calculatedPercentage =
        completionPercentage !== undefined
            ? completionPercentage
            : documentGuide.counts.required_count > 0
              ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100)
              : 100;

    const allRequiredUploaded = uploadedRequiredCount === documentGuide.counts.required_count;

    const handleUploadClick = (documentValue: string, documentName: string, isRequired: boolean, isReplace: boolean) => {
        setConfirmDialog({
            open: true,
            documentValue,
            documentName,
            isRequired,
            isReplace,
        });
    };

    const handleConfirmUpload = () => {
        if (onUploadClick) {
            onUploadClick(confirmDialog.documentValue, confirmDialog.documentName, confirmDialog.isRequired);
        }
        setConfirmDialog({ open: false, documentValue: '', documentName: '', isRequired: false, isReplace: false });
    };

    const handleCancelUpload = () => {
        setConfirmDialog({ open: false, documentValue: '', documentName: '', isRequired: false, isReplace: false });
    };

    return (
        <>
        <Card className={cn('border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md', className)}>
            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                    <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                    Document Checklist
                </CardTitle>
                <CardDescription className="text-sm">Track your document upload progress</CardDescription>
            </CardHeader>

            <CardContent className="space-y-4 sm:space-y-6">
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

                {/* Required Documents */}
                {documentGuide.required_documents.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">Required Documents</h4>
                            <Badge variant="secondary" className="text-xs">
                                {documentGuide.counts.required_count}
                            </Badge>
                        </div>
                        <div className="space-y-2">
                            {documentGuide.required_documents.map((doc) => {
                                const uploaded = isDocumentUploaded(doc.value);
                                return (
                                    <div
                                        key={doc.value}
                                        className={cn(
                                            'flex items-start gap-2 rounded-lg border p-2 text-sm transition-colors',
                                            uploaded
                                                ? 'border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20'
                                                : 'border-muted bg-muted/30'
                                        )}
                                    >
                                        {uploaded ? (
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-600 dark:text-green-500" />
                                        ) : (
                                            <Circle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className={cn('font-medium leading-tight', uploaded && 'text-green-700 dark:text-green-400')}>
                                                {doc.display_name}
                                            </p>
                                            {doc.description && (
                                                <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{doc.description}</p>
                                            )}
                                        </div>
                                        {canUpload && onUploadClick && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={uploaded ? 'outline' : 'default'}
                                                className="ml-2 h-7 shrink-0 text-xs"
                                                onClick={() => handleUploadClick(doc.value, doc.display_name, true, uploaded)}
                                            >
                                                {uploaded ? (
                                                    <>
                                                        <RefreshCw className="mr-1 h-3 w-3" />
                                                        Replace
                                                    </>
                                                ) : (
                                                    <>
                                                        <Upload className="mr-1 h-3 w-3" />
                                                        Upload
                                                    </>
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Optional Documents */}
                {documentGuide.optional_documents.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">Optional Documents</h4>
                            <Badge variant="outline" className="text-xs">
                                {documentGuide.counts.optional_count}
                            </Badge>
                        </div>
                        <div className="space-y-2">
                            {documentGuide.optional_documents.map((doc) => {
                                const uploaded = isDocumentUploaded(doc.value);
                                return (
                                    <div
                                        key={doc.value}
                                        className={cn(
                                            'flex items-start gap-2 rounded-lg border p-2 text-sm transition-colors',
                                            uploaded
                                                ? 'border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20'
                                                : 'border-muted/50 bg-background'
                                        )}
                                    >
                                        {uploaded ? (
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-500" />
                                        ) : (
                                            <Circle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground/50" />
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className={cn('font-medium leading-tight', uploaded && 'text-blue-700 dark:text-blue-400')}>
                                                {doc.display_name}
                                            </p>
                                            {doc.description && (
                                                <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{doc.description}</p>
                                            )}
                                        </div>
                                        {canUpload && onUploadClick && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={uploaded ? 'outline' : 'secondary'}
                                                className="ml-2 h-7 shrink-0 text-xs"
                                                onClick={() => handleUploadClick(doc.value, doc.display_name, false, uploaded)}
                                            >
                                                {uploaded ? (
                                                    <>
                                                        <RefreshCw className="mr-1 h-3 w-3" />
                                                        Replace
                                                    </>
                                                ) : (
                                                    <>
                                                        <Upload className="mr-1 h-3 w-3" />
                                                        Upload
                                                    </>
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

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
                </div>
            </CardContent>
        </Card>

            <AlertDialog open={confirmDialog.open} onOpenChange={(open) => !open && handleCancelUpload()}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirmDialog.isReplace ? 'Replace Document on Blockchain?' : 'Upload Document to Blockchain?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription className="space-y-2">
                            <p>
                                {confirmDialog.isReplace
                                    ? 'You are about to replace the following document on the blockchain:'
                                    : 'You are about to upload the following document to the blockchain:'}
                            </p>
                            <p className="font-semibold text-foreground">{confirmDialog.documentName}</p>
                            <p className="text-sm">
                                {confirmDialog.isReplace
                                    ? 'This will create a new immutable record on the blockchain, replacing the current version. The previous version will remain in the blockchain history.'
                                    : 'Once uploaded, this document will be permanently recorded on the blockchain and cannot be deleted.'}
                            </p>
                            <p className="text-sm font-medium">
                                Document Type: <span className="text-muted-foreground">{confirmDialog.isRequired ? 'Required' : 'Optional'}</span>
                            </p>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={handleCancelUpload}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmUpload}>
                            {confirmDialog.isReplace ? 'Replace Document' : 'Upload Document'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

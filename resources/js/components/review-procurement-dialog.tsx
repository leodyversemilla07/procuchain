import React from 'react';
import { FileText } from 'lucide-react';
import { format } from 'date-fns';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';

interface FileMetadata {
    document_type: string;
    submission_date: string;
    municipal_offices: string;
    signatory_details: string;
    [key: string]: string;
}

interface ReviewProcurementDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: string;
    procurementTitle: string;
    files: (File | null)[];
    metadata: FileMetadata[];
    onSubmit: (e: React.FormEvent) => void;
    processing: boolean;
}

function parseDate(dateStr: string): Date | undefined {
    if (!dateStr) return undefined;

    try {
        const date = new Date(dateStr);
        return !isNaN(date.getTime()) ? date : undefined;
    } catch (e) {
        console.error("Error parsing date:", e);
        return undefined;
    }
}

const formatDateForDisplay = (dateValue: Date | string | undefined): string => {
    if (!dateValue) return 'Not set';

    try {
        if (dateValue instanceof Date) {
            return !isNaN(dateValue.getTime())
                ? format(dateValue, 'yyyy-MM-dd')
                : 'Invalid date';
        }

        if (typeof dateValue === 'string' && dateValue.trim()) {
            const parsedDate = parseDate(dateValue);
            return parsedDate ? format(parsedDate, 'yyyy-MM-dd') : dateValue;
        }

        return 'Invalid date';
    } catch (error) {
        console.error("Error formatting date:", error);
        return 'Invalid date';
    }
};

export default function ReviewProcurementDialog({
    open,
    onOpenChange,
    procurementId,
    procurementTitle,
    files,
    metadata,
    onSubmit,
    processing
}: ReviewProcurementDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent id="review-procurement-dialog" className="max-w-full w-full !max-h-none !h-auto">
                <DialogHeader>
                    <DialogTitle>Review Procurement Details</DialogTitle>
                    <DialogDescription>
                        Please review all details before submitting. Are you sure you want to proceed?
                    </DialogDescription>
                </DialogHeader>
                {/* Use custom ScrollArea for scrollable content */}
                <ScrollArea className="max-h-[70vh]">
                    {/* Procurement Details at the top */}
                    <div className="mb-4">
                        <Card className="p-4 sm:p-6 border-[var(--sidebar-border)] dark:border-[var(--sidebar-border)] shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                            <div className="space-y-4 sm:space-y-5">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <Label className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var(--foreground)] mb-0.5 sm:mb-0 flex items-center gap-2">
                                        Procurement ID:
                                        <span className="px-3 py-2 text-sm sm:text-base font-normal truncate max-w-[180px] sm:max-w-[300px]">
                                            {procurementId
                                                ? procurementId.length > 40
                                                    ? `${procurementId.slice(0, 40)}...`
                                                    : procurementId
                                                : <span className="italic text-gray-400">Not set</span>}
                                        </span>
                                    </Label>
                                </div>
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <Label className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var(--foreground)] mb-0.5 sm:mb-0 flex items-center gap-2">
                                        Procurement Title:
                                        <span className="px-3 py-2 text-sm sm:text-base font-normal truncate max-w-[180px] sm:max-w-[300px]">
                                            {procurementTitle
                                                ? procurementTitle.length > 40
                                                    ? `${procurementTitle.slice(0, 40)}...`
                                                    : procurementTitle
                                                : <span className="italic text-gray-400">Not set</span>}
                                        </span>
                                    </Label>
                                </div>
                            </div>
                        </Card>
                    </div>
                    {/* Uploaded Documents below */}
                    <div className="mb-4">
                        <Label htmlFor="files" className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var,--foreground)] mb-2 block">
                            Uploaded Documents
                        </Label>
                        <div className="flex flex-col gap-4 pr-1">
                            {files.map((file, index) => {
                                const meta = metadata[index];
                                if (!file) return null;
                                return (
                                    <Card key={index} className="p-3 sm:p-4 rounded-lg border bg-muted dark:bg-muted/80 transition-all duration-200">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-5 w-5 text-[var(--primary)]" />
                                                <span className="font-medium text-[var(--foreground)] dark:text-[var,--foreground)]">
                                                    Document {index + 1}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                            <div className="flex-1">
                                                <p
                                                    className="text-sm text-muted-foreground truncate overflow-hidden whitespace-nowrap max-w-[12rem]"
                                                    title={file ? file.name : undefined}
                                                >
                                                    {file
                                                        ? file.name.length > 40
                                                            ? `${file.name.slice(0, 20)}...${file.name.slice(-17)}`
                                                            : file.name
                                                        : 'No file'}
                                                </p>
                                            </div>
                                        </div>
                                        {/* Show metadata details */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <div>
                                                <span className="font-semibold">Type:</span>{" "}
                                                {meta?.document_type
                                                    ? meta.document_type.length > 30
                                                        ? `${meta.document_type.slice(0, 30)}...`
                                                        : meta.document_type
                                                    : '-'}
                                            </div>
                                            <div>
                                                <span className="font-semibold">Submission Date:</span>{" "}
                                                {meta?.submission_date ? formatDateForDisplay(meta.submission_date) : '-'}
                                            </div>
                                            <div>
                                                <span className="font-semibold">Municipal Office:</span>{" "}
                                                {meta?.municipal_offices
                                                    ? meta.municipal_offices.length > 30
                                                        ? `${meta.municipal_offices.slice(0, 30)}...`
                                                        : meta.municipal_offices
                                                    : '-'}
                                            </div>
                                            <div>
                                                <span className="font-semibold">Signatories:</span>{" "}
                                                {meta?.signatory_details
                                                    ? meta.signatory_details.length > 40
                                                        ? `${meta.signatory_details.slice(0, 40)}...`
                                                        : meta.signatory_details
                                                    : '-'}
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </ScrollArea>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={(e) => onSubmit(e)} disabled={processing}>
                        Submit Procurement
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

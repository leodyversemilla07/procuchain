import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    FileText, Save, Building, Plus, X
} from 'lucide-react';
import { format } from 'date-fns';
import { useForm, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import DatePicker from '@/components/date-picker';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { MUNICIPAL_OFFICES } from '@/types/blockchain';
import PeopleInput from '@/components/people-input';
import FileUploadArea from '@/components/file-upload-area';
import { useMultiFileDrop } from '@/hooks/use-file-drop';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

interface FileMetadata {
    document_type: string;
    submission_date: string;
    municipal_offices: string;
    signatory_details: string;
    [key: string]: string; // Fix: allow dynamic string keys for metadata fields
}

interface HeaderProps {
    formState?: {
        isDraft?: boolean;
        isComplete?: boolean;
        createdAt?: string;
        lastUpdated?: string;
        reference?: string;
    };
}

type UseFormData = {
    procurement_id: string;
    procurement_title: string;
    files: (File | null)[];
    metadata: FileMetadata[];
    // Remove the index signature to allow setData to accept string keys
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurement Initiation', href: '#' },
];

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

export default function ProcurementInitiationForm({ formState }: HeaderProps) {
    const [dates, setDates] = useState<Record<number, Date | undefined>>({});
    const [showConfirm, setShowConfirm] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors
    } = useForm<UseFormData>({
        procurement_id: '',
        procurement_title: '',
        files: [null],
        metadata: [{
            document_type: '',
            submission_date: format(new Date(), 'yyyy-MM-dd'),
            municipal_offices: '',
            signatory_details: ''
        }]
    });

    const validateFile = useCallback((file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            toast.error("File too large", { description: "Maximum file size is 10MB" });
            return false;
        }

        if (file.type && file.type !== 'application/pdf') {
            toast.error("Invalid file type", { description: `File "${file.name}" does not appear to be a PDF. Detected type: ${file.type}.` });
            return false;
        }

        if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
            toast.error("Invalid file type", { description: `File "${file.name}" is not recognized as a PDF and has no .pdf extension.` });
            return false;
        }

        return true;
    }, []);

    const handleMetadataChange = useCallback(
        (index: number, field: keyof FileMetadata, value: string) => {
            clearErrors();
            const updated = Array.isArray(data.metadata) ? [...data.metadata] : [];
            if (!updated[index]) {
                updated[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatory_details: '' };
            }
            updated[index] = { ...updated[index], [field]: value };
            setData('metadata', updated);
        },
        [data.metadata, setData, clearErrors]
    );

    const handleDateChange = useCallback(
        (index: number, date: Date | undefined) => {
            clearErrors();
            setDates(prev => ({ ...prev, [index]: date }));
            if (date) {
                handleMetadataChange(index, 'submission_date', format(date, 'yyyy-MM-dd'));
            } else {
                handleMetadataChange(index, 'submission_date', '');
            }
        },
        [handleMetadataChange, clearErrors]
    );

    const handleFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
            clearErrors();
            const newSelectedFile = e.target.files?.[0] || null;
            const updatedFiles = Array.isArray(data.files) ? [...data.files] : [];
            if (newSelectedFile && validateFile(newSelectedFile)) {
                updatedFiles[index] = newSelectedFile;
                setData('files', updatedFiles);
                const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
                if (!meta[index]) {
                    meta[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatory_details: '' };
                    setData('metadata', meta);
                }
            } else {
                if (newSelectedFile) {
                    e.target.value = '';
                }
                if (updatedFiles[index] !== null) {
                    updatedFiles[index] = null;
                    setData('files', updatedFiles);
                }
            }
        },
        [data.files, data.metadata, validateFile, setData, clearErrors]
    );

    const validateDocuments = useCallback(() => {
        const files = Array.isArray(data.files) ? data.files : [];
        const meta = Array.isArray(data.metadata) ? data.metadata : [];
        if (files.length === 0 || files.some(f => f === null)) {
            return false;
        }
        const allRequiredMetadataPresent = files.every((file, index) => {
            const metadataEntry = meta[index];
            return metadataEntry && metadataEntry.document_type && String(metadataEntry.document_type).trim() !== '';
        });
        return allRequiredMetadataPresent;
    }, [data.files, data.metadata]);

    const addFile = useCallback(() => {
        const files = Array.isArray(data.files) ? [...data.files, null] : [];
        const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
        const last = meta.length - 1;
        const copy = last >= 0 && meta[last] ? meta[last] : { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatory_details: '' };
        meta.push({ ...copy, document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd') });
        setData('files', files);
        setData('metadata', meta);
        setDates(d => last >= 0 ? { ...d, [last + 1]: parseDate(format(new Date(), 'yyyy-MM-dd')) } : { 0: parseDate(format(new Date(), 'yyyy-MM-dd')) });
    }, [data.files, data.metadata, setData]);

    const removeFile = useCallback((index: number) => {
        const files = Array.isArray(data.files) ? [...data.files] : [];
        files.splice(index, 1);
        const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
        meta.splice(index, 1);
        setData('files', files);
        setData('metadata', meta);
    }, [data.files, data.metadata, setData]);

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

    const handleFieldChange = (field: keyof UseFormData, value: string): void => {
        clearErrors(field);
        setData(field, value);
    };

    const validateForm = () => {
        let isBasicValid = true;
        if (!data.procurement_id || String(data.procurement_id).trim() === '') {
            isBasicValid = false;
        }
        if (!data.procurement_title || String(data.procurement_title).trim() === '') {
            isBasicValid = false;
        }
        const areDocumentsValid = validateDocuments();
        return isBasicValid && areDocumentsValid;
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!validateForm()) {
            setShowConfirm(true);
            return;
        }
        const submissionToast = toast.loading("Submitting Procurement...");
        post('/bac-secretariat/publish-procurement-initiation', {
            onSuccess: () => {
                toast.success("Procurement successfully submitted", {
                    id: submissionToast,
                });
                reset();
            },
            onError: (formErrors: Record<string, string>) => {
                toast.error("Failed to submit", {
                    id: submissionToast,
                    description: Object.values(formErrors)[0]
                });
            },
            forceFormData: true,
            preserveScroll: true,
            preserveState: true
        });
    };

    const handleSaveDraft = () => {
        const draftToast = toast.loading("Saving draft...");
        post('/bac-secretariat/save-procurement-draft', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Draft saved successfully', {
                    id: draftToast
                });
            },
            onError: (errors: Record<string, string>) => {
                toast.error('Failed to save draft', {
                    id: draftToast,
                    description: Object.values(errors)[0]
                });
            }
        });
    };

    const hasError = (field: string) => {
        return Object.keys(errors).some(error => error === field || error.startsWith(`${field}.`));
    };

    const fileIndices = useMemo(
        () => Array.isArray(data.files)
            ? Array.from({ length: data.files.length }, (_, i) => i)
            : [],
        [data.files]
    );

    useEffect(() => {
        try {
            const newDates: { [key: number]: Date | undefined } = {};
            const metadata = Array.isArray(data.metadata) ? data.metadata : [];
            metadata.forEach((meta: FileMetadata, index: number) => {
                if (meta.submission_date) {
                    const parsedDate = parseDate(meta.submission_date);
                    if (parsedDate) {
                        newDates[index] = parsedDate;
                    }
                }
            });
            setDates(newDates);
        } catch (e) {
            console.error("Error setting dates:", e);
        }
    }, [data.metadata]);

    const fileDropHandlers = useMultiFileDrop(
        fileIndices,
        validateFile,
        (index, file) => {
            const newFiles = [...data.files];
            newFiles[index] = file;
            setData('files', newFiles);
        }
    );

    const incompleteFields = [];

    if (!data.procurement_id || !data.procurement_title) {
        incompleteFields.push('Basic procurement details');
    }

    const hasDocuments = data.files.some((file) => file);
    if (!hasDocuments) {
        incompleteFields.push('Document uploads');
    }

    const allMetadataComplete = data.metadata.every(
        (meta) =>
            meta.document_type &&
            meta.submission_date &&
            meta.municipal_offices &&
            meta.signatory_details
    );

    if (!allMetadataComplete) {
        incompleteFields.push('Document metadata');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                {/* Header Section (redesigned to match procurements-list) */}
                <div className="border-b pb-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-xl md:text-2xl lg:text-3xl font-bold tracking-tight flex items-center">
                                <FileText className="h-5 w-5 md:h-6 md:w-6 lg:h-8 lg:w-8 mr-2 md:mr-3 text-primary" />
                                New Procurement
                            </h1>
                            <p className="text-muted-foreground mt-1 md:mt-2 text-xs md:text-sm lg:text-base">
                                Start your procurement process by providing necessary details.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 mt-2 md:mt-0">
                            <Badge
                                className="bg-[var(--primary)]/10 hover:bg-[var(--primary)]/20 text-[var(--primary)] text-xs md:text-sm px-2 py-1 md:px-3 md:py-1.5 rounded-md font-medium transition-colors duration-200"
                            >
                                Procurement Initiation
                            </Badge>
                            {formState?.reference && (
                                <Badge className="text-xs md:text-sm bg-[var(--blue-1)]/10 text-[var(--blue-1)] dark:bg-[var(--blue-1)]/20 dark:text-[var(--blue-1)] px-2 py-1 md:px-3 md:py-1.5 rounded-md">
                                    {formState.reference}
                                </Badge>
                            )}
                            {formState?.isDraft && (
                                <Badge className="text-xs md:text-sm bg-[var(--yellow-1)]/10 hover:bg-[var(--yellow-1)]/20 text-[var(--yellow-1)] dark:bg-[var(--yellow-1)]/20 dark:text-[var(--yellow-1)] px-2 py-1 md:px-3 md:py-1.5 rounded-md transition-colors duration-200">
                                    Draft
                                </Badge>
                            )}
                            {formState?.isComplete && (
                                <Badge className="text-xs md:text-sm bg-[var(--green-1)]/10 hover:bg-[var(--green-1)]/20 text-[var(--green-1)] dark:bg-[var(--green-1)]/20 dark:text-[var(--green-1)] px-2 py-1 md:px-3 md:py-1.5 rounded-md transition-colors duration-200">
                                    Complete
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mt-2 sm:mt-0">
                    <div className="mt-4 sm:mt-6 space-y-4 sm:space-y-6">

                        <Card className="border-[var(--sidebar-border)] dark:border-[var(--sidebar-border)] relative overflow-hidden bg-[var(--card)] dark:bg-[var(--card)]/80 p-4 sm:p-6 shadow-sm">
                            <div className="space-y-6 sm:space-y-8 animate-fadeIn">
                                <div className="flex flex-col sm:flex-row sm:items-center gap-2 mb-4 sm:mb-6">
                                    <h2 className="text-xl sm:text-2xl font-semibold text-[var(--foreground)] dark:text-[var(--foreground)]">
                                        Procurement Details & Upload Documents
                                    </h2>
                                </div>

                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
                                    <Card className="p-4 sm:p-6 border-[var(--sidebar-border)] dark:border-[var(--sidebar-border)] shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                                        <div className="space-y-4 sm:space-y-5">
                                            <div>
                                                <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between mb-1 sm:mb-2">
                                                    <Label
                                                        htmlFor="procurement_id"
                                                        className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var(--foreground)] mb-0.5 sm:mb-0"
                                                    >
                                                        Procurement ID
                                                        <span className="text-red-600 dark:text-red-400 text-base ml-0.5 align-super" aria-label="Required">*</span>
                                                    </Label>
                                                </div>

                                                <Input
                                                    id="procurement_id"
                                                    type="text"
                                                    value={data.procurement_id}
                                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleFieldChange('procurement_id', e.target.value)}
                                                    onFocus={() => clearErrors('procurement_id')}
                                                    placeholder="Enter a unique ID for this procurement"
                                                    className={`transition-all duration-200 ${hasError('procurement_id')
                                                        ? 'border-[var(--destructive)] dark:border-[var(--destructive)] ring-1 ring-[var(--destructive)]/30'
                                                        : 'border-[var(--input)] dark:border-[var(--input)] focus:border-[var(--primary)]'}`}
                                                    aria-invalid={hasError('procurement_id')}
                                                />

                                                <InputError
                                                    message={hasError('procurement_id') ? errors.procurement_id : ''}
                                                    className="mt-1.5 sm:mt-2"
                                                />

                                                <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-[var(--muted-foreground)]">
                                                    The procurement ID is a unique identifier for this procurement process.
                                                </p>
                                            </div>

                                            <div className="p-3 sm:p-4 bg-[var(--accent)] dark:bg-[var(--accent)] rounded-lg border border-[var(--accent-foreground)] dark:border-[var(--accent-foreground)]">
                                                <p className="text-xs sm:text-sm text-[var(--accent-foreground)] dark:text-[var(--accent-foreground)]">
                                                    <span className="font-medium">Tip:</span> The procurement ID should follow your organization's naming convention, for example: PROC-2025-001
                                                </p>
                                            </div>
                                        </div>
                                    </Card>

                                    <Card className="p-4 sm:p-6 border-[var(--sidebar-border)] dark:border-[var(--sidebar-border)] shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                                        <div className="space-y-4 sm:space-y-5">
                                            <div>
                                                <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between mb-1 sm:mb-2">
                                                    <Label
                                                        htmlFor="procurement_title"
                                                        className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var,--foreground)] mb-0.5 sm:mb-0"
                                                    >
                                                        Procurement Title
                                                        <span className="text-red-600 dark:text-red-400 text-base ml-0.5 align-super" aria-label="Required">*</span>
                                                    </Label>
                                                </div>

                                                <Input
                                                    id="procurement_title"
                                                    type="text"
                                                    value={data.procurement_title}
                                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleFieldChange('procurement_title', e.target.value)}
                                                    onFocus={() => clearErrors('procurement_title')}
                                                    placeholder="Enter a descriptive title for this procurement"
                                                    className={`transition-all duration-200 ${hasError('procurement_title')
                                                        ? 'border-[var(--destructive)] dark:border-[var(--destructive)] ring-1 ring-[var(--destructive)]/30'
                                                        : 'border-[var(--input)] dark:border-[var(--input)] focus:border-[var(--primary)]'}`}
                                                    aria-invalid={hasError('procurement_title')}
                                                />

                                                <InputError
                                                    message={hasError('procurement_title') ? errors.procurement_title : ''}
                                                    className="mt-1.5 sm:mt-2"
                                                />

                                                <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-[var(--muted-foreground)]">
                                                    The procurement title should clearly describe what is being procured.
                                                </p>
                                            </div>

                                            <div className="p-3 sm:p-4 bg-accent-foreground dark:bg-accent rounded-lg border border-[var(--accent-foreground)] dark:border-[var(--accent-foreground)]">
                                                <p className="text-xs sm:text-sm text-[var(--accent-foreground)] dark:text-[var(--accent-foreground)]">
                                                    <span className="font-medium">Example:</span> "Supply and Delivery of Office Equipment for the Municipal Hall"
                                                </p>
                                            </div>
                                        </div>
                                    </Card>
                                </div>

                                <div className="space-y-6 sm:space-y-8 pt-4">
                                    {fileIndices.map((index, i) => {
                                        const file = data.files[index];
                                        const meta = data.metadata[index];
                                        const date = dates[index];
                                        const drop = fileDropHandlers[i];

                                        return (
                                            <div
                                                key={index}
                                                className={cn(
                                                    "border-[var(--sidebar-border)] dark:border-[var(--sidebar-border)] rounded-xl overflow-hidden bg-[var(--card)] dark:bg-[var(--card)]/50 transition-all duration-200",
                                                    hasError(`files.${index}`) || hasError(`metadata.${index}`)
                                                        ? 'ring-2 ring-[var(--destructive)]/30 border-[var(--destructive)] dark:border-[var,--destructive)]'
                                                        : 'shadow-sm hover:shadow-md'
                                                )}
                                            >
                                                <div className="bg-[var(--popover)] dark:bg-[var(--popover)] px-3 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2 sm:gap-3">
                                                        <FileText className="h-4 sm:h-5 w-4 sm:w-5 text-[var(--primary)]" />
                                                        <h3 className="font-medium text-base sm:text-lg">Document {index + 1}</h3>
                                                        {file && (
                                                            <Badge variant="outline" className="hidden sm:inline-flex bg-[var(--chart-1)]/10 text-[var(--chart-1)] dark:bg-[var(--chart-1)]/20 dark:text-[var(--chart-1)] border-[var(--chart-1)] dark:border-[var(--chart-1)]/50">
                                                                File Selected
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {fileIndices.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => removeFile(index)}
                                                            className="text-[var(--destructive)] hover:text-[var,--destructive] hover:bg-[var(--destructive)]/10 dark:hover:bg-[var(--destructive)]/20"
                                                        >
                                                            <X className="h-4 w-4 mr-1 sm:mr-0" />
                                                            <span className="sm:sr-only">Remove Document</span>
                                                        </Button>
                                                    )}
                                                </div>

                                                <Separator />

                                                <div className="p-3 sm:p-6">
                                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
                                                        <div className="space-y-4 sm:space-y-6">
                                                            <FileUploadArea
                                                                label="Document File"
                                                                file={file}
                                                                error={hasError(`files.${index}`) ? (errors as Record<string, string>)[`files.${index}`] : undefined}
                                                                isDragging={drop.isDragging}
                                                                onFileChange={e => handleFileChange(e, index)}
                                                                onDragEnter={drop.handleDragEnter}
                                                                onDragLeave={drop.handleDragLeave}
                                                                onDragOver={drop.handleDragOver}
                                                                onDrop={drop.handleDrop}
                                                                onRemove={() => {
                                                                    // Only remove the file, not the document entry
                                                                    const newFiles = [...data.files];
                                                                    newFiles[index] = null;
                                                                    setData('files', newFiles);
                                                                }}
                                                                inputId={`file-${index}`}
                                                                accept=".pdf"
                                                                required
                                                            />

                                                            <div>
                                                                <div className="flex items-baseline justify-between mb-2">
                                                                    <Label
                                                                        htmlFor={`document-type-${index}`}
                                                                        className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var,--foreground)]"
                                                                    >
                                                                        Document Type
                                                                    </Label>
                                                                    <span className="text-[0.65rem] sm:text-[0.7rem] text-[var(--muted-foreground)]">Required</span>
                                                                </div>

                                                                <Input
                                                                    id={`document-type-${index}`}
                                                                    type="text"
                                                                    value={meta?.document_type || ''}
                                                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleMetadataChange(index, 'document_type', e.target.value)}
                                                                    placeholder="Enter document type"
                                                                    className={cn(
                                                                        "transition-all duration-200",
                                                                        hasError(`metadata.${index}.document_type`)
                                                                            ? 'border-[var(--destructive)] dark:border-[var(--destructive)] ring-1 ring-[var(--destructive)]/30'
                                                                            : 'border-[var(--input)] dark:border-[var(--input)] focus:border-[var(--primary)]'
                                                                    )}
                                                                />

                                                                <InputError
                                                                    message={hasError(`metadata.${index}.document_type`) ? (errors as Record<string, string>)[`metadata.${index}.document_type`] : undefined}
                                                                    className="mt-2"
                                                                />

                                                                <p className="mt-2 text-xs sm:text-sm text-[var(--muted-foreground)]">
                                                                    Enter the type of document being uploaded (e.g., Project Proposal, Technical Requirements)
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <div className="relative">
                                                                    <DatePicker
                                                                        label="Submission Date"
                                                                        value={date}
                                                                        onChange={(newDate: Date | undefined) => handleDateChange(index, newDate)}
                                                                        error={(errors as Record<string, string>)[`metadata.${index}.submission_date`]}
                                                                        required
                                                                    />
                                                                </div>

                                                                <InputError
                                                                    message={(errors as Record<string, string>)[`metadata.${index}.submission_date`]}
                                                                    className="mt-2"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="space-y-4 sm:space-y-6">
                                                            <div>
                                                                <div className="flex items-baseline justify-between mb-2">
                                                                    <Label
                                                                        htmlFor={`municipal-offices-${index}`}
                                                                        className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var,--foreground)] flex items-center gap-2"
                                                                    >
                                                                        <Building className="h-4 w-4 text-[var(--primary)]/70" />
                                                                        Municipal Offices
                                                                    </Label>
                                                                    <span className="text-[0.65rem] sm:text-[0.7rem] text-[var(--muted-foreground)]">Required</span>
                                                                </div>

                                                                <Select
                                                                    value={meta?.municipal_offices || ''}
                                                                    onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                                                                >
                                                                    <SelectTrigger
                                                                        id={`municipal-offices-${index}`}
                                                                        className={cn(
                                                                            hasError(`metadata.${index}.municipal_offices`)
                                                                                ? 'border-[var(--destructive)] dark:border-[var(--destructive)] ring-1 ring-[var(--destructive)]/30'
                                                                                : 'border-[var(--input)] dark:border-[var(--input)]'
                                                                        )}
                                                                    >
                                                                        <SelectValue placeholder="Select municipal office" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {MUNICIPAL_OFFICES.map((office) => (
                                                                            <SelectItem key={office.value} value={office.value}>
                                                                                {office.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>

                                                                <InputError
                                                                    message={hasError(`metadata.${index}.municipal_offices`) ? (errors as Record<string, string>)[`metadata.${index}.municipal_offices`] : undefined}
                                                                    className="mt-2"
                                                                />

                                                                <p className="mt-2 text-xs sm:text-sm text-[var(--muted-foreground]">
                                                                    Select the municipal office involved in this document.
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <PeopleInput
                                                                    label="Signatories"
                                                                    value={meta?.signatory_details ? meta.signatory_details.split(',').map(s => s.trim()).filter(Boolean) : []}
                                                                    onChange={peopleArr => handleMetadataChange(index, 'signatory_details', peopleArr.join(', '))}
                                                                    error={hasError(`metadata.${index}.signatory_details`) ? (errors as Record<string, string>)[`metadata.${index}.signatory_details`] : undefined}
                                                                    required
                                                                    placeholder="Enter signatory names and positions, then press Enter or Add"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="flex justify-center pt-4 sm:pt-6">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addFile}
                                        className="flex items-center gap-2 px-4 sm:px-8 py-4 sm:py-6 border-dashed hover:bg-primary/5 transition-colors duration-200 w-full sm:w-auto"
                                    >
                                        <Plus className="h-4 w-4" />
                                        <span>Add Another Document</span>
                                    </Button>
                                </div>
                            </div>
                        </Card>

                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 order-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleSaveDraft}
                                className="gap-2 text-xs sm:text-sm"
                            >
                                <Save className="h-3 sm:h-4 w-3 sm:w-4" />
                                Save Draft
                            </Button>

                            <Button
                                type="button"
                                onClick={() => setShowConfirm(true)}
                                className="gap-2 w-full sm:w-auto text-xs sm:text-sm"
                            >
                                Submit
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog open={showConfirm} onOpenChange={setShowConfirm}>
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
                                                {data.procurement_id
                                                    ? data.procurement_id.length > 40
                                                        ? `${data.procurement_id.slice(0, 40)}...`
                                                        : data.procurement_id
                                                    : <span className="italic text-gray-400">Not set</span>}
                                            </span>
                                        </Label>
                                    </div>
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <Label className="text-sm sm:text-base font-medium text-[var(--foreground)] dark:text-[var(--foreground)] mb-0.5 sm:mb-0 flex items-center gap-2">
                                            Procurement Title:
                                            <span className="px-3 py-2 text-sm sm:text-base font-normal truncate max-w-[180px] sm:max-w-[300px]">
                                                {data.procurement_title
                                                    ? data.procurement_title.length > 40
                                                        ? `${data.procurement_title.slice(0, 40)}...`
                                                        : data.procurement_title
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
                                {data.files.map((file, index) => {
                                    const meta = data.metadata[index];
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
                        <Button variant="outline" onClick={() => setShowConfirm(false)}>
                            Cancel
                        </Button>
                        <Button onClick={onSubmit} disabled={processing}>
                            Submit Procurement
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

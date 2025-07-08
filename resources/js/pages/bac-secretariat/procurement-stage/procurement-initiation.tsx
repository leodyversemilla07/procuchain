import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    FileText, Save, Plus, X
} from 'lucide-react';
import { format } from 'date-fns';
import { useForm, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import DatePicker from '@/components/date-picker';
import { cn } from '@/lib/utils';
import PeopleInput from '@/components/people-input';
import FileUploadArea from '@/components/file-upload-area';
import { useMultiFileDrop } from '@/hooks/use-file-drop';
import { InputWithLabel } from '@/components/input-with-label';
import MunicipalOfficeSelect from '@/components/municipal-office-select';
import ReviewProcurementDialog from '@/components/review-procurement-dialog';

interface FileMetadata {
    document_type: string;
    submission_date: string;
    municipal_offices: string;
    signatories: string;
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
            signatories: ''
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
                updated[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatories: '' };
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
                    meta[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatories: '' };
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
        const copy = last >= 0 && meta[last] ? meta[last] : { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatories: '' };
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
            meta.signatories
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
                                className="bg-primary/10 hover:bg-primary/20 text-primary text-xs md:text-sm px-2 py-1 md:px-3 md:py-1.5 rounded-md font-medium transition-colors duration-200"
                            >
                                Procurement Initiation
                            </Badge>
                            {formState?.reference && (
                                <Badge className="text-xs md:text-sm bg-chart-1/10 text-chart-1 dark:bg-chart-1/20 dark:text-chart-1 px-2 py-1 md:px-3 md:py-1.5 rounded-md">
                                    {formState.reference}
                                </Badge>
                            )}
                            {formState?.isDraft && (
                                <Badge className="text-xs md:text-sm bg-chart-4/10 hover:bg-chart-4/20 text-chart-4 dark:bg-chart-4/20 dark:text-chart-4 px-2 py-1 md:px-3 md:py-1.5 rounded-md transition-colors duration-200">
                                    Draft
                                </Badge>
                            )}
                            {formState?.isComplete && (
                                <Badge className="text-xs md:text-sm bg-chart-2/10 hover:bg-chart-2/20 text-chart-2 dark:bg-chart-2/20 dark:text-chart-2 px-2 py-1 md:px-3 md:py-1.5 rounded-md transition-colors duration-200">
                                    Complete
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mt-2 sm:mt-0">
                    <div className="mt-4 sm:mt-6 space-y-4 sm:space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 animate-fadeIn">
                            {/* Left Column - Procurement Details */}
                            <div className="space-y-4 sm:space-y-6">
                                {/* Procurement ID */}
                                <Card className="p-4 sm:p-6 border-sidebar-border shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                                    <div className="space-y-4 sm:space-y-5">
                                        <div>
                                            <InputWithLabel
                                                id="procurement_id"
                                                label="Procurement ID"
                                                required
                                                type="text"
                                                value={data.procurement_id}
                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleFieldChange('procurement_id', e.target.value)}
                                                onFocus={() => clearErrors('procurement_id')}
                                                placeholder="Enter a unique ID for this procurement"
                                                className={`transition-all duration-200 ${hasError('procurement_id')
                                                    ? 'border-destructive ring-1 ring-destructive/30'
                                                    : 'border-input focus:border-primary'}`}
                                                aria-invalid={hasError('procurement_id')}
                                                error={hasError('procurement_id') ? errors.procurement_id : ''}
                                                errorClassName="mt-1.5 sm:mt-2"
                                            />
                                            <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-muted-foreground">
                                                The procurement ID is a unique identifier for this procurement process.
                                            </p>
                                        </div>
                                        <div className="p-3 sm:p-4 bg-accent rounded-lg border border-accent-foreground">
                                            <p className="text-xs sm:text-sm text-accent-foreground">
                                                <span className="font-medium">Tip:</span> The procurement ID should follow your organization's naming convention, for example: PROC-2025-0001-0001
                                            </p>
                                        </div>
                                    </div>

                                    {/* Procurement Title */}
                                    <div className="space-y-4 sm:space-y-5">
                                        <div>
                                            <InputWithLabel
                                                id="procurement_title"
                                                label="Procurement Title"
                                                required
                                                type="text"
                                                value={data.procurement_title}
                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleFieldChange('procurement_title', e.target.value)}
                                                onFocus={() => clearErrors('procurement_title')}
                                                placeholder="Enter a descriptive title for this procurement"
                                                className={`transition-all duration-200 ${hasError('procurement_title')
                                                    ? 'border-destructive ring-1 ring-destructive/30'
                                                    : 'border-input focus:border-primary'}`}
                                                aria-invalid={hasError('procurement_title')}
                                                error={hasError('procurement_title') ? errors.procurement_title : ''}
                                                errorClassName="mt-1.5 sm:mt-2"
                                            />
                                            <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-muted-foreground">
                                                The procurement title should clearly describe what is being procured.
                                            </p>
                                        </div>

                                        <div className="p-3 sm:p-4 bg-accent rounded-lg border border-accent-foreground">
                                            <p className="text-xs sm:text-sm text-accent-foreground">
                                                <span className="font-medium">Example:</span> "Supply and Delivery of Office Equipment for the Municipal Hall"
                                            </p>
                                        </div>
                                    </div>
                                </Card>
                            </div>

                            {/* Right Column - Documents */}
                            <div className="lg:col-span-2 space-y-6 sm:space-y-8">
                                {fileIndices.map((index, i) => {
                                    const file = data.files[index];
                                    const meta = data.metadata[index];
                                    const date = dates[index];
                                    const drop = fileDropHandlers[i];

                                    return (
                                        <Card
                                            key={index}
                                            className={cn(
                                                "border-sidebar-border transition-all duration-200",
                                                hasError(`files.${index}`) || hasError(`metadata.${index}`)
                                                    ? 'ring-2 ring-destructive/30 border-destructive'
                                                    : 'shadow-sm hover:shadow-md'
                                            )}
                                        >
                                            <CardHeader className="bg-popover">
                                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2 sm:gap-3">
                                                        <FileText className="h-4 sm:h-5 w-4 sm:w-5 text-primary" />
                                                        <h3 className="font-medium text-base sm:text-lg">Document {index + 1}</h3>
                                                        {file && (
                                                            <Badge variant="outline" className="hidden sm:inline-flex bg-chart-1/10 text-chart-1 border-chart-1/50">
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
                                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                                        >
                                                            <X className="h-4 w-4 mr-1 sm:mr-0" />
                                                            <span className="sm:sr-only">Remove Document</span>
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardHeader>

                                            <CardContent>
                                                <div className="space-y-4 sm:space-y-6">
                                                    {/* File Upload - Full Width */}
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

                                                    {/* Metadata Fields - 2 Column Grid */}
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                                                        {/* Document Type */}
                                                        <div>
                                                            <InputWithLabel
                                                                id={`document-type-${index}`}
                                                                label="Document Type"
                                                                required
                                                                type="text"
                                                                value={meta?.document_type || ''}
                                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleMetadataChange(index, 'document_type', e.target.value)}
                                                                placeholder="Enter document type"
                                                                className={cn(
                                                                    "transition-all duration-200",
                                                                    hasError(`metadata.${index}.document_type`)
                                                                        ? 'border-destructive ring-1 ring-destructive/30'
                                                                        : 'border-input focus:border-primary'
                                                                )}
                                                                error={hasError(`metadata.${index}.document_type`) ? (errors as Record<string, string>)[`metadata.${index}.document_type`] : undefined}
                                                                errorClassName="mt-1.5 sm:mt-2"
                                                            />
                                                            <p className="mt-2 text-xs sm:text-sm text-muted-foreground">
                                                                Enter the type of document being uploaded (e.g., Project Proposal, Technical Requirements)
                                                            </p>
                                                        </div>

                                                        {/* Submission Date */}
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
                                                        </div>

                                                        {/* Municipal Offices */}
                                                        <div>
                                                            <MunicipalOfficeSelect
                                                                id={`municipal-offices-${index}`}
                                                                label="Municipal Offices"
                                                                value={meta?.municipal_offices || ''}
                                                                onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                                                                error={hasError(`metadata.${index}.municipal_offices`) ? (errors as Record<string, string>)[`metadata.${index}.municipal_offices`] : undefined}
                                                                required
                                                            />
                                                        </div>

                                                        {/* Signatories - Takes remaining space */}
                                                        <div>
                                                        <PeopleInput
                                                            label="Signatories"
                                                            value={meta?.signatories ?
                                                                meta.signatories.split(';')
                                                                    .map(s => {
                                                                        const [name, affiliation] = s.split('|').map(str => str.trim());
                                                                        if (name && affiliation) {
                                                                            return { name, affiliation };
                                                                        }
                                                                        return undefined;
                                                                    })
                                                                    .filter((p): p is { name: string; affiliation: string } => !!p)
                                                                : []
                                                            }
                                                            onChange={peopleArr => handleMetadataChange(
                                                                index,
                                                                'signatories',
                                                                peopleArr.map(p => `${p.name}|${p.affiliation}`).join('; ')
                                                            )}
                                                            error={hasError(`metadata.${index}.signatories`) ? (errors as Record<string, string>)[`metadata.${index}.signatories`] : undefined}
                                                            required
                                                            affiliationType="position"
                                                            namePlaceholder="Enter signatory name"
                                                        />
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}

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
                        </div>

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

            <ReviewProcurementDialog
                open={showConfirm}
                onOpenChange={setShowConfirm}
                procurementId={data.procurement_id}
                procurementTitle={data.procurement_title}
                files={data.files}
                metadata={data.metadata}
                onSubmit={onSubmit}
                processing={processing}
            />
        </AppLayout>
    );
}

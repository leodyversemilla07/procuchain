import { Head, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { useMultiFileDrop } from '@/hooks/use-file-drop';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

import AppLayout from '@/layouts/app-layout';

import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import MunicipalOfficeSelect from '@/components/municipal-office-select';
import PeopleInput from '@/components/people-input';

import { FileText, Plus, X } from 'lucide-react';
import { index as procurementsListIndex } from '@/routes/bac-secretariat/procurements';

interface FileMetadata {
    document_type: string;
    submission_date: Date | string;
    municipal_offices: string;
    signatories: { name: string; position: string }[];
    [key: string]: string | Date | { name: string; position: string }[];
}

type UseFormData = {
    procurement_id: string;
    procurement_title: string;
    files: (File | null)[];
    metadata: FileMetadata[];
    // Remove the index signature to allow setData to accept string keys
};

interface HeaderProps {
    formState?: {
        isComplete?: boolean;
        createdAt?: string;
        lastUpdated?: string;
        reference?: string;
    };
}

export default function ProcurementInitiationForm({ formState }: HeaderProps) {
    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        { title: 'Procurement Initiation', href: '#' },
    ]);
    
    const [dates, setDates] = useState<Record<number, Date | undefined>>({});

    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<UseFormData>({
        procurement_id: '',
        procurement_title: '',
        files: [null],
        metadata: [
            {
                document_type: '',
                submission_date: new Date(),
                municipal_offices: '',
                signatories: [],
            },
        ],
    });

    const parseDate = (dateStr: string): Date | undefined => {
        if (!dateStr) return undefined;

        try {
            const date = new Date(dateStr);
            return !isNaN(date.getTime()) ? date : undefined;
        } catch (e) {
            console.error('Error parsing date:', e);
            return undefined;
        }
    };

    const validateFile = useCallback((file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            toast.error('File too large', { description: 'Maximum file size is 10MB' });
            return false;
        }

        if (file.type && file.type !== 'application/pdf') {
            toast.error('Invalid file type', { description: `File "${file.name}" does not appear to be a PDF. Detected type: ${file.type}.` });
            return false;
        }

        if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
            toast.error('Invalid file type', { description: `File "${file.name}" is not recognized as a PDF and has no .pdf extension.` });
            return false;
        }

        return true;
    }, []);

    const handleMetadataChange = useCallback(
        (index: number, field: keyof FileMetadata, value: string | Date | { name: string; position: string }[]) => {
            clearErrors();
            const updated = Array.isArray(data.metadata) ? [...data.metadata] : [];
            if (!updated[index]) {
                updated[index] = { document_type: '', submission_date: new Date(), municipal_offices: '', signatories: [] };
            }
            // If field is submission_date, ensure value is a Date
            if (field === 'submission_date') {
                updated[index] = { ...updated[index], [field]: value instanceof Date ? value : value ? new Date(value as string) : new Date() };
            } else {
                updated[index] = { ...updated[index], [field]: value };
            }
            setData('metadata', updated);
        },
        [data.metadata, setData, clearErrors],
    );

    const handleDateChange = useCallback(
        (index: number, date: Date | undefined) => {
            clearErrors();
            setDates((prev) => ({ ...prev, [index]: date }));
            handleMetadataChange(index, 'submission_date', date || new Date());
        },
        [handleMetadataChange, clearErrors],
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
                    meta[index] = { document_type: '', submission_date: new Date(), municipal_offices: '', signatories: [] };
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
        [data.files, data.metadata, validateFile, setData, clearErrors],
    );

    const validateDocuments = useCallback(() => {
        const files = Array.isArray(data.files) ? data.files : [];
        const meta = Array.isArray(data.metadata) ? data.metadata : [];
        if (files.length === 0 || files.some((f) => f === null)) {
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
        const copy =
            last >= 0 && meta[last] ? meta[last] : { document_type: '', submission_date: new Date(), municipal_offices: '', signatories: [] };
        meta.push({ ...copy, document_type: '', submission_date: new Date(), signatories: [] });
        setData('files', files);
        setData('metadata', meta);
        setDates((d) => (last >= 0 ? { ...d, [last + 1]: new Date() } : { 0: new Date() }));
    }, [data.files, data.metadata, setData]);

    const removeFile = useCallback(
        (index: number) => {
            const files = Array.isArray(data.files) ? [...data.files] : [];
            files.splice(index, 1);
            const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
            meta.splice(index, 1);
            setData('files', files);
            setData('metadata', meta);
        },
        [data.files, data.metadata, setData],
    );

    const handleFieldChange = (field: keyof UseFormData, value: string): void => {
        clearErrors(field);
        setData(field, value);
    };

    const validateForm = useCallback(() => {
        let isBasicValid = true;
        if (!data.procurement_id || String(data.procurement_id).trim() === '') {
            isBasicValid = false;
        }
        if (!data.procurement_title || String(data.procurement_title).trim() === '') {
            isBasicValid = false;
        }
        const areDocumentsValid = validateDocuments();
        return isBasicValid && areDocumentsValid;
    }, [data.procurement_id, data.procurement_title, validateDocuments]);

    const onSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            if (!validateForm()) {
                toast.error('Please complete all required fields', {
                    description: 'Fill in all required fields and upload necessary documents before submitting.',
                });
                return;
            }
            const submissionToast = toast.loading('Submitting Procurement...');

            transform((formData) => ({
                ...formData,
                metadata: Array.isArray(formData.metadata)
                    ? formData.metadata.map((m) => ({
                          ...m,
                          submission_date: m.submission_date instanceof Date ? format(m.submission_date, 'yyyy-MM-dd') : m.submission_date,
                      }))
                    : formData.metadata,
            }));

            post('/bac-secretariat/publish-procurement-initiation', {
                onSuccess: () => {
                    toast.success('Procurement successfully submitted', {
                        id: submissionToast,
                    });
                    reset();
                    // Navigate to the procurement list with a success message
                    setTimeout(() => {
                        router.visit(procurementsListIndex.url(), {
                            preserveState: false,
                            replace: true,
                        });
                    }, 1500);
                },
                onError: (formErrors: Record<string, string>) => {
                    toast.error('Failed to submit', {
                        id: submissionToast,
                        description: Object.values(formErrors)[0],
                    });
                },
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
            });
        },
        [validateForm, transform, post, reset],
    );

    const hasError = useCallback(
        (field: string) => {
            return Object.keys(errors).some((error) => error === field || error.startsWith(`${field}.`));
        },
        [errors],
    );

    const fileIndices = useMemo(() => (Array.isArray(data.files) ? Array.from({ length: data.files.length }, (_, i) => i) : []), [data.files]);

    useEffect(() => {
        try {
            const newDates: { [key: number]: Date | undefined } = {};
            const metadata = Array.isArray(data.metadata) ? data.metadata : [];
            metadata.forEach((meta: FileMetadata, index: number) => {
                if (meta.submission_date) {
                    let parsedDate: Date | undefined;
                    if (typeof meta.submission_date === 'string') {
                        parsedDate = parseDate(meta.submission_date);
                    } else if (meta.submission_date instanceof Date) {
                        parsedDate = meta.submission_date;
                    }
                    if (parsedDate) {
                        newDates[index] = parsedDate;
                    }
                }
            });
            setDates(newDates);
        } catch (e) {
            console.error('Error setting dates:', e);
        }
    }, [data.metadata]);

    const fileDropHandlers = useMultiFileDrop(fileIndices, validateFile, (index, file) => {
        const newFiles = [...data.files];
        newFiles[index] = file;
        setData('files', newFiles);
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8" role="main" aria-labelledby="page-title">
                {/* Header Section */}
                <HeroCard
                    icon={FileText}
                    title="New Procurement"
                    description="Start your procurement process by providing necessary details."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="bg-primary/10 hover:bg-primary/20 text-primary rounded-md px-2 py-1 text-xs font-medium transition-colors duration-200 md:px-3 md:py-1.5 md:text-sm">
                                Procurement Initiation
                            </Badge>
                            {formState?.reference && (
                                <Badge className="bg-chart-1/10 text-chart-1 dark:bg-chart-1/20 dark:text-chart-1 rounded-md px-2 py-1 text-xs md:px-3 md:py-1.5 md:text-sm">
                                    {formState.reference}
                                </Badge>
                            )}
                            {formState?.isComplete && (
                                <Badge className="bg-chart-2/10 hover:bg-chart-2/20 text-chart-2 dark:bg-chart-2/20 dark:text-chart-2 rounded-md px-2 py-1 text-xs transition-colors duration-200 md:px-3 md:py-1.5 md:text-sm">
                                    Complete
                                </Badge>
                            )}
                        </div>
                    }
                />

                <div className="mt-2 sm:mt-0">
                    <div className="mt-4 space-y-4 sm:mt-6 sm:space-y-6">
                        <div className="animate-fadeIn grid grid-cols-1 gap-6 sm:gap-8 lg:grid-cols-3">
                            {/* Left Column - Procurement Details */}
                            <div className="space-y-4 sm:space-y-6">
                                {/* Procurement ID */}
                                <Card className="border-sidebar-border relative overflow-hidden p-4 shadow-sm transition-all duration-200 hover:shadow-md sm:p-6">
                                    <div className="space-y-4 sm:space-y-5">
                                        <Field>
                                            <FieldLabel htmlFor="pr-number">
                                                Procurement ID
                                                <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <div className="grid w-full grid-cols-1 gap-2 sm:grid-cols-4">
                                                <Input
                                                    id="pr-number"
                                                    type="text"
                                                    value="PR"
                                                    readOnly
                                                    className="border-border text-foreground w-full text-center"
                                                    required
                                                />
                                                <Input
                                                    id="pr-year"
                                                    type="text"
                                                    value={String(new Date().getFullYear())}
                                                    readOnly
                                                    className="border-border text-foreground w-full text-center"
                                                    required
                                                />
                                                <Input
                                                    id="pr-serial1"
                                                    type="text"
                                                    value={data.procurement_id.split('-')[2] || ''}
                                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                                        const val = e.target.value.replace(/\D/g, '').slice(0, 4);
                                                        const parts = data.procurement_id.split('-');
                                                        const year = new Date().getFullYear().toString();
                                                        const serial2 = parts[3] || '';
                                                        setData('procurement_id', `PR-${year}-${val}-${serial2}`);
                                                    }}
                                                    className="border-border text-foreground w-full text-center"
                                                    maxLength={4}
                                                    placeholder="0001"
                                                    required
                                                />
                                                <Input
                                                    id="pr-serial2"
                                                    type="text"
                                                    value={data.procurement_id.split('-')[3] || ''}
                                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                                        const val = e.target.value.replace(/\D/g, '').slice(0, 4);
                                                        const parts = data.procurement_id.split('-');
                                                        const year = new Date().getFullYear().toString();
                                                        const serial1 = parts[2] || '';
                                                        setData('procurement_id', `PR-${year}-${serial1}-${val}`);
                                                    }}
                                                    className="border-border text-foreground w-full text-center"
                                                    maxLength={4}
                                                    placeholder="0001"
                                                    required
                                                />
                                            </div>
                                            <FieldDescription>
                                                The procurement ID is a unique identifier for this procurement process.
                                            </FieldDescription>
                                            {hasError('procurement_id') && <FieldError>{errors.procurement_id}</FieldError>}
                                        </Field>
                                    </div>

                                    {/* Procurement Title */}
                                    <div className="space-y-4 sm:space-y-5">
                                        <Field>
                                            <FieldLabel htmlFor="procurement_title">
                                                Procurement Title
                                                <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <Input
                                                id="procurement_title"
                                                type="text"
                                                value={data.procurement_title}
                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                                                    handleFieldChange('procurement_title', e.target.value)
                                                }
                                                onFocus={() => clearErrors('procurement_title')}
                                                placeholder="Enter a descriptive title for this procurement"
                                                className={cn(
                                                    'transition-all duration-200',
                                                    hasError('procurement_title')
                                                        ? 'border-destructive ring-destructive/30 ring-1'
                                                        : 'border-input focus:border-primary',
                                                )}
                                                aria-invalid={hasError('procurement_title')}
                                                required
                                            />
                                            <FieldDescription>The procurement title should clearly describe what is being procured.</FieldDescription>
                                            {hasError('procurement_title') && <FieldError>{errors.procurement_title}</FieldError>}
                                        </Field>

                                        <div className="bg-accent/10 dark:bg-accent/20 border-accent-foreground/50 dark:border-accent-foreground/70 rounded-lg border p-3 transition-colors duration-200 sm:p-4">
                                            <p className="text-accent-foreground dark:text-accent-foreground/80 text-xs sm:text-sm">
                                                <span className="font-medium">Example:</span> "Supply and Delivery of Office Equipment for the
                                                Municipal Hall"
                                            </p>
                                        </div>
                                    </div>
                                </Card>

                                {/* Preview Card */}
                                {(data.procurement_id || data.procurement_title || data.files.some((f) => f !== null)) && (
                                    <Card className="border-sidebar-border bg-muted/30 dark:bg-muted/10 p-4 shadow-sm transition-all duration-200 hover:shadow-md sm:p-6">
                                        <CardHeader className="px-0 pt-0 pb-4">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-muted-foreground text-sm font-semibold">Procurement Preview</h3>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4 px-0 pb-0">
                                            {/* Procurement ID Preview */}
                                            {data.procurement_id && (
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                                        Procurement ID
                                                    </p>
                                                    <p className="text-foreground font-mono text-sm font-semibold">{data.procurement_id}</p>
                                                </div>
                                            )}

                                            {/* Procurement Title Preview */}
                                            {data.procurement_title && (
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Title</p>
                                                    <p className="text-foreground text-sm">{data.procurement_title}</p>
                                                </div>
                                            )}

                                            {/* Files Preview */}
                                            {data.files.some((f) => f !== null) && (
                                                <div className="space-y-2">
                                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                                        Documents ({data.files.filter((f) => f !== null).length})
                                                    </p>
                                                    <div className="space-y-2">
                                                        {data.files.map((file, index) => {
                                                            if (!file) return null;
                                                            const metadata = data.metadata[index];
                                                            return (
                                                                <div
                                                                    key={index}
                                                                    className="bg-background/50 flex items-start gap-3 rounded border p-2"
                                                                >
                                                                    <FileText className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                                                    <div className="min-w-0 flex-1 space-y-1">
                                                                        <p className="text-foreground truncate text-xs font-medium" title={file.name}>
                                                                            {file.name}
                                                                        </p>
                                                                        <div className="text-muted-foreground flex flex-wrap gap-2 text-xs">
                                                                            <span>{(file.size / 1024 / 1024).toFixed(2)} MB</span>
                                                                            {metadata?.document_type && (
                                                                                <Badge variant="secondary" className="h-4 px-1 text-[10px]">
                                                                                    {metadata.document_type}
                                                                                </Badge>
                                                                            )}
                                                                            {metadata?.municipal_offices && (
                                                                                <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                                                    {metadata.municipal_offices}
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                        {metadata?.signatories && metadata.signatories.length > 0 && (
                                                                            <p className="text-muted-foreground text-xs">
                                                                                {metadata.signatories.length} signator
                                                                                {metadata.signatories.length === 1 ? 'y' : 'ies'}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                            </div>

                            {/* Right Column - Documents */}
                            <div className="space-y-6 sm:space-y-8 lg:col-span-2">
                                {fileIndices.map((index, i) => {
                                    const file = data.files[index];
                                    const meta = data.metadata[index];
                                    const date = dates[index];
                                    const drop = fileDropHandlers[i];

                                    return (
                                        <Card
                                            key={index}
                                            className={cn(
                                                'border-sidebar-border transition-all duration-200',
                                                hasError(`files.${index}`) || hasError(`metadata.${index}`)
                                                    ? 'ring-destructive/30 border-destructive ring-2'
                                                    : 'shadow-sm hover:shadow-md',
                                            )}
                                        >
                                            <CardHeader className="bg-popover">
                                                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                                    <div className="flex items-center gap-2 sm:gap-3">
                                                        <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                                        <h3 className="text-base font-medium sm:text-lg">Document {index + 1}</h3>
                                                        {file && (
                                                            <Badge
                                                                variant="outline"
                                                                className="bg-chart-1/10 text-chart-1 border-chart-1/50 hidden sm:inline-flex"
                                                            >
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
                                                            <X className="mr-1 h-4 w-4 sm:mr-0" />
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
                                                        error={
                                                            hasError(`files.${index}`)
                                                                ? (errors as Record<string, string>)[`files.${index}`]
                                                                : undefined
                                                        }
                                                        isDragging={drop.isDragging}
                                                        onFileChange={(e: React.ChangeEvent<HTMLInputElement>) => handleFileChange(e, index)}
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
                                                        required
                                                    />

                                                    {/* Metadata Fields - 2 Column Grid */}
                                                    <div className="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2">
                                                        {/* Document Type */}
                                                        <Field>
                                                            <FieldLabel htmlFor={`document-type-${index}`}>
                                                                Document Type
                                                                <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                                                                    *
                                                                </span>
                                                            </FieldLabel>
                                                            <Input
                                                                id={`document-type-${index}`}
                                                                type="text"
                                                                value={meta?.document_type || ''}
                                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                                                                    handleMetadataChange(index, 'document_type', e.target.value)
                                                                }
                                                                placeholder="Enter document type"
                                                                className={cn(
                                                                    'transition-all duration-200',
                                                                    hasError(`metadata.${index}.document_type`)
                                                                        ? 'border-destructive ring-destructive/30 ring-1'
                                                                        : 'border-input focus:border-primary',
                                                                )}
                                                                required
                                                            />
                                                            <FieldDescription>
                                                                Enter the type of document being uploaded (e.g., Project Proposal, Technical
                                                                Requirements)
                                                            </FieldDescription>
                                                            {hasError(`metadata.${index}.document_type`) && (
                                                                <FieldError>
                                                                    {(errors as Record<string, string>)[`metadata.${index}.document_type`]}
                                                                </FieldError>
                                                            )}
                                                        </Field>

                                                        {/* Submission Date */}
                                                        <DatePicker
                                                            id={`submission-date-${index}`}
                                                            label="Submission Date"
                                                            value={date}
                                                            onChange={(newDate: Date | undefined) => handleDateChange(index, newDate)}
                                                            error={(errors as Record<string, string>)[`metadata.${index}.submission_date`]}
                                                            required
                                                        />

                                                        {/* Municipal Offices */}
                                                        <MunicipalOfficeSelect
                                                            id={`municipal-offices-${index}`}
                                                            label="Municipal Offices"
                                                            value={meta?.municipal_offices || ''}
                                                            onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                                                            error={
                                                                hasError(`metadata.${index}.municipal_offices`)
                                                                    ? (errors as Record<string, string>)[`metadata.${index}.municipal_offices`]
                                                                    : undefined
                                                            }
                                                            required
                                                        />

                                                        {/* Signatories */}
                                                        <PeopleInput
                                                            label="Signatories"
                                                            value={
                                                                Array.isArray(meta?.signatories)
                                                                    ? meta.signatories.map((s) => ({ name: s.name, affiliation: s.position }))
                                                                    : []
                                                            }
                                                            onChange={(peopleArr) =>
                                                                handleMetadataChange(
                                                                    index,
                                                                    'signatories',
                                                                    peopleArr.map((p) => ({ name: p.name, position: p.affiliation })),
                                                                )
                                                            }
                                                            error={
                                                                hasError(`metadata.${index}.signatories`)
                                                                    ? (errors as Record<string, string>)[`metadata.${index}.signatories`]
                                                                    : undefined
                                                            }
                                                            required
                                                            affiliationType="position"
                                                            namePlaceholder="Enter signatory name"
                                                        />
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
                                        className="hover:bg-primary/5 flex w-full items-center gap-2 border-dashed px-4 py-4 transition-colors duration-200 sm:w-auto sm:px-8 sm:py-6"
                                    >
                                        <Plus className="h-4 w-4" />
                                        <span>Add Another Document</span>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="order-3 flex flex-col items-stretch justify-end gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <Button type="submit" onClick={onSubmit} disabled={processing} className="w-full gap-2 text-xs sm:w-auto sm:text-sm">
                                {processing ? 'Submitting...' : 'Submit Procurement'}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

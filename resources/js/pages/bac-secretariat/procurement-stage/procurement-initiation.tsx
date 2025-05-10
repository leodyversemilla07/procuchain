import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { FileText, Upload, ClipboardList, CheckCircle2, ChevronLeft, ChevronRight, Save } from 'lucide-react';
import { format } from 'date-fns';
import { useForm, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import { type BreadcrumbItem } from '@/types';
import { type FormSummaryProps } from '@/components/procurement-initiation/form-summary';
import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { FormHeader } from '@/components/procurement-initiation/form-header';
import { ProcurementDetails } from '@/components/procurement-initiation/procurement-details';
import { ProcurementDocuments } from '@/components/procurement-initiation/procurement-documents';
import { FormSummary } from '@/components/procurement-initiation/form-summary';
import { FormDataConvertible } from '@inertiajs/core';

interface FileMetadata {
    document_type: string;
    submission_date: string;
    municipal_offices: string;
    signatory_details: string;
    [key: string]: FormDataConvertible;
}

type FormDataValue = string | File | null | (File | null)[] | FileMetadata[] | FormDataConvertible;

interface UseFormData {
    procurement_id: string;
    procurement_title: string;
    file: File | null;
    files: (File | null)[];
    metadata: FileMetadata[];
    [key: string]: FormDataValue;
}

interface PostOptions {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    forceFormData?: boolean;
    preserveScroll?: boolean;
    preserveState?: boolean;
}

interface InertiaFormInstance<TForm> {
    setData(key: keyof TForm, value: FormDataConvertible): void;
    setData(values: Partial<TForm>): void;
}

interface ExtendedForm {
    data: UseFormData;
    setData: SetDataFunction;
    post: (url: string, options?: PostOptions) => void;
    processing: boolean;
    errors: Record<string, string>;
    reset: () => void;
    clearErrors: () => void;
}

type SetDataFunction = {
    (field: keyof UseFormData, value: FormDataValue): void;
    (fields: Partial<UseFormData>): void;
}

interface ProcurementDetailsStepProps {
    data: {
        procurement_id: string;
        procurement_title: string;
    };
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    handleFieldChange: (field: string, value: string) => void;
    clearErrors: () => void;
}

type ComponentFormData = FormSummaryProps['data'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurement Initiation', href: '#' },
];

interface FormStep {
    id: number;
    title: string;
    description: string;
    icon: React.ReactNode;
}

const formSteps: FormStep[] = [
    {
        id: 1,
        title: "Details",
        description: "Basic procurement information",
        icon: <FileText className="h-6 w-6" />,
    },
    {
        id: 2,
        title: "Documents",
        description: "Upload required files",
        icon: <Upload className="h-6 w-6" />,
    },
    {
        id: 3,
        title: "Review",
        description: "Verify and submit",
        icon: <ClipboardList className="h-6 w-6" />,
    }
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

const prepareFormSummaryData = (
    data: UseFormData,
    formatDateFn: (date: Date | string | undefined) => string
): UseFormData => {
    const result = { ...data };

    result.metadata = Array.isArray(data.metadata) ? data.metadata.map(meta => ({
        ...meta,
        submission_date: formatDateFn(meta.submission_date)
    })) : [];

    return result;
};

export default function ProcurementInitiationForm() {
    const [formCompletion, setFormCompletion] = useState({
        details: false,
        document: false,
        documents: false,
    });
    const [isDragging, setIsDragging] = useState(false);
    const [dates, setDates] = useState<Record<number, Date | undefined>>({});
    const [currentStep, setCurrentStep] = useState(1);

    const form = useForm<UseFormData>({
        procurement_id: '',
        procurement_title: '',
        file: null,
        files: [null],
        metadata: [{
            document_type: '',
            submission_date: format(new Date(), 'yyyy-MM-dd'),
            municipal_offices: '',
            signatory_details: ''
        }]
    }) as unknown as ExtendedForm & InertiaFormInstance<UseFormData>;

    const { data, post, processing, errors, reset } = form;

    const updateFormData = useCallback(
        (field: keyof UseFormData, value: FormDataValue) => form.setData(field, value as FormDataConvertible),
        [form]
    );

    const validateFile = useCallback((file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            toast.error("File too large", { description: "Maximum file size is 10MB" });
            return false;
        }

        // Stricter validation:
        // 1. If a MIME type is reported by the browser, and it's not 'application/pdf', reject.
        //    This catches files like PNGs renamed to PDF.
        if (file.type && file.type !== 'application/pdf') {
            toast.error("Invalid file type", { description: `File "${file.name}" does not appear to be a PDF. Detected type: ${file.type}.` });
            return false;
        }

        // 2. If no MIME type is reported (file.type is empty or null), then rely on the file extension.
        //    It must end with .pdf.
        if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
            toast.error("Invalid file type", { description: `File "${file.name}" is not recognized as a PDF and has no .pdf extension.` });
            return false;
        }
        
        // Passes if:
        // - file.type is 'application/pdf' (name doesn't strictly matter in this case for client-side)
        // - file.type is empty/null AND file.name.toLowerCase().endsWith('.pdf')
        return true;
    }, []);

    const handleMetadataChange = useCallback(
        (index: number, field: keyof FileMetadata, value: string) => {
            form.clearErrors();
            const updated = Array.isArray(data.metadata) ? [...data.metadata] : [];
            if (!updated[index]) {
                updated[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatory_details: '' };
            }
            updated[index] = { ...updated[index], [field]: value };
            updateFormData('metadata', updated);
        },
        [form, data.metadata, updateFormData]
    );

    const handleDateChange = useCallback(
        (index: number, date: Date | undefined) => {
            form.clearErrors();
            setDates(prev => ({ ...prev, [index]: date }));
            if (date) {
                handleMetadataChange(index, 'submission_date', format(date, 'yyyy-MM-dd'));
            } else {
                handleMetadataChange(index, 'submission_date', '');
            }
        },
        [form, handleMetadataChange]
    );

    const handleFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
            form.clearErrors();
            const newSelectedFile = e.target.files?.[0] || null;
            const updatedFiles = Array.isArray(data.files) ? [...data.files] : [];

            if (newSelectedFile && validateFile(newSelectedFile)) {
                updatedFiles[index] = newSelectedFile;
                updateFormData('files', updatedFiles);

                const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
                if (!meta[index]) {
                    meta[index] = { document_type: '', submission_date: format(new Date(), 'yyyy-MM-dd'), municipal_offices: '', signatory_details: '' };
                    updateFormData('metadata', meta);
                }
            } else {
                // newSelectedFile is either null (input cleared) or invalid.
                if (newSelectedFile) { // It was an invalid file selection
                    // validateFile would have shown a toast.
                    e.target.value = ''; // Clear the file input UI.
                }
                // Set the file at this index to null in the form's state.
                if (updatedFiles[index] !== null) { // Avoid redundant update if already null
                    updatedFiles[index] = null;
                    updateFormData('files', updatedFiles);
                }
            }
        },
        [form, data.files, data.metadata, validateFile, updateFormData]
    );

    const handleMainFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0] || null;
            if (file && validateFile(file)) updateFormData('file', file);
        },
        [validateFile, updateFormData]
    );

    const handleFileDragEvent = useCallback(
        (e: React.DragEvent, action: 'enter' | 'leave' | 'over' | 'drop', index?: number) => {
            e.preventDefault(); e.stopPropagation();
            if (action === 'enter') setIsDragging(true);
            if (action === 'leave') setIsDragging(false);
            if (action === 'drop') {
                setIsDragging(false);
                const file = e.dataTransfer.files[0];
                if (file && validateFile(file)) {
                    if (index != null) {
                        const files = Array.isArray(data.files) ? [...data.files] : [];
                        files[index] = file;
                        updateFormData('files', files);
                    } else {
                        updateFormData('file', file);
                    }
                }
            }
        },
        [data.files, validateFile, updateFormData]
    );

    const validateDocuments = useCallback(() => {
        const files = Array.isArray(data.files) ? data.files : [];
        const meta = Array.isArray(data.metadata) ? data.metadata : [];

        // Corresponds to backend 'files' => 'required|array|min:1'
        // and 'files.*' => 'required|file|...'
        // This means the files array cannot be empty and all its elements must be actual files.
        if (files.length === 0 || files.some(f => f === null)) {
            return false;
        }

        // If all files are present, their *required* metadata must also be complete.
        // Based on PHP validation: 'metadata.*.document_type' => 'required|string|max:255'
        // Other metadata fields like submission_date, municipal_offices, signatory_details are nullable
        // so we only strictly check for document_type here for each file.
        const allRequiredMetadataPresent = files.every((file, index) => {
            // file is guaranteed non-null here due to the check above.
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
        updateFormData('files', files);
        updateFormData('metadata', meta);
        setDates(d => last >= 0 ? { ...d, [last + 1]: parseDate(format(new Date(), 'yyyy-MM-dd')) } : { 0: parseDate(format(new Date(), 'yyyy-MM-dd')) });
        setFormCompletion(c => ({ ...c, documents: false }));
    }, [data.files, data.metadata, updateFormData]);

    const removeFile = useCallback((index: number) => {
        const files = Array.isArray(data.files) ? [...data.files] : [];
        files.splice(index, 1);
        const meta = Array.isArray(data.metadata) ? [...data.metadata] : [];
        meta.splice(index, 1);
        updateFormData('files', files);
        updateFormData('metadata', meta);
    }, [data.files, data.metadata, updateFormData]);

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

    const convertToComponentFormData = (formData: UseFormData): ComponentFormData => {
        return {
            procurement_id: String(formData.procurement_id || ''),
            procurement_title: String(formData.procurement_title || ''),
            files: Array.isArray(formData.files) ? formData.files.map(file => file || undefined) : [],
            metadata: Array.isArray(formData.metadata) ? formData.metadata : [],
        } as ComponentFormData;
    };

    const handleFieldChange = (field: keyof UseFormData, value: FormDataConvertible): void => {
        form.clearErrors();
        updateFormData(field, value);
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

        setFormCompletion({
            details: isBasicValid,
            document: areDocumentsValid, // Use the result of validateDocuments
            documents: areDocumentsValid  // Use the result of validateDocuments
        });

        return isBasicValid && areDocumentsValid;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            toast.error("Please fix validation errors", {
                description: "Check all required fields and try again"
            });
            return;
        }

        const submissionToast = toast.loading("Submitting Procurement...");

        post('/bac-secretariat/publish-procurement-initiation', {
            onSuccess: () => {
                toast.success("Procurement successfully submitted", {
                    id: submissionToast,
                });
                reset();
                setCurrentStep(1);
            },
            onError: (formErrors: Record<string, string>) => {
                toast.error("Failed to submit", {
                    id: submissionToast,
                    description: Object.values(formErrors)[0]
                });

                const hasFileErrors = Object.keys(formErrors).some(key => key.startsWith('files') || key.startsWith('metadata'));
                const hasDetailErrors = Object.keys(formErrors).some(key => key.startsWith('procurement_id') || key.startsWith('procurement_title'));

                if (hasFileErrors) {
                    setCurrentStep(2);
                    setFormCompletion(prev => ({
                        ...prev,
                        document: false,
                        documents: false,
                    }));
                } else if (hasDetailErrors) {
                    setCurrentStep(1);
                    setFormCompletion(prev => ({
                        ...prev,
                        details: false,
                    }));
                }
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

    useEffect(() => {
        setFormCompletion({
            details: !!data.procurement_id && !!data.procurement_title,
            document: validateDocuments(),
            documents: validateDocuments()
        });
    }, [data.procurement_id, data.procurement_title, validateDocuments]);

    const progressValue = useMemo(() => {
        let p = 0;
        if (formCompletion.details) p += 33;
        if (formCompletion.document) p += 33;
        if (formCompletion.documents) p += 34;
        return p;
    }, [formCompletion]);

    const handleStepClick = (stepId: number) => {
        if (stepId === currentStep) return;
        if (stepId > currentStep) {
            if (stepId === 2 && !formCompletion.details) {
                toast.error("Please complete all details before proceeding");
                return;
            }
            if (stepId === 3 && !formCompletion.documents) {
                toast.error("Please complete all document information before proceeding");
                return;
            }
        }
        setCurrentStep(stepId);
    };

    const procurementDetailsProps: ProcurementDetailsStepProps = {
        data: {
            procurement_id: String(data.procurement_id || ''),
            procurement_title: String(data.procurement_title || '')
        },
        errors: errors || {},
        hasError,
        handleFieldChange: (field: string, value: string) =>
            handleFieldChange(field as keyof UseFormData, value as FormDataConvertible),
        clearErrors: () => form.clearErrors()
    };

    const procurementDocumentsProps = {
        data: {
            file: data.file,
            files: Array.isArray(data.files) ? data.files : [],
            metadata: Object.fromEntries(
                (Array.isArray(data.metadata) ? data.metadata : []).map((meta: FileMetadata, index: number) => [
                    index,
                    {
                        ...meta,
                        submission_date: meta.submission_date
                            ? parseDate(meta.submission_date)
                            : undefined
                    }
                ])
            )
        },
        fileIndices,
        addFile,
        removeFile,
        isDragging,
        hasError,
        handleFileChange,
        handleMainFileChange,
        handleMetadataChange: (index: number, field: string, value: string) =>
            handleMetadataChange(index, field as keyof FileMetadata, value),
        handleDateChange,
        handleDragEnter: (e: React.DragEvent) => handleFileDragEvent(e, 'enter'),
        handleDragLeave: (e: React.DragEvent) => handleFileDragEvent(e, 'leave'),
        handleDragOver: (e: React.DragEvent) => handleFileDragEvent(e, 'over'),
        handleFileDrop: (e: React.DragEvent, index?: number) => handleFileDragEvent(e, 'drop', index),
        dates,
        validateFile,
        setData: (key: string, value: unknown) =>
            updateFormData(key as keyof UseFormData, value as FormDataConvertible),
        errors,
    };

    const formSummaryProps = {
        data: convertToComponentFormData(prepareFormSummaryData(data, formatDateForDisplay)),
        formCompletion,
        addFile,
        setCurrentStep
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 p-3 sm:p-6">
                <FormHeader
                    formState={{
                        isDraft: false,
                        lastUpdated: format(new Date(), 'MMMM dd, yyyy')
                    }}
                />

                <div className="mt-2 sm:mt-0">
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border bg-white/80 dark:bg-black/90 p-4 sm:p-6 md:p-8 shadow-md rounded-xl transition-all duration-200">
                        <div className="space-y-4 sm:space-y-6">
                            <div>
                                <h2 className="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Progress</h2>
                                <div className="relative pt-1">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 gap-2 sm:gap-0">
                                        <div>
                                            <span className="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-primary bg-primary/10">
                                                Step {currentStep} of {formSteps.length}
                                            </span>
                                        </div>
                                        <div className="text-left sm:text-right">
                                            <span className="text-xs font-semibold inline-block text-primary">
                                                {progressValue}%
                                            </span>
                                        </div>
                                    </div>
                                    <Progress
                                        value={progressValue}
                                        className="h-2 sm:h-2.5 rounded-full transition-all duration-500 ease-in-out"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
                                {formSteps.map((step) => {
                                    const isComplete = (
                                        (step.id === 1 && formCompletion.details) ||
                                        (step.id === 2 && formCompletion.document) ||
                                        (step.id === 3 && formCompletion.documents)
                                    );

                                    const isActive = currentStep === step.id;

                                    const isDisabled = step.id > currentStep &&
                                        ((step.id === 2 && !formCompletion.details) ||
                                            (step.id === 3 && !formCompletion.documents));

                                    return (
                                        <button
                                            key={step.id}
                                            onClick={() => handleStepClick(step.id)}
                                            disabled={isDisabled}
                                            className={`flex items-center relative overflow-hidden border rounded-xl p-3 sm:p-4 md:p-5 transition-all duration-300 ${isActive
                                                ? 'bg-primary/10 border-primary shadow-sm ring-1 ring-primary/30'
                                                : isComplete
                                                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-900/50 hover:border-green-300 dark:hover:border-green-800'
                                                    : 'bg-white dark:bg-gray-800/50 border-gray-100 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                                } ${isDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:shadow-md'}`}
                                        >
                                            {isComplete && (
                                                <div className="absolute top-2 right-2">
                                                    <CheckCircle2 className="h-4 sm:h-5 w-4 sm:w-5 text-green-500" />
                                                </div>
                                            )}

                                            <div className={`flex-shrink-0 rounded-lg p-2 sm:p-3 mr-3 sm:mr-4 ${isActive
                                                ? 'bg-primary text-white'
                                                : isComplete
                                                    ? 'bg-green-100 dark:bg-green-800/30 text-green-700 dark:text-green-400'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                                                }`}>
                                                {step.icon}
                                            </div>

                                            <div className="text-left flex-1">
                                                <h3 className={`font-medium mb-0 sm:mb-1 text-sm sm:text-base ${isActive
                                                    ? 'text-primary font-semibold'
                                                    : isComplete
                                                        ? 'text-green-700 dark:text-green-400'
                                                        : 'text-gray-700 dark:text-gray-300'
                                                    }`}>
                                                    {step.title}
                                                </h3>
                                                <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 hidden sm:block">
                                                    {step.description}
                                                </p>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </Card>

                    <div className="mt-4 sm:mt-6 space-y-4 sm:space-y-6">
                        {currentStep === 1 && (
                            <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                                <ProcurementDetails {...procurementDetailsProps} />
                            </Card>
                        )}

                        {currentStep === 2 && (
                            <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                                <ProcurementDocuments {...procurementDocumentsProps} />
                            </Card>
                        )}

                        {currentStep === 3 && (
                            <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                                <FormSummary {...formSummaryProps} />
                            </Card>
                        )}

                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-3 sm:p-4 md:p-5 shadow-sm">
                            <div className="grid grid-cols-1 sm:grid-cols-3 items-center gap-3 sm:gap-4">
                                <div className="flex items-center gap-2 sm:gap-4 order-2 sm:order-1">
                                    {currentStep > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setCurrentStep(currentStep - 1)}
                                            className="gap-2 w-full sm:w-auto text-xs sm:text-sm"
                                        >
                                            <ChevronLeft className="h-3 sm:h-4 w-3 sm:w-4" />
                                            Back to {formSteps[currentStep - 2].title}
                                        </Button>
                                    )}
                                </div>

                                <div className="text-sm text-muted-foreground text-center order-1 sm:order-2">
                                    Step {currentStep} of {formSteps.length}
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

                                    {currentStep < 3 ? (
                                        <Button
                                            type="button"
                                            onClick={() => {
                                                if (currentStep === 1 && !formCompletion.details) {
                                                    toast.error("Please complete all details before continuing");
                                                    return;
                                                }
                                                if (currentStep === 2 && !formCompletion.documents) {
                                                    toast.error("Please complete all document information before continuing");
                                                    return;
                                                }
                                                setCurrentStep(currentStep + 1);
                                            }}
                                            className="gap-2 w-full sm:w-auto text-xs sm:text-sm"
                                        >
                                            Continue to {formSteps[currentStep].title}
                                            <ChevronRight className="h-3 sm:h-4 w-3 sm:w-4" />
                                        </Button>
                                    ) : (
                                        <form onSubmit={handleSubmit} className="w-full sm:w-auto">
                                            <Button
                                                type="submit"
                                                disabled={processing || !formCompletion.details || !formCompletion.document || !formCompletion.documents}
                                                className="bg-primary hover:bg-primary/90 text-white gap-2 w-full text-xs sm:text-sm py-2 sm:py-2.5 h-auto"
                                            >
                                                {processing ? (
                                                    <>
                                                        <div className="h-3 sm:h-4 w-3 sm:w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                                                        <span>Submitting Procurement...</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <Upload className="h-3 sm:h-4 w-3 sm:w-4" />
                                                        Submit Procurement
                                                    </>
                                                )}
                                            </Button>
                                        </form>
                                    )}
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

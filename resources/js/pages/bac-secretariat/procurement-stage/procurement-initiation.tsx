import React, { useState, useEffect, useCallback } from 'react';
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
import { Documents } from '@/components/procurement-initiation/documents';
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
    { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
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
        icon: <FileText className="h-5 w-5" />,
    },
    {
        id: 2,
        title: "Documents",
        description: "Upload required files",
        icon: <Upload className="h-5 w-5" />,
    },
    {
        id: 3,
        title: "Review",
        description: "Verify and submit",
        icon: <ClipboardList className="h-5 w-5" />,
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

const copyMetadataFromPrevious = (
    metadata: FileMetadata[],
    lastIndex: number
): FileMetadata => {
    if (lastIndex >= 0 && metadata[lastIndex]) {
        return {
            document_type: '',
            submission_date: metadata[lastIndex].submission_date || '',
            municipal_offices: metadata[lastIndex].municipal_offices || '',
            signatory_details: metadata[lastIndex].signatory_details || ''
        };
    }

    return {
        document_type: '',
        submission_date: '',
        municipal_offices: '',
        signatory_details: ''
    };
};

export default function ProcurementInitiationForm() {
    const [fileCount, setFileCount] = useState(1);
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
            submission_date: '',
            municipal_offices: '',
            signatory_details: ''
        }]
    }) as unknown as ExtendedForm & InertiaFormInstance<UseFormData>;

    const { data, post, processing, errors, reset } = form;

    const updateFormData = (field: keyof UseFormData, value: FormDataValue): void => {
        form.setData(field, value as FormDataConvertible);
    };

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

    const handleDateChange = (index: number, date: Date | undefined) => {
        form.clearErrors();
        setDates(prev => ({ ...prev, [index]: date }));

        if (date) {
            try {
                const formattedDate = format(date, 'yyyy-MM-dd');
                handleMetadataChange(index, 'submission_date', formattedDate);
            } catch (e) {
                console.error("Error formatting date:", e);
                handleMetadataChange(index, 'submission_date', date.toISOString().split('T')[0]);
            }
        } else {
            handleMetadataChange(index, 'submission_date', '');
        }
    };

    const handleMetadataChange = (index: number, field: keyof FileMetadata, value: string): void => {
        form.clearErrors();
        const updatedMetadata = Array.isArray(data.metadata)
            ? [...data.metadata]
            : [];

        if (!updatedMetadata[index]) {
            updatedMetadata[index] = {
                document_type: '',
                submission_date: '',
                municipal_offices: '',
                signatory_details: ''
            };
        }

        updatedMetadata[index] = {
            ...updatedMetadata[index],
            [field]: value
        };

        updateFormData('metadata', updatedMetadata);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        form.clearErrors();
        const file = e.target.files?.[0] || null;
        if (file) {
            if (validateFile(file)) {
                const updatedFiles = Array.isArray(data.files) ? [...data.files] : [];
                updatedFiles[index] = file;
                updateFormData('files', updatedFiles);

                // Initialize metadata if it doesn't exist
                const updatedMetadata = Array.isArray(data.metadata) ? [...data.metadata] : [];
                if (!updatedMetadata[index]) {
                    updatedMetadata[index] = {
                        document_type: '',
                        submission_date: format(new Date(), 'yyyy-MM-dd'),
                        municipal_offices: '',
                        signatory_details: ''
                    };
                    updateFormData('metadata', updatedMetadata);
                }
            } else {
                e.target.value = ''; // Clear the file input
            }
        }
    };

    const handleMainFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        if (file && validateFile(file)) {
            updateFormData('file', file);
        }
    };

    const addFile = () => {
        setFileCount(prev => prev + 1);

        const newFiles = Array.isArray(data.files) ? [...data.files, null] : [];
        const newMetadata = Array.isArray(data.metadata) ? [...data.metadata] : [];
        const lastIndex = newMetadata.length - 1;

        const newDocMetadata = copyMetadataFromPrevious(newMetadata, lastIndex);
        newMetadata.push(newDocMetadata);

        updateFormData('files', newFiles);
        updateFormData('metadata', newMetadata);

        if (lastIndex >= 0 && dates[lastIndex]) {
            setDates(prev => ({
                ...prev,
                [lastIndex + 1]: dates[lastIndex]
            }));
        }

        setFormCompletion(prev => ({ ...prev, documents: false }));
    };

    const removeFile = (index: number) => {
        const newFiles = Array.isArray(data.files) ? [...data.files] : [];
        newFiles.splice(index, 1);

        const newMetadata = Array.isArray(data.metadata) ? [...data.metadata] : [];
        newMetadata.splice(index, 1);

        updateFormData('files', newFiles);
        updateFormData('metadata', newMetadata);
        setFileCount(fileCount - 1);
    };

    const validateFile = (file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            toast.error("File too large", { description: "Maximum file size is 10MB" });
            return false;
        }

        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        if (!isPdf) {
            toast.error("Invalid file type", {
                description: "Please upload PDF files only"
            });
            return false;
        }

        return true;
    };

    const handleFileDragEvent = (e: React.DragEvent, action: 'enter' | 'leave' | 'over' | 'drop', index?: number) => {
        e.preventDefault();
        e.stopPropagation();

        switch (action) {
            case 'enter':
                setIsDragging(true);
                break;
            case 'leave':
                setIsDragging(false);
                break;
            case 'drop': {
                setIsDragging(false);

                if (!e.dataTransfer.files.length) return;

                const file = e.dataTransfer.files[0];
                if (!validateFile(file)) return;

                if (index !== undefined) {
                    const updatedFiles = Array.isArray(data.files) ? [...data.files] : [];
                    updatedFiles[index] = file;
                    updateFormData('files', updatedFiles);
                } else {
                    updateFormData('file', file);
                }
                break;
            }
        }
    };

    const validateForm = () => {
        let isValid = true;

        const validateBasicFields = () => {
            if (!data.procurement_id) {
                isValid = false;
            }

            if (!data.procurement_title) {
                isValid = false;
            }
        };

        const validateDocumentMetadata = () => {
            const files = Array.isArray(data.files) ? data.files : [];
            const metadata = Array.isArray(data.metadata) ? data.metadata : [];

            files.forEach((file: File | null, index: number) => {
                if (file) {
                    const meta = metadata[index];
                    const metadataFields: Array<keyof FileMetadata> = [
                        'document_type',
                        'submission_date',
                        'municipal_offices',
                        'signatory_details'
                    ];

                    metadataFields.forEach(field => {
                        if (!meta?.[field]) {
                            isValid = false;
                        }
                    });
                }
            });
        };

        validateBasicFields();
        validateDocumentMetadata();

        setFormCompletion({
            details: !!data.procurement_id && !!data.procurement_title,
            document: validateDocuments(),
            documents: validateDocuments()
        });

        return isValid;
    };

    const validateDocuments = useCallback((): boolean => {
        const files = Array.isArray(data.files) ? data.files : [];
        const metadata = Array.isArray(data.metadata) ? data.metadata : [];

        return files.some((file: File | null) => !!file) &&
            metadata.every((meta: FileMetadata, index: number) =>
                !files[index] || (
                    !!meta.document_type &&
                    !!meta.submission_date &&
                    !!meta.municipal_offices &&
                    !!meta.signatory_details
                )
            );
    }, [data.files, data.metadata]);

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

                if (formErrors.files || formErrors.metadata) {
                    setCurrentStep(2);
                } else if (formErrors.procurement_id || formErrors.procurement_title) {
                    setCurrentStep(1);
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

    const fileIndices = Array.isArray(data.files)
        ? Array.from({ length: data.files.length }, (_, i) => i)
        : [];

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

    const calculateProgress = () => {
        let progress = 0;
        if (formCompletion.details) progress += 33;
        if (formCompletion.document) progress += 33;
        if (formCompletion.documents) progress += 34;
        return progress;
    };

    const progressValue = calculateProgress();

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

    const documentsProps = {
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
        setData: (key: string, value: UseFormData[keyof UseFormData]) =>
            updateFormData(key as keyof UseFormData, value),
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
            <Head title="Create Procurement" />

            <div className="flex h-full flex-1 flex-col gap-5 p-3 sm:p-5">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                    <FormHeader />

                    <div className="mt-6 sm:mt-8">
                        <Progress value={progressValue} className="h-2" />

                        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                            {formSteps.map((step) => (
                                <button
                                    key={step.id}
                                    onClick={() => setCurrentStep(step.id)}
                                    className={`flex items-start p-3 sm:p-4 rounded-lg transition-all ${
                                        currentStep === step.id
                                            ? 'bg-primary/10 border border-primary'
                                            : 'hover:bg-muted/50'
                                    }`}
                                >
                                    <div className={`rounded-full p-1.5 sm:p-2 mr-2 sm:mr-3 ${
                                        currentStep === step.id
                                            ? 'bg-primary text-white'
                                            : 'bg-muted'
                                    }`}>
                                        {step.icon}
                                    </div>
                                    <div className="text-left">
                                        <h3 className="font-medium flex items-center gap-2 text-sm sm:text-base">
                                            {step.title}
                                            {(
                                                (step.id === 1 && formCompletion.details) ||
                                                (step.id === 2 && formCompletion.document) ||
                                                (step.id === 3 && formCompletion.documents)
                                            ) && (
                                                <CheckCircle2 className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-green-500" />
                                            )}
                                        </h3>
                                        <p className="text-xs sm:text-sm text-muted-foreground">{step.description}</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </Card>

                <div className="flex-1 space-y-5">
                    {currentStep === 1 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                            <ProcurementDetails {...procurementDetailsProps} />
                        </Card>
                    )}

                    {currentStep === 2 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                            <Documents {...documentsProps} />
                        </Card>
                    )}

                    {currentStep === 3 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-6 shadow-sm">
                            <FormSummary {...formSummaryProps} />
                        </Card>
                    )}

                    <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-4 sm:p-5 shadow-sm">
                        <div className="grid grid-cols-1 sm:grid-cols-3 items-center gap-4">
                            <div className="flex items-center gap-4 order-2 sm:order-1">
                                {currentStep > 1 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCurrentStep(currentStep - 1)}
                                        className="gap-2 w-full sm:w-auto"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Back to {formSteps[currentStep - 2].title}
                                    </Button>
                                )}
                            </div>

                            <div className="text-sm text-muted-foreground text-center order-1 sm:order-2">
                                Step {currentStep} of {formSteps.length}
                            </div>

                            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 order-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleSaveDraft}
                                    className="gap-2"
                                >
                                    <Save className="h-4 w-4" />
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
                                        className="gap-2 w-full sm:w-auto"
                                    >
                                        Continue to {formSteps[currentStep].title}
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                ) : (
                                    <form onSubmit={handleSubmit} className="w-full sm:w-auto">
                                        <Button
                                            type="submit"
                                            disabled={processing || !formCompletion.details || !formCompletion.document || !formCompletion.documents}
                                            className="bg-primary hover:bg-primary/90 text-white gap-2 w-full"
                                        >
                                            {processing ? (
                                                <>
                                                    <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                                                    <span>Submitting Procurement...</span>
                                                </>
                                            ) : (
                                                <>
                                                    <Upload className="h-4 w-4" />
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
        </AppLayout>
    );
}
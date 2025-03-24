import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { FormProvider, useForm as useReactHookForm } from 'react-hook-form';
import { format } from 'date-fns';
import * as z from 'zod';
import AppLayout from '@/layouts/app-layout';
import { FormHeader } from '@/components/pr-initiation/form-header';
import { StepIndicator } from '@/components/pr-initiation/step-indicator';
import { ProcurementDetailsStep } from '@/components/pr-initiation/steps/procurement-details-step';
import { PRDocumentStep } from '@/components/pr-initiation/steps/pr-document-step';
import { SupportingDocumentsStep } from '@/components/pr-initiation/steps/supporting-documents-step';
import { FormSummary, FormSummaryProps } from '@/components/pr-initiation/form-summary';
import { FormNavigation } from '@/components/pr-initiation/form-navigation';
import { BreadcrumbItem } from '@/types';
import { toast } from 'sonner';

// Simplified interfaces
interface SupportingFileMetadata {
    document_type: string;
    submission_date?: string;
    municipal_offices?: string;
    signatory_details?: string;
    [key: string]: string | undefined;
}

interface PRInitiationFormData {
    procurement_id: string;
    procurement_title: string;
    pr_file: File | null;
    pr_metadata: {
        document_type: string;
        submission_date: string;
        municipal_offices: string;
        signatory_details: string;
    };
    supporting_files: (File | null)[];
    supporting_metadata: SupportingFileMetadata[];
    [key: string]: string | number | boolean | null | undefined | File | File[] | (File | null)[] | {
        document_type: string;
        submission_date: string;
        municipal_offices: string;
        signatory_details: string;
    } | SupportingFileMetadata[];
}

type ComponentFormData = FormSummaryProps['data'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Purchase Request Initiation', href: '#' },
];
// Define a form validation schema with proper typing
const formValidationSchema = {
    step1: {
        procurement_id: (value: string | undefined): true | string =>
            !!value || 'Procurement ID is required',
        procurement_title: (value: string | undefined): true | string =>
            !!value || 'Procurement Title is required',
    },
    step2: {
        pr_file: (value: File | null | undefined): true | string =>
            !!value || 'PR File is required',
        'pr_metadata.submission_date': (value: string | undefined): true | string =>
            !!value || 'Submission date is required',
        'pr_metadata.municipal_offices': (value: string | undefined): true | string =>
            !!value || 'Municipal office is required',
        'pr_metadata.signatory_details': (value: string | undefined): true | string =>
            !!value || 'Signatory details are required',
    },
    step3: {
        supporting_files: (files: (File | null)[]): true | string =>
            files.length === 0 || files.every(file => file !== null) || 'All supporting documents must have files'
    }
};

// Simple date parser that handles common formats
function parseDate(dateStr: string): Date | undefined {
    if (!dateStr) return undefined;

    try {
        // Try direct parsing first for ISO format
        const date = new Date(dateStr);
        if (!isNaN(date.getTime())) return date;

        // If that fails, try parsing yyyy-MM-dd format
        const [year, month, day] = dateStr.split('-').map(Number);
        if (year && month && day) {
            return new Date(year, month - 1, day);
        }

        return undefined;
    } catch {
        return undefined;
    }
}

export default function PRInitiationForm() {


    // Use Inertia form for API interactions and server-side validation
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<PRInitiationFormData>({
        procurement_id: '',
        procurement_title: '',
        pr_file: null,
        pr_metadata: {
            document_type: 'Purchase Request',
            submission_date: '',
            municipal_offices: '',
            signatory_details: ''
        },
        supporting_files: [],
        supporting_metadata: []
    });

    // React Hook Form for client-side validation
    const formSchema = z.object({
        procurement_id: z.string().min(1, "Procurement ID is required"),
        procurement_title: z.string().min(1, "Procurement Title is required"),
    });

    const form = useReactHookForm<z.infer<typeof formSchema>>({
        resolver: async (values) => {
            try {
                const validatedData = await formSchema.parseAsync(values);
                return { values: validatedData, errors: {} };
            } catch (error) {
                if (error instanceof z.ZodError) {
                    const errors = error.errors.reduce((acc: Record<string, { type: string; message: string }>, curr) => {
                        const path = curr.path.join('.');
                        acc[path] = { type: 'validation', message: curr.message };
                        return acc;
                    }, {});
                    return { values: {}, errors };
                }
                return { values: {}, errors: { '': { type: 'validation', message: 'Validation failed' } } };
            }
        },
        defaultValues: {
            procurement_id: data.procurement_id,
            procurement_title: data.procurement_title,
        },
    });

    // Core state
    const [currentStep, setCurrentStep] = useState(1);
    const [supportingFileCount, setSupportingFileCount] = useState(0);
    const [useCustomMetadata, setUseCustomMetadata] = useState<{ [key: number]: boolean }>({});
    const [formCompletion, setFormCompletion] = useState({
        details: false,
        prDocument: false,
        supporting: false
    });
    const [isDragging, setIsDragging] = useState(false);
    const [showValidationSummary, setShowValidationSummary] = useState(false);

    // Date handling
    const [submissionDate, setSubmissionDate] = useState<Date | undefined>(() =>
        data.pr_metadata.submission_date ? parseDate(data.pr_metadata.submission_date) : undefined
    );
    const [supportingDates, setSupportingDates] = useState<{ [key: number]: Date | undefined }>({});

    // Simplified field change handler
    const handleFieldChange = (field: string, value: string | number | boolean | null | File | undefined) => {
        setData(field as keyof PRInitiationFormData, value as never);
        if (field === 'procurement_id' || field === 'procurement_title') {
            form.setValue(field, value as string, { shouldValidate: true });
        }
    };

    // Simplified date change handling
    const handleDateChange = (date: Date | undefined) => {
        setSubmissionDate(date);
        if (!date) return;

        const formattedDate = format(date, 'yyyy-MM-dd');
        setData('pr_metadata', { ...data.pr_metadata, submission_date: formattedDate });

        // Update supporting docs that use the same date
        const updatedMetadata = data.supporting_metadata.map((metadata, index) =>
            useCustomMetadata[index] ? metadata : { ...metadata, submission_date: formattedDate }
        );
        setData('supporting_metadata', updatedMetadata);
    };

    const handleSupportingDateChange = (index: number, date: Date | undefined) => {
        if (!date) return;

        // Update date state
        setSupportingDates(prev => ({ ...prev, [index]: date }));

        // Update metadata
        const formattedDate = format(date, 'yyyy-MM-dd');
        handleSupportingMetadataChange(index, 'submission_date', formattedDate);
    };

    // Sync supporting dates with metadata
    useEffect(() => {
        const dates: { [key: number]: Date | undefined } = {};
        data.supporting_metadata.forEach((metadata, index) => {
            if (metadata.submission_date) {
                dates[index] = parseDate(metadata.submission_date);
            }
        });
        setSupportingDates(dates);
    }, [data.supporting_metadata]);

    // Simplified file handlers
    const handleSupportingFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        const file = e.target.files?.[0] || null;
        if (file) {
            const updatedFiles = [...data.supporting_files];
            updatedFiles[index] = file;
            setData('supporting_files', updatedFiles);
        }
    };

    // Simplified metadata change handler
    const handleSupportingMetadataChange = (index: number, field: string, value: string) => {
        const updatedMetadata = [...data.supporting_metadata];
        if (!updatedMetadata[index]) {
            updatedMetadata[index] = {
                document_type: '',
                submission_date: data.pr_metadata.submission_date,
                municipal_offices: data.pr_metadata.municipal_offices,
                signatory_details: data.pr_metadata.signatory_details
            };
        }
        updatedMetadata[index][field as keyof SupportingFileMetadata] = value;
        setData('supporting_metadata', updatedMetadata);
    };

    // Supporting files management - simplified
    const addSupportingFile = () => {
        const newCount = supportingFileCount + 1;
        setSupportingFileCount(newCount);

        // Add empty file and metadata
        setData({
            ...data,
            supporting_files: [...data.supporting_files, null],
            supporting_metadata: [
                ...data.supporting_metadata,
                {
                    document_type: '',
                    submission_date: data.pr_metadata.submission_date,
                    municipal_offices: data.pr_metadata.municipal_offices,
                    signatory_details: data.pr_metadata.signatory_details
                }
            ]
        });

        // Mark supporting step as incomplete
        setFormCompletion(prev => ({ ...prev, supporting: false }));

        // Add toast notification for supporting file
        toast.info("New supporting document added", {
            description: "Please upload the file and complete the details"
        });
    };

    // Simplified file removal
    const removeSupportingFile = (index: number) => {
        const fileName = data.supporting_files[index]?.name || "Supporting document";

        setData({
            ...data,
            supporting_files: data.supporting_files.filter((_, i) => i !== index),
            supporting_metadata: data.supporting_metadata.filter((_, i) => i !== index)
        });

        setSupportingFileCount(prev => prev - 1);

        // Add toast notification for file removal
        toast.info(`${fileName} removed`, {
            description: "The supporting document has been removed"
        });
    };

    // Simplified form validation
    const validateStep = (step: number): boolean => {
        clearErrors();

        // Select validation schema based on step
        const schema = step === 1 ? formValidationSchema.step1 :
            step === 2 ? formValidationSchema.step2 :
                formValidationSchema.step3;

        // Track validation status
        let isValid = true;

        // Loop through validation rules for the current step
        Object.entries(schema).forEach(([field, validator]) => {
            // Get field value (handling nested fields)
            let value;
            if (field.includes('.')) {
                const [parent, child] = field.split('.');
                const parentValue = data[parent as keyof PRInitiationFormData];
                // Type guard to ensure we're accessing an object with string keys
                if (parentValue && typeof parentValue === 'object' && parentValue !== null && !Array.isArray(parentValue)) {
                    value = (parentValue as Record<string, unknown>)[child];
                }
            } else {
                value = data[field as keyof PRInitiationFormData];
            }

            // Validate and set error if needed
            const errorMessage = validator(value);
            if (errorMessage !== true) {
                setError(field, errorMessage);
                isValid = false;
            }
        });

        // Update form completion state
        if (step === 1) {
            setFormCompletion(prev => ({ ...prev, details: isValid }));
        } else if (step === 2) {
            setFormCompletion(prev => ({ ...prev, prDocument: isValid }));
        } else if (step === 3) {
            setFormCompletion(prev => ({ ...prev, supporting: isValid }));
        }

        return isValid;
    };

    // Check if the form as a whole is valid
    const isFormValid = () => {
        return validateStep(1) &&
            validateStep(2) &&
            (data.supporting_files.length === 0 || validateStep(3));
    };

    // Enhanced form submission
    // Enhanced form submission
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Early return if validation fails
        if (!isFormValid()) {
            setShowValidationSummary(true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            toast.error("Form validation failed", {
                description: "Please fix all errors before submitting"
            });
            return;
        }

        // Show submission toast
        toast.loading("Submitting Purchase Request...", {
            id: "pr-submission"
        });

        // Submit the form
        post('/bac-secretariat/publish-pr-initiation', {
            onSuccess: handleSubmissionSuccess,
            onError: handleSubmissionError,
            preserveState: true,
            preserveScroll: true,
            forceFormData: true
        });
    };

    // Simplified success handler without flash dependency
    const handleSubmissionSuccess = () => {
        // Dismiss the loading toast
        toast.dismiss("pr-submission");

        // Display success message
        toast.success("Purchase Request successfully submitted", {
            description: "Your procurement request has been initiated"
        });
    };

    // Handle submission errors
    const handleSubmissionError = (errors: Record<string, string>) => {
        console.error('Submission error:', errors);

        // Dismiss the loading toast and show error
        toast.dismiss("pr-submission");
        toast.error("Failed to submit Purchase Request", {
            description: "Please check your connection and try again"
        });
    };

    // Simple toggle function
    const toggleCustomMetadata = (index: number) => {
        setUseCustomMetadata(prev => ({
            ...prev,
            [index]: !prev[index]
        }));
    };

    // Simplified array creation
    const supportingFileIndices = Array.from({ length: data.supporting_files.length }, (_, i) => i);

    // Simple error checker
    const hasError = (field: string) => {
        return Object.keys(errors).some(error => error === field || error.startsWith(`${field}.`));
    };

    // Form completion tracking
    useEffect(() => {
        setFormCompletion({
            details: !!data.procurement_id && !!data.procurement_title,
            prDocument: !!data.pr_file && !!data.pr_metadata.submission_date &&
                !!data.pr_metadata.municipal_offices && !!data.pr_metadata.signatory_details,
            supporting: data.supporting_files.length === 0 || (
                data.supporting_files.every(file => file !== null) &&
                data.supporting_metadata.every(meta => meta.document_type !== '')
            )
        });
    }, [data]);

    // Simplified step navigation
    const handleNextStep = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();

        if (validateStep(currentStep)) {
            setCurrentStep(Math.min(3, currentStep + 1));
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Add toast notification for step progress
            if (currentStep === 1) {
                toast.success("Procurement details saved", {
                    description: "Please complete the PR document details"
                });
            } else if (currentStep === 2) {
                toast.success("PR document details saved", {
                    description: "You can now add supporting documents"
                });
            }
        } else {
            setShowValidationSummary(true);
            toast.error("Please fix the validation errors", {
                description: "Some required fields need attention"
            });
        }
    };

    const handlePrevStep = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        setCurrentStep(Math.max(1, currentStep - 1));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Simple percentage calculation
    const getFormCompletionPercentage = () => {
        let completed = 0;
        if (formCompletion.details) completed++;
        if (formCompletion.prDocument) completed++;
        if (formCompletion.supporting) completed++;

        return Math.round((completed / 3) * 100);
    };

    // Unified file drag/drop handler
    const handleFileDragEvent = (e: React.DragEvent, action: 'enter' | 'leave' | 'over' | 'drop', index?: number) => {
        e.preventDefault();
        e.stopPropagation();

        if (action === 'enter') {
            setIsDragging(true);
        }
        else if (action === 'leave') {
            setIsDragging(false);
        }
        else if (action === 'drop') {
            setIsDragging(false);

            if (!e.dataTransfer.files.length) return;

            const file = e.dataTransfer.files[0];

            if (index !== undefined) {
                // For supporting files
                const updatedFiles = [...data.supporting_files];
                updatedFiles[index] = file;
                setData('supporting_files', updatedFiles);
                toast.success(`Supporting file added: ${file.name}`, {
                    description: "File has been attached to your request"
                });
            } else {
                // For PR file
                setData('pr_file', file);
                toast.success(`PR document added: ${file.name}`, {
                    description: "Purchase Request document has been attached"
                });
            }
        }
    };

    // Simple date formatter
    const formatDateForDisplay = (dateValue: Date | string | undefined): string => {
        if (!dateValue) return 'Not set';

        try {
            if (dateValue instanceof Date) {
                return format(dateValue, 'yyyy-MM-dd');
            }

            if (typeof dateValue === 'string') {
                return dateValue;
            }

            return 'Invalid date';
        } catch {
            return 'Invalid date';
        }
    };

    // Simplified conversion function
    const convertToComponentFormData = (formData: PRInitiationFormData): ComponentFormData => {
        return {
            ...formData,
            pr_file: formData.pr_file || undefined,
            supporting_files: formData.supporting_files.map(file => file || undefined),
        } as unknown as ComponentFormData;
    };

    // The rest of your JSX rendering code remains largely the same
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Purchase Request" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header Container */}
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-6">
                    <FormHeader
                        currentStep={currentStep}
                        formCompletion={formCompletion}
                        getFormCompletionPercentage={getFormCompletionPercentage}
                        setShowValidationSummary={setShowValidationSummary}
                        showValidationSummary={showValidationSummary}
                        validationErrors={Object.entries(errors).map(([field, message]) => ({
                            field,
                            message: message as string
                        }))}
                    />
                </div>

                {/* Step Indicator */}
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-4">
                    <StepIndicator
                        currentStep={currentStep}
                        formCompletion={formCompletion}
                    />
                </div>

                {/* Form Content */}
                <FormProvider {...form}>
                    <form onSubmit={handleSubmit} className="flex-1 space-y-4">
                        <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-6">
                            {currentStep === 1 && (
                                <ProcurementDetailsStep
                                    data={{
                                        procurement_id: data.procurement_id,
                                        procurement_title: data.procurement_title
                                    }}
                                    errors={errors as Record<string, string>}
                                    hasError={hasError}
                                    handleFieldChange={handleFieldChange}
                                    clearErrors={clearErrors}
                                />
                            )}

                            {currentStep === 2 && (
                                <PRDocumentStep
                                    data={{
                                        ...data,
                                        pr_document_file: data.pr_file,
                                        pr_document_metadata: {
                                            ...data.pr_metadata,
                                            submission_date: submissionDate || new Date()
                                        }
                                    }}
                                    setData={(key: string, value: unknown) => {
                                        if (key === 'pr_document_file') {
                                            setData('pr_file', value as File | null);
                                        } else if (key === 'pr_document_metadata') {
                                            if (!data.pr_metadata.submission_date) {
                                                const now = new Date();
                                                const formattedDate = format(now, 'yyyy-MM-dd');
                                                setData('pr_metadata', {
                                                    ...data.pr_metadata,
                                                    ...(value as Record<string, string>),
                                                    submission_date: formattedDate
                                                });
                                                handleDateChange(now);
                                            } else {
                                                setData('pr_metadata', {
                                                    ...data.pr_metadata,
                                                    ...(value as Record<string, string>)
                                                });
                                            }
                                        } else {
                                            setData(key as keyof PRInitiationFormData, value as never);
                                        }
                                    }}
                                    errors={errors as Record<string, string>}
                                    isDragging={isDragging}
                                    hasError={hasError}
                                    submissionDate={submissionDate || new Date()}
                                    handleDateChange={handleDateChange}
                                    handleDragEnter={(e) => handleFileDragEvent(e, 'enter')}
                                    handleDragLeave={(e) => handleFileDragEvent(e, 'leave')}
                                    handleDragOver={(e) => handleFileDragEvent(e, 'over')}
                                    handleDrop={(e) => handleFileDragEvent(e, 'drop')}
                                />
                            )}

                            {currentStep === 3 && (
                                <>
                                    <SupportingDocumentsStep
                                        data={{
                                            ...data,
                                            supporting_metadata: Object.fromEntries(
                                                data.supporting_metadata.map((meta, index) => {
                                                    const now = new Date();
                                                    const formattedNow = format(now, 'yyyy-MM-dd');

                                                    // Set default submission date if missing
                                                    if (!meta.submission_date) {
                                                        handleSupportingMetadataChange(index, 'submission_date', formattedNow);
                                                        handleSupportingDateChange(index, now);
                                                        meta.submission_date = formattedNow;
                                                    }

                                                    const submissionDate = parseDate(meta.submission_date) || now;

                                                    return [
                                                        index,
                                                        {
                                                            ...meta,
                                                            submission_date: submissionDate
                                                        }
                                                    ];
                                                })
                                            )
                                        }}
                                        setData={(key: string, value: unknown) => setData(key as keyof PRInitiationFormData, value as never)}
                                        supportingFileIndices={supportingFileIndices}
                                        addSupportingFile={addSupportingFile}
                                        removeSupportingFile={removeSupportingFile}
                                        isDragging={isDragging}
                                        hasError={hasError}
                                        errors={errors as Record<string, string>}
                                        useCustomMetadata={useCustomMetadata}
                                        toggleCustomMetadata={toggleCustomMetadata}
                                        handleSupportingFileChange={handleSupportingFileChange}
                                        handleSupportingMetadataChange={handleSupportingMetadataChange}
                                        handleSupportingDateChange={handleSupportingDateChange}
                                        handleDragEnter={(e) => handleFileDragEvent(e, 'enter')}
                                        handleDragLeave={(e) => handleFileDragEvent(e, 'leave')}
                                        handleDragOver={(e) => handleFileDragEvent(e, 'over')}
                                        handleSupportingFileDrop={(e, index) => handleFileDragEvent(e, 'drop', index)}
                                        supportingDates={supportingDates}
                                    />

                                    <div className="mt-8 pt-8 border-t">
                                        <FormSummary
                                            data={convertToComponentFormData({
                                                ...data,
                                                pr_metadata: {
                                                    ...data.pr_metadata,
                                                    submission_date: formatDateForDisplay(data.pr_metadata.submission_date)
                                                },
                                                supporting_metadata: data.supporting_metadata.map(meta => ({
                                                    ...meta,
                                                    submission_date: formatDateForDisplay(meta.submission_date)
                                                }))
                                            })}
                                            setCurrentStep={setCurrentStep}
                                            formCompletion={formCompletion}
                                            addSupportingFile={addSupportingFile}
                                        />
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Navigation */}
                        <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-4">
                            <FormNavigation
                                currentStep={currentStep}
                                handlePrevStep={handlePrevStep}
                                handleNextStep={handleNextStep}
                                processing={processing}
                                formCompletion={formCompletion}
                            />
                        </div>
                    </form>
                </FormProvider>
            </div>
        </AppLayout>
    );
}

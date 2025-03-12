import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { FormProvider, useForm as useReactHookForm } from 'react-hook-form';
import { format } from 'date-fns';
import * as z from 'zod';
import AppLayout from '@/layouts/app-layout';
import { FormHeader } from '@/components/pr-initiation/form-header';
import { StepIndicator } from '@/components/pr-initiation/step-indicator';
import { ProcurementDetailsStep } from '@/components/pr-initiation/steps/procurement-details-step';
import { PRDocumentStep } from '@/components/pr-initiation/steps/pr-document-step';
import { SupportingDocumentsStep } from '@/components/pr-initiation/steps/supporting-documents-step';
import { FormSummary } from '@/components/pr-initiation/form-summary';
import { FormNavigation } from '@/components/pr-initiation/form-navigation';
import { BreadcrumbItem } from '@/types';

interface SupportingFileMetadata {
    document_type: string;
    submission_date?: string;
    municipal_offices?: string;
    signatory_details?: string;
}

interface FormData {
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
    [key: string]: any; // Index signature to satisfy FormDataType constraint
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'PR Initiation',
        href: '#',
    },
];

export default function PRInitiationForm() {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<FormData>({
        procurement_id: '',
        procurement_title: '',
        pr_file: null as File | null,
        pr_metadata: {
            document_type: 'PR',
            submission_date: '',
            municipal_offices: '',
            signatory_details: ''
        },
        supporting_files: [] as (File | null)[],
        supporting_metadata: [] as SupportingFileMetadata[]
    });

    // Form schema for validation
    const formSchema = z.object({
        procurement_id: z.string().min(1, "Procurement ID is required"),
        procurement_title: z.string().min(1, "Procurement Title is required"),
        // Add more validation rules as needed
    });

    function parse(dateStr: string, format: string, baseDate: Date): Date | undefined {
        try {
            // Expecting format 'yyyy-MM-dd'
            const [year, month, day] = dateStr.split('-').map(Number);

            // Check if we have valid numbers
            if (!year || !month || !day) {
                return undefined;
            }

            // Create new date (month is 0-based in JavaScript)
            const parsedDate = new Date(year, month - 1, day);

            // Validate the date is valid
            if (isNaN(parsedDate.getTime())) {
                return undefined;
            }

            return parsedDate;
        } catch (error) {
            return undefined;
        }
    }

    function zodResolver<T extends z.ZodType>(schema: T) {
        return async (values: any) => {
            try {
                const validatedData = await schema.parseAsync(values);
                return {
                    values: validatedData,
                    errors: {}
                };
            } catch (error) {
                if (error instanceof z.ZodError) {
                    const errors = error.errors.reduce((acc: any, curr) => {
                        const path = curr.path.join('.');
                        acc[path] = {
                            type: 'validation',
                            message: curr.message
                        };
                        return acc;
                    }, {});

                    return {
                        values: {},
                        errors: errors
                    };
                }
                return {
                    values: {},
                    errors: {
                        '': {
                            type: 'validation',
                            message: 'Validation failed'
                        }
                    }
                };
            }
        };
    }

    const form = useReactHookForm<z.infer<typeof formSchema>>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            procurement_id: data.procurement_id,
            procurement_title: data.procurement_title,
        },
    });

    // State management
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

    const [submissionDate, setSubmissionDate] = useState<Date | undefined>(() => {
        try {
            if (data.pr_metadata.submission_date && typeof data.pr_metadata.submission_date === 'string') {
                return parse(data.pr_metadata.submission_date, 'yyyy-MM-dd', new Date());
            }
            return undefined;
        } catch (error) {
            console.error('Error parsing submission date:', error);
            return undefined;
        }
    });

    const [supportingDates, setSupportingDates] = useState<{ [key: number]: Date | undefined }>({});

    // Handle field changes
    const handleFieldChange = (field: string, value: any) => {
        setData(field as any, value);
        form.setValue(field as any, value);
    };

    // Handle date changes
    const handleDateChange = (date: Date | undefined) => {
        setSubmissionDate(date);
        if (date) {
            const formattedDate = format(date, 'yyyy-MM-dd');
            setData('pr_metadata', { ...data.pr_metadata, submission_date: formattedDate });

            const updatedMetadata = [...data.supporting_metadata];
            updatedMetadata.forEach((metadata, index) => {
                if (!useCustomMetadata[index]) {
                    metadata.submission_date = formattedDate;
                }
            });
            setData('supporting_metadata', updatedMetadata);
        }
    };

    const handleSupportingDateChange = (index: number, date: Date | undefined) => {
        const newSupportingDates = { ...supportingDates };
        newSupportingDates[index] = date;
        setSupportingDates(newSupportingDates);

        if (date) {
            const formattedDate = format(date, 'yyyy-MM-dd');
            handleSupportingMetadataChange(index, 'submission_date', formattedDate);
        }
    };

    // Initialize supporting dates from metadata
    useEffect(() => {
        const dates: { [key: number]: Date | undefined } = {};
        data.supporting_metadata.forEach((metadata, index) => {
            if (metadata.submission_date && typeof metadata.submission_date === 'string') {
                dates[index] = parse(metadata.submission_date, 'yyyy-MM-dd', new Date());
            }
        });
        setSupportingDates(dates);
    }, []);

    const handleSupportingFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            const updatedFiles = [...data.supporting_files];
            updatedFiles[index] = files[0];
            setData('supporting_files', updatedFiles);
        }
    };

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

    // Supporting files management
    const addSupportingFile = () => {
        const newIndex = supportingFileCount;
        setSupportingFileCount(newIndex + 1);

        const updatedFiles = [...data.supporting_files];
        updatedFiles[newIndex] = null;

        const updatedMetadata = [...data.supporting_metadata];
        updatedMetadata[newIndex] = {
            document_type: '',
            submission_date: data.pr_metadata.submission_date,
            municipal_offices: data.pr_metadata.municipal_offices,
            signatory_details: data.pr_metadata.signatory_details
        };

        setData({
            ...data,
            supporting_files: updatedFiles,
            supporting_metadata: updatedMetadata
        });

        // Mark the supporting files step as potentially invalid since we just added an empty slot
        setFormCompletion(prev => ({
            ...prev,
            supporting: false
        }));
    };

    const removeSupportingFile = (index: number) => {
        const updatedFiles = [...data.supporting_files];
        const updatedMetadata = [...data.supporting_metadata];

        updatedFiles.splice(index, 1);
        updatedMetadata.splice(index, 1);

        setData({
            ...data,
            supporting_files: updatedFiles,
            supporting_metadata: updatedMetadata
        });

        setSupportingFileCount(supportingFileCount - 1);
    };

    // Handle form submission
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.handleSubmit(() => {
            // Validate all steps, not just 1 and 2
            if (!validateStep(1) || !validateStep(2) || (data.supporting_files.length > 0 && !validateStep(3))) {
                setShowValidationSummary(true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            const formData = new FormData();
            formData.append('procurement_id', data.procurement_id);
            formData.append('procurement_title', data.procurement_title);

            if (data.pr_file) {
                formData.append('pr_file', data.pr_file);
                formData.append('pr_metadata[document_type]', data.pr_metadata.document_type);
                formData.append('pr_metadata[submission_date]', data.pr_metadata.submission_date || '');
                formData.append('pr_metadata[municipal_offices]', data.pr_metadata.municipal_offices || '');
                formData.append('pr_metadata[signatory_details]', data.pr_metadata.signatory_details || '');
            }

            data.supporting_files.forEach((file, index) => {
                if (file) {
                    formData.append(`supporting_files[${index}]`, file);

                    const metadata = data.supporting_metadata[index] || {
                        document_type: '',
                        submission_date: '',
                        municipal_offices: '',
                        signatory_details: ''
                    };

                    formData.append(`supporting_metadata[${index}][document_type]`, metadata.document_type || '');
                    formData.append(`supporting_metadata[${index}][submission_date]`, metadata.submission_date || data.pr_metadata.submission_date || '');
                    formData.append(`supporting_metadata[${index}][municipal_offices]`, metadata.municipal_offices || data.pr_metadata.municipal_offices || '');
                    formData.append(`supporting_metadata[${index}][signatory_details]`, metadata.signatory_details || data.pr_metadata.signatory_details || '');
                }
            });

            router.post('/bac-secretariat/publish-pr-initiation', formData, {
                forceFormData: true,
            });
        })(e);
    };

    // Form validation
    const toggleCustomMetadata = (index: number) => {
        setUseCustomMetadata({
            ...useCustomMetadata,
            [index]: !useCustomMetadata[index]
        });
    };

    const supportingFileIndices = Array.from({ length: data.supporting_files.length }, (_, i) => i);

    const hasError = (field: string) => {
        return Object.keys(errors).some(error => error === field || error.startsWith(`${field}.`));
    };

    const validateStep = (step: number): boolean => {
        clearErrors();
        let isValid = true;

        if (step === 1) {
            if (!data.procurement_id) {
                setError('procurement_id', 'Procurement ID is required');
                isValid = false;
            }
            if (!data.procurement_title) {
                setError('procurement_title', 'Procurement Title is required');
                isValid = false;
            }
            setFormCompletion(prev => ({ ...prev, details: isValid }));
        }
        else if (step === 2) {
            if (!data.pr_file) {
                setError('pr_file', 'PR File is required');
                isValid = false;
            }
            if (!data.pr_metadata.submission_date) {
                setError('pr_metadata.submission_date', 'Submission date is required');
                isValid = false;
            }
            if (!data.pr_metadata.municipal_offices) {
                setError('pr_metadata.municipal_offices', 'Municipal office is required');
                isValid = false;
            }
            if (!data.pr_metadata.signatory_details) {
                setError('pr_metadata.signatory_details', 'Signatory details are required');
                isValid = false;
            }
            setFormCompletion(prev => ({ ...prev, prDocument: isValid }));
        }
        else if (step === 3) {
            // Only validate if there are supporting files
            if (data.supporting_files.length > 0) {
                data.supporting_files.forEach((file, index) => {
                    if (!file) {
                        setError(`supporting_files.${index}`, 'A file is required for each supporting document');
                        isValid = false;
                    }
                });
                setFormCompletion(prev => ({ ...prev, supporting: isValid && data.supporting_files.length > 0 }));
            } else {
                // If no supporting files, this step is valid
                setFormCompletion(prev => ({ ...prev, supporting: true }));
            }
        }

        return isValid;
    };

    // Track form completion status
    useEffect(() => {
        const allSupportingFilesValid = data.supporting_files.length > 0 &&
            data.supporting_files.every(file => file !== null) &&
            data.supporting_metadata.every(meta => meta.document_type !== '');

        setFormCompletion(prev => ({
            ...prev,
            details: !!data.procurement_id && !!data.procurement_title,
            prDocument: !!data.pr_file && !!data.pr_metadata.submission_date &&
                !!data.pr_metadata.municipal_offices && !!data.pr_metadata.signatory_details,
            supporting: data.supporting_files.length === 0 || allSupportingFilesValid
        }));
    }, [data]);

    // Navigation between steps
    const handleNextStep = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (validateStep(currentStep)) {
            setCurrentStep(Math.min(3, currentStep + 1));
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            setShowValidationSummary(true);
        }
    };

    const handlePrevStep = (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();

        setCurrentStep(Math.max(1, currentStep - 1));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const getFormCompletionPercentage = () => {
        let percentage = 0;
        if (formCompletion.details) percentage += 33.3;
        if (formCompletion.prDocument) percentage += 33.3;
        if (formCompletion.supporting) percentage += 33.3;
        return Math.round(percentage);
    };

    // File drag and drop handlers
    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            setData('pr_file', e.dataTransfer.files[0]);
        }
    };

    const handleSupportingFileDrop = (e: React.DragEvent, index: number) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const updatedFiles = [...data.supporting_files];
            updatedFiles[index] = e.dataTransfer.files[0];
            setData('supporting_files', updatedFiles);
        }
    };

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
        } catch (error) {
            console.error('Error formatting date:', error);
            return 'Invalid date';
        }
    };

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
                                    data={data}
                                    errors={errors}
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
                                    setData={(key: string, value: any) => {
                                        if (key === 'pr_document_file') {
                                            setData('pr_file', value);
                                        } else if (key === 'pr_document_metadata') {
                                            setData('pr_metadata', value);
                                        } else {
                                            setData(key as any, value);
                                        }
                                    }}
                                    errors={errors as Record<string, string>}
                                    isDragging={isDragging}
                                    hasError={hasError}
                                    submissionDate={submissionDate}
                                    handleDateChange={handleDateChange}
                                    handleDragEnter={handleDragEnter}
                                    handleDragLeave={handleDragLeave}
                                    handleDragOver={handleDragOver}
                                    handleDrop={handleDrop}
                                />
                            )}

                            {currentStep === 3 && (
                                <>
                                    <SupportingDocumentsStep
                                        data={{
                                            ...data,
                                            supporting_metadata: Object.fromEntries(
                                                data.supporting_metadata.map((meta, index) => {
                                                    // Safely convert submission_date to Date object if it's a string
                                                    let submissionDate;
                                                    try {
                                                        if (meta.submission_date && typeof meta.submission_date === 'string') {
                                                            submissionDate = parse(meta.submission_date, 'yyyy-MM-dd', new Date());
                                                        }
                                                    } catch (error) {
                                                        console.error('Error parsing supporting document date:', error);
                                                        submissionDate = undefined;
                                                    }

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
                                        setData={setData}
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
                                        handleDragEnter={handleDragEnter}
                                        handleDragLeave={handleDragLeave}
                                        handleDragOver={handleDragOver}
                                        handleSupportingFileDrop={handleSupportingFileDrop}
                                        supportingDates={supportingDates}
                                    />

                                    <div className="mt-8 pt-8 border-t">
                                        <FormSummary
                                            data={{
                                                ...data,
                                                pr_metadata: {
                                                    ...data.pr_metadata,
                                                    submission_date: formatDateForDisplay(data.pr_metadata.submission_date)
                                                },
                                                supporting_metadata: data.supporting_metadata.map(meta => ({
                                                    ...meta,
                                                    submission_date: formatDateForDisplay(meta.submission_date)
                                                }))
                                            }}
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

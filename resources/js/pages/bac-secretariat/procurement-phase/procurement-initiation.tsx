import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { FormHeader } from '@/components/pr-initiation/form-header';
import { ProcurementDetails } from '@/components/pr-initiation/steps/procurement-details';
import { Documents } from '@/components/pr-initiation/steps/documents';
import { FormSummary, FormSummaryProps } from '@/components/pr-initiation/form-summary';
import { ValidationSummary } from '@/components/pr-initiation/validation-summary';

import { Button } from '@/components/ui/button';

import { BreadcrumbItem } from '@/types';

// 1. Type Definitions
interface FileMetadata {
    document_type: string;
    submission_date: string;
    municipal_offices: string;
    signatory_details: string;
    [key: string]: string | number | boolean | null | undefined;
}

interface ProcurementInitiationFormData {
    procurement_id: string;
    procurement_title: string;
    files: (File | null)[];
    metadata: FileMetadata[];
    file?: File | null;
    [key: string]: string | number | boolean | File | Date | null | undefined | (File | null)[] | FileMetadata[];
}

type ComponentFormData = FormSummaryProps['data'];

// 2. Constants
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurement Initiation', href: '#' },
];

// 3. Helper Functions
function parseDate(dateStr: string): Date | undefined {
    if (!dateStr) return undefined;

    try {
        // Handle different date formats
        const date = new Date(dateStr);
        return !isNaN(date.getTime()) ? date : undefined;
    } catch (e) {
        console.error("Error parsing date:", e);
        return undefined;
    }
}

const prepareMetadataWithDefaults = (
    metadata: FileMetadata[],
    dates: Record<number, Date | undefined>,
): FileMetadata[] => {
    return metadata.map((item, index) => {
        const date = dates[index] || parseDate(item.submission_date || '');
        return {
            document_type: item.document_type || '',
            submission_date: date ? format(date, 'yyyy-MM-dd') : '',
            municipal_offices: item.municipal_offices || '',
            signatory_details: item.signatory_details || '',
        };
    });
};

const prepareFormSummaryData = (
    data: ProcurementInitiationFormData,
    formatDateFn: (date: Date | string | undefined) => string
): ProcurementInitiationFormData => {
    const result = { ...data };

    // Format submission dates in metadata
    result.metadata = data.metadata.map(meta => ({
        ...meta,
        submission_date: formatDateFn(meta.submission_date)
    }));

    return result;
};

export default function ProcurementInitiationForm() {
    // 1. Form state and initialization
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<ProcurementInitiationFormData>({
        procurement_id: '',
        procurement_title: '',
        file: null,
        files: [],
        metadata: [],
    });

    // 2. Component state variables
    const [fileCount, setFileCount] = useState(0);
    const [formCompletion, setFormCompletion] = useState({
        details: false,
        document: false,
        documents: false,
    });
    const [isDragging, setIsDragging] = useState(false);
    const [dates, setDates] = useState<Record<number, Date | undefined>>({});
    const [validationAttempted, setValidationAttempted] = useState(false);

    // 3. Format and conversion helpers
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

    const convertToComponentFormData = (formData: ProcurementInitiationFormData): ComponentFormData => {
        return {
            procurement_id: formData.procurement_id,
            procurement_title: formData.procurement_title,
            files: formData.files.map(file => file || undefined),
            metadata: formData.metadata,
        } as ComponentFormData;
    };

    // 4. Form field handlers
    const handleFieldChange = (field: string, value: string | number | boolean | null | File | undefined) => {
        setData(field as keyof ProcurementInitiationFormData, value as never);

        if (errors[field]) {
            clearErrors(field);
        }
    };

    const handleDateChange = (index: number, date: Date | undefined) => {
        setDates(prev => ({ ...prev, [index]: date }));

        if (date) {
            try {
                const formattedDate = format(date, 'yyyy-MM-dd');
                handleMetadataChange(index, 'submission_date', formattedDate);
            } catch (e) {
                console.error("Error formatting date:", e);
                // Still update with the raw date if formatting fails
                handleMetadataChange(index, 'submission_date', date.toISOString().split('T')[0]);
            }
        } else {
            // Clear the date if undefined
            handleMetadataChange(index, 'submission_date', '');
        }
    };

    const handleMetadataChange = (index: number, field: string, value: string) => {
        const updatedMetadata = [...data.metadata];
        if (!updatedMetadata[index]) {
            updatedMetadata[index] = {
                document_type: '',
                submission_date: '',
                municipal_offices: '',
                signatory_details: ''
            };
        }
        updatedMetadata[index][field as keyof FileMetadata] = value;
        setData('metadata', updatedMetadata);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        const file = e.target.files?.[0] || null;
        if (file) {
            const updatedFiles = [...data.files];
            updatedFiles[index] = file;
            setData('files', updatedFiles);
        }
    };

    const handleMainFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        if (file && validateFile(file)) {
            setData('file', file);
        }
    };

    // 5. File management functions
    const addFile = () => {
        setFileCount(prevCount => prevCount + 1);

        const newFiles = [...data.files, null];
        const newMetadata = [...data.metadata];
        
        // Get metadata from the last document if available
        const lastIndex = newMetadata.length - 1;
        let newDocMetadata;
        
        if (lastIndex >= 0 && newMetadata[lastIndex]) {
            // Copy metadata but reset document_type
            newDocMetadata = {
                document_type: '',
                submission_date: newMetadata[lastIndex].submission_date || '',
                municipal_offices: newMetadata[lastIndex].municipal_offices || '',
                signatory_details: newMetadata[lastIndex].signatory_details || ''
            };
        } else {
            newDocMetadata = {
                document_type: '',
                submission_date: '',
                municipal_offices: '',
                signatory_details: ''
            };
        }
        
        newMetadata.push(newDocMetadata);

        setData('files', newFiles);
        setData('metadata', newMetadata);

        // Copy date from previous document if available
        if (lastIndex >= 0 && dates[lastIndex]) {
            setDates(prev => ({
                ...prev,
                [lastIndex + 1]: dates[lastIndex]
            }));
        }

        setFormCompletion(prev => ({ ...prev, documents: false }));
    };

    const removeFile = (index: number) => {
        const newFiles = [...data.files];
        newFiles.splice(index, 1);

        const newMetadata = [...data.metadata];
        newMetadata.splice(index, 1);

        setData('files', newFiles);
        setData('metadata', newMetadata);
        setFileCount(fileCount - 1);
    };

    const validateFile = (file: File): boolean => {
        // Check file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
            toast.error("File too large", { description: "Maximum file size is 10MB" });
            return false;
        }
        
        // Check file type (add more as needed)
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!allowedTypes.includes(file.type)) {
            toast.error("Invalid file type", { 
                description: "Please upload PDF, Word, or image files only" 
            });
            return false;
        }
        
        return true;
    };

    // 6. Drag and drop handlers
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
                    const updatedFiles = [...data.files];
                    updatedFiles[index] = file;
                    setData('files', updatedFiles);
                } else {
                    setData('file', file);
                }
                break;
            }
        }
    };

    // Add this function to copy metadata from one document to another
    const copyMetadataFromPrevious = (targetIndex: number) => {
        if (targetIndex <= 0 || !data.metadata[targetIndex - 1]) return;
        
        const updatedMetadata = [...data.metadata];
        // Copy metadata from the previous document but keep the document_type if it exists
        const currentDocType = updatedMetadata[targetIndex]?.document_type || '';
        updatedMetadata[targetIndex] = {
            ...updatedMetadata[targetIndex - 1],
            document_type: currentDocType // Preserve the current document type
        };
        
        setData('metadata', updatedMetadata);
        
        // Also update dates state
        if (dates[targetIndex - 1]) {
            setDates(prev => ({
                ...prev,
                [targetIndex]: dates[targetIndex - 1]
            }));
        }
    };

    // Add function to apply first document metadata to all
    const applyMetadataToAll = () => {
        if (!data.metadata[0] || data.files.length <= 1) return;
        
        const sourceMetadata = data.metadata[0];
        const updatedMetadata = [...data.metadata];
        
        // Apply the first document's metadata to all documents except document_type
        for (let i = 1; i < updatedMetadata.length; i++) {
            const currentDocType = updatedMetadata[i]?.document_type || '';
            updatedMetadata[i] = {
                ...sourceMetadata,
                document_type: currentDocType // Preserve each document's type
            };
        }
        
        setData('metadata', updatedMetadata);
        
        // Also update dates state
        if (dates[0]) {
            const newDates = { ...dates };
            for (let i = 1; i < data.files.length; i++) {
                newDates[i] = dates[0];
            }
            setDates(newDates);
        }
    };

    // 7. Form validation and submission
    const validateForm = (): boolean => {
        clearErrors();
        let isValid = true;

        // Step 1 validations
        if (!data.procurement_id) {
            setError('procurement_id', 'Procurement ID is required');
            isValid = false;
        }

        if (!data.procurement_title) {
            setError('procurement_title', 'Procurement title is required');
            isValid = false;
        }

        // Step 2 validations - Documents validation
        data.files.forEach((file, index) => {
            // Only validate metadata if file exists
            if (file) {
                if (!data.metadata[index]?.document_type) {
                    setError(`metadata.${index}.document_type`, 'Document type is required');
                    isValid = false;
                }
                
                if (!data.metadata[index]?.submission_date) {
                    setError(`metadata.${index}.submission_date`, 'Submission date is required');
                    isValid = false;
                }
                
                if (!data.metadata[index]?.municipal_offices) {
                    setError(`metadata.${index}.municipal_offices`, 'Municipal office is required');
                    isValid = false;
                }
                
                if (!data.metadata[index]?.signatory_details) {
                    setError(`metadata.${index}.signatory_details`, 'Signatory details are required');
                    isValid = false;
                }
            }
        });

        // Update form completion state
        setFormCompletion({
            details: !!data.procurement_id && !!data.procurement_title,
            document: data.files.every((file, index) => !file || (
                !!data.metadata[index]?.document_type &&
                !!data.metadata[index]?.submission_date &&
                !!data.metadata[index]?.municipal_offices &&
                !!data.metadata[index]?.signatory_details
            )),
            documents: true
        });

        return isValid;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationAttempted(true);

        if (!validateForm()) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            toast.error("Form validation failed", {
                description: "Please fix all errors before submitting"
            });
            return;
        }

        toast.loading("Submitting Procurement...", {
            id: "procurement-submission"
        });

        post('/bac-secretariat/publish-procurement-initiation', {
            onSuccess: handleSubmissionSuccess,
            onError: handleSubmissionError,
            preserveState: true,
            preserveScroll: true,
            forceFormData: true
        });
    };

    const handleSubmissionSuccess = () => {
        toast.success("Procurement successfully submitted", {
            id: "procurement-submission",
            description: "Your procurement request has been initiated"
        });
    };

    const handleSubmissionError = (errors: Record<string, string>) => {
        const errorMessage = Object.values(errors)[0] || "Please check your connection and try again";
        toast.error("Failed to submit procurement", {
            id: "procurement-submission",
            description: errorMessage
        });
    };

    // 8. Helper functions
    const hasError = (field: string) => {
        return Object.keys(errors).some(error => error === field || error.startsWith(`${field}.`));
    };

    const fileIndices = Array.from({ length: data.files.length }, (_, i) => i);

    // 9. Side effects
    useEffect(() => {
        try {
            const newDates: { [key: number]: Date | undefined } = {};
            data.metadata.forEach((metadata, index) => {
                if (metadata.submission_date) {
                    const parsedDate = parseDate(metadata.submission_date);
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
            document: !!data.file && 
                !!data.metadata[0]?.submission_date &&
                !!data.metadata[0]?.municipal_offices &&
                !!data.metadata[0]?.signatory_details,
            documents: data.files.length === 0 || (
                data.files.every((file, index) => !file || !!data.metadata[index + 1]?.document_type)
            )
        });
    }, [data]);

    // 10. Component props
    const procurementDetailsProps = {
        data: {
            procurement_id: data.procurement_id,
            procurement_title: data.procurement_title
        },
        errors: errors as Record<string, string>,
        hasError,
        handleFieldChange,
        clearErrors: (field: string) => clearErrors(field as keyof ProcurementInitiationFormData)
    };

    const documentsProps = {
        data: {
            file: data.file,
            files: data.files,
            metadata: Object.fromEntries(
                prepareMetadataWithDefaults(data.metadata, dates).map((meta, index) => [
                    index,
                    {
                        ...meta,
                        // More safely handle the submission_date
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
        errors: errors as Record<string, string>,
        handleFileChange,
        handleMainFileChange,
        handleMetadataChange,
        handleDateChange,
        handleDragEnter: (e: React.DragEvent) => handleFileDragEvent(e, 'enter'),
        handleDragLeave: (e: React.DragEvent) => handleFileDragEvent(e, 'leave'),
        handleDragOver: (e: React.DragEvent) => handleFileDragEvent(e, 'over'),
        handleFileDrop: (e: React.DragEvent, index?: number) => handleFileDragEvent(e, 'drop', index),
        dates,
        validateFile,
        setData: (key: string, value: unknown) => {
            setData(key as keyof ProcurementInitiationFormData, value as never);
        },
        copyMetadataFromPrevious,
        applyMetadataToAll,
    };

    const formSummaryProps = {
        data: convertToComponentFormData(prepareFormSummaryData(data, formatDateForDisplay)),
        formCompletion,
        addFile
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Procurement" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-6">
                    <FormHeader />
                </div>

                {validationAttempted && (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-6">
                        <ValidationSummary 
                            errors={errors as Record<string, string>} 
                            hasErrors={Object.keys(errors).length > 0}
                            isSubmitting={processing} 
                        />
                    </div>
                )}

                <form onSubmit={handleSubmit} className="flex-1 space-y-4">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-6">
                        <div className="space-y-8">
                            <div className="border-b pb-6">
                                <h2 className="text-xl font-semibold mb-4">Procurement Details</h2>
                                <ProcurementDetails {...procurementDetailsProps} />
                            </div>

                            <div className="border-b pb-6">
                                <h2 className="text-xl font-semibold mb-4">Document Upload</h2>
                                <Documents {...documentsProps} />
                            </div>

                            <div>
                                <h2 className="text-xl font-semibold mb-4">Procurement Summary</h2>
                                <FormSummary {...formSummaryProps} />
                            </div>
                        </div>
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border bg-white dark:bg-black/80 p-4">
                        <div className="flex justify-between">
                            <Button
                                type="button"
                                onClick={() => {
                                    setValidationAttempted(true);
                                    validateForm();
                                }}
                                variant="outline"
                                className="px-4 py-2"
                            >
                                Validate Form
                            </Button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Submitting...' : 'Submit Procurement'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
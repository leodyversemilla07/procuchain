import React, { useState, useEffect } from 'react';
import { FileText, Upload, ClipboardList, CheckCircle2, ChevronLeft, ChevronRight, Save } from 'lucide-react';
import { format } from 'date-fns';
import { useForm, Head } from '@inertiajs/react';
import { toast } from 'sonner';
import type { BreadcrumbItem } from '@/types';
import type { FormSummaryProps } from '@/components/procurement-initiation/form-summary';
import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { FormHeader } from '@/components/procurement-initiation/form-header';
import { ProcurementDetails } from '@/components/procurement-initiation/procurement-details';
import { Documents } from '@/components/procurement-initiation/documents';
import { FormSummary } from '@/components/procurement-initiation/form-summary';

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

    result.metadata = data.metadata.map(meta => ({
        ...meta,
        submission_date: formatDateFn(meta.submission_date)
    }));

    return result;
};

export default function ProcurementInitiationForm() {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<ProcurementInitiationFormData>({
        procurement_id: '',
        procurement_title: '',
        file: null,
        files: [null],
        metadata: [{
            document_type: '',
            submission_date: '',
            municipal_offices: '',
            signatory_details: ''
        }],
    });

    const [fileCount, setFileCount] = useState(1);
    const [formCompletion, setFormCompletion] = useState({
        details: false,
        document: false,
        documents: false,
    });
    const [isDragging, setIsDragging] = useState(false);
    const [dates, setDates] = useState<Record<number, Date | undefined>>({});
    const [currentStep, setCurrentStep] = useState(1);

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
                handleMetadataChange(index, 'submission_date', date.toISOString().split('T')[0]);
            }
        } else {
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

    const addFile = () => {
        setFileCount(prevCount => prevCount + 1);

        const newFiles = [...data.files, null];
        const newMetadata = [...data.metadata];
        
        const lastIndex = newMetadata.length - 1;
        let newDocMetadata;
        
        if (lastIndex >= 0 && newMetadata[lastIndex]) {
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
        if (file.size > 10 * 1024 * 1024) {
            toast.error("File too large", { description: "Maximum file size is 10MB" });
            return false;
        }
        
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

    const copyMetadataFromPrevious = (targetIndex: number) => {
        if (targetIndex <= 0 || !data.metadata[targetIndex - 1]) return;
        
        const updatedMetadata = [...data.metadata];
        const currentDocType = updatedMetadata[targetIndex]?.document_type || '';
        updatedMetadata[targetIndex] = {
            ...updatedMetadata[targetIndex - 1],
            document_type: currentDocType
        };
        
        setData('metadata', updatedMetadata);
        
        if (dates[targetIndex - 1]) {
            setDates(prev => ({
                ...prev,
                [targetIndex]: dates[targetIndex - 1]
            }));
        }
    };

    const applyMetadataToAll = () => {
        if (!data.metadata[0] || data.files.length <= 1) return;
        
        const sourceMetadata = data.metadata[0];
        const updatedMetadata = [...data.metadata];
        
        for (let i = 1; i < updatedMetadata.length; i++) {
            const currentDocType = updatedMetadata[i]?.document_type || '';
            updatedMetadata[i] = {
                ...sourceMetadata,
                document_type: currentDocType
            };
        }
        
        setData('metadata', updatedMetadata);
        
        if (dates[0]) {
            const newDates = { ...dates };
            for (let i = 1; i < data.files.length; i++) {
                newDates[i] = dates[0];
            }
            setDates(newDates);
        }
    };

    const validateForm = (): boolean => {
        clearErrors();
        let isValid = true;

        if (!data.procurement_id) {
            setError('procurement_id', 'Procurement ID is required');
            isValid = false;
        }

        if (!data.procurement_title) {
            setError('procurement_title', 'Procurement title is required');
            isValid = false;
        }

        data.files.forEach((file, index) => {
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

        setFormCompletion({
            details: !!data.procurement_id && !!data.procurement_title,
            document: data.files.some(file => !!file) && data.metadata.every((meta, index) => 
                !data.files[index] || (
                    !!meta.document_type &&
                    !!meta.submission_date &&
                    !!meta.municipal_offices &&
                    !!meta.signatory_details
                )
            ),
            documents: data.files.some(file => !!file) && data.metadata.every((meta, index) => 
                !data.files[index] || (
                    !!meta.document_type &&
                    !!meta.submission_date &&
                    !!meta.municipal_offices &&
                    !!meta.signatory_details
                )
            )
        });

        return isValid;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

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

    const hasError = (field: string) => {
        return Object.keys(errors).some(error => error === field || error.startsWith(`${field}.`));
    };

    const fileIndices = Array.from({ length: data.files.length }, (_, i) => i);

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
            document: data.files.some(file => !!file) && data.metadata.every((meta, index) => 
                !data.files[index] || (
                    !!meta.document_type &&
                    !!meta.submission_date &&
                    !!meta.municipal_offices &&
                    !!meta.signatory_details
                )
            ),
            documents: data.files.some(file => !!file) && data.metadata.every((meta, index) => 
                !data.files[index] || (
                    !!meta.document_type &&
                    !!meta.submission_date &&
                    !!meta.municipal_offices &&
                    !!meta.signatory_details
                )
            )
        });
    }, [data]);

    const calculateProgress = () => {
        let progress = 0;
        if (formCompletion.details) progress += 33;
        if (formCompletion.document) progress += 33;
        if (formCompletion.documents) progress += 34;
        return progress;
    };

    const progressValue = calculateProgress();

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
        addFile,
        setCurrentStep
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Procurement" />

            <div className="flex h-full flex-1 flex-col gap-5 p-5">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-6 shadow-sm">
                    <FormHeader />
                    
                    <div className="mt-8">
                        <Progress value={progressValue} className="h-2" />
                        
                        <div className="mt-4 grid grid-cols-3 gap-4">
                            {formSteps.map((step) => (
                                <button
                                    key={step.id}
                                    onClick={() => setCurrentStep(step.id)}
                                    className={`flex items-start p-4 rounded-lg transition-all ${
                                        currentStep === step.id
                                            ? 'bg-primary/10 border border-primary'
                                            : 'hover:bg-muted/50'
                                    }`}
                                >
                                    <div className={`rounded-full p-2 mr-3 ${
                                        currentStep === step.id
                                            ? 'bg-primary text-white'
                                            : 'bg-muted'
                                    }`}>
                                        {step.icon}
                                    </div>
                                    <div className="text-left">
                                        <h3 className="font-medium flex items-center gap-2">
                                            {step.title}
                                            {(
                                                (step.id === 1 && formCompletion.details) ||
                                                (step.id === 2 && formCompletion.document) ||
                                                (step.id === 3 && formCompletion.documents)
                                            ) && (
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            )}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">{step.description}</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </Card>

                <form onSubmit={handleSubmit} className="flex-1 space-y-5">
                    {currentStep === 1 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-6 shadow-sm">
                            <ProcurementDetails {...procurementDetailsProps} />
                        </Card>
                    )}

                    {currentStep === 2 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-6 shadow-sm">
                            <Documents {...documentsProps} />
                        </Card>
                    )}

                    {currentStep === 3 && (
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-6 shadow-sm">
                            <FormSummary {...formSummaryProps} />
                        </Card>
                    )}

                    <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden bg-white dark:bg-black/80 p-5 shadow-sm">
                        <div className="grid grid-cols-3 items-center">
                            <div className="flex items-center gap-4">
                                {currentStep > 1 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCurrentStep(currentStep - 1)}
                                        className="gap-2"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Back to {formSteps[currentStep - 2].title}
                                    </Button>
                                )}
                            </div>
                            
                            <div className="text-sm text-muted-foreground text-center">
                                Step {currentStep} of {formSteps.length}
                            </div>

                            <div className="flex items-center justify-end gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        post('/bac-secretariat/save-procurement-draft', {
                                            preserveScroll: true,
                                            preserveState: true,
                                            headers: {
                                                'X-Inertia': 'true',
                                                Accept: 'application/json',
                                            },
                                            onSuccess: () => {
                                                toast.success('Draft saved successfully');
                                            },
                                            onError: () => {
                                                toast.error('Failed to save draft');
                                            }
                                        });
                                    }}
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
                                        className="gap-2"
                                    >
                                        Continue to {formSteps[currentStep].title}
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        disabled={processing || !formCompletion.details || !formCompletion.document || !formCompletion.documents}
                                        className="bg-primary hover:bg-primary/90 text-white gap-2"
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
                                )}
                            </div>
                        </div>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
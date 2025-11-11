import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import InputError from '@/components/input-error';
import PeopleInput from '@/components/people-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertCircle, BarChart4, CalendarIcon, Upload } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_FILE_TYPES = ['application/pdf'];

interface BidEvaluationUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
    return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function BidEvaluationUpload({ procurement = { id: '', title: '' } }: BidEvaluationUploadProps) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        summary_file: null as File | null,
        abstract_file: null as File | null,
        evaluation_date: new Date(),
        evaluator_names: '',
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Bid Evaluation Report - ${procurement.id}`, href: '#' },
    ]);

    // File validation
    const validateFile = (file: File) => {
        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            toast.error('Invalid file type', { description: 'Only PDF files are allowed.' });
            return false;
        }
        if (file.size > MAX_FILE_SIZE) {
            toast.error('File size exceeds 10MB limit');
            return false;
        }
        return true;
    };

    // File drop hooks
    const summaryDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('summary_file', file),
    });
    const abstractDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('abstract_file', file),
    });

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Client-side validation
        if (!data.summary_file) {
            toast.error('Missing summary file', { description: 'Please upload the evaluation summary PDF.' });
            return;
        }

        if (!data.abstract_file) {
            toast.error('Missing abstract file', { description: 'Please upload the bid abstract PDF.' });
            return;
        }

        if (!data.evaluation_date) {
            toast.error('Missing evaluation date', { description: 'Please select the evaluation date.' });
            return;
        }

        if (!data.evaluator_names.trim()) {
            toast.error('Missing evaluator names', { description: 'Please enter at least one evaluator.' });
            return;
        }

        transform((formData) => ({
            ...formData,
            evaluation_date: formData.evaluation_date ? format(formData.evaluation_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-bid-evaluation-documents', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Bid evaluation report uploaded successfully!', {
                    description: 'Bid evaluation report has been submitted.',
                });
                reset();
                clearErrors();
            },
            onError: () => {
                toast.error('Failed to upload bid evaluation report', {
                    description: 'Please check the form for errors and try again.',
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Bid Evaluation Report" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-3 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <BarChart4 className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Bid Evaluation Report</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl text-sm sm:text-base">
                        Upload the bid evaluation report for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>

                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <BarChart4 className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Required Documents
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Please upload the bid evaluation summary and abstract in PDF format (max 10MB each)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6 sm:space-y-8">
                                <FileUploadArea
                                    label="Evaluation Summary"
                                    file={data.summary_file}
                                    error={getFieldError(errors, 'summary_file')}
                                    isDragging={summaryDrop.isDragging}
                                    onFileChange={(e) => {
                                        if (e.target.files && e.target.files.length > 0) {
                                            const file = e.target.files[0];
                                            if (validateFile(file)) setData('summary_file', file);
                                        }
                                    }}
                                    onDragEnter={summaryDrop.handleDragEnter}
                                    onDragLeave={summaryDrop.handleDragLeave}
                                    onDragOver={summaryDrop.handleDragOver}
                                    onDrop={summaryDrop.handleDrop}
                                    onRemove={() => setData('summary_file', null)}
                                    inputId="summary-file-input"
                                    required
                                />
                                {getFieldError(errors, 'summary_file') && <InputError message={getFieldError(errors, 'summary_file')} />}
                                <FileUploadArea
                                    label="Bid Abstract"
                                    file={data.abstract_file}
                                    error={getFieldError(errors, 'abstract_file')}
                                    isDragging={abstractDrop.isDragging}
                                    onFileChange={(e) => {
                                        if (e.target.files && e.target.files.length > 0) {
                                            const file = e.target.files[0];
                                            if (validateFile(file)) setData('abstract_file', file);
                                        }
                                    }}
                                    onDragEnter={abstractDrop.handleDragEnter}
                                    onDragLeave={abstractDrop.handleDragLeave}
                                    onDragOver={abstractDrop.handleDragOver}
                                    onDrop={abstractDrop.handleDrop}
                                    onRemove={() => setData('abstract_file', null)}
                                    inputId="abstract-file-input"
                                    required
                                />
                                {getFieldError(errors, 'abstract_file') && <InputError message={getFieldError(errors, 'abstract_file')} />}
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <CalendarIcon className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Evaluation Details
                                </CardTitle>
                                <CardDescription className="text-sm">Provide information about the evaluation</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 sm:space-y-6">
                                <DatePicker
                                    label="Evaluation Date"
                                    value={data.evaluation_date instanceof Date ? data.evaluation_date : new Date(data.evaluation_date)}
                                    onChange={(date) => date && setData('evaluation_date', date)}
                                    error={getFieldError(errors, 'evaluation_date')}
                                    required
                                />
                                {getFieldError(errors, 'evaluation_date') && <InputError message={getFieldError(errors, 'evaluation_date')} />}
                                <PeopleInput
                                    label="Evaluator Names"
                                    value={
                                        data.evaluator_names
                                            ? data.evaluator_names
                                                  .split('\n')
                                                  .filter(Boolean)
                                                  .map((name) => ({ name, affiliation: '' }))
                                            : []
                                    }
                                    onChange={(updated) => setData('evaluator_names', updated.map((p) => p.name).join('\n'))}
                                    error={getFieldError(errors, 'evaluator_names')}
                                    required
                                    namePlaceholder="Type evaluator name"
                                    affiliationType="position"
                                />
                                {getFieldError(errors, 'evaluator_names') && <InputError message={getFieldError(errors, 'evaluator_names')} />}
                            </CardContent>
                            <CardFooter className="flex flex-col gap-2 border-t pt-3 sm:gap-3 sm:pt-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="flex h-10 w-full items-center gap-2 text-sm sm:h-11 sm:text-base"
                                >
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                            Processing...
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Submit Evaluation
                                        </>
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    disabled={processing}
                                    className="h-9 w-full text-sm sm:h-10 sm:text-base"
                                >
                                    Cancel
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </form>
                {Object.keys(errors).length > 0 && (
                    <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
                        <CardContent className="p-3 sm:p-4">
                            <div className="flex items-start">
                                <AlertCircle className="text-destructive mt-0.5 mr-2 h-4 w-4 sm:mr-3 sm:h-5 sm:w-5" />
                                <div>
                                    <h4 className="text-destructive text-xs font-medium sm:text-sm">Please fix the following errors:</h4>
                                    <ul className="text-destructive/90 mt-1 list-inside list-disc space-y-0.5 text-xs sm:mt-2 sm:space-y-1 sm:text-sm">
                                        {Object.entries(errors).map(([field, message]) => (
                                            <li key={field}>{message}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

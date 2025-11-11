import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import InputError from '@/components/input-error';
import { TextareaWithLabel } from '@/components/textarea-with-label';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertCircle, CalendarIcon, CheckCircle, FileText, Upload } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
    return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

interface CompletionUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

export default function CompletionUpload({ procurement = { id: '', title: '' } }: CompletionUploadProps) {
    // Format current date for initial form data
    const currentDate = new Date();

    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        completion_file: null as File | null,
        completion_date: currentDate,
        completion_notes: '',
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Certificate of Completion - ${procurement.id}`, href: '#' },
    ]);

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Client-side validation
        if (!data.completion_file) {
            toast.error('Missing Certificate of Completion', { description: 'Please upload the certificate of completion PDF.' });
            return;
        }
        if (!data.completion_date) {
            toast.error('Missing completion date', { description: 'Please select the completion date.' });
            return;
        }

        transform((formData) => ({
            ...formData,
            completion_date: formData.completion_date ? format(formData.completion_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-completion-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Certificate of Completion uploaded successfully!', {
                    description: 'Completion document has been submitted.',
                });
                reset();
                clearErrors();
            },
            onError: () => {
                toast.error('Failed to upload Certificate of Completion', {
                    description: 'Please check the form for errors.',
                });
            },
        });
    };

    // File validation
    const validateFile = (file: File) => {
        if (file.type !== 'application/pdf') {
            toast.error('Invalid file type', { description: 'Please upload only PDF files.' });
            return false;
        }
        if (file.size > 10 * 1024 * 1024) {
            toast.error('File too large', { description: 'Maximum file size is 10MB.' });
            return false;
        }
        return true;
    };

    // Use custom file drop hook for completion file
    const completionDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('completion_file', file),
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (validateFile(file)) {
                setData('completion_file', file);
            }
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Certificate of Completion" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-6 rounded-xl bg-linear-to-b p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <CheckCircle className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Certificate of Completion</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the Certificate of Completion document for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <FileText className="text-primary h-5 w-5" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>Please upload the Certificate of Completion in PDF format</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8">
                                <FileUploadArea
                                    label="Certificate of Completion Document"
                                    file={data.completion_file}
                                    error={getFieldError(errors, 'completion_file')}
                                    isDragging={completionDrop.isDragging}
                                    onFileChange={handleFileChange}
                                    onDragEnter={completionDrop.handleDragEnter}
                                    onDragLeave={completionDrop.handleDragLeave}
                                    onDragOver={completionDrop.handleDragOver}
                                    onDrop={completionDrop.handleDrop}
                                    onRemove={() => setData('completion_file', null)}
                                    inputId="completion-file-input"
                                    required={true}
                                />
                                {getFieldError(errors, 'completion_file') && <InputError message={getFieldError(errors, 'completion_file')} />}
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <CalendarIcon className="text-primary h-5 w-5" />
                                    Completion Details
                                </CardTitle>
                                <CardDescription>Provide information about the Completion</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <DatePicker
                                    label="Completion Date"
                                    value={data.completion_date instanceof Date ? data.completion_date : new Date(data.completion_date)}
                                    onChange={(date) => {
                                        if (date) setData('completion_date', date);
                                    }}
                                    error={getFieldError(errors, 'completion_date')}
                                    required
                                />
                                {getFieldError(errors, 'completion_date') && <InputError message={getFieldError(errors, 'completion_date')} />}
                                <div className="space-y-2">
                                    <TextareaWithLabel
                                        label="Notes"
                                        value={data.completion_notes}
                                        onChange={(e) => setData('completion_notes', e.target.value)}
                                        placeholder="Enter any additional notes"
                                        rows={5} // Updated to match the height in monitoring-upload.tsx
                                        required={false}
                                        error={getFieldError(errors, 'completion_notes')}
                                        errorClassName="mt-1.5 sm:mt-2"
                                    />
                                    {getFieldError(errors, 'completion_notes') && <InputError message={getFieldError(errors, 'completion_notes')} />}{' '}
                                    {/* Renamed key */}
                                </div>
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || !data.completion_file || (data.completion_file && data.completion_file.size > 10 * 1024 * 1024)
                                    }
                                    className="flex h-11 w-full items-center gap-2"
                                >
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                            Processing...
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Submit Completion
                                        </>
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    disabled={processing}
                                    className="h-10 w-full"
                                >
                                    Cancel
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </form>
                {Object.keys(errors).length > 0 && (
                    <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
                        <CardContent className="p-4">
                            <div className="flex items-start">
                                <AlertCircle className="text-destructive mt-0.5 mr-3 h-5 w-5" />
                                <div>
                                    <h4 className="text-destructive text-sm font-medium">Please fix the following errors:</h4>
                                    <ul className="text-destructive/90 mt-2 list-inside list-disc space-y-1 text-sm">
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

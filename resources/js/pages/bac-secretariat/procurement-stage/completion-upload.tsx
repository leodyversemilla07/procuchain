import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { CalendarIcon, FileText, Upload, AlertCircle, CheckCircle } from 'lucide-react';
import {
    Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';
import FileUploadArea from '@/components/file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import { Label } from '@/components/ui/label';

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
    const formattedDate = format(currentDate, 'yyyy-MM-dd');

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        completion_file: null as File | null, // Renamed from acceptance_are_par_file
        completion_date: formattedDate, // Renamed from acceptance_date
        completion_date_object: currentDate, // Renamed from acceptance_date_object
        completion_notes: '', // Renamed from remarks
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
        { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Certificate of Completion - ${procurement.id}`, href: '#' },
    ];

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
        post('/bac-secretariat/upload-completion-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success("Certificate of Completion uploaded successfully!", {
                    description: "Completion document has been submitted."
                });
                reset();
                clearErrors();
            },
            onError: () => {
                toast.error("Failed to upload Certificate of Completion", {
                    description: 'Please check the form for errors.'
                });
            }
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
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-primary">
                        <CheckCircle className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Certificate of Completion</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the Certificate of Completion document for procurement
                        <span className="font-medium text-foreground"> #{procurement.id}</span>:
                        <span className="font-medium text-foreground italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>
                                    Please upload the Certificate of Completion in PDF format
                                </CardDescription>
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
                                {getFieldError(errors, 'completion_file') && (
                                    <InputError message={getFieldError(errors, 'completion_file')} />
                                )}
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <CalendarIcon className="h-5 w-5 text-primary" />
                                    Completion Details
                                </CardTitle>
                                <CardDescription>
                                    Provide information about the Completion
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <DatePicker
                                    label="Completion Date"
                                    value={data.completion_date_object}
                                    onChange={date => {
                                        if (date) {
                                            setData('completion_date_object', date);
                                            setData('completion_date', format(date, 'yyyy-MM-dd'));
                                        }
                                    }}
                                    error={getFieldError(errors, 'completion_date')}
                                    required
                                />
                                {getFieldError(errors, 'completion_date') && (
                                    <InputError message={getFieldError(errors, 'completion_date')} />
                                )}
                                <div className="space-y-2">
                                    <Label className="flex items-center text-base font-medium">
                                        <FileText className="h-4 w-4 mr-2" />
                                        Notes
                                    </Label>
                                    <Textarea
                                        placeholder="Enter any additional notes" // Updated placeholder
                                        rows={3}
                                        className="min-h-[120px] resize-none"
                                        value={data.completion_notes} // Renamed key
                                        onChange={(e) => setData('completion_notes', e.target.value)} // Renamed key
                                    />
                                    {getFieldError(errors, 'completion_notes') && <InputError message={getFieldError(errors, 'completion_notes')} />} {/* Renamed key */}
                                </div>
                            </CardContent>
                            <CardFooter className="pt-4 border-t flex flex-col gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing || !data.completion_file || (data.completion_file && data.completion_file.size > 10 * 1024 * 1024)}
                                    className="w-full flex items-center gap-2 h-11"
                                >
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
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
                                    className="w-full h-10"
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
                                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                                <div>
                                    <h4 className="text-sm font-medium text-destructive">
                                        Please fix the following errors:
                                    </h4>
                                    <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
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

import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertCircle, CalendarIcon, Loader2, PlayCircle, Upload } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

interface NoticeToProceedUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
    return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function NoticeToProceedUpload({ procurement = { id: '', title: '' } }: NoticeToProceedUploadProps) {
    const currentDate = new Date();

    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement.id || '',
        procurement_title: procurement.title || '',
        ntp_file: null as File | null,
        issuance_date: currentDate,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
        { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Notice to Proceed - ${procurement.id}`, href: '#' },
    ];

    const validateFile = (file: File) => {
        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            toast.error('Invalid file type', { description: 'Only PDF files are allowed.' });
            return false;
        }
        if (file.size > MAX_FILE_SIZE) {
            toast.error('File too large', { description: 'Maximum file size is 10MB.' });
            return false;
        }
        return true;
    };

    const ntpDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('ntp_file', file),
    });

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Client-side validation
        if (!data.ntp_file) {
            toast.error('Missing file', { description: 'Please upload the Notice to Proceed document.' });
            return;
        }
        if (!data.issuance_date) {
            toast.error('Missing issuance date', { description: 'Please select the issuance date.' });
            return;
        }

        transform((formData) => ({
            ...formData,
            issuance_date: formData.issuance_date ? format(formData.issuance_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-ntp-document', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Notice to Proceed uploaded successfully!', {
                    description: 'Notice to Proceed has been submitted.',
                });
                reset();
                clearErrors();
            },
            onError: () => {
                toast.error('Submission failed.', {
                    description: 'Please check the form for errors.',
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Notice to Proceed" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-6 rounded-xl bg-gradient-to-b p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <PlayCircle className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Notice to Proceed</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the Notice to Proceed for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <PlayCircle className="text-primary h-5 w-5" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>Please upload the Notice to Proceed document in PDF format</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8">
                                <FileUploadArea
                                    label="Notice to Proceed Document"
                                    file={data.ntp_file}
                                    error={getFieldError(errors, 'ntp_file')}
                                    isDragging={ntpDrop.isDragging}
                                    onFileChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                        if (e.target.files && e.target.files.length > 0) {
                                            const file = e.target.files[0];
                                            if (validateFile(file)) {
                                                setData('ntp_file', file);
                                            }
                                        }
                                    }}
                                    onDragEnter={ntpDrop.handleDragEnter}
                                    onDragLeave={ntpDrop.handleDragLeave}
                                    onDragOver={ntpDrop.handleDragOver}
                                    onDrop={ntpDrop.handleDrop}
                                    onRemove={() => setData('ntp_file', null)}
                                    inputId="ntp-file-input"
                                    required={true}
                                />
                                {getFieldError(errors, 'ntp_file') && <InputError message={getFieldError(errors, 'ntp_file')} />}
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <CalendarIcon className="text-primary h-5 w-5" />
                                    Document Details
                                </CardTitle>
                                <CardDescription>Provide information about the Notice to Proceed</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <DatePicker
                                    label="Issuance Date"
                                    value={data.issuance_date instanceof Date ? data.issuance_date : new Date(data.issuance_date)}
                                    onChange={(date) => {
                                        if (date) setData('issuance_date', date);
                                    }}
                                    error={getFieldError(errors, 'issuance_date')}
                                    required
                                />
                                {getFieldError(errors, 'issuance_date') && <InputError message={getFieldError(errors, 'issuance_date')} />}
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button type="submit" disabled={processing} className="flex h-11 w-full items-center gap-2">
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Processing...
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Submit Notice
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
                                            <li key={field}>{message as string}</li>
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

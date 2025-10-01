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
import { AlertCircle, CalendarIcon, ClipboardCheck, Upload } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface MonitoringUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
    return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function MonitoringUpload({ procurement = { id: '', title: '' } }: MonitoringUploadProps) {
    const currentDate = new Date();

    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        compliance_file: null as File | null,
        report_date: currentDate,
        report_notes: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
        { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Compliance Report - ${procurement.id}: ${procurement.title}`, href: '#' },
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

    const complianceDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('compliance_file', file),
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (validateFile(file)) {
                setData('compliance_file', file);
            }
        }
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Client-side validation
        if (!data.compliance_file) {
            toast.error('Missing file', { description: 'Please upload the compliance report.' });
            return;
        }
        if (!data.report_date) {
            toast.error('Missing report date', { description: 'Please select the report date.' });
            return;
        }

        transform((formData) => ({
            ...formData,
            report_date: formData.report_date ? format(formData.report_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-monitoring-document', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Compliance report uploaded successfully!', {
                    description: 'Compliance report has been submitted.',
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
            <Head title="Upload Compliance Report" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-6 rounded-xl bg-gradient-to-b p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <ClipboardCheck className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Compliance Report</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the compliance report for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <ClipboardCheck className="text-primary h-5 w-5" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>Please upload the compliance report in PDF format</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8">
                                <FileUploadArea
                                    label="Compliance Report Document"
                                    file={data.compliance_file}
                                    error={getFieldError(errors, 'compliance_file')}
                                    isDragging={complianceDrop.isDragging}
                                    onFileChange={handleFileChange}
                                    onDragEnter={complianceDrop.handleDragEnter}
                                    onDragLeave={complianceDrop.handleDragLeave}
                                    onDragOver={complianceDrop.handleDragOver}
                                    onDrop={complianceDrop.handleDrop}
                                    onRemove={() => setData('compliance_file', null)}
                                    inputId="compliance-file-input"
                                    required={true}
                                />
                                {getFieldError(errors, 'compliance_file') && <InputError message={getFieldError(errors, 'compliance_file')} />}
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <CalendarIcon className="text-primary h-5 w-5" />
                                    Report Details
                                </CardTitle>
                                <CardDescription>Provide information about the compliance report</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <DatePicker
                                    label="Report Date"
                                    value={data.report_date instanceof Date ? data.report_date : new Date(data.report_date)}
                                    onChange={(date) => {
                                        if (date) setData('report_date', date);
                                    }}
                                    error={getFieldError(errors, 'report_date')}
                                    required
                                />
                                {getFieldError(errors, 'report_date') && <InputError message={getFieldError(errors, 'report_date')} />}
                                <div className="space-y-2">
                                    <TextareaWithLabel
                                        label="Report Notes"
                                        value={data.report_notes}
                                        onChange={(e) => setData('report_notes', e.target.value)}
                                        placeholder="Enter any additional notes or comments about the compliance report"
                                        rows={5}
                                        required={false}
                                        error={getFieldError(errors, 'report_notes')}
                                        errorClassName="mt-1.5 sm:mt-2"
                                    />
                                    {getFieldError(errors, 'report_notes') && <InputError message={getFieldError(errors, 'report_notes')} />}
                                </div>
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button type="submit" disabled={processing} className="flex h-11 w-full items-center gap-2">
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                            Processing...
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Submit Report
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

import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import PeopleInput from '@/components/people-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertCircle, CalendarIcon, ClipboardList, FileText, Loader2, Upload } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

// Allowed file types and max file size for uploads
const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface PreProcurementUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

export default function PreProcurementUpload({ procurement = { id: '', title: '' } }: PreProcurementUploadProps) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        minutes_file: null as File | null,
        attendance_file: null as File | null,
        meeting_date: new Date(), // store as Date object
        participants: [] as Array<{ name: string; organization: string }>,
    });

    // File validation
    const validateFile = (file: File) => {
        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            toast.error('Invalid file type', {
                description: 'Only PDF files are allowed.',
            });
            return false;
        }
        if (file.size > MAX_FILE_SIZE) {
            toast.error('File too large', {
                description: 'Maximum file size is 10MB.',
            });
            return false;
        }
        return true;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
        { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Pre-Procurement Documents - ${procurement.id}: ${procurement.title}`, href: '#' },
    ];

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fieldName: 'minutes_file' | 'attendance_file') => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (validateFile(file)) {
                setData(fieldName, file);
            }
        }
    };

    const handleDateSelect = (date: Date | undefined) => {
        if (date) {
            setData('meeting_date', date);
        }
    };

    // Use custom hook for minutes file
    const minutesDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('minutes_file', file),
    });
    // Use custom hook for attendance file
    const attendanceDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('attendance_file', file),
    });

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.minutes_file || !data.attendance_file) {
            toast.error('Missing files', {
                description: 'Please upload both minutes and attendance files.',
            });
            return;
        }

        if (!Array.isArray(data.participants) || data.participants.length === 0) {
            toast.error('Missing participants', {
                description: 'Please add at least one participant.',
            });
            return;
        }

        transform((formData) => ({
            ...formData,
            meeting_date: formData.meeting_date ? format(formData.meeting_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-pre-procurement-conference-documents', {
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Documents uploaded successfully!', {
                    description: 'Pre-procurement conference documents have been submitted.',
                });
                reset();
                clearErrors();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Pre-Procurement Documents" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-3 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <ClipboardList className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Pre-Procurement Conference Documents</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl text-sm sm:text-base">
                        Upload meeting minutes and attendance records for the pre-procurement conference of procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>

                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Required Documents
                                </CardTitle>
                                <CardDescription className="text-sm">Please upload all required documents in PDF format (max 10MB)</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6 sm:space-y-8">
                                <FileUploadArea
                                    label="Minutes of Pre-Procurement Conference"
                                    file={data.minutes_file}
                                    error={errors.minutes_file}
                                    isDragging={minutesDrop.isDragging}
                                    onFileChange={(e) => handleFileChange(e, 'minutes_file')}
                                    onDragEnter={minutesDrop.handleDragEnter}
                                    onDragLeave={minutesDrop.handleDragLeave}
                                    onDragOver={minutesDrop.handleDragOver}
                                    onDrop={minutesDrop.handleDrop}
                                    onRemove={() => setData('minutes_file', null)}
                                    inputId="minutes-file-input"
                                    required={true}
                                />
                                <FileUploadArea
                                    label="Attendance Sheet"
                                    file={data.attendance_file}
                                    error={errors.attendance_file}
                                    isDragging={attendanceDrop.isDragging}
                                    onFileChange={(e) => handleFileChange(e, 'attendance_file')}
                                    onDragEnter={attendanceDrop.handleDragEnter}
                                    onDragLeave={attendanceDrop.handleDragLeave}
                                    onDragOver={attendanceDrop.handleDragOver}
                                    onDrop={attendanceDrop.handleDrop}
                                    onRemove={() => setData('attendance_file', null)}
                                    inputId="attendance-file-input"
                                    required={true}
                                />
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <CalendarIcon className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Meeting Details
                                </CardTitle>
                                <CardDescription className="text-sm">Provide information about the conference</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 sm:space-y-6">
                                <DatePicker
                                    label="Meeting Date"
                                    value={data.meeting_date}
                                    onChange={handleDateSelect}
                                    error={errors.meeting_date}
                                    required
                                />
                                <PeopleInput
                                    label="Participants"
                                    value={
                                        Array.isArray(data.participants)
                                            ? data.participants.map((p) => ({ name: p.name, affiliation: p.organization }))
                                            : []
                                    }
                                    onChange={(updated) => {
                                        // Map {name, affiliation} from PeopleInput to {name, organization} for form state
                                        const normalized = Array.isArray(updated)
                                            ? updated.map((p) => ({ name: p.name, organization: p.affiliation }))
                                            : [];
                                        setData('participants', normalized);
                                    }}
                                    error={errors.participants}
                                    required
                                    affiliationType="organization"
                                    namePlaceholder="Enter participant name"
                                />
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button type="submit" disabled={processing} className="flex h-10 w-full items-center gap-2 sm:h-11">
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Submitting documents...
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4" />
                                            Submit Documents
                                        </>
                                    )}
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit('/bac-secretariat/procurements-list')}
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
                        <CardContent className="p-3 sm:p-4">
                            <div className="flex items-start">
                                <AlertCircle className="text-destructive mt-0.5 mr-2 h-4 w-4 sm:mr-3 sm:h-5 sm:w-5" />
                                <div>
                                    <h4 className="text-destructive text-xs font-medium sm:text-sm">Please fix the following errors:</h4>
                                    <ul className="text-destructive/90 mt-1 list-inside list-disc space-y-1 text-xs sm:mt-2 sm:text-sm">
                                        {Object.entries(errors).map(([field, message]) => (
                                            <li key={field} className="text-destructive/90">
                                                <span className="text-xs font-medium sm:text-sm">{field.replace('_', ' ').toUpperCase()}</span>
                                                <span className="text-xs sm:text-sm">: {message}</span>
                                            </li>
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

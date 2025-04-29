import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, CheckCircle } from 'lucide-react';
import {
    Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
    Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface CompletionUploadProps {
    procurement: {
        id: string;
        title: string;
    };
    errors?: Record<string, string>;
}

export default function CompletionUpload({ procurement, errors = {} }: CompletionUploadProps) {
    const [isDraggingFile, setIsDraggingFile] = useState(false);

    // Format current date for initial form data
    const currentDate = new Date();
    const formattedDate = format(currentDate, 'yyyy-MM-dd');

    const { data, setData, post, processing } = useForm({
        procurement_id: procurement.id || '',
        procurement_title: procurement.title || '',
        completion_file: null as File | null, // Renamed from acceptance_are_par_file
        completion_date: formattedDate, // Renamed from acceptance_date
        completion_date_object: currentDate, // Renamed from acceptance_date_object
        completion_notes: '', // Renamed from remarks
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Certificate of Completion - ${procurement.id}`, href: '#' }, // Updated title
    ];

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/bac-secretariat/upload-completion-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success("Certificate of Completion uploaded successfully!", { // Updated message
                    description: "Completion document has been submitted."
                });
            },
            onError: (errors) => {
                toast.error("Failed to upload Certificate of Completion", { // Updated message
                    description: Object.values(errors)[0] as string
                });
            }
        });
    };

    const handleDragEvents = (e: React.DragEvent, isDragging = true) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDraggingFile(isDragging);
    };

    const handleFileDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDraggingFile(false);

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0];
            if (file.type === 'application/pdf') {
                setData('completion_file', file); // Renamed key
            } else {
                toast.error('Invalid file type', { description: 'Please upload only PDF files.' });
            }
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (file.type === 'application/pdf') {
                setData('completion_file', file); // Renamed key
            } else {
                toast.error('Invalid file type', { description: 'Please upload only PDF files.' });
                e.target.value = ''; // Reset file input
            }
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Certificate of Completion" /> {/* Updated title */}

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-primary">
                        <CheckCircle className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Certificate of Completion</h1> {/* Updated title */}
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the Certificate of Completion document for procurement {/* Updated text */}
                        <span className="font-medium text-foreground"> #{procurement.id}</span>:
                        <span className="font-medium text-foreground italic"> {procurement.title}</span>
                    </p>
                </div>

                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* File Upload Card */}
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>
                                    Please upload the Certificate of Completion in PDF format {/* Updated text */}
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-8">
                                <div className="space-y-2">
                                    <label className="flex items-center text-base font-medium">
                                        <FileText className="h-4 w-4 mr-2" />
                                        Certificate of Completion Document {/* Updated label */}
                                    </label>
                                    <div
                                        className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingFile
                                            ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                                            : data.completion_file // Renamed key
                                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                                : errors.completion_file // Renamed key
                                                    ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                                    : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                                            } cursor-pointer group`}
                                        onDragEnter={(e) => handleDragEvents(e)}
                                        onDragLeave={(e) => handleDragEvents(e, false)}
                                        onDragOver={(e) => handleDragEvents(e)}
                                        onDrop={handleFileDrop}
                                        onClick={() => document.getElementById('file-input')?.click()}
                                    >
                                        {!data.completion_file ? ( // Renamed key
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                                    <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                                </div>
                                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                                    Drag and drop your Certificate of Completion here {/* Updated text */}
                                                </p>
                                                <p className="text-sm text-muted-foreground/70 mb-5">
                                                    Only PDF files are supported • Max 10MB
                                                </p>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="group-hover:bg-primary/5 transition-colors"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        document.getElementById('file-input')?.click();
                                                    }}
                                                >
                                                    Browse Files
                                                </Button>
                                                <input
                                                    id="file-input"
                                                    type="file"
                                                    accept="application/pdf"
                                                    className="hidden"
                                                    onChange={handleFileChange}
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center overflow-hidden mr-2">
                                                    <div className="rounded-full bg-primary/10 p-2.5 mr-3 flex-shrink-0">
                                                        <FileText className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="overflow-hidden">
                                                        <p className="font-medium text-sm truncate" title={data.completion_file.name}>{data.completion_file.name}</p> {/* Renamed key */}
                                                        <p className="text-xs text-muted-foreground">
                                                            {(data.completion_file.size / (1024 * 1024)).toFixed(2)} MB • PDF {/* Renamed key */}
                                                        </p>
                                                        {data.completion_file.size > 10 * 1024 * 1024 && ( // Renamed key
                                                            <p className="text-xs text-destructive mt-1">
                                                                File exceeds 10MB limit
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors flex-shrink-0 h-7 w-7"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setData('completion_file', null); // Renamed key
                                                    }}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                    {errors.completion_file && <InputError message={errors.completion_file} />} {/* Renamed key */}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Completion Details Card */}
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <CalendarIcon className="h-5 w-5 text-primary" />
                                    Completion Details {/* Updated title */}
                                </CardTitle>
                                <CardDescription>
                                    Provide information about the Completion {/* Updated text */}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <label className="flex items-center text-base font-medium">
                                        <CalendarIcon className="h-4 w-4 mr-2" />
                                        Completion Date {/* Updated label */}
                                    </label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="w-full justify-start text-left font-normal"
                                            >
                                                <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                                                {data.completion_date_object ? format(data.completion_date_object, 'PPP') : <span>Pick a date</span>} {/* Renamed key */}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={data.completion_date_object} // Renamed key
                                                onSelect={(date) => {
                                                    if (date) {
                                                        setData('completion_date_object', date); // Renamed key
                                                        setData('completion_date', format(date, 'yyyy-MM-dd')); // Renamed key
                                                    }
                                                }}
                                                initialFocus
                                                className="rounded-md border shadow-md"
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    {errors.completion_date && <InputError message={errors.completion_date} />} {/* Renamed key */}
                                </div>

                                <div className="space-y-2">
                                    <label className="flex items-center text-base font-medium">
                                        <FileText className="h-4 w-4 mr-2" />
                                        Notes {/* Updated label */}
                                    </label>
                                    <Textarea
                                        placeholder="Enter any additional notes" // Updated placeholder
                                        rows={3}
                                        className="min-h-[120px] resize-none"
                                        value={data.completion_notes} // Renamed key
                                        onChange={(e) => setData('completion_notes', e.target.value)} // Renamed key
                                    />
                                    {errors.completion_notes && <InputError message={errors.completion_notes} />} {/* Renamed key */}
                                </div>
                            </CardContent>

                            <CardFooter className="pt-4 border-t flex flex-col gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing || !data.completion_file || (data.completion_file && data.completion_file.size > 10 * 1024 * 1024)} // Renamed key
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
                                            Submit Completion {/* Updated text */}
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

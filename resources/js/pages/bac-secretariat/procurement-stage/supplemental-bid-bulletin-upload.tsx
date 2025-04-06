import { Head, useForm } from '@inertiajs/react';
import { FileUp, FileText, X, ClipboardList, CalendarIcon, Upload } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface SupplementalBidBulletinUploadProps {
    procurement: {
        id: string;
        title: string;
    };
    errors?: {
        bulletin_file?: string;
        bulletin_number?: string;
        bulletin_title?: string;
        issue_date?: string;
    };
}

export default function SupplementalBidBulletinUpload({ procurement, errors = {} }: SupplementalBidBulletinUploadProps) {
    const [isDraggingFile, setIsDraggingFile] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        procurement_id: procurement.id,
        procurement_title: procurement.title,
        bulletin_file: null as File | null,
        bulletin_number: '',
        bulletin_title: '',
        issue_date: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Supplemental Bid Bulletin - ${procurement.id}`, href: '#' },
    ];

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/bac-secretariat/upload-supplemental-bid-bulletin-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                reset('bulletin_file');
                toast.success('Supplemental Bid Bulletin uploaded successfully!', {
                    description: 'The bulletin has been submitted.',
                });
            },
            onError: (errors) => {
                toast.error('Failed to upload Supplemental Bid Bulletin', {
                    description: Object.values(errors)[0] as string,
                });
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            setData('bulletin_file', file);
        }
    };

    const handleFileDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDraggingFile(true);
    };

    const handleFileDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDraggingFile(false);
    };

    const handleFileDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleFileDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDraggingFile(false);

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0];
            if (file.type === 'application/pdf') {
                setData('bulletin_file', file);
            } else {
                toast.error('Invalid file type', {
                    description: 'Please upload a PDF file',
                });
            }
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Supplemental Bid Bulletin" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-primary">
                        <ClipboardList className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Supplemental Bid Bulletin</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the supplemental bid bulletin for procurement
                        <span className="font-medium text-foreground"> #{procurement.id}</span>:
                        <span className="font-medium text-foreground italic"> {procurement.title}</span>
                    </p>
                </div>

                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <ClipboardList className="h-5 w-5 text-primary" />
                                    Required Document
                                </CardTitle>
                                <CardDescription>
                                    Please upload the supplemental bid bulletin in PDF format
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-8">
                                <div className="space-y-2">
                                    <label className="flex items-center text-base font-medium">
                                        <ClipboardList className="h-4 w-4 mr-2" />
                                        Bulletin File
                                    </label>
                                    <div
                                        className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingFile
                                                ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                                                : data.bulletin_file
                                                    ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                                    : errors.bulletin_file
                                                        ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                                            } cursor-pointer group`}
                                        onDragEnter={handleFileDragEnter}
                                        onDragLeave={handleFileDragLeave}
                                        onDragOver={handleFileDragOver}
                                        onDrop={handleFileDrop}
                                        onClick={() => document.getElementById('file-input')?.click()}
                                    >
                                        {!data.bulletin_file ? (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                                    <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                                </div>
                                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                                    Drag and drop your bulletin file here
                                                </p>
                                                <p className="text-sm text-muted-foreground/70 mb-5">
                                                    Only PDF files are supported
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
                                                <div className="flex items-center">
                                                    <div className="rounded-full bg-primary/10 p-3 mr-4">
                                                        <FileText className="h-6 w-6 text-primary" />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium">{data.bulletin_file.name}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {(data.bulletin_file.size / 1024).toFixed(2)} KB • PDF
                                                        </p>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setData('bulletin_file', null);
                                                    }}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                    {errors.bulletin_file && (
                                        <InputError message={errors.bulletin_file} />
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                            <CardHeader className="pb-4 space-y-1">
                                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                                    <CalendarIcon className="h-5 w-5 text-primary" />
                                    Bulletin Details
                                </CardTitle>
                                <CardDescription>
                                    Provide information about the bulletin
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <label htmlFor="bulletin_number" className="text-sm font-medium">
                                        Bulletin Number
                                    </label>
                                    <Input
                                        id="bulletin_number"
                                        value={data.bulletin_number}
                                        onChange={(e) => setData('bulletin_number', e.target.value)}
                                        placeholder="Enter bulletin number"
                                    />
                                    {errors.bulletin_number && (
                                        <InputError message={errors.bulletin_number} />
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="bulletin_title" className="text-sm font-medium">
                                        Bulletin Title
                                    </label>
                                    <Input
                                        id="bulletin_title"
                                        value={data.bulletin_title}
                                        onChange={(e) => setData('bulletin_title', e.target.value)}
                                        placeholder="Enter bulletin title"
                                    />
                                    {errors.bulletin_title && (
                                        <InputError message={errors.bulletin_title} />
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="issue_date" className="text-sm font-medium">
                                        Issue Date
                                    </label>
                                    <Input
                                        id="issue_date"
                                        type="date"
                                        value={data.issue_date}
                                        onChange={(e) => setData('issue_date', e.target.value)}
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.issue_date && (
                                        <InputError message={errors.issue_date} />
                                    )}
                                </div>
                            </CardContent>

                            <CardFooter className="pt-4 border-t flex flex-col gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
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
                                            Submit Bulletin
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
            </div>
        </AppLayout>
    );
}
import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMultiFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertCircle, Clock, FileText, PhilippinePeso, Plus, Trash2, Upload, User } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';

interface BidSubmissionUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
    errors?: Record<string, string>;
}

// Helper to get nested error keys in a type-safe way
function getBidderError<T extends object>(errors: T, index: number, field: 'file' | 'bidder_name' | 'bid_value'): string | undefined {
    return errors && typeof errors === 'object' ? (errors as Record<string, string>)[`bidders.${index}.${field}`] : undefined;
}

export default function BidSubmissionUpload({ procurement = { id: '', title: '' } }: BidSubmissionUploadProps) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement?.id || '',
        procurement_title: procurement?.title || '',
        opening_date: new Date(),
        bidders: [{ file: null, bidder_name: '', bid_value: '' }] as { file: File | null; bidder_name: string; bid_value: string }[],
    });

    // File validation
    const ALLOWED_FILE_TYPES = ['application/pdf'];
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
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

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Bid Opening - ${procurement.id}: ${procurement.title}`, href: '#' },
    ]);

    const addBidder = () => {
        setData('bidders', [...(data.bidders || []), { file: null, bidder_name: '', bid_value: '' }]);
    };

    const removeBidder = (index: number) => {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders.splice(index, 1);
        setData('bidders', updatedBidders);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (file && validateFile(file)) {
                const updatedBidders = [...(data.bidders || [])];
                updatedBidders[index].file = file as File | null;
                setData('bidders', updatedBidders);
            }
        }
    };

    const handleFileRemove = (index: number) => {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].file = null;
        setData('bidders', updatedBidders);
    };

    const handleBidderNameChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].bidder_name = e.target.value;
        setData('bidders', updatedBidders);
    };

    const handleBidValueChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].bid_value = e.target.value;
        setData('bidders', updatedBidders);
    };

    const handleOpeningDateChange = (date: Date | undefined) => {
        setData('opening_date', date || new Date());
    };

    const fileDropHooks = useMultiFileDrop(data.bidders, validateFile, (index, file) => {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].file = file as File | null;
        setData('bidders', updatedBidders);
    });

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.opening_date) {
            toast.error('Missing opening date', {
                description: 'Please select the opening date.',
            });
            return;
        }
        if (!data.bidders || data.bidders.length === 0) {
            toast.error('Missing bidders', {
                description: 'Please add at least one bidder.',
            });
            return;
        }
        for (let i = 0; i < data.bidders.length; i++) {
            const bidder = data.bidders[i];
            if (!bidder.file) {
                toast.error(`Missing file for bidder #${i + 1}`, {
                    description: 'Please upload the bid document.',
                });
                return;
            }
            if (!bidder.bidder_name.trim()) {
                toast.error(`Missing bidder name for bidder #${i + 1}`, {
                    description: 'Please enter the bidder name.',
                });
                return;
            }
            if (!bidder.bid_value || isNaN(Number(bidder.bid_value))) {
                toast.error(`Missing or invalid bid value for bidder #${i + 1}`, {
                    description: 'Please enter a valid bid value.',
                });
                return;
            }
        }

        transform((formData) => ({
            ...formData,
            opening_date: formData.opening_date
                ? typeof formData.opening_date === 'string'
                    ? formData.opening_date
                    : formData.opening_date instanceof Date
                      ? format(formData.opening_date, 'yyyy-MM-dd')
                      : ''
                : '',
        }));

        post('/bac-secretariat/upload-bid-opening-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Bid submissions uploaded successfully!', {
                    description: 'Bid submission documents have been recorded.',
                });
                reset();
                clearErrors();
            },
            onError: () => {
                toast.error('Failed to upload bid submissions', {
                    description: 'Please check the form for errors and try again.',
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Bid Submissions" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-6 rounded-xl bg-linear-to-b p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <FileText className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Bid Opening</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the bid opening documents for procurement
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
                                    Bid Documents
                                </CardTitle>
                                <CardDescription>Please upload the bid documents for each bidder in PDF format</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-8">
                                {data.bidders?.map((bidder, index) => (
                                    <div key={index} className="space-y-6 border-b pb-6 last:border-b-0 last:pb-0">
                                        <div className="mb-2 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <FileText className="text-primary h-5 w-5" />
                                                <span className="font-semibold">Bidder #{index + 1}</span>
                                            </div>
                                            {index > 0 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="hover:bg-destructive/10 hover:text-destructive rounded-full transition-colors"
                                                    onClick={() => removeBidder(index)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                        <FileUploadArea
                                            label="Bid Document"
                                            file={bidder.file}
                                            error={getBidderError(errors, index, 'file')}
                                            isDragging={fileDropHooks[index].isDragging}
                                            onFileChange={(e) => handleFileChange(e, index)}
                                            onDragEnter={fileDropHooks[index].handleDragEnter}
                                            onDragLeave={fileDropHooks[index].handleDragLeave}
                                            onDragOver={fileDropHooks[index].handleDragOver}
                                            onDrop={fileDropHooks[index].handleDrop}
                                            onRemove={() => handleFileRemove(index)}
                                            inputId={`file-input-${index}`}
                                            required={true}
                                        />
                                        {getBidderError(errors, index, 'file') && <InputError message={getBidderError(errors, index, 'file')} />}
                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label className="flex items-center text-base font-medium">
                                                    <User className="mr-2 h-4 w-4" />
                                                    Bidder Name
                                                </Label>
                                                <Input
                                                    placeholder="Enter company or bidder name"
                                                    value={bidder.bidder_name}
                                                    onChange={(e) => handleBidderNameChange(e, index)}
                                                />
                                                {getBidderError(errors, index, 'bidder_name') && (
                                                    <InputError message={getBidderError(errors, index, 'bidder_name')} />
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="flex items-center text-base font-medium">
                                                    <PhilippinePeso className="mr-2 h-4 w-4" />
                                                    Bid Value
                                                </Label>
                                                <Input
                                                    placeholder="Enter bid amount"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={bidder.bid_value}
                                                    onChange={(e) => handleBidValueChange(e, index)}
                                                />
                                                {getBidderError(errors, index, 'bid_value') && (
                                                    <InputError message={getBidderError(errors, index, 'bid_value')} />
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <Button type="button" variant="outline" className="w-full" onClick={addBidder}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Another Bidder
                                </Button>
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <Clock className="text-primary h-5 w-5" />
                                    Bid Opening Details
                                </CardTitle>
                                <CardDescription>Set the date when the bids were opened</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <DatePicker
                                        label="Opening Date"
                                        value={data.opening_date instanceof Date ? data.opening_date : new Date(data.opening_date)}
                                        onChange={handleOpeningDateChange}
                                        error={errors.opening_date}
                                        required={true}
                                    />
                                    {errors.opening_date && <InputError message={errors.opening_date} />}
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
                                            Submit Bid Documents
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

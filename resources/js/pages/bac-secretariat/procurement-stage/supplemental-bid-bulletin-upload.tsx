import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { ClipboardList, Upload } from 'lucide-react';
import { toast } from 'sonner';

import DatePicker from '@/components/date-picker';
import FileUploadArea from '@/components/file-upload-area';
import { InputWithLabel } from '@/components/input-with-label';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileDrop } from '@/hooks/use-file-drop';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';

// Allowed file types and max file size for uploads
const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface SupplementalBidBulletinUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
}

export default function SupplementalBidBulletinUpload({ procurement = { id: '', title: '' } }: SupplementalBidBulletinUploadProps) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        procurement_id: procurement.id,
        procurement_title: procurement.title,
        bulletin_file: null as File | null,
        bulletin_number: '',
        bulletin_title: '',
        issue_date: new Date(),
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Supplemental Bid Bulletin - ${procurement.id}: ${procurement.title}`, href: '#' },
    ]);

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.bulletin_file) {
            toast.error('Missing file', {
                description: 'Please upload the bulletin file.',
            });
            return;
        }
        if (!data.bulletin_number.trim()) {
            toast.error('Missing bulletin number', {
                description: 'Please enter the bulletin number.',
            });
            return;
        }
        if (!data.bulletin_title.trim()) {
            toast.error('Missing bulletin title', {
                description: 'Please enter the bulletin title.',
            });
            return;
        }
        if (!data.issue_date) {
            toast.error('Missing issue date', {
                description: 'Please select the issue date.',
            });
            return;
        }

        transform((formData) => ({
            ...formData,
            issue_date: formData.issue_date ? format(formData.issue_date, 'yyyy-MM-dd') : '',
        }));

        post('/bac-secretariat/upload-supplemental-bid-bulletin-documents', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                clearErrors();
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

    // Use custom hook for file drop
    const fileDrop = useFileDrop({
        validateFile,
        setFile: (file) => setData('bulletin_file', file),
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            if (validateFile(file)) {
                setData('bulletin_file', file);
            }
        }
    };

    const handleDateSelect = (date: Date | undefined) => {
        if (date) {
            setData('issue_date', date);
        } else {
            setData('issue_date', new Date());
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Supplemental Bid Bulletin" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-3 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <ClipboardList className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Supplemental Bid Bulletin</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl text-sm sm:text-base">
                        Upload the supplemental bid bulletin for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <ClipboardList className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Required Document
                                </CardTitle>
                                <CardDescription className="text-sm">Please upload the supplemental bid bulletin in PDF format</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col justify-center space-y-6 py-0 sm:space-y-8">
                                <FileUploadArea
                                    label="Bulletin File"
                                    file={data.bulletin_file}
                                    error={errors.bulletin_file}
                                    isDragging={fileDrop.isDragging}
                                    onFileChange={handleFileChange}
                                    onDragEnter={fileDrop.handleDragEnter}
                                    onDragLeave={fileDrop.handleDragLeave}
                                    onDragOver={fileDrop.handleDragOver}
                                    onDrop={fileDrop.handleDrop}
                                    onRemove={() => setData('bulletin_file', null)}
                                    inputId="file-input"
                                    required={true}
                                />
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <ClipboardList className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Bulletin Details
                                </CardTitle>
                                <CardDescription className="text-sm">Provide information about the bulletin</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 sm:space-y-6">
                                <div className="space-y-2">
                                    <InputWithLabel
                                        id="bulletin_number"
                                        label="Bulletin Number"
                                        value={data.bulletin_number}
                                        onChange={(e) => setData('bulletin_number', e.target.value)}
                                        placeholder="Enter bulletin number"
                                        className="h-10"
                                        required
                                        error={errors.bulletin_number}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <InputWithLabel
                                        id="bulletin_title"
                                        label="Bulletin Title"
                                        value={data.bulletin_title}
                                        onChange={(e) => setData('bulletin_title', e.target.value)}
                                        placeholder="Enter bulletin title"
                                        className="h-10"
                                        required
                                        error={errors.bulletin_title}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <DatePicker
                                        label="Issue Date"
                                        value={data.issue_date instanceof Date ? data.issue_date : new Date(data.issue_date)}
                                        onChange={handleDateSelect}
                                        error={errors.issue_date}
                                        required
                                    />
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button type="submit" disabled={processing} className="flex h-10 w-full items-center gap-2 sm:h-11">
                                    {processing ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
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
                                    className="h-10 w-full"
                                >
                                    Cancel
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </form>
                s
            </div>
        </AppLayout>
    );
}

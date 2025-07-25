import { Head, useForm } from '@inertiajs/react';
import { ClipboardList, Upload } from 'lucide-react';
import { toast } from 'sonner';
import { format } from 'date-fns';
import { useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { InputWithLabel } from '@/components/input-with-label';
import { BreadcrumbItem } from '@/types';
import DatePicker from '@/components/date-picker';
import SmartContractFileUploadArea from '@/components/smart-contract-file-upload-area';
import SmartContractDashboard from '@/components/smart-contract-dashboard';
import { useFileDrop } from '@/hooks/use-file-drop';
import { SmartContractValidationResult } from '@/types/smart-contracts';

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

    // Smart contract validation state - used in onValidationComplete callback
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [bulletinValidation, setBulletinValidation] = useState<SmartContractValidationResult | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
        { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
        { title: `Upload Supplemental Bid Bulletin - ${procurement.id}: ${procurement.title}`, href: '#' },
    ];

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
            <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 rounded-xl p-3 sm:p-6 bg-gradient-to-b from-background to-muted/20">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-primary">
                        <ClipboardList className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl sm:text-2xl font-bold">Supplemental Bid Bulletin</h1>
                    </div>
                    <p className="text-sm sm:text-base text-muted-foreground max-w-3xl">
                        Upload the supplemental bid bulletin for procurement
                        <span className="font-medium text-foreground"> #{procurement.id}</span>:
                        <span className="font-medium text-foreground italic"> {procurement.title}</span>
                    </p>
                </div>
                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="pb-2 sm:pb-4 space-y-1">
                                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                                    <ClipboardList className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                                    Required Document
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Please upload the supplemental bid bulletin in PDF format
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-0 flex flex-col justify-center space-y-6 sm:space-y-8">
                                <SmartContractFileUploadArea
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
                                    documentType="Bidding Documents"
                                    stage="Supplemental Bid Bulletin"
                                    procurementId={procurement.id}
                                    enableSmartValidation={true}
                                    showValidationDetails={true}
                                    onValidationComplete={(result) => {
                                        setBulletinValidation(result);
                                        if (!result.compliant) {
                                            toast.error('Document validation failed', {
                                                description: 'Please review the validation details and fix any issues.'
                                            });
                                        } else {
                                            toast.success('Document validation passed', {
                                                description: 'All validation checks passed successfully.'
                                            });
                                        }
                                    }}
                                />
                            </CardContent>
                        </Card>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                            <CardHeader className="pb-2 sm:pb-4 space-y-1">
                                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                                    <ClipboardList className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                                    Bulletin Details
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Provide information about the bulletin
                                </CardDescription>
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

                            <CardFooter className="pt-4 border-t flex flex-col gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full flex items-center gap-2 h-10 sm:h-11"
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

                {/* Smart Contract Dashboard */}
                <SmartContractDashboard procurementId={procurement.id} />
            </div>
        </AppLayout>
    );
}

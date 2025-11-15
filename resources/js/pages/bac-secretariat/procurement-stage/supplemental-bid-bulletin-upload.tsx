import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useProgressiveUpload } from '@/hooks/use-progressive-upload';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, FileText } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { DocumentChecklistCard } from '@/components/procurement/document-checklist-card';
import type { DocumentGuide } from '@/types/document-guide';
import { markStageComplete } from '@/actions/App/Http/Controllers/Procurement/ProcurementController';

interface SupplementalBidBulletinUploadProps {
    procurement: {
        id: string;
        title: string;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function SupplementalBidBulletinUpload({ procurement, documentGuide, uploadedDocuments = [] }: SupplementalBidBulletinUploadProps) {
    // Progressive upload hook
    const { isUploading, handleDocumentUpload } = useProgressiveUpload({
        procurementId: procurement?.id || '',
        stage: 'supplemental_bid_bulletin',
        phase: 'procurement',
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Supplemental/Bid Bulletin - ${procurement?.id || 'Unknown ID'}${procurement?.title ? ': ' + procurement.title : ''}`, href: '#' },
    ]);

    const handleMarkComplete = () => {
        router.post(
            markStageComplete({ pr_number: procurement.id, stage: 'supplemental_bid_bulletin' }).url,
            {},
            {
                onSuccess: () => {
                    toast.success('Stage marked as complete!', {
                        description: 'All required documents have been uploaded.',
                    });
                },
                onError: () => {
                    toast.error('Failed to mark stage as complete', {
                        description: 'Please try again or contact support.',
                    });
                },
            },
        );
    };

    const allRequiredUploaded = documentGuide && uploadedDocuments.length >= documentGuide.counts.required_count;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Supplemental/Bid Bulletin" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <FileText className="h-5 w-5 sm:h-6 sm:w-6" />
                        <h1 className="text-xl font-bold sm:text-2xl">Supplemental/Bid Bulletin</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl text-sm sm:text-base">
                        Upload the Supplemental/Bid Bulletin for procurement
                        <span className="text-foreground font-medium"> #{procurement?.id || 'Unknown'}</span>
                        {procurement?.title && (
                            <>
                                :<span className="text-foreground font-medium italic"> {procurement.title}</span>
                            </>
                        )}
                    </p>
                </div>

                <div className="space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                        {/* Document Checklist - Progressive Upload */}
                        {documentGuide && (
                            <DocumentChecklistCard
                                documentGuide={documentGuide}
                                uploadedDocuments={uploadedDocuments}
                                canUpload={!isUploading}
                                onUploadClick={handleDocumentUpload}
                                className="lg:order-last"
                            />
                        )}

                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                    <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                    Progressive Document Upload
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    Use the checklist on the right to upload required documents one at a time. Once all required documents are uploaded, you can mark this stage as complete.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-6 sm:space-y-8">
                                <div className="rounded-lg border border-muted bg-muted/30 p-4">
                                    <h3 className="text-sm font-medium mb-2">How to Upload Documents:</h3>
                                    <ol className="text-sm text-muted-foreground space-y-1 list-decimal list-inside">
                                        <li>Click the "Upload" button next to each document in the checklist</li>
                                        <li>Select the PDF file from your computer (max 10MB)</li>
                                        <li>Documents are automatically saved to the blockchain</li>
                                        <li>Track your progress in real-time</li>
                                    </ol>
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col gap-3 border-t pt-4">
                                <Button
                                    type="button"
                                    disabled={!allRequiredUploaded || isUploading}
                                    onClick={handleMarkComplete}
                                    className="flex h-10 w-full items-center gap-2 text-sm sm:h-11 sm:text-base"
                                >
                                    {isUploading ? (
                                        <div className="flex items-center gap-2">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                            Uploading...
                                        </div>
                                    ) : (
                                        <>
                                            <CheckCircle2 className="h-4 w-4" />
                                            Mark Stage as Complete
                                        </>
                                    )}
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    disabled={isUploading}
                                    className="h-10 w-full text-sm sm:text-base"
                                >
                                    Back to Procurements
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}


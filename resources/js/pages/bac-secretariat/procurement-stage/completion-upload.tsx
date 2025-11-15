import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useProgressiveUpload } from '@/hooks/use-progressive-upload';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle2, CheckCircle } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';
import { getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { DocumentChecklistCard } from '@/components/procurement/document-checklist-card';
import type { DocumentGuide } from '@/types/document-guide';

interface CompletionUploadProps {
    procurement?: {
        id: string;
        title: string;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function CompletionUpload({ procurement = { id: '', title: '' }, documentGuide, uploadedDocuments = [] }: CompletionUploadProps) {
    const { isUploading, handleDocumentUpload } = useProgressiveUpload({
        procurementId: procurement?.id || '',
        stage: 'completed',
        phase: 'post-procurement',
    });

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        { title: `Upload Certificate of Completion - ${procurement.id}`, href: '#' },
    ]);

    const handleMarkComplete = () => {
        toast.success('Stage marked as complete!', {
            description: 'All required documents have been uploaded.',
        });
    };

    const allRequiredUploaded = documentGuide && uploadedDocuments.length >= documentGuide.counts.required_count;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Upload Certificate of Completion" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-6 rounded-xl bg-linear-to-b p-6">
                <div className="flex flex-col gap-2">
                    <div className="text-primary flex items-center gap-2">
                        <CheckCircle className="h-6 w-6" />
                        <h1 className="text-2xl font-bold">Certificate of Completion</h1>
                    </div>
                    <p className="text-muted-foreground max-w-3xl">
                        Upload the Certificate of Completion document for procurement
                        <span className="text-foreground font-medium"> #{procurement.id}</span>:
                        <span className="text-foreground font-medium italic"> {procurement.title}</span>
                    </p>
                </div>

                <div className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
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
                            <CardHeader className="space-y-1 pb-4">
                                <CardTitle className="flex items-center gap-2 text-xl font-semibold">
                                    <CheckCircle className="text-primary h-5 w-5" />
                                    Progressive Document Upload
                                </CardTitle>
                                <CardDescription>
                                    Use the checklist on the right to upload required documents one at a time. Once all required documents are uploaded, you can mark this stage as complete.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-8">
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
                                    className="flex h-11 w-full items-center gap-2"
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
                                    className="h-10 w-full"
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

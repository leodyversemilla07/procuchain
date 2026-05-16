import { AlertCircle, ArrowLeft, Clock, Edit, FileText, RefreshCw } from 'lucide-react';
import { useCallback, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { Document, Event, SharedData, TimelineItem } from '@/types';
import { getProcurementDetailBreadcrumbs } from '@/utils/breadcrumbs';
import { Head, usePage } from '@inertiajs/react';

import { ProcurementCorrectionsTab } from '../../components/show-procurement/corrections-tab';
import { DetailsTab } from '../../components/show-procurement/details-tab';
import { DocumentsTab } from '../../components/show-procurement/documents-tab';
import { ProcurementHeader } from '../../components/show-procurement/procurement-header';
import { TimelineTab } from '../../components/show-procurement/timeline-tab';

interface ProcurementDetails {
    pr_number: string;
    app_reference?: string;
    title: string;
    description: string;
    abc_amount: number;
    abc_amount_formatted: string;
    funding_source: string;
    category: string;
    category_label: string;
    procurement_mode: string;
    procurement_mode_label: string;
    office: string;
    end_user?: string;
    // Delivery details are optional - populated at Contract Implementation stage per NGPA
    delivery_location?: string;
    delivery_date?: string;
    delivery_date_formatted?: string;
    delivery_term_days?: number;
    prepared_by?: string;
    bac_resolution_number?: string;
    bac_resolution_date?: string;
    bac_resolution_date_formatted?: string;
    philgeps_reference?: string;
    philgeps_posting_date?: string;
    philgeps_posting_date_formatted?: string;
    approved_by?: string;
    approval_date?: string;
    approval_date_formatted?: string;
    created_at: string;
    created_at_formatted: string;
}

interface ProcurementStatus {
    stage: string;
    stage_formatted: string;
    stage_description?: string;
    stage_order: number;
    current_status: string;
    status_formatted?: string;
    timestamp: string;
    formatted_date: string;
    formatted_date_only: string;
    pr_number?: string;
    procurement_title?: string;
    user_address?: string;
    progress: number;
    total_stages: number;
    phase: string;
    phase_display_name: string;
}

interface Procurement {
    id: string;
    title: string;
    status: ProcurementStatus;
    documents: Document[];
    events: Event[];
    timeline?: TimelineItem[];
    details?: ProcurementDetails;
    has_corrections?: boolean;
    latest_correction?: {
        timestamp: string;
        corrected_by: string;
        reason: string;
        changed_fields: string[];
    };
}

interface WorkflowInfo {
    mode: string;
    name: string;
    stages: {
        value: string;
        display_name: string;
        url: string;
        is_completed: boolean;
        is_current: boolean;
        is_optional: boolean;
    }[];
}

interface ShowProps {
    procurement: Procurement;
    workflow?: WorkflowInfo;
    now?: string;
    error?: string;
}

// ============================================================================
// MAIN COMPONENT
// ============================================================================

export default function ShowProcurement({ procurement, workflow, error }: ShowProps) {
    const [currentError] = useState<string | null>(error || null);
    const [isLoading] = useState<boolean>(false);

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.role || auth?.user?.role || 'guest';
    const breadcrumbs = getProcurementDetailBreadcrumbs(userRole, procurement?.title);

    const totalDocuments = procurement?.documents?.length ?? 0;

    const handleRetry = useCallback(() => {
        window.location.reload();
    }, []);

    const handleGoBack = useCallback(() => {
        window.history.back();
    }, []);

    // Loading State
    if (isLoading || (!procurement && !currentError)) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head>
                    <title>Loading Procurement...</title>
                </Head>
                <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                    {/* Header Skeleton */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                        <CardContent className="space-y-4 p-4 sm:p-6">
                            <div className="flex items-center gap-2">
                                <Skeleton className="h-5 w-5 sm:h-6 sm:w-6" />
                                <Skeleton className="h-7 w-64 sm:h-8" />
                            </div>
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-2 w-full rounded-full" />
                            <div className="flex gap-4">
                                <Skeleton className="h-16 w-32" />
                                <Skeleton className="h-16 w-32" />
                                <Skeleton className="h-16 w-32" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tabs Skeleton */}
                    <div className="space-y-4">
                        <div className="flex gap-2">
                            <Skeleton className="h-10 w-24" />
                            <Skeleton className="h-10 w-24" />
                            <Skeleton className="h-10 w-24" />
                            <Skeleton className="h-10 w-24" />
                        </div>
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                            <CardHeader className="p-4 sm:p-6">
                                <Skeleton className="h-6 w-48" />
                            </CardHeader>
                            <CardContent className="space-y-4 p-4 pt-0 sm:p-6 sm:pt-0">
                                <Skeleton className="h-24 w-full" />
                                <Skeleton className="h-24 w-full" />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Error State
    if (currentError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head>
                    <title>Error Loading Procurement</title>
                </Head>
                <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                    <div className="flex min-h-[60vh] items-center justify-center">
                        <Card className="border-destructive/50 bg-destructive/5 w-full max-w-md shadow-md">
                            <CardHeader className="text-center">
                                <div className="bg-destructive/10 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                    <AlertCircle className="text-destructive h-8 w-8" aria-hidden="true" />
                                </div>
                                <CardTitle className="text-destructive text-xl">Unable to Load Procurement</CardTitle>
                                <CardDescription className="text-destructive/80 mt-2">{currentError}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 sm:flex-row">
                                <Button variant="outline" className="flex-1" onClick={handleGoBack} aria-label="Go back to previous page">
                                    <ArrowLeft className="mr-2 h-4 w-4" aria-hidden="true" />
                                    Go Back
                                </Button>
                                <Button variant="default" className="flex-1" onClick={handleRetry} aria-label="Retry loading procurement">
                                    <RefreshCw className="mr-2 h-4 w-4" aria-hidden="true" />
                                    Retry
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // No Procurement State
    if (!procurement) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head>
                    <title>Procurement Not Found</title>
                </Head>
                <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText className="text-muted-foreground" />
                            </EmptyMedia>
                            <EmptyTitle>Procurement Not Found</EmptyTitle>
                            <EmptyDescription>The procurement you're looking for doesn't exist or has been removed.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button onClick={handleGoBack} variant="outline">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Go Back
                            </Button>
                        </EmptyContent>
                    </Empty>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head>
                <title>{procurement?.title ? `${procurement.title} | Procurement Details` : 'Procurement Details'}</title>
            </Head>

            {/* Skip to content link for accessibility */}
            <a
                href="#main-content"
                className="focus:bg-primary focus:text-primary-foreground focus:ring-ring sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-4 focus:rounded-md focus:p-3 focus:ring-2 focus:ring-offset-2 focus:outline-none"
            >
                Skip to content
            </a>

            <div
                id="main-content"
                className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6"
            >
                {/* Procurement Header */}
                <ProcurementHeader
                    title={procurement.title}
                    pr_number={procurement.id}
                    status={procurement.status}
                    procurementMode={procurement.details?.procurement_mode}
                    procurementModeLabel={procurement.details?.procurement_mode_label}
                    workflow={workflow}
                />

                <Tabs defaultValue="details" className="w-full">
                    <TabsList className="w-full sm:w-auto">
                        <TabsTrigger value="details" aria-label="Details tab">
                            <FileText className="size-4" aria-hidden="true" />
                            <span>Details</span>
                        </TabsTrigger>
                        <TabsTrigger value="documents" aria-label={`Documents tab, ${totalDocuments} documents`}>
                            <FileText className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Documents</span>
                            <span className="sm:hidden">Docs</span>
                            <Badge variant="secondary" className="ml-1 text-[10px] sm:ml-2 sm:text-xs">
                                {totalDocuments}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="corrections" aria-label="Corrections tab">
                            <Edit className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Corrections</span>
                            <span className="sm:hidden">Fixes</span>
                            {procurement.has_corrections && (
                                <Badge variant="secondary" className="ml-1 text-[10px] sm:ml-2 sm:text-xs">
                                    ✓
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="timeline" aria-label="Timeline tab">
                            <Clock className="size-4" aria-hidden="true" />
                            <span>Timeline</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Details Tab */}
                    <TabsContent value="details" className="mt-4 sm:mt-6">
                        {procurement.details ? (
                            <DetailsTab details={procurement.details} />
                        ) : (
                            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                                <CardContent className="p-6">
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <AlertCircle className="text-muted-foreground" />
                                            </EmptyMedia>
                                            <EmptyTitle>No Details Available</EmptyTitle>
                                            <EmptyDescription>Procurement details are not available at this time.</EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Documents Tab */}
                    <TabsContent value="documents" className="mt-4 sm:mt-6">
                        <DocumentsTab documents={procurement.documents} />
                    </TabsContent>

                    {/* Corrections Tab */}
                    <TabsContent value="corrections" className="mt-4 sm:mt-6">
                        <ProcurementCorrectionsTab
                            prNumber={procurement.id}
                            hasCorrections={procurement.has_corrections || false}
                            latestCorrection={procurement.latest_correction}
                            procurement={
                                procurement.details
                                    ? {
                                          title: procurement.details.title,
                                          description: procurement.details.description,
                                          abc_amount: procurement.details.abc_amount,
                                          formatted_abc_amount: procurement.details.abc_amount_formatted,
                                          funding_source: procurement.details.funding_source,
                                          category: procurement.details.category,
                                          procurement_mode: procurement.details.procurement_mode,
                                          office: procurement.details.office,
                                          end_user: procurement.details.end_user || '',
                                          bac_resolution_number: procurement.details.bac_resolution_number || '',
                                          bac_resolution_date: procurement.details.bac_resolution_date || '',
                                          philgeps_reference: procurement.details.philgeps_reference || '',
                                          philgeps_posting_date: procurement.details.philgeps_posting_date || '',
                                          approved_by: procurement.details.approved_by || '',
                                          approval_date: procurement.details.approval_date || '',
                                      }
                                    : undefined
                            }
                        />
                    </TabsContent>

                    {/* Timeline Tab */}
                    <TabsContent value="timeline" className="mt-4 sm:mt-6">
                        <TimelineTab timeline={procurement.timeline} events={procurement.events} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

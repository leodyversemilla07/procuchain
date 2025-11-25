import { AlertCircle, ArrowLeft, Clock, Edit, FileText, RefreshCw } from 'lucide-react';
import { useCallback, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { Document, Event, SharedData, TimelineItem } from '@/types';
import { getProcurementDetailBreadcrumbs } from '@/utils/breadcrumbs';
import { Head, usePage } from '@inertiajs/react';

import { ProcurementCorrectionsTab } from '../../components/show-procurement/corrections-tab';
import { DocumentsTab } from '../../components/show-procurement/documents-tab';
import { ProcurementHeader } from '../../components/show-procurement/procurement-header';
import { TimelineTab } from '../../components/show-procurement/timeline-tab';

interface ProcurementDetails {
    pr_number: string;
    ppmp_reference?: string;
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
    purpose: string;
    delivery_location: string;
    delivery_date: string;
    delivery_date_formatted: string;
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

interface ShowProps {
    procurement: Procurement;
    now?: string;
    error?: string;
}

// ============================================================================
// MAIN COMPONENT
// ============================================================================

export default function ShowProcurement({ procurement, error }: ShowProps) {
    const [currentError] = useState<string | null>(error || null);
    const [isLoading] = useState<boolean>(false);

    const { auth } = usePage<SharedData>().props;
    // Extract role from roles array (roles[0]) instead of user.role
    const userRole = auth?.roles?.[0] || auth?.user?.role || 'guest';
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
                <div className="animate-pulse space-y-6 p-4 md:p-6 lg:p-8">
                    {/* Header Skeleton */}
                    <Card className="border">
                        <CardHeader className="space-y-4 pb-4">
                            <div className="bg-muted h-8 w-3/4 rounded-md"></div>
                            <div className="bg-muted h-4 w-1/4 rounded-md"></div>
                            <div className="bg-muted h-2 w-full rounded-full"></div>
                            <div className="flex gap-4">
                                <div className="bg-muted h-16 w-32 rounded-md"></div>
                                <div className="bg-muted h-16 w-32 rounded-md"></div>
                                <div className="bg-muted h-16 w-32 rounded-md"></div>
                            </div>
                        </CardHeader>
                    </Card>

                    {/* Tabs Skeleton */}
                    <div className="space-y-4">
                        <div className="flex gap-2">
                            <div className="bg-muted h-10 w-32 rounded-md"></div>
                            <div className="bg-muted h-10 w-32 rounded-md"></div>
                        </div>
                        <Card className="border">
                            <CardHeader>
                                <div className="bg-muted h-6 w-48 rounded-md"></div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-muted h-24 w-full rounded-md"></div>
                                <div className="bg-muted h-24 w-full rounded-md"></div>
                                <div className="bg-muted h-24 w-full rounded-md"></div>
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
                <div className="p-4 sm:p-6">
                    <div className="flex min-h-[60vh] items-center justify-center p-4">
                        <Card className="border-destructive/50 bg-destructive/5 w-full max-w-md">
                            <CardHeader className="text-center">
                                <div className="bg-destructive/10 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                    <AlertCircle className="text-destructive h-8 w-8" aria-hidden="true" />
                                </div>
                                <CardTitle className="text-destructive text-xl">Unable to Load Procurement</CardTitle>
                                <CardDescription className="text-destructive/80 mt-2">{currentError}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 sm:flex-row">
                                {handleGoBack && (
                                    <Button variant="outline" className="flex-1" onClick={handleGoBack} aria-label="Go back to previous page">
                                        <ArrowLeft className="mr-2 h-4 w-4" aria-hidden="true" />
                                        Go Back
                                    </Button>
                                )}
                                {handleRetry && (
                                    <Button variant="default" className="flex-1" onClick={handleRetry} aria-label="Retry loading procurement">
                                        <RefreshCw className="mr-2 h-4 w-4" aria-hidden="true" />
                                        Retry
                                    </Button>
                                )}
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
                <div className="p-4 sm:p-6">
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

            <div id="main-content" className="flex h-full flex-1 flex-col space-y-4 p-3 sm:space-y-6 sm:p-4 md:p-6 lg:p-8">
                {/* Procurement Header */}
                <ProcurementHeader title={procurement.title} pr_number={procurement.id} status={procurement.status} />

                <Tabs defaultValue="details" className="w-full">
                    <TabsList className="grid w-full grid-cols-4 gap-1 sm:gap-0 lg:inline-grid lg:w-auto">
                        <TabsTrigger
                            value="details"
                            className="gap-1.5 text-xs transition-all duration-200 sm:gap-2 sm:text-sm"
                            aria-label="Details tab"
                        >
                            <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span>Details</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="documents"
                            className="gap-1.5 text-xs transition-all duration-200 sm:gap-2 sm:text-sm"
                            aria-label={`Documents tab, ${totalDocuments} documents`}
                        >
                            <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Documents</span>
                            <span className="sm:hidden">Docs</span>
                            <Badge variant="secondary" className="ml-1 text-xs transition-all duration-200 sm:ml-2">
                                {totalDocuments}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger
                            value="corrections"
                            className="gap-1.5 text-xs transition-all duration-200 sm:gap-2 sm:text-sm"
                            aria-label="Corrections tab"
                        >
                            <Edit className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span>Corrections</span>
                            {procurement.has_corrections && (
                                <Badge variant="secondary" className="ml-1 text-xs transition-all duration-200 sm:ml-2">
                                    ✓
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="timeline"
                            className="gap-1.5 text-xs transition-all duration-200 sm:gap-2 sm:text-sm"
                            aria-label="Timeline tab"
                        >
                            <Clock className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span>Timeline</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Details Tab */}
                    <TabsContent value="details" className="mt-3 sm:mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Procurement Information</CardTitle>
                                <CardDescription>Complete details from procurement initiation</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {procurement.details ? (
                                    <>
                                        {/* Basic Information */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Basic Information</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">PR Number</label>
                                                    <p className="mt-1 text-sm">{procurement.details.pr_number}</p>
                                                </div>
                                                {procurement.details.ppmp_reference && (
                                                    <div>
                                                        <label className="text-muted-foreground text-sm font-medium">PPMP Reference</label>
                                                        <p className="mt-1 text-sm">{procurement.details.ppmp_reference}</p>
                                                    </div>
                                                )}
                                                <div className="sm:col-span-2">
                                                    <label className="text-muted-foreground text-sm font-medium">Title</label>
                                                    <p className="mt-1 text-sm">{procurement.details.title}</p>
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <label className="text-muted-foreground text-sm font-medium">Description</label>
                                                    <p className="mt-1 text-sm">{procurement.details.description}</p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Financial Information */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Financial Information</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">ABC Amount</label>
                                                    <p className="text-primary mt-1 text-sm font-semibold">
                                                        {procurement.details.abc_amount_formatted}
                                                    </p>
                                                </div>
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Funding Source</label>
                                                    <p className="mt-1 text-sm">{procurement.details.funding_source}</p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Classification */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Classification</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Category</label>
                                                    <p className="mt-1 text-sm">{procurement.details.category_label}</p>
                                                </div>
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Procurement Mode</label>
                                                    <p className="mt-1 text-sm">{procurement.details.procurement_mode_label}</p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Office and Purpose */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Office & Purpose</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Office</label>
                                                    <p className="mt-1 text-sm">{procurement.details.office}</p>
                                                </div>
                                                {procurement.details.end_user && (
                                                    <div>
                                                        <label className="text-muted-foreground text-sm font-medium">End User</label>
                                                        <p className="mt-1 text-sm">{procurement.details.end_user}</p>
                                                    </div>
                                                )}
                                                <div className="sm:col-span-2">
                                                    <label className="text-muted-foreground text-sm font-medium">Purpose</label>
                                                    <p className="mt-1 text-sm">{procurement.details.purpose}</p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Delivery Details */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Delivery Details</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Delivery Location</label>
                                                    <p className="mt-1 text-sm">{procurement.details.delivery_location}</p>
                                                </div>
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Delivery Date</label>
                                                    <p className="mt-1 text-sm">{procurement.details.delivery_date_formatted}</p>
                                                </div>
                                                {procurement.details.delivery_term_days && (
                                                    <div>
                                                        <label className="text-muted-foreground text-sm font-medium">Delivery Term</label>
                                                        <p className="mt-1 text-sm">{procurement.details.delivery_term_days} days</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Additional Information */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-semibold">Additional Information</h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                {procurement.details.prepared_by && (
                                                    <div>
                                                        <label className="text-muted-foreground text-sm font-medium">Prepared By</label>
                                                        <p className="mt-1 text-sm">{procurement.details.prepared_by}</p>
                                                    </div>
                                                )}
                                                <div>
                                                    <label className="text-muted-foreground text-sm font-medium">Created At</label>
                                                    <p className="mt-1 text-sm">{procurement.details.created_at_formatted}</p>
                                                </div>
                                                {procurement.details.bac_resolution_number && (
                                                    <>
                                                        <div>
                                                            <label className="text-muted-foreground text-sm font-medium">BAC Resolution Number</label>
                                                            <p className="mt-1 text-sm">{procurement.details.bac_resolution_number}</p>
                                                        </div>
                                                        {procurement.details.bac_resolution_date_formatted && (
                                                            <div>
                                                                <label className="text-muted-foreground text-sm font-medium">
                                                                    BAC Resolution Date
                                                                </label>
                                                                <p className="mt-1 text-sm">{procurement.details.bac_resolution_date_formatted}</p>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                                {procurement.details.philgeps_reference && (
                                                    <>
                                                        <div>
                                                            <label className="text-muted-foreground text-sm font-medium">PhilGEPS Reference</label>
                                                            <p className="mt-1 text-sm">{procurement.details.philgeps_reference}</p>
                                                        </div>
                                                        {procurement.details.philgeps_posting_date_formatted && (
                                                            <div>
                                                                <label className="text-muted-foreground text-sm font-medium">
                                                                    PhilGEPS Posting Date
                                                                </label>
                                                                <p className="mt-1 text-sm">{procurement.details.philgeps_posting_date_formatted}</p>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                                {procurement.details.approved_by && (
                                                    <>
                                                        <div>
                                                            <label className="text-muted-foreground text-sm font-medium">Approved By</label>
                                                            <p className="mt-1 text-sm">{procurement.details.approved_by}</p>
                                                        </div>
                                                        {procurement.details.approval_date_formatted && (
                                                            <div>
                                                                <label className="text-muted-foreground text-sm font-medium">Approval Date</label>
                                                                <p className="mt-1 text-sm">{procurement.details.approval_date_formatted}</p>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <AlertCircle className="text-muted-foreground" />
                                            </EmptyMedia>
                                            <EmptyTitle>No Details Available</EmptyTitle>
                                            <EmptyDescription>Procurement details are not available at this time.</EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Documents Tab */}
                    <TabsContent value="documents" className="mt-3 sm:mt-6">
                        <DocumentsTab documents={procurement.documents} />
                    </TabsContent>

                    {/* Corrections Tab */}
                    <TabsContent value="corrections" className="mt-3 sm:mt-6">
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
                                          purpose: procurement.details.purpose,
                                          delivery_location: procurement.details.delivery_location,
                                          delivery_date: procurement.details.delivery_date,
                                          delivery_term_days: procurement.details.delivery_term_days || 0,
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
                    <TabsContent value="timeline" className="mt-3 sm:mt-6">
                        <TimelineTab timeline={procurement.timeline} events={procurement.events} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

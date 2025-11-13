import { AlertCircle, ArrowLeft, Clock, FileText, RefreshCw } from 'lucide-react';
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

import { DocumentsTab } from '../../components/show-procurement/documents-tab';
import { ProcurementHeader } from '../../components/show-procurement/procurement-header';
import { TimelineTab } from '../../components/show-procurement/timeline-tab';

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
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    progress: number;
    total_stages: number;
}

interface Procurement {
    id: string;
    title: string;
    status: ProcurementStatus;
    documents: Document[];
    events: Event[];
    timeline?: TimelineItem[];
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
    const userRole = auth?.user?.role || 'guest';
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
                <Head><title>Loading Procurement...</title></Head>
                <div className="animate-pulse space-y-6 p-4 md:p-6 lg:p-8">
                    {/* Header Skeleton */}
                    <Card className="border">
                        <CardHeader className="space-y-4 pb-4">
                            <div className="h-8 w-3/4 rounded-md bg-muted"></div>
                            <div className="h-4 w-1/4 rounded-md bg-muted"></div>
                            <div className="h-2 w-full rounded-full bg-muted"></div>
                            <div className="flex gap-4">
                                <div className="h-16 w-32 rounded-md bg-muted"></div>
                                <div className="h-16 w-32 rounded-md bg-muted"></div>
                                <div className="h-16 w-32 rounded-md bg-muted"></div>
                            </div>
                        </CardHeader>
                    </Card>

                    {/* Tabs Skeleton */}
                    <div className="space-y-4">
                        <div className="flex gap-2">
                            <div className="h-10 w-32 rounded-md bg-muted"></div>
                            <div className="h-10 w-32 rounded-md bg-muted"></div>
                        </div>
                        <Card className="border">
                            <CardHeader>
                                <div className="h-6 w-48 rounded-md bg-muted"></div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="h-24 w-full rounded-md bg-muted"></div>
                                <div className="h-24 w-full rounded-md bg-muted"></div>
                                <div className="h-24 w-full rounded-md bg-muted"></div>
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
                <Head><title>Error Loading Procurement</title></Head>
                <div className="p-4 sm:p-6">
                    <div className="flex min-h-[60vh] items-center justify-center p-4">
                        <Card className="w-full max-w-md border-destructive/50 bg-destructive/5">
                            <CardHeader className="text-center">
                                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                                    <AlertCircle className="h-8 w-8 text-destructive" aria-hidden="true" />
                                </div>
                                <CardTitle className="text-xl text-destructive">Unable to Load Procurement</CardTitle>
                                <CardDescription className="mt-2 text-destructive/80">{currentError}</CardDescription>
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
                <Head><title>Procurement Not Found</title></Head>
                <div className="p-4 sm:p-6">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText className="text-muted-foreground" />
                            </EmptyMedia>
                            <EmptyTitle>Procurement Not Found</EmptyTitle>
                            <EmptyDescription>
                                The procurement you're looking for doesn't exist or has been removed.
                            </EmptyDescription>
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
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-4 focus:rounded-md focus:bg-primary focus:p-3 focus:text-primary-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            >
                Skip to content
            </a>

            <div id="main-content" className="flex h-full flex-1 flex-col space-y-4 p-3 sm:space-y-6 sm:p-4 md:p-6 lg:p-8">
                {/* Procurement Header */}
                <ProcurementHeader 
                    title={procurement.title}
                    procurementId={procurement.id}
                    status={procurement.status}
                    userRole={userRole}
                />

                <Tabs defaultValue="documents" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 gap-1 sm:gap-0 lg:w-auto lg:inline-grid">
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
                            value="timeline"
                            className="gap-1.5 text-xs transition-all duration-200 sm:gap-2 sm:text-sm"
                            aria-label="Timeline tab"
                        >
                            <Clock className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span>Timeline</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Documents Tab */}
                    <TabsContent value="documents" className="mt-3 sm:mt-6">
                        <DocumentsTab documents={procurement.documents} />
                    </TabsContent>

                    {/* Timeline Tab */}
                    <TabsContent value="timeline" className="mt-3 sm:mt-6">
                        <TimelineTab 
                            timeline={procurement.timeline}
                            events={procurement.events}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

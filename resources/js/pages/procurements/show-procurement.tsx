import {
    AlertCircle,
    ArrowLeft,
    Building,
    Calendar,
    CheckCircle,
    Clock,
    Download,
    Eye,
    FileCheck,
    FileText,
    HardDrive,
    Hash,
    Lock,
    PoundSterlingIcon as PhilippinePeso,
    RefreshCw,
    TrendingUp,
    UserRound,
    Users,
    XCircle,
} from 'lucide-react';
import { useCallback, useMemo, useState, type FC, type JSX } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import files from '@/routes/files';
import pdf from '@/routes/pdf';
import type {
    Document,
    Event,
    SharedData,
    StageMetadata,
    TimelineItem,
} from '@/types';
import { STAGE_ORDER } from '@/types';
import { getProcurementDetailBreadcrumbs } from '@/utils/breadcrumbs';
import { Head, Link, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

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

type ProcessedTimelineItem = {
    timestamp: string;
    formatted_date: string;
    formatted_date_only: string;
    formatted_time_only: string;
    type: 'stage_change' | 'event';
    stageOrder: number;
    content: JSX.Element;
    stage?: string;
    stage_formatted?: string;
};

interface ShowProps {
    procurement: Procurement;
    now?: string;
    error?: string;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

const shortenHash = (hash?: string, startLength = 5, endLength = 5): string => {
    if (!hash) return 'N/A';
    if (hash.length <= startLength + endLength) return hash;
    return `${hash.substring(0, startLength)}...${hash.substring(hash.length - endLength)}`;
};

interface DocumentItemProps {
    doc: Document;
}

// ============================================================================
// SUB-COMPONENTS
// ============================================================================

const DocumentItem: FC<DocumentItemProps> = ({ doc }) => {
    const handleCopyHash = useCallback(async () => {
        if (!doc.hash) return;
        try {
            await navigator.clipboard.writeText(doc.hash);
            toast.success('Hash copied to clipboard');
        } catch (error) {
            toast.error('Failed to copy hash: ' + String(error));
        }
    }, [doc.hash]);

    return (
        <li className="group border-b p-4 transition-all duration-200 last:border-b-0 hover:bg-muted/30">
            <div className="flex flex-col gap-4">
                {/* Document Header */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg border p-2 transition-all duration-200 group-hover:border-primary/30 group-hover:bg-primary/5">
                            <FileText className="text-destructive h-6 w-6" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <h4
                                className="mb-2 font-medium transition-colors duration-200 group-hover:text-primary"
                                title={doc.document_type_formatted || doc.document_type}
                            >
                                {doc.document_type_formatted || doc.document_type || 'Unnamed Document'}
                            </h4>
                            <div className="text-muted-foreground flex flex-wrap gap-4 text-sm">
                                <span className="flex items-center gap-1" aria-label={`File key: ${doc.file_key || 'N/A'}`}>
                                    <Hash className="h-4 w-4 shrink-0" aria-hidden="true" />
                                    {/* Mobile: Shortened file key */}
                                    <span className="truncate md:hidden">
                                        {shortenHash(doc.file_key, 6, 4)}
                                    </span>
                                    {/* Desktop: Full file key */}
                                    <span className="hidden break-all md:inline">
                                        {doc.file_key || 'N/A'}
                                    </span>
                                </span>
                                {doc.file_size_formatted && (
                                    <span className="flex items-center gap-1" aria-label={`File size: ${doc.file_size_formatted}`}>
                                        <HardDrive className="h-4 w-4" aria-hidden="true" />
                                        {doc.file_size_formatted}
                                    </span>
                                )}
                                {doc.formatted_date_only && (
                                    <span className="flex items-center gap-1" aria-label={`Upload date: ${doc.formatted_date_only}`}>
                                        <Calendar className="h-4 w-4" aria-hidden="true" />
                                        {doc.formatted_date_only}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex shrink-0 gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="h-8 text-xs font-medium shadow-sm transition-all duration-200 hover:border-primary hover:bg-primary/5 hover:shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:h-9 sm:text-sm"
                        >
                            <Link
                                href={pdf.viewer.url({ fileKey: encodeURIComponent(doc.file_key) })}
                                className="flex items-center"
                                aria-label={`View ${doc.document_type || 'document'} with analytics`}
                            >
                                <TrendingUp className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">View with Analytics</span>
                                <span className="sm:hidden">Analytics</span>
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="h-8 text-xs font-medium shadow-sm transition-all duration-200 hover:border-primary hover:bg-primary/5 hover:shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:h-9 sm:text-sm"
                        >
                            <a
                                href={files.download.url({ fileKey: encodeURIComponent(doc.file_key) })}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center"
                                aria-label={`Quick view ${doc.document_type || 'document'}`}
                            >
                                <Eye className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">Quick View</span>
                                <span className="sm:hidden">View</span>
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Hash Section */}
                <div className="rounded-lg border bg-muted/30 p-3 transition-all duration-200 group-hover:border-primary/30">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex min-w-0 flex-1 items-center gap-2">
                            <Lock className="text-muted-foreground h-4 w-4 shrink-0" aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <div className="text-muted-foreground mb-1 text-xs font-medium">Document Hash</div>
                                {/* Mobile: Shortened hash */}
                                <code
                                    className="block truncate font-mono text-sm md:hidden"
                                    title={doc.hash}
                                    aria-label={`Full hash: ${doc.hash}`}
                                >
                                    {shortenHash(doc.hash)}
                                </code>
                                {/* Desktop: Full hash with word break */}
                                <code
                                    className="hidden break-all font-mono text-sm md:block"
                                    title={doc.hash}
                                    aria-label={`Full hash: ${doc.hash}`}
                                >
                                    {doc.hash || 'N/A'}
                                </code>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleCopyHash}
                            className="shrink-0 transition-all duration-200 hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            aria-label="Copy hash to clipboard"
                        >
                            <Download className="mr-1.5 h-3.5 w-3.5" aria-hidden="true" />
                            Copy
                        </Button>
                    </div>
                </div>

                {/* Metadata Section */}
                {doc.stage_metadata && (() => {
                    const metadata = doc.stage_metadata;
                    
                    if (!metadata || Object.values(metadata).every((v) => !v)) {
                        return null;
                    }

                    const metadataMap: Array<{
                        key: keyof StageMetadata;
                        label: string;
                        icon: JSX.Element;
                        useFormatted?: boolean;
                    }> = [
                        { key: 'pr_number', label: 'PR Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'pr_purpose', label: 'PR Purpose', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'requested_by', label: 'Requested By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'approved_by', label: 'Approved By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'appropriation',
                            label: 'Appropriation',
                            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'funding_source', label: 'Funding Source', icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'meeting_date',
                            label: 'Meeting Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'participants', label: 'Participants', icon: <Users className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'submission_date',
                            label: 'Submission Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'issuance_date',
                            label: 'Issuance Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'opening_date',
                            label: 'Opening Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'bidder_name', label: 'Bidder Name', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'bid_value',
                            label: 'Bid Value',
                            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'evaluation_date',
                            label: 'Evaluation Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'evaluator_names', label: 'Evaluator Names', icon: <Users className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'outcome',
                            label: 'Verification Outcome',
                            icon:
                                metadata?.outcome === 'Verified' ? (
                                    <CheckCircle className="text-primary h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                ) : (
                                    <XCircle className="text-destructive h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                ),
                        },
                        { key: 'signatory_details', label: 'Signatory Details', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'bond_amount',
                            label: 'Bond Amount',
                            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'signing_date',
                            label: 'Signing Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'report_date',
                            label: 'Report Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'report_notes', label: 'Report Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'municipal_offices', label: 'Municipal Offices', icon: <Building className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'bulletin_number', label: 'Bulletin Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        { key: 'bulletin_title', label: 'Bulletin Title', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                        {
                            key: 'issue_date',
                            label: 'Issue Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        {
                            key: 'completion_date',
                            label: 'Completion Date',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                            useFormatted: true,
                        },
                        { key: 'completion_notes', label: 'Completion Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
                    ];

                    const renderMetadataItem = (
                        key: keyof StageMetadata,
                        item: { label: string; icon: JSX.Element; useFormatted?: boolean },
                    ) => {
                        if (key === 'validity_period' && metadata.validity_period) {
                            const startFormatted = metadata.validity_period.start_date_formatted || 'Invalid Date';
                            const endFormatted = metadata.validity_period.end_date_formatted || 'Invalid Date';

                            return (
                                <div key={key} className="col-span-2">
                                    <div className="group flex items-start rounded-md p-2 transition-colors duration-200 ease-in-out hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <div className="text-primary bg-primary/10 mt-0.5 mr-2 shrink-0 rounded-md p-1.5 sm:mr-2.5">
                                            <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <span className="text-xs font-medium tracking-wide text-neutral-700 uppercase dark:text-neutral-300">
                                                Validity Period
                                            </span>
                                            <div className="mt-1 leading-relaxed font-medium wrap-break-word text-neutral-800 dark:text-neutral-200">
                                                <div className="line-clamp-2 transition-all duration-200 ease-in-out group-hover:line-clamp-none">
                                                    {`${startFormatted} - ${endFormatted}`}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        }

                        if ((key === 'bidder_name' || key === 'bid_value') && metadata.document_type === 'Bid Document') {
                            const formattedKey = `${key}_formatted` as keyof StageMetadata;
                            const value = (item.useFormatted && metadata[formattedKey]) ? metadata[formattedKey] : metadata[key];

                            if (!value || String(value).trim() === '') {
                                return null;
                            }

                            return (
                                <div key={`${key}-${metadata[key]}`} className="flex items-start gap-3 border-b p-3 last:border-b-0 bg-primary/5 border-primary/20">
                                    <div className="text-muted-foreground mt-0.5">{item.icon}</div>
                                    <div className="min-w-0 flex-1">
                                        <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{item.label}</div>
                                        <div className="text-sm wrap-break-word text-primary font-semibold">{value as string}</div>
                                    </div>
                                </div>
                            );
                        }

                        if (metadata[key]) {
                            const formattedKey = `${key}_formatted` as keyof StageMetadata;
                            const value = (item.useFormatted && metadata[formattedKey]) ? metadata[formattedKey] : metadata[key];

                            if (!value || String(value).trim() === '') {
                                return null;
                            }

                            return (
                                <div key={key} className="flex items-start gap-3 border-b p-3 last:border-b-0">
                                    <div className="text-muted-foreground mt-0.5">{item.icon}</div>
                                    <div className="min-w-0 flex-1">
                                        <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{item.label}</div>
                                        <div className="text-sm wrap-break-word text-foreground">{value as string}</div>
                                    </div>
                                </div>
                            );
                        }

                        return null;
                    };

                    return (
                        <Card className="bg-card border-border shadow-sm transition-all duration-200 hover:shadow">
                            <CardHeader className="p-3 pb-1.5 sm:p-4 sm:pb-2">
                                <CardTitle className="text-foreground flex items-center text-xs font-semibold sm:text-sm">
                                    <FileCheck className="text-primary mr-1.5 h-3.5 w-3.5 sm:mr-2 sm:h-4 sm:w-4" />
                                    Document Metadata
                                    {metadata.document_type === 'Bid Document' && (
                                        <Badge variant="outline" className="ml-2">
                                            Bid Document
                                        </Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-3 pt-0 sm:p-4 md:p-5">
                                <div className="grid grid-cols-1 gap-x-4 gap-y-3">
                                    {metadata.document_type === 'Bid Document' && metadata.opening_date && (metadata.opening_date_formatted && metadata.opening_date_formatted.trim() !== '') && (
                                        <div className="flex items-start gap-3 border-b p-3 last:border-b-0 bg-primary/5 border-primary/20">
                                            <div className="text-muted-foreground mt-0.5">
                                                <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">Opening Date</div>
                                                <div className="text-sm wrap-break-word text-primary font-semibold">{metadata.opening_date_formatted || 'Invalid Date'}</div>
                                            </div>
                                        </div>
                                    )}

                                    {metadataMap.map((item) => renderMetadataItem(item.key, item))}

                                    {metadata.validity_period &&
                                        renderMetadataItem('validity_period', {
                                            label: 'Validity Period',
                                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                                        })}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })()}
            </div>
        </li>
    );
};

// ============================================================================
// CUSTOM HOOKS
// ============================================================================

const useDocumentsByStage = (documents?: Document[]) => {
    return useMemo(() => {
        if (!documents) return {};

        const grouped = documents.reduce((acc: Record<string, Document[]>, doc) => {
            const stage = doc.stage_formatted || doc.stage || 'Procurement Initiation';
            if (!acc[stage]) {
                acc[stage] = [];
            }
            acc[stage].push(doc);
            return acc;
        }, {});

        Object.keys(grouped).forEach((stage) => {
            if (stage === 'Bid Opening' || stage === 'Performance Bond, Contract and PO') {
                grouped[stage] = grouped[stage].sort(
                    (a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0),
                );
            } else {
                const uniqueDocs = new Map<string, Document>();

                grouped[stage]
                    .sort((a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0))
                    .forEach((doc) => {
                        const key = doc.document_type || doc.file_key;

                        if (!uniqueDocs.has(key)) {
                            uniqueDocs.set(key, doc);
                        }
                    });

                grouped[stage] = Array.from(uniqueDocs.values()).sort(
                    (a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0),
                );
            }
        });

        return grouped;
    }, [documents]);
};

const useSortedStageKeys = (documentsByStage: Record<string, Document[]>) => {
    return useMemo(() => {
        const stageKeys = Object.keys(documentsByStage);
        return stageKeys.sort((a, b) => {
            const aIndex = STAGE_ORDER.indexOf(a as typeof STAGE_ORDER[number]);
            const bIndex = STAGE_ORDER.indexOf(b as typeof STAGE_ORDER[number]);

            if (aIndex === -1 && bIndex === -1) return a.localeCompare(b);
            if (aIndex === -1) return 1;
            if (bIndex === -1) return -1;
            return bIndex - aIndex; // Reversed to show latest stages first
        });
    }, [documentsByStage]);
};

const useTimelineItems = (timeline?: TimelineItem[], events?: Event[]) => {
    return useMemo(() => {
        const combinedItems: Array<Omit<ProcessedTimelineItem, 'content' | 'stageOrder'> & { raw: TimelineItem | Event }> = [];

        (timeline ?? []).forEach((item) => {
            combinedItems.push({
                timestamp: item.timestamp,
                formatted_date: item.formatted_date,
                formatted_date_only: item.formatted_date_only || '',
                formatted_time_only: item.formatted_time_only || '',
                type: 'stage_change',
                stage: item.stage,
                raw: item,
            });
        });
        (events ?? []).forEach((event) => {
            combinedItems.push({
                timestamp: event.timestamp,
                formatted_date: event.formatted_date || '',
                formatted_date_only: event.formatted_date_only || '',
                formatted_time_only: event.formatted_time_only || '',
                type: 'event',
                stage: event.stage,
                raw: event,
            });
        });

        // Sort timeline items with latest first (descending order)
        combinedItems.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

        const itemsByDate: Record<string, ProcessedTimelineItem[]> = {};

        combinedItems.forEach((item) => {
            const date = item.formatted_date_only;
            const stageIndex = item.raw.stage_order ?? 999;

            let itemContent: JSX.Element;
            if (item.type === 'stage_change') {
                const stageItem = item.raw as TimelineItem;
                itemContent = (
                    <div className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <div className="bg-primary text-primary-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                                {stageIndex !== 999 ? stageIndex + 1 : '?'}
                            </div>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                                <time dateTime={stageItem.timestamp}>{stageItem.formatted_time_only}</time>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium">Stage Transition</h3>
                                    <Badge variant="secondary" className="text-xs">
                                        {stageItem.stage_formatted || stageItem.stage}
                                    </Badge>
                                </div>
                                <div className="bg-muted rounded-lg border p-3">
                                    <div className="mb-1 flex items-center justify-between">
                                        <span className="text-sm font-medium">
                                            Status: {stageItem.status_formatted || stageItem.status}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Procurement moved to <strong>{stageItem.stage_formatted || stageItem.stage}</strong> stage
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            } else {
                const eventItem = item.raw as Event;
                let eventDetails: string | JSX.Element = eventItem.details;

                if (eventItem.document_count && eventItem.document_count > 0) {
                    eventDetails = (
                        <>
                            {eventItem.details}
                            <div className="bg-muted border-border text-muted-foreground mt-2 inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                                <FileText className="mr-1.5 h-3.5 w-3.5" />
                                {eventItem.document_count} {eventItem.document_count === 1 ? 'document' : 'documents'} processed
                            </div>
                        </>
                    );
                }

                itemContent = (
                    <div className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <div className="bg-muted flex h-8 w-8 shrink-0 items-center justify-center rounded-full border">
                                <FileText className="text-muted-foreground h-4 w-4" />
                            </div>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                                <time dateTime={eventItem.timestamp}>{eventItem.formatted_time_only}</time>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium capitalize">{eventItem.event_type.replace(/_/g, ' ')}</h3>
                                    {eventItem.stage && (
                                        <Badge variant="outline" className="text-xs">
                                            {eventItem.stage_formatted || eventItem.stage}
                                        </Badge>
                                    )}
                                    {eventItem.category && (
                                        <Badge variant="secondary" className="text-xs capitalize">
                                            {eventItem.category}
                                        </Badge>
                                    )}
                                </div>
                                <div className="bg-muted rounded-lg border p-3">
                                    <div className="text-muted-foreground text-sm">{eventDetails}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            }

            if (!itemsByDate[date]) {
                itemsByDate[date] = [];
            }

            itemsByDate[date].push({
                ...item,
                stageOrder: stageIndex,
                content: itemContent,
            });
        });

        return itemsByDate;
    }, [timeline, events]);
};

// ============================================================================
// MAIN COMPONENT
// ============================================================================

export default function ShowProcurement({ procurement: initialProcurement, error }: ShowProps) {
    const [procurement] = useState<Procurement | null>(initialProcurement);
    const [currentError] = useState<string | null>(error || null);
    const [isLoading] = useState<boolean>(false);

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const breadcrumbs = getProcurementDetailBreadcrumbs(userRole, procurement?.title);

    const documentsByStage = useDocumentsByStage(procurement?.documents);
    const sortedStageKeys = useSortedStageKeys(documentsByStage);
    const totalDocuments = useMemo(() => procurement?.documents?.length ?? 0, [procurement?.documents]);
    const timelineItemsByDate = useTimelineItems(procurement?.timeline, procurement?.events);

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

            <div id="main-content" className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Procurement Header */}
                <Card className="mb-6 overflow-hidden border shadow-sm transition-shadow duration-200 hover:shadow-md">
                    {/* Header Accent Bar */}
                    <div className="h-1.5 w-full bg-primary" aria-hidden="true"></div>

                    <CardHeader className="space-y-6 pb-6">
                        {/* Title and ID Section */}
                        <div className="space-y-2">
                            <CardTitle className="text-2xl font-bold tracking-tight sm:text-3xl">{procurement.title}</CardTitle>
                            <CardDescription className="flex items-center gap-2 text-base">
                                <Hash className="h-4 w-4" aria-hidden="true" />
                                <span className="font-mono">Procurement ID: {procurement.id}</span>
                            </CardDescription>
                        </div>

                        {/* Progress Bar */}
                        {(() => {
                            const stageIndex = procurement.status?.stage ? (STAGE_ORDER.indexOf(procurement.status.stage as typeof STAGE_ORDER[number]) + 1) : 0;
                            const totalStages = STAGE_ORDER.length;
                            const progress = stageIndex > 0 ? (stageIndex / totalStages) * 100 : 0;

                            return progress > 0 ? (
                                <div
                                    className="w-full space-y-2"
                                    role="progressbar"
                                    aria-valuenow={progress}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label={`Procurement progress: ${progress.toFixed(0)}%`}
                                >
                                    <div className="text-muted-foreground flex justify-between text-sm font-medium">
                                        <span>Overall Progress</span>
                                        <span className="font-semibold">{progress.toFixed(0)}%</span>
                                    </div>
                                    <div className="relative h-3 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="absolute inset-y-0 left-0 rounded-full bg-primary shadow-sm transition-all duration-500 ease-out"
                                            style={{ width: `${progress}%` }}
                                        >
                                            <div className="absolute inset-0 animate-pulse bg-white/20"></div>
                                        </div>
                                    </div>
                                </div>
                            ) : null;
                        })()}

                        {/* Status Information Grid */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Current Stage */}
                            {procurement.status?.stage && (() => {
                                const stageIndex = STAGE_ORDER.indexOf(procurement.status.stage as typeof STAGE_ORDER[number]) + 1;
                                const totalStages = STAGE_ORDER.length;

                                return (
                                    <div className="rounded-lg border bg-muted p-4 transition-all duration-200 hover:shadow-sm">
                                        <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                            <FileCheck className="h-4 w-4" aria-hidden="true" />
                                            Current Stage
                                        </div>
                                        <Badge variant="secondary" className="mb-2 text-sm font-medium">
                                            {procurement.status.stage_formatted || procurement.status.stage}
                                        </Badge>
                                        {procurement.status.stage_description && (
                                            <p className="text-xs italic text-muted-foreground line-clamp-2">
                                                {procurement.status.stage_description}
                                            </p>
                                        )}
                                        <div className="mt-2 text-xs text-muted-foreground">
                                            Stage {stageIndex} of {totalStages}
                                        </div>
                                    </div>
                                );
                            })()}

                            {/* Current Status */}
                            {procurement.status?.status_formatted && (
                                <div className="rounded-lg border bg-muted p-4 transition-all duration-200 hover:shadow-sm">
                                    <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                        <Clock className="h-4 w-4" aria-hidden="true" />
                                        Status
                                    </div>
                                    <Badge
                                        variant="default"
                                        className="inline-flex w-fit items-center gap-1.5 text-sm font-medium"
                                    >
                                        {procurement.status.status_formatted}
                                    </Badge>
                                </div>
                            )}

                            {/* Last Updated */}
                            {procurement.status?.timestamp && (
                                <div className="rounded-lg border bg-muted p-4 transition-all duration-200 hover:shadow-sm sm:col-span-2 lg:col-span-1">
                                    <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                        <Calendar className="h-4 w-4" aria-hidden="true" />
                                        Last Updated
                                    </div>
                                    <time
                                        dateTime={procurement.status.timestamp}
                                        className="block text-sm font-medium"
                                    >
                                        {procurement.status.formatted_date}
                                    </time>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                </Card>

                <Tabs defaultValue="documents" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 lg:w-auto lg:inline-grid">
                        <TabsTrigger
                            value="documents"
                            className="gap-2 transition-all duration-200"
                            aria-label={`Documents tab, ${totalDocuments} documents`}
                        >
                            <FileText className="h-4 w-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Documents</span>
                            <span className="sm:hidden">Docs</span>
                            <Badge variant="secondary" className="ml-2 transition-all duration-200">
                                {totalDocuments}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger
                            value="timeline"
                            className="gap-2 transition-all duration-200"
                            aria-label="Timeline tab"
                        >
                            <Clock className="h-4 w-4" aria-hidden="true" />
                            <span>Timeline</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Documents Tab */}
                    <TabsContent value="documents" className="mt-6">
                        <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
                            {totalDocuments === 0 ? (
                                <CardContent className="p-0">
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <FileText className="text-muted-foreground" />
                                            </EmptyMedia>
                                            <EmptyTitle>No Documents Yet</EmptyTitle>
                                            <EmptyDescription>
                                                Documents will appear here once they are uploaded to this procurement.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </CardContent>
                            ) : (
                                <>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                    <FileText className="h-5 w-5 text-primary" aria-hidden="true" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Procurement Documents</CardTitle>
                                                    <CardDescription className="text-sm">
                                                        Documents organized by procurement stage
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant="outline" className="hidden font-medium sm:inline-flex">
                                                {totalDocuments} {totalDocuments === 1 ? 'Document' : 'Documents'}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <div role="list" aria-label="Documents by stage">
                                            {sortedStageKeys.map((stage, stageIndex) => {
                                                const isLatestStage = stageIndex === 0;
                                                const stageDocuments = documentsByStage[stage];

                                                return (
                                                    <section
                                                        key={stage}
                                                        className="border-b last:border-b-0"
                                                        aria-labelledby={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                                                    >
                                                        <div className="sticky top-0 z-10 border-b bg-muted/80 p-4 backdrop-blur-sm">
                                                            <div className="flex items-center justify-between">
                                                                <h3
                                                                    id={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                                                                    className="flex items-center gap-2 text-base font-semibold"
                                                                >
                                                                    <FileCheck className="h-4 w-4 text-primary" aria-hidden="true" />
                                                                    {stage}
                                                                    <Badge variant="outline" className="ml-1 text-xs">
                                                                        {stageDocuments.length}
                                                                    </Badge>
                                                                    {isLatestStage && (
                                                                        <Badge variant="default" className="text-xs">
                                                                            Latest Stage
                                                                        </Badge>
                                                                    )}
                                                                </h3>
                                                            </div>
                                                        </div>
                                                        <ul role="list">
                                                            {stageDocuments.map((doc, docIndex) => (
                                                                <DocumentItem key={`${doc.file_key}-${docIndex}`} doc={doc} />
                                                            ))}
                                                        </ul>
                                                    </section>
                                                );
                                            })}
                                        </div>
                                    </CardContent>
                                </>
                            )}
                        </Card>
                    </TabsContent>

                    {/* Timeline Tab */}
                    <TabsContent value="timeline" className="mt-6">
                        <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
                            {Object.keys(timelineItemsByDate).length === 0 ? (
                                <CardContent className="p-0">
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Clock className="text-muted-foreground" />
                                            </EmptyMedia>
                                            <EmptyTitle>No Timeline Events</EmptyTitle>
                                            <EmptyDescription>
                                                Timeline events will appear here as the procurement progresses.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </CardContent>
                            ) : (
                                <>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                    <Clock className="h-5 w-5 text-primary" aria-hidden="true" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Event Timeline</CardTitle>
                                                    <CardDescription className="text-sm">
                                                        Chronological history of procurement events
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant="outline" className="hidden font-medium sm:inline-flex">
                                                Latest First
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <div className="space-y-0" role="list" aria-label="Timeline events">
                                            {Object.keys(timelineItemsByDate)
                                                .sort((a, b) => new Date(b).getTime() - new Date(a).getTime())
                                                .map((date, dateIndex) => {
                                                    const isFirstDate = dateIndex === 0;

                                                    return (
                                                        <section
                                                            key={date}
                                                            className="border-b last:border-b-0"
                                                            role="listitem"
                                                        >
                                                            <div className="sticky top-0 z-10 border-b bg-muted/80 p-4 backdrop-blur-sm">
                                                                <div className="flex items-center gap-2">
                                                                    <Calendar className="h-4 w-4 text-primary" aria-hidden="true" />
                                                                    <time
                                                                        dateTime={date}
                                                                        className="text-base font-semibold"
                                                                    >
                                                                        {date}
                                                                    </time>
                                                                    {isFirstDate && (
                                                                        <Badge variant="default" className="text-xs">
                                                                            Latest
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            <div className="space-y-0">
                                                                {timelineItemsByDate[date]
                                                                    .sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
                                                                    .map((item, itemIndex) => (
                                                                        <div
                                                                            key={`${item.timestamp}-${itemIndex}`}
                                                                            className="border-b p-4 last:border-b-0"
                                                                        >
                                                                            {item.content}
                                                                        </div>
                                                                    ))}
                                                            </div>
                                                        </section>
                                                    );
                                                })}
                                        </div>
                                    </CardContent>
                                    <CardFooter className="justify-center border-t py-6">
                                        <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                            <CheckCircle className="h-4 w-4" aria-hidden="true" />
                                            Beginning of Timeline
                                        </span>
                                    </CardFooter>
                                </>
                            )}
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

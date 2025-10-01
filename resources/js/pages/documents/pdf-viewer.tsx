import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { BreadcrumbItem, SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import {
    Activity,
    Award,
    BarChart3,
    Building2,
    Calendar,
    CalendarDays,
    CheckCircle,
    Clock,
    Download,
    Eye,
    FileCheck,
    FileText,
    Flag,
    Gavel,
    Globe,
    HardDrive,
    Hash,
    PlayCircle,
    Shield,
    Target,
    User,
    Users,
    Users2,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

interface User {
    name: string;
    role: string;
}

interface DocumentView {
    id: number;
    user: User;
    viewed_at: string;
    viewed_at_human: string;
    ip_address: string;
    view_duration?: number;
    user_address?: string;
}

interface ViewStats {
    total_views: number;
    unique_viewers: number;
    today_views: number;
    week_views: number;
    month_views: number;
    views_by_role: Record<string, number>;
    views_by_day: Record<string, number>;
    first_viewed?: string;
    last_viewed?: string;
}

interface Document {
    procurement_id: string;
    procurement_title: string;
    document_type: string;
    stage: string;
    file_size?: number;
    timestamp: string;
    hash?: string;
    user_address: string;
    current_status?: string;
    status_timestamp?: string;
}

interface Props {
    document: Document;
    fileKey: string;
    pdfUrl: string;
    viewStats: ViewStats;
    recentViews: DocumentView[];
}

const getBreadcrumbs = (role?: string, procurementId?: string): BreadcrumbItem[] => {
    const getProcurementDetailsHref = (role: string, id?: string) => {
        if (!id || id === 'Unknown' || id.trim() === '') return '#';
        switch (role) {
            case 'bac_secretariat':
                return `/bac-secretariat/procurements-list/${id}`;
            case 'bac_chairman':
                return `/bac-chairman/procurements-list/${id}`;
            case 'hope':
                return `/hope/procurements-list/${id}`;
            case 'admin':
                return `/admin/procurements-list/${id}`;
            default:
                return '#';
        }
    };

    const procurementDetailsHref = getProcurementDetailsHref(role || '', procurementId);

    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Bids and Awards Committee Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '/bac-chairman/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Head of Procuring Entity Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '/hope/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Procurement List', href: '/admin/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
    }
};

const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case 'bac_chairman':
            return 'bg-primary/10 text-primary';
        case 'bac_secretariat':
            return 'bg-info/10 text-info';
        case 'hope':
            return 'bg-success/10 text-success';
        case 'admin':
            return 'bg-destructive/10 text-destructive';
        default:
            return 'bg-muted text-muted-foreground';
    }
};

const formatRole = (role: string) => {
    switch (role) {
        case 'bac_chairman':
            return 'BAC Chairman';
        case 'bac_secretariat':
            return 'BAC Secretariat';
        case 'hope':
            return 'Head of Office';
        case 'admin':
            return 'Administrator';
        default:
            return role.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }
};

const formatStage = (stage: string) => {
    const stageFormatMap: Record<string, string> = {
        ProcurementInitiation: 'Procurement Initiation',
        PreProcurementConference: 'Pre-Procurement Conference',
        BiddingDocuments: 'Bidding Documents',
        PreBidConference: 'Pre-Bid Conference',
        BidOpening: 'Bid Opening',
        BidEvaluation: 'Bid Evaluation',
        PostQualification: 'Post Qualification',
        NoticeOfAward: 'Notice of Award',
        NoticeToProceed: 'Notice to Proceed',
        PerformanceBondContractAndPo: 'Performance Bond, Contract & PO',
        Monitoring: 'Monitoring',
        Completion: 'Completion',
        BacResolution: 'BAC Resolution',
        SupplementalBidBulletin: 'Supplemental Bid Bulletin',
    };

    return stageFormatMap[stage] || stage.replace(/([A-Z])/g, ' $1').trim();
};

const getStageIcon = (stage: string) => {
    const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
        ProcurementInitiation: PlayCircle,
        PreProcurementConference: Users2,
        BiddingDocuments: FileCheck,
        PreBidConference: Users2,
        BidOpening: FileText,
        BidEvaluation: BarChart3,
        PostQualification: CheckCircle,
        NoticeOfAward: Award,
        NoticeToProceed: Flag,
        PerformanceBondContractAndPo: FileCheck,
        Monitoring: Activity,
        Completion: Target,
        BacResolution: Gavel,
        SupplementalBidBulletin: FileText,
    };

    const IconComponent = iconMap[stage] || FileText;
    return IconComponent;
};

const formatFileSize = (bytes?: number): string => {
    if (bytes === undefined || bytes === null || isNaN(bytes) || bytes < 0) return 'N/A';
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(i > 1 ? 1 : 0));

    return `${size} ${units[i]}`;
};

const formatTimestamp = (timestamp: string) => {
    try {
        const date = new Date(timestamp);
        return {
            date: date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }),
            time: date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }),
            relative: getRelativeTime(date),
        };
    } catch {
        return {
            date: 'Invalid Date',
            time: 'Invalid Time',
            relative: 'Unknown',
        };
    }
};

const getRelativeTime = (date: Date) => {
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} days ago`;
    if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)} months ago`;
    return `${Math.floor(diffInSeconds / 31536000)} years ago`;
};

const formatUserAddress = (address: string) => {
    if (!address || address.length < 10) return address;
    return `${address.slice(0, 6)}...${address.slice(-4)}`;
};

const formatStatus = (status: string) => {
    if (!status) return 'Unknown';
    return status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const getStatusBadgeColor = (status: string) => {
    if (!status) return 'bg-muted text-muted-foreground';

    const lowerStatus = status.toLowerCase();
    if (lowerStatus.includes('active') || lowerStatus.includes('in_progress') || lowerStatus.includes('ongoing')) {
        return 'bg-success/10 text-success';
    } else if (lowerStatus.includes('pending') || lowerStatus.includes('waiting')) {
        return 'bg-warning/10 text-warning';
    } else if (lowerStatus.includes('complete') || lowerStatus.includes('finished') || lowerStatus.includes('closed')) {
        return 'bg-primary/10 text-primary';
    } else if (lowerStatus.includes('cancelled') || lowerStatus.includes('rejected')) {
        return 'bg-destructive/10 text-destructive';
    }
    return 'bg-muted text-muted-foreground';
};

const getStatusIcon = (status: string) => {
    if (!status) return Activity;

    const lowerStatus = status.toLowerCase();
    if (lowerStatus.includes('active') || lowerStatus.includes('in_progress') || lowerStatus.includes('ongoing')) {
        return PlayCircle;
    } else if (lowerStatus.includes('pending') || lowerStatus.includes('waiting')) {
        return Clock;
    } else if (lowerStatus.includes('complete') || lowerStatus.includes('finished') || lowerStatus.includes('closed')) {
        return CheckCircle;
    } else if (lowerStatus.includes('cancelled') || lowerStatus.includes('rejected')) {
        return Flag;
    }
    return Activity;
};

export default function PdfViewer({ document, fileKey, pdfUrl, viewStats, recentViews }: Props) {
    const [pdfLoading, setPdfLoading] = useState(true);
    const [pdfError, setPdfError] = useState(false);
    const [pdfHeight, setPdfHeight] = useState(800);
    const [showAllViewersDialog, setShowAllViewersDialog] = useState(false);

    const statisticsPanelRef = useRef<HTMLDivElement>(null);
    const pdfViewerRef = useRef<HTMLDivElement>(null);

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const breadcrumbs = getBreadcrumbs(userRole, document.procurement_id);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (pdfLoading) {
                console.log('PDF still loading after 15 seconds, but not forcing error');
                setPdfLoading(false);
            }
        }, 15000);

        return () => clearTimeout(timer);
    }, [pdfLoading]);

    useEffect(() => {
        const initialHeight = 800;
        setPdfHeight(initialHeight);

        const updateHeight = () => {
            if (statisticsPanelRef.current && window.innerWidth >= 1024) {
                const statsHeight = statisticsPanelRef.current.offsetHeight;
                const newHeight = Math.max(600, Math.min(1200, statsHeight));
                setPdfHeight(newHeight);
            }
        };

        const delayedUpdate = setTimeout(() => {
            updateHeight();
        }, 2000);

        let resizeTimeout: NodeJS.Timeout;
        const debouncedResize = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateHeight, 500);
        };

        window.addEventListener('resize', debouncedResize);

        return () => {
            window.removeEventListener('resize', debouncedResize);
            clearTimeout(delayedUpdate);
            clearTimeout(resizeTimeout);
        };
    }, []);

    return (
        <TooltipProvider>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`PDF Viewer - ${document.document_type}`} />

                <div className="p-4 md:p-6 lg:p-8">
                    <div className="mb-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-primary text-2xl font-bold">{document.document_type}</h1>
                                <p className="text-muted-foreground mt-1 text-sm">{document.procurement_title}</p>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="flex items-center gap-1.5">
                                        {React.createElement(getStageIcon(document.stage), { className: 'h-3.5 w-3.5' })}
                                        <span className="font-medium">{formatStage(document.stage)}</span>
                                    </Badge>
                                    {document.current_status && (
                                        <Badge
                                            variant="outline"
                                            className={cn('flex items-center gap-1.5 px-3 py-1', getStatusBadgeColor(document.current_status))}
                                        >
                                            {React.createElement(getStatusIcon(document.current_status), { className: 'h-3.5 w-3.5' })}
                                            <span className="font-medium">{formatStatus(document.current_status)}</span>
                                        </Badge>
                                    )}
                                </div>
                                <p className="mt-2 font-mono text-xs">
                                    PDF URL:{' '}
                                    <a
                                        href={pdfUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary hover:text-primary/80 underline"
                                    >
                                        {pdfUrl}
                                    </a>
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <Badge variant="outline" className="flex items-center gap-1">
                                    <Eye className="h-3 w-3" />
                                    {viewStats.total_views} views
                                </Badge>
                                <Badge variant="outline" className="flex items-center gap-1">
                                    <Users className="h-3 w-3" />
                                    {viewStats.unique_viewers} unique
                                </Badge>
                                {pdfError && (
                                    <Badge variant="destructive" className="flex items-center gap-1">
                                        <FileText className="h-3 w-3" />
                                        PDF Blocked
                                    </Badge>
                                )}
                                <Button variant="outline" size="sm" asChild>
                                    <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                        <Eye className="mr-2 h-4 w-4" />
                                        Open in Tab
                                    </a>
                                </Button>
                                <Button asChild>
                                    <a href={pdfUrl} download>
                                        <Download className="mr-2 h-4 w-4" />
                                        Download
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div
                            ref={pdfViewerRef}
                            className="bg-background relative rounded-lg border lg:col-span-2"
                            style={{
                                height: `${pdfHeight}px`,
                                minHeight: '600px',
                            }}
                        >
                            {pdfError ? (
                                <div className="bg-muted flex h-full flex-col items-center justify-center">
                                    <div className="max-w-md p-8 text-center">
                                        <FileText className="text-muted-foreground mx-auto mb-4 h-16 w-16" />
                                        <h3 className="text-primary mb-2 text-lg font-semibold">PDF Viewer Error</h3>
                                        <p className="text-muted-foreground mb-6 text-sm">
                                            Unable to display the PDF in the browser. You can view the document using the options below.
                                        </p>
                                        <div className="space-y-3">
                                            <Button asChild className="w-full">
                                                <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                                    <Eye className="mr-2 h-4 w-4" />
                                                    Open PDF in New Tab
                                                </a>
                                            </Button>
                                            <Button variant="outline" asChild className="w-full">
                                                <a href={pdfUrl} download>
                                                    <Download className="mr-2 h-4 w-4" />
                                                    Download PDF
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <object
                                        data={pdfUrl}
                                        type="application/pdf"
                                        className="bg-background h-full w-full rounded-lg"
                                        style={{ minHeight: '600px' }}
                                        onLoad={() => {
                                            console.log('PDF object loaded successfully');
                                            setPdfLoading(false);
                                            setPdfError(false);
                                        }}
                                        onError={() => {
                                            console.log('PDF object load event failed - showing fallback');
                                            setPdfError(true);
                                            setPdfLoading(false);
                                        }}
                                    >
                                        <div
                                            className="bg-muted flex h-full w-full flex-col items-center justify-center rounded-lg"
                                            style={{ minHeight: '600px' }}
                                        >
                                            <div className="max-w-md p-8 text-center">
                                                <FileText className="text-muted-foreground mx-auto mb-4 h-16 w-16" />
                                                <h3 className="text-primary mb-2 text-lg font-semibold">PDF Plugin Not Available</h3>
                                                <p className="text-muted-foreground mb-6 text-sm">
                                                    Your browser doesn't support embedded PDFs. Use the buttons below to view the document.
                                                </p>
                                                <div className="space-y-3">
                                                    <Button asChild className="w-full">
                                                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            Open PDF in New Tab
                                                        </a>
                                                    </Button>
                                                    <Button variant="outline" asChild className="w-full">
                                                        <a href={pdfUrl} download>
                                                            <Download className="mr-2 h-4 w-4" />
                                                            Download PDF
                                                        </a>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </object>

                                    {pdfLoading && (
                                        <div className="bg-background/95 absolute inset-0 z-10 flex items-center justify-center rounded-lg backdrop-blur-sm">
                                            <div className="p-8 text-center">
                                                <div className="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-3"></div>
                                                <p className="text-primary text-lg font-medium">Loading PDF...</p>
                                                <p className="text-muted-foreground mt-2 text-sm">Please wait while the document loads</p>
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                        <div ref={statisticsPanelRef} className="space-y-6">
                            <div className="grid grid-cols-2 gap-4">
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-muted-foreground text-sm">Total Views</p>
                                                <p className="text-2xl font-bold">{viewStats.total_views}</p>
                                            </div>
                                            <Eye className="text-muted-foreground h-8 w-8" />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-muted-foreground text-sm">Unique Viewers</p>
                                                <p className="text-2xl font-bold">{viewStats.unique_viewers}</p>
                                            </div>
                                            <Users className="text-muted-foreground h-8 w-8" />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-muted-foreground text-sm">Today</p>
                                                <p className="text-2xl font-bold">{viewStats.today_views}</p>
                                            </div>
                                            <Calendar className="text-muted-foreground h-8 w-8" />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-muted-foreground text-sm">This Week</p>
                                                <p className="text-2xl font-bold">{viewStats.week_views}</p>
                                            </div>
                                            <Activity className="text-muted-foreground h-8 w-8" />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Document Information
                                    </CardTitle>
                                    <CardDescription>Complete details about this document</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-3">
                                        <div className="flex items-start justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <Hash className="h-3.5 w-3.5" />
                                                Procurement ID:
                                            </span>
                                            <span className="bg-muted rounded px-2 py-1 font-mono text-sm font-medium">
                                                {document.procurement_id}
                                            </span>
                                        </div>

                                        <div className="flex items-start justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <Building2 className="h-3.5 w-3.5" />
                                                Procurement Title:
                                            </span>
                                            <span className="max-w-[200px] text-right text-sm font-medium">{document.procurement_title}</span>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <FileText className="h-3.5 w-3.5" />
                                                Document Type:
                                            </span>
                                            <Badge variant="secondary" className="font-medium">
                                                {document.document_type}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <Target className="h-3.5 w-3.5" />
                                                Current Stage:
                                            </span>
                                            <Badge variant="outline" className="flex items-center gap-1.5">
                                                {React.createElement(getStageIcon(document.stage), { className: 'h-3.5 w-3.5' })}
                                                {formatStage(document.stage)}
                                            </Badge>
                                        </div>
                                        {document.current_status && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Activity className="h-3.5 w-3.5" />
                                                    Procurement Status:
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={cn('flex items-center gap-1.5', getStatusBadgeColor(document.current_status))}
                                                >
                                                    {React.createElement(getStatusIcon(document.current_status), { className: 'h-3.5 w-3.5' })}
                                                    {formatStatus(document.current_status)}
                                                </Badge>
                                            </div>
                                        )}

                                        {document.status_timestamp && (
                                            <div className="flex items-start justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    Status Updated:
                                                </span>
                                                <div className="text-right">
                                                    <span className="text-sm font-medium">{formatTimestamp(document.status_timestamp).date}</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        {formatTimestamp(document.status_timestamp).time} (
                                                        {formatTimestamp(document.status_timestamp).relative})
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="border-t pt-3">
                                        <h4 className="text-primary mb-3 text-sm font-medium">File Details</h4>
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <HardDrive className="h-3.5 w-3.5" />
                                                    File Size:
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {document.file_size && document.file_size > 0 ? formatFileSize(document.file_size) : 'N/A'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Globe className="h-3.5 w-3.5" />
                                                    File Key:
                                                </span>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span className="bg-muted max-w-[180px] cursor-help truncate rounded px-2 py-1 font-mono text-xs">
                                                            {fileKey}
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent className="max-w-md">
                                                        <p className="font-mono text-xs break-all">{fileKey}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-3">
                                        <h4 className="text-primary mb-3 text-sm font-medium">Blockchain & Security</h4>
                                        <div className="space-y-3">
                                            <div className="flex items-start justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Shield className="h-3.5 w-3.5" />
                                                    Document Hash:
                                                </span>
                                                <div className="text-right">
                                                    {document.hash && document.hash.trim() !== '' ? (
                                                        <>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <span className="bg-muted text-muted-foreground cursor-help rounded px-2 py-1 font-mono text-xs">
                                                                        {formatUserAddress(document.hash)}
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent className="max-w-md">
                                                                    <p className="font-mono text-xs break-all">{document.hash}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <p className="text-muted-foreground mt-1 text-xs">Blockchain verified</p>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="bg-muted text-muted-foreground rounded px-2 py-1 font-mono text-xs">
                                                                Not available
                                                            </span>
                                                            <p className="text-muted-foreground mt-1 text-xs">No blockchain data</p>
                                                        </>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-start justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <CalendarDays className="h-3.5 w-3.5" />
                                                    Created:
                                                </span>
                                                <div className="text-right">
                                                    <span className="text-sm font-medium">{formatTimestamp(document.timestamp).date}</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        {formatTimestamp(document.timestamp).time} • {formatTimestamp(document.timestamp).relative}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-3">
                                        <h4 className="text-primary mb-3 text-sm font-medium">Viewing Statistics</h4>
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Eye className="h-3.5 w-3.5" />
                                                    Total Views:
                                                </span>
                                                <span className="text-primary text-sm font-bold">{viewStats.total_views}</span>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Users className="h-3.5 w-3.5" />
                                                    Unique Viewers:
                                                </span>
                                                <span className="text-success text-sm font-bold">{viewStats.unique_viewers}</span>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground text-sm">First Viewed:</span>
                                                <span className="text-sm font-medium">{viewStats.first_viewed || 'Never'}</span>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground text-sm">Last Viewed:</span>
                                                <span className="text-sm font-medium">{viewStats.last_viewed || 'Never'}</span>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    Current Session:
                                                </span>
                                                <span className="text-warning text-sm font-medium">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <BarChart3 className="h-5 w-5" />
                                        Views by Role
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {Object.entries(viewStats.views_by_role).map(([role, count]) => (
                                            <div key={role} className="flex items-center justify-between">
                                                <Badge variant="secondary" className={cn('text-xs', getRoleBadgeColor(role))}>
                                                    {formatRole(role)}
                                                </Badge>
                                                <span className="text-sm font-medium">{count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <Clock className="h-5 w-5" />
                                                Recent Viewers
                                            </CardTitle>
                                            <CardDescription>
                                                Last {Math.min(recentViews.length, 10)} of {recentViews.length} viewers
                                            </CardDescription>
                                        </div>
                                        {recentViews.length > 10 && (
                                            <Dialog open={showAllViewersDialog} onOpenChange={setShowAllViewersDialog}>
                                                <DialogTrigger asChild>
                                                    <Button variant="outline" size="sm">
                                                        View All ({recentViews.length})
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent className="max-h-[80vh] max-w-4xl">
                                                    <DialogHeader>
                                                        <DialogTitle className="flex items-center gap-2">
                                                            <Users className="h-5 w-5" />
                                                            All Document Viewers
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Complete list of {recentViews.length} users who have viewed this document
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <ScrollArea className="mt-4 h-[60vh]">
                                                        <div className="space-y-3 pr-4">
                                                            {recentViews.map((view, index) => (
                                                                <div
                                                                    key={view.id}
                                                                    className="bg-card hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3 transition-colors"
                                                                >
                                                                    <div className="flex items-center gap-3">
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-muted-foreground bg-muted rounded px-2 py-1 font-mono text-xs">
                                                                                #{index + 1}
                                                                            </span>
                                                                            <Avatar className="h-8 w-8">
                                                                                <AvatarFallback>
                                                                                    {view.user.name.charAt(0).toUpperCase()}
                                                                                </AvatarFallback>
                                                                            </Avatar>
                                                                        </div>
                                                                        <div>
                                                                            <div className="flex items-center gap-2">
                                                                                <span className="text-sm font-medium">{view.user.name}</span>
                                                                                <Badge
                                                                                    variant="secondary"
                                                                                    className={cn('text-xs', getRoleBadgeColor(view.user.role))}
                                                                                >
                                                                                    {formatRole(view.user.role)}
                                                                                </Badge>
                                                                            </div>
                                                                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                                <span>{view.viewed_at_human}</span>
                                                                                {view.user_address && (
                                                                                    <>
                                                                                        <span>•</span>
                                                                                        <span className="bg-muted flex items-center gap-1 rounded px-1 py-0.5 font-mono text-xs">
                                                                                            <Shield className="h-3 w-3" />
                                                                                            {formatUserAddress(view.user_address)}
                                                                                        </span>
                                                                                    </>
                                                                                )}
                                                                                <span>•</span>
                                                                                <span className="flex items-center gap-1">
                                                                                    <Globe className="h-3 w-3" />
                                                                                    {view.ip_address}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </ScrollArea>
                                                </DialogContent>
                                            </Dialog>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <ScrollArea className="h-64">
                                        <div className="space-y-3">
                                            {recentViews.slice(0, 10).map((view) => (
                                                <div key={view.id} className="flex items-center justify-between rounded-lg border p-2">
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="h-8 w-8">
                                                            <AvatarFallback>{view.user.name.charAt(0).toUpperCase()}</AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium">{view.user.name}</span>
                                                                <Badge
                                                                    variant="secondary"
                                                                    className={cn('text-xs', getRoleBadgeColor(view.user.role))}
                                                                >
                                                                    {formatRole(view.user.role)}
                                                                </Badge>
                                                            </div>
                                                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                <span>{view.viewed_at_human}</span>
                                                                {view.user_address && (
                                                                    <>
                                                                        <span>•</span>
                                                                        <span className="bg-muted text-muted-foreground rounded px-1 py-0.5 font-mono text-xs">
                                                                            {formatUserAddress(view.user_address)}
                                                                        </span>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">{view.ip_address}</div>
                                                </div>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                    {recentViews.length > 10 && (
                                        <div className="mt-4 border-t pt-4">
                                            <Button variant="ghost" className="w-full text-sm" onClick={() => setShowAllViewersDialog(true)}>
                                                View All {recentViews.length} Viewers
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </TooltipProvider>
    );
}

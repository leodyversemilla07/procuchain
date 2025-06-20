import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Eye,
    Users,
    Calendar,
    Clock,
    Download,
    FileText,
    Activity,
    BarChart3,
    Timer,
    PlayCircle,
    FileCheck,
    Gavel,
    Users2,
    Award,
    CheckCircle,
    Flag,
    Target,
    Hash,
    Building2,
    User,
    Globe,
    HardDrive,
    CalendarDays,
    Shield
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { SharedData, BreadcrumbItem } from '@/types';

// Type definitions for Inertia.js page props
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

// Inertia.js Props interface
interface Props {
    document: Document;
    fileKey: string;
    pdfUrl: string;
    viewStats: ViewStats;
    recentViews: DocumentView[];
}

const getBreadcrumbs = (role?: string, procurementId?: string): BreadcrumbItem[] => {
    // Generate the correct procurement details URL based on role
    const getProcurementDetailsHref = (role: string, id?: string) => {
        // If no procurement ID or it's 'Unknown', return # to disable the link
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
                { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'BAC Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '/bac-chairman/procurements-list' },
                { title: 'Procurement Details', href: procurementDetailsHref },
                { title: 'PDF Viewer', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'HOPE Dashboard', href: '/hope/dashboard' },
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
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300';
        case 'bac_secretariat':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300';
        case 'hope':
            return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300';
        case 'admin':
            return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300';
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
            return role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
};

const formatStage = (stage: string) => {
    const stageFormatMap: Record<string, string> = {
        'ProcurementInitiation': 'Procurement Initiation',
        'PreProcurementConference': 'Pre-Procurement Conference',
        'BiddingDocuments': 'Bidding Documents',
        'PreBidConference': 'Pre-Bid Conference',
        'BidOpening': 'Bid Opening',
        'BidEvaluation': 'Bid Evaluation',
        'PostQualification': 'Post Qualification',
        'NoticeOfAward': 'Notice of Award',
        'NoticeToProceed': 'Notice to Proceed',
        'PerformanceBondContractAndPo': 'Performance Bond, Contract & PO',
        'Monitoring': 'Monitoring',
        'Completion': 'Completion',
        'BacResolution': 'BAC Resolution',
        'SupplementalBidBulletin': 'Supplemental Bid Bulletin'
    };

    return stageFormatMap[stage] || stage.replace(/([A-Z])/g, ' $1').trim();
};

const getStageIcon = (stage: string) => {
    const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
        'ProcurementInitiation': PlayCircle,
        'PreProcurementConference': Users2,
        'BiddingDocuments': FileCheck,
        'PreBidConference': Users2,
        'BidOpening': FileText,
        'BidEvaluation': BarChart3,
        'PostQualification': CheckCircle,
        'NoticeOfAward': Award,
        'NoticeToProceed': Flag,
        'PerformanceBondContractAndPo': FileCheck,
        'Monitoring': Activity,
        'Completion': Target,
        'BacResolution': Gavel,
        'SupplementalBidBulletin': FileText
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

const formatDuration = (seconds?: number) => {
    if (!seconds) return 'Unknown';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    if (minutes > 0) {
        return `${minutes}m ${remainingSeconds}s`;
    }
    return `${remainingSeconds}s`;
};

const formatTimestamp = (timestamp: string) => {
    try {
        const date = new Date(timestamp);
        return {
            date: date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }),
            time: date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }),
            relative: getRelativeTime(date)
        };
    } catch {
        return {
            date: 'Invalid Date',
            time: 'Invalid Time',
            relative: 'Unknown'
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
    // Convert status to a more readable format
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getStatusBadgeColor = (status: string) => {
    if (!status) return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300';

    const lowerStatus = status.toLowerCase();
    if (lowerStatus.includes('active') || lowerStatus.includes('in_progress') || lowerStatus.includes('ongoing')) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300';
    } else if (lowerStatus.includes('pending') || lowerStatus.includes('waiting')) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300';
    } else if (lowerStatus.includes('complete') || lowerStatus.includes('finished') || lowerStatus.includes('closed')) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300';
    } else if (lowerStatus.includes('cancelled') || lowerStatus.includes('rejected')) {
        return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300';
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

// Inertia.js Page Component following proper structure
export default function PdfViewer({ document, fileKey, pdfUrl, viewStats, recentViews }: Props) {
    // State management
    const [viewStartTime] = useState(Date.now());
    const [currentViewDuration, setCurrentViewDuration] = useState(0);
    const [pdfLoading, setPdfLoading] = useState(true);
    const [pdfError, setPdfError] = useState(false);
    const [pdfHeight, setPdfHeight] = useState(800);
    const [actualFileSize, setActualFileSize] = useState<number | null>(null);
    const [showAllViewersDialog, setShowAllViewersDialog] = useState(false);

    // Refs for DOM elements
    const statisticsPanelRef = useRef<HTMLDivElement>(null);
    const pdfViewerRef = useRef<HTMLDivElement>(null);
    
    // Get authenticated user from Inertia shared data
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole, document.procurement_id);
      // Effects for component lifecycle management
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentViewDuration(Math.floor((Date.now() - viewStartTime) / 1000));
        }, 1000); 
        return () => clearInterval(interval);
    }, [viewStartTime]);

    // Auto-fallback if PDF doesn't load within reasonable time
    useEffect(() => {
        const timer = setTimeout(() => {
            if (pdfLoading) {
                console.warn('PDF taking too long to load, showing fallback');
                setPdfLoading(false);
            }
        }, 5000); // Give 5 seconds for PDF to load

        return () => clearTimeout(timer);
    }, [pdfLoading]);

    // Height matching effect
    useEffect(() => {
        const updateHeight = () => {
            if (statisticsPanelRef.current && window.innerWidth >= 1024) { // lg breakpoint
                const statsHeight = statisticsPanelRef.current.offsetHeight;
                setPdfHeight(Math.max(600, statsHeight)); // Minimum 600px height
            } else {
                setPdfHeight(800); // Default height for mobile/tablet
            }
        };

        updateHeight();
        window.addEventListener('resize', updateHeight);

        // Update after a short delay to account for content loading
        const timeoutId = setTimeout(updateHeight, 500);

        return () => {
            window.removeEventListener('resize', updateHeight);
            clearTimeout(timeoutId);
        };
    }, [viewStats, recentViews]); // Re-run when data changes

    // Fetch actual file size if not provided (simplified version)
    useEffect(() => {
        // Only fetch if we don't have a valid file size and haven't already fetched
        const hasValidFileSize = document.file_size && document.file_size > 0;

        if (!hasValidFileSize && pdfUrl && actualFileSize === null) {
            console.log('Fetching file size from:', pdfUrl);
            fetch(pdfUrl, { method: 'HEAD' })
                .then(response => {
                    const contentLength = response.headers.get('content-length');
                    console.log('Content-Length header:', contentLength);
                    if (contentLength && parseInt(contentLength, 10) > 0) {
                        const size = parseInt(contentLength, 10);
                        console.log('Setting actual file size to:', size);
                        setActualFileSize(size);
                    } else {
                        // Set to 0 to indicate we tried and failed
                        setActualFileSize(0);
                    }
                })
                .catch(error => {
                    console.warn('Could not fetch file size:', error);
                    // Set to 0 to indicate we tried and failed
                    setActualFileSize(0);
                });
        }
    }, [document.file_size, pdfUrl, actualFileSize]);

    // Track PDF view duration when component unmounts
    useEffect(() => {
        return () => {
            // Send view duration to backend
            const csrfToken = window.document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fetch(`/api/document-views/update-duration`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    file_key: fileKey,
                    duration: Math.floor((Date.now() - viewStartTime) / 1000),
                }),
            }).catch(console.error);
        };    }, [fileKey, viewStartTime]);

    // Render the PDF Viewer page with proper Inertia.js structure
    return (
        <TooltipProvider>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`PDF Viewer - ${document.document_type}`} />

                <div className="p-4 md:p-6 lg:p-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    {document.document_type}
                                </h1>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {document.procurement_title}
                                </p>
                                <div className="mt-2 flex items-center gap-2 flex-wrap">
                                    <Badge variant="outline" className="bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-700 border-blue-200 dark:from-blue-900/20 dark:to-indigo-900/20 dark:text-blue-300 dark:border-blue-800 flex items-center gap-1.5 px-3 py-1">
                                        {React.createElement(getStageIcon(document.stage), { className: "h-3.5 w-3.5" })}
                                        <span className="font-medium">{formatStage(document.stage)}</span>
                                    </Badge>
                                    {document.current_status && (
                                        <Badge variant="outline" className={cn("flex items-center gap-1.5 px-3 py-1", getStatusBadgeColor(document.current_status))}>
                                            {React.createElement(getStatusIcon(document.current_status), { className: "h-3.5 w-3.5" })}
                                            <span className="font-medium">{formatStatus(document.current_status)}</span>
                                        </Badge>
                                    )}
                                </div>
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-500 font-mono">
                                    PDF URL: {pdfUrl}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <Badge variant="outline" className="flex items-center gap-1">
                                    <Eye className="h-3 w-3" />
                                    {viewStats.total_views} views
                                </Badge>
                                <Badge variant="outline" className="flex items-center gap-1">
                                    <Timer className="h-3 w-3" />
                                    {formatDuration(currentViewDuration)}
                                </Badge>
                                {pdfError && (
                                    <Badge variant="destructive" className="flex items-center gap-1">
                                        <FileText className="h-3 w-3" />
                                        PDF Blocked
                                    </Badge>
                                )}
                                {/* Moved action buttons to header */}
                                <Button variant="outline" size="sm" asChild>
                                    <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                        <Eye className="h-4 w-4 mr-2" />
                                        Open in Tab
                                    </a>
                                </Button>
                                <Button asChild>
                                    <a href={pdfUrl} download>
                                        <Download className="h-4 w-4 mr-2" />
                                        Download
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* PDF Viewer */}
                        <div
                            ref={pdfViewerRef}
                            className="lg:col-span-2 relative"
                            style={{ height: `${pdfHeight}px` }}
                        >
                            {pdfError ? (
                                /* Fallback when PDF fails to load */
                                <div className="flex flex-col items-center justify-center h-full bg-gray-50 dark:bg-gray-800">
                                    <div className="text-center p-8 max-w-md">
                                        <FileText className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                            PDF Viewer Error
                                        </h3>
                                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                            Unable to display the PDF in the browser. You can view the document using the options below.
                                        </p>
                                        <div className="space-y-3">
                                            <Button asChild className="w-full">
                                                <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                                    <Eye className="h-4 w-4 mr-2" />
                                                    Open PDF in New Tab
                                                </a>
                                            </Button>
                                            <Button variant="outline" asChild className="w-full">
                                                <a href={pdfUrl} download>
                                                    <Download className="h-4 w-4 mr-2" />
                                                    Download PDF
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    {/* Using object tag instead of iframe - better browser compatibility */}
                                    <object
                                        data={pdfUrl}
                                        type="application/pdf"
                                        className="w-full h-full rounded-lg"
                                        onLoad={() => {
                                            console.log('PDF object loaded successfully');
                                            setPdfLoading(false);
                                        }}
                                        onError={() => {
                                            console.error('PDF object failed to load');
                                            setPdfError(true);
                                            setPdfLoading(false);
                                        }}
                                    >
                                        {/* Fallback content when object fails */}
                                        <div className="flex flex-col items-center justify-center h-full bg-gray-50 dark:bg-gray-800">
                                            <div className="text-center p-8 max-w-md">
                                                <FileText className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                                    PDF Plugin Not Available
                                                </h3>
                                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                                    Your browser doesn't have a PDF plugin or it's disabled. Use the buttons below to view the document.
                                                </p>
                                                <div className="space-y-3">
                                                    <Button asChild className="w-full">
                                                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                                            <Eye className="h-4 w-4 mr-2" />
                                                            Open PDF in New Tab
                                                        </a>
                                                    </Button>
                                                    <Button variant="outline" asChild className="w-full">
                                                        <a href={pdfUrl} download>
                                                            <Download className="h-4 w-4 mr-2" />
                                                            Download PDF
                                                        </a>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </object>

                                    {pdfLoading && (
                                        <div className="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-800/80 rounded-lg">
                                            <div className="text-center">
                                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">Loading PDF...</p>
                                            </div>
                                        </div>)}
                                </>
                            )}
                        </div>
                        {/* Statistics Panel */}
                        <div ref={statisticsPanelRef} className="space-y-6">
                            {/* Quick Stats */}
                            <div className="grid grid-cols-2 gap-4">
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-muted-foreground">Total Views</p>
                                                <p className="text-2xl font-bold">{viewStats.total_views}</p>
                                            </div>
                                            <Eye className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-muted-foreground">Unique Viewers</p>
                                                <p className="text-2xl font-bold">{viewStats.unique_viewers}</p>
                                            </div>
                                            <Users className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-muted-foreground">Today</p>
                                                <p className="text-2xl font-bold">{viewStats.today_views}</p>
                                            </div>
                                            <Calendar className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-muted-foreground">This Week</p>
                                                <p className="text-2xl font-bold">{viewStats.week_views}</p>
                                            </div>
                                            <Activity className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                            {/* Document Info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Document Information
                                    </CardTitle>
                                    <CardDescription>
                                        Complete details about this document
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Basic Document Info */}
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-start">
                                            <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                <Hash className="h-3.5 w-3.5" />
                                                Procurement ID:
                                            </span>
                                            <span className="text-sm font-medium font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                {document.procurement_id}
                                            </span>
                                        </div>

                                        <div className="flex justify-between items-start">
                                            <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                <Building2 className="h-3.5 w-3.5" />
                                                Procurement Title:
                                            </span>
                                            <span className="text-sm font-medium text-right max-w-[200px]">
                                                {document.procurement_title}
                                            </span>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                <FileText className="h-3.5 w-3.5" />
                                                Document Type:
                                            </span>
                                            <Badge variant="secondary" className="font-medium">
                                                {document.document_type}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                <Target className="h-3.5 w-3.5" />
                                                Current Stage:
                                            </span>
                                            <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800 flex items-center gap-1.5">
                                                {React.createElement(getStageIcon(document.stage), { className: "h-3.5 w-3.5" })}
                                                {formatStage(document.stage)}
                                            </Badge>
                                        </div>
                                        {/* Procurement Status */}
                                        {document.current_status && (
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Activity className="h-3.5 w-3.5" />
                                                    Procurement Status:
                                                </span>
                                                <Badge variant="outline" className={cn("flex items-center gap-1.5", getStatusBadgeColor(document.current_status))}>
                                                    {React.createElement(getStatusIcon(document.current_status), { className: "h-3.5 w-3.5" })}
                                                    {formatStatus(document.current_status)}
                                                </Badge>
                                            </div>
                                        )}

                                        {/* Status Last Updated */}
                                        {document.status_timestamp && (
                                            <div className="flex justify-between items-start">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    Status Updated:
                                                </span>
                                                <div className="text-right">
                                                    <span className="text-sm font-medium">
                                                        {formatTimestamp(document.status_timestamp).date}
                                                    </span>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatTimestamp(document.status_timestamp).time} ({formatTimestamp(document.status_timestamp).relative})
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="border-t pt-3">
                                        <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">File Details</h4>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <HardDrive className="h-3.5 w-3.5" />
                                                    File Size:
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {(() => {
                                                            // Try document.file_size first, then actualFileSize
                                                            const fileSize = document.file_size || actualFileSize;
                                                            if (fileSize && fileSize > 0) {
                                                                return formatFileSize(fileSize);
                                                            } else if (actualFileSize === null && pdfUrl) {
                                                                return 'Loading...';
                                                            } else {
                                                                return 'N/A';
                                                            }
                                                        })()}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Globe className="h-3.5 w-3.5" />
                                                    File Key:
                                                </span>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span className="text-xs font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded max-w-[180px] truncate cursor-help">
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
                                        <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">Blockchain & Security</h4>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-start">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Shield className="h-3.5 w-3.5" />
                                                    Document Hash:
                                                </span>
                                                <div className="text-right">
                                                    {document.hash && document.hash.trim() !== '' ? (
                                                        <>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <span className="text-xs font-mono bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded cursor-help">
                                                                        {formatUserAddress(document.hash)}
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent className="max-w-md">
                                                                    <p className="font-mono text-xs break-all">{document.hash}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <p className="text-xs text-muted-foreground mt-1">
                                                                Blockchain verified
                                                            </p>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="text-xs font-mono bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-1 rounded">
                                                                Not available
                                                            </span>
                                                            <p className="text-xs text-muted-foreground mt-1">
                                                                No blockchain data
                                                            </p>
                                                        </>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex justify-between items-start">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <CalendarDays className="h-3.5 w-3.5" />
                                                    Created:
                                                </span>
                                                <div className="text-right">
                                                    <span className="text-sm font-medium">
                                                        {formatTimestamp(document.timestamp).date}
                                                    </span>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatTimestamp(document.timestamp).time} • {formatTimestamp(document.timestamp).relative}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-3">
                                        <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">Viewing Statistics</h4>
                                        <div className="space-y-3">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Eye className="h-3.5 w-3.5" />
                                                    Total Views:
                                                </span>
                                                <span className="text-sm font-bold text-blue-600 dark:text-blue-400">
                                                    {viewStats.total_views}
                                                </span>
                                            </div>

                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Users className="h-3.5 w-3.5" />
                                                    Unique Viewers:
                                                </span>
                                                <span className="text-sm font-bold text-green-600 dark:text-green-400">
                                                    {viewStats.unique_viewers}
                                                </span>
                                            </div>

                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground">First Viewed:</span>
                                                <span className="text-sm font-medium">
                                                    {viewStats.first_viewed || 'Never'}
                                                </span>
                                            </div>

                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground">Last Viewed:</span>
                                                <span className="text-sm font-medium">
                                                    {viewStats.last_viewed || 'Never'}
                                                </span>
                                            </div>

                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <Timer className="h-3.5 w-3.5" />
                                                    Current Session:
                                                </span>
                                                <span className="text-sm font-medium text-orange-600 dark:text-orange-400">
                                                    {formatDuration(currentViewDuration)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Views by Role */}
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
                                                <Badge
                                                    variant="secondary"
                                                    className={cn("text-xs", getRoleBadgeColor(role))}
                                                >
                                                    {formatRole(role)}
                                                </Badge>
                                                <span className="text-sm font-medium">{count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Recent Viewers */}
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
                                                <DialogContent className="max-w-4xl max-h-[80vh]">
                                                    <DialogHeader>
                                                        <DialogTitle className="flex items-center gap-2">
                                                            <Users className="h-5 w-5" />
                                                            All Document Viewers
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Complete list of {recentViews.length} users who have viewed this document
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <ScrollArea className="h-[60vh] mt-4">
                                                        <div className="space-y-3 pr-4">
                                                            {recentViews.map((view, index) => (
                                                                <div
                                                                    key={view.id}
                                                                    className="flex items-center justify-between p-3 rounded-lg border bg-card hover:bg-accent/50 transition-colors"
                                                                >
                                                                    <div className="flex items-center gap-3">
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-xs text-muted-foreground font-mono bg-muted px-2 py-1 rounded">
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
                                                                                    className={cn("text-xs", getRoleBadgeColor(view.user.role))}
                                                                                >
                                                                                    {formatRole(view.user.role)}
                                                                                </Badge>
                                                                            </div>
                                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                                <span>{view.viewed_at_human}</span>
                                                                                {view.view_duration && (
                                                                                    <>
                                                                                        <span>•</span>
                                                                                        <span className="flex items-center gap-1">
                                                                                            <Timer className="h-3 w-3" />
                                                                                            {formatDuration(view.view_duration)}
                                                                                        </span>
                                                                                    </>
                                                                                )}
                                                                                {view.user_address && (
                                                                                    <>
                                                                                        <span>•</span>
                                                                                        <span className="font-mono text-xs bg-muted px-1 py-0.5 rounded flex items-center gap-1">
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
                                                <div
                                                    key={view.id}
                                                    className="flex items-center justify-between p-2 rounded-lg border"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="h-8 w-8">
                                                            <AvatarFallback>
                                                                {view.user.name.charAt(0).toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium">{view.user.name}</span>
                                                                <Badge
                                                                    variant="secondary"
                                                                    className={cn("text-xs", getRoleBadgeColor(view.user.role))}
                                                                >
                                                                    {formatRole(view.user.role)}
                                                                </Badge>
                                                            </div>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <span>{view.viewed_at_human}</span>
                                                                {view.view_duration && (
                                                                    <>
                                                                        <span>•</span>
                                                                        <span>{formatDuration(view.view_duration)}</span>
                                                                    </>
                                                                )}
                                                                {view.user_address && (
                                                                    <>
                                                                        <span>•</span>
                                                                        <span className="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">
                                                                            {formatUserAddress(view.user_address)}
                                                                        </span>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {view.ip_address}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                    {recentViews.length > 10 && (
                                        <div className="mt-4 pt-4 border-t">
                                            <Button
                                                variant="ghost"
                                                className="w-full text-sm"
                                                onClick={() => setShowAllViewersDialog(true)}
                                            >
                                                View All {recentViews.length} Viewers
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </AppLayout>        </TooltipProvider>
    );
}

// Export the component for Inertia.js page routing
// This component should be registered in your Laravel routes (web.php) 
// and rendered via Inertia::render('documents/pdf-viewer', $props)

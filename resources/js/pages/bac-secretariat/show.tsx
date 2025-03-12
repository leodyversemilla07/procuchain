import { useState, JSX } from 'react';
import {
    FileText, Hash, Clock, Plus, RefreshCw, Lock, Download, FileCheck,
    CheckCircle, XCircle, Upload, AlertCircle, Calendar, Building, UserRound, HardDrive
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { BreadcrumbItem } from '@/types';

// Import shadcn UI components
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { TooltipProvider, Tooltip, TooltipTrigger, TooltipContent } from "@/components/ui/tooltip";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
    { title: 'Procurement Details', href: '#' },
];

// Define the Procurement interface
interface Document {
    file_key: string;
    document_type: string;
    spaces_url?: string;
    hash?: string;
    file_size?: number;
    phase_metadata?: {
        submission_date?: string;
        municipal_offices?: string;
        signatory_details?: string;
    };
}

interface Event {
    timestamp: string;
    event_type: string;
    details: string;
}

interface State {
    phase_identifier: string;
    current_state: string;
    timestamp: string;
}

interface Procurement {
    id: string;
    title: string;
    state: State;
    documents: Document[];
    events: Event[];
    raw_state?: any;
    raw_documents?: any;
    raw_events?: any;
}

// Define the props interface
interface ShowProps {
    procurement: Procurement;
}

// Functional component with typed props
export default function Show({ procurement }: ShowProps) {
    const [activeTab, setActiveTab] = useState('documents');

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    };

    const getStatusColor = (state: string) => {
        const states: Record<string, { variant: "default" | "destructive" | "outline" | "secondary" | null, icon: JSX.Element }> = {
            'pr submitted': {
                variant: "default",
                icon: <FileText className="w-4 h-4 mr-1.5" />
            },
            'pre-procurement completed': {
                variant: "secondary",
                icon: <FileCheck className="w-4 h-4 mr-1.5" />
            },
            'bid invitation published': {
                variant: "secondary",
                icon: <Upload className="w-4 h-4 mr-1.5" />
            },
            'bids opened': {
                variant: "outline",
                icon: <FileText className="w-4 h-4 mr-1.5" />
            },
            'bids evaluated': {
                variant: "default",
                icon: <CheckCircle className="w-4 h-4 mr-1.5" />
            },
            'post-qualification verified': {
                variant: "secondary",
                icon: <CheckCircle className="w-4 h-4 mr-1.5" />
            },
            'resolution recorded': {
                variant: "default",
                icon: <FileText className="w-4 h-4 mr-1.5" />
            },
            'awarded': {
                variant: "secondary",
                icon: <CheckCircle className="w-4 h-4 mr-1.5" />
            },
            'performance bond recorded': {
                variant: "secondary",
                icon: <FileText className="w-4 h-4 mr-1.5" />
            },
            'contract & po recorded': {
                variant: "outline",
                icon: <FileText className="w-4 h-4 mr-1.5" />
            },
            'ntp recorded': {
                variant: "default",
                icon: <Clock className="w-4 h-4 mr-1.5" />
            },
            'monitoring': {
                variant: "secondary",
                icon: <FileCheck className="w-4 h-4 mr-1.5" />
            },
        };

        const defaultStatus = {
            variant: "outline" as const,
            icon: <AlertCircle className="w-4 h-4 mr-1.5" />
        };

        return states[state.toLowerCase()] || defaultStatus;
    };

    const getDocumentIcon = () => {
        // Since we're only using PDF files, just return the PDF icon
        return <FileText className="w-6 h-6 text-red-500" />;
    };

    const getEventIcon = (type: string) => {
        const typeLower = type.toLowerCase();

        if (typeLower.includes('create') || typeLower.includes('new')) {
            return <Avatar className="h-8 w-8 bg-green-100"><AvatarFallback className="bg-green-100 text-green-600"><Plus className="w-4 h-4" /></AvatarFallback></Avatar>
        }

        if (typeLower.includes('update') || typeLower.includes('change')) {
            return <Avatar className="h-8 w-8 bg-blue-100"><AvatarFallback className="bg-blue-100 text-blue-600"><RefreshCw className="w-4 h-4" /></AvatarFallback></Avatar>
        }

        if (typeLower.includes('approve') || typeLower.includes('confirm')) {
            return <Avatar className="h-8 w-8 bg-emerald-100"><AvatarFallback className="bg-emerald-100 text-emerald-600"><CheckCircle className="w-4 h-4" /></AvatarFallback></Avatar>
        }

        if (typeLower.includes('reject') || typeLower.includes('decline')) {
            return <Avatar className="h-8 w-8 bg-red-100"><AvatarFallback className="bg-red-100 text-red-600"><XCircle className="w-4 h-4" /></AvatarFallback></Avatar>
        }

        if (typeLower.includes('document') || typeLower.includes('upload')) {
            return <Avatar className="h-8 w-8 bg-purple-100"><AvatarFallback className="bg-purple-100 text-purple-600"><FileText className="w-4 h-4" /></AvatarFallback></Avatar>
        }

        return <Avatar className="h-8 w-8 bg-gray-100"><AvatarFallback className="bg-gray-100 text-gray-600"><AlertCircle className="w-4 h-4" /></AvatarFallback></Avatar>
    };

    // Format file size to human-readable format
    const formatFileSize = (bytes?: number): string => {
        if (!bytes) return 'Unknown size';

        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(size < 10 && unitIndex > 0 ? 1 : 0)} ${units[unitIndex]}`;
    };

    const statusInfo = getStatusColor(procurement.state.current_state);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement Details" />
            <div className="flex h-full flex-1 flex-col gap-3 sm:gap-4 p-2 sm:p-4 rounded-xl">
                {/* Header Card */}
                <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border">
                    <CardHeader className="relative z-10 p-4 sm:p-6">
                        <div className="space-y-3 sm:space-y-4">
                            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 sm:gap-4">
                                <div className="space-y-2 sm:space-y-3">
                                    <CardTitle className="text-xl sm:text-2xl md:text-3xl font-semibold tracking-tight leading-tight">
                                        {procurement.title}
                                    </CardTitle>
                                    <CardDescription className="space-y-2 text-neutral-600 dark:text-neutral-400">
                                        <div className="flex items-center text-[14px] sm:text-[15px]">
                                            <Hash className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 sm:mr-2.5 text-primary" />
                                            <span className="font-semibold">ID:</span>
                                            <code className="ml-2 sm:ml-2.5 font-mono bg-neutral-100 dark:bg-neutral-800 px-1.5 sm:px-2 py-0.5 rounded text-xs sm:text-sm tracking-wide truncate max-w-[120px] sm:max-w-none">
                                                {procurement.id}
                                            </code>
                                        </div>
                                        <div className="flex items-center text-[14px] sm:text-[15px]">
                                            <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 sm:mr-2.5 text-primary" />
                                            <span className="font-semibold">Last Updated:</span>
                                            <time className="ml-2 sm:ml-2.5 text-sm sm:text-base">{formatDate(procurement.state.timestamp)}</time>
                                        </div>
                                    </CardDescription>
                                </div>
                                <div className="flex flex-row sm:flex-col items-center sm:items-start lg:items-end gap-3 mt-2 sm:mt-0">
                                    <Badge
                                        variant={statusInfo.variant}
                                        className="flex items-center px-2 sm:px-3 py-0.5 sm:py-1 text-xs sm:text-sm"
                                    >
                                        {statusInfo.icon}
                                        <span className="ml-1 sm:ml-1.5 font-medium">{procurement.state.current_state}</span>
                                    </Badge>
                                    <div className="text-[13px] sm:text-[15px] text-neutral-600 dark:text-neutral-400 font-medium">
                                        Phase: {procurement.state.phase_identifier}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Tabs */}
                <Tabs defaultValue={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="mb-3 sm:mb-4 grid grid-cols-2 w-full max-w-[300px]">
                        <TabsTrigger value="documents" className="flex items-center">
                            <FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
                            Documents
                        </TabsTrigger>
                        <TabsTrigger value="timeline" className="flex items-center">
                            <Clock className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
                            Timeline
                        </TabsTrigger>
                    </TabsList>

                    <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border">
                        {/* Documents Tab */}
                        <TabsContent value="documents" className="p-0 m-0">
                            <CardHeader className="border-b p-4 sm:p-6">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-1 sm:mb-2">
                                    <div className="flex items-center space-x-2 sm:space-x-3">
                                        <div className="p-1.5 sm:p-2 bg-neutral-100 dark:bg-neutral-800 rounded-lg">
                                            <FileText className="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">PDF Documents</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">View and manage procurement documents</CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Badge variant="outline" className="bg-neutral-100 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm">
                                            {procurement.documents.length} {procurement.documents.length === 1 ? 'Document' : 'Documents'}
                                        </Badge>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                {procurement.documents.length === 0 ? (
                                    <div className="p-6 sm:p-12 text-center">
                                        <Avatar className="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-neutral-100 dark:bg-neutral-800 mb-3 sm:mb-4 border border-neutral-200 dark:border-neutral-700">
                                            <AvatarFallback className="bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                                                <Plus className="w-6 h-6 sm:w-8 sm:h-8" />
                                            </AvatarFallback>
                                        </Avatar>
                                        <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-400 mb-3">No PDF documents available</p>
                                        <Button variant="default" size="sm" className="sm:h-10 text-xs sm:text-sm">
                                            <Plus className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
                                            Add PDF Document
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="overflow-visible">
                                        <ul className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                            {procurement.documents.map((doc, index) => (
                                                <li key={doc.file_key || index} className="p-4 sm:p-6 hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                                                    <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                                                        <div className="flex items-start group">
                                                            <div className="p-2 sm:p-2.5 bg-neutral-100 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 flex-shrink-0 transition-colors">
                                                                {getDocumentIcon()}
                                                            </div>
                                                            <div className="ml-3 sm:ml-3.5 max-w-xl">
                                                                <h4 className="text-sm sm:text-base font-medium tracking-tight mb-1 sm:mb-1.5">
                                                                    {doc.document_type}
                                                                </h4>
                                                                <div className="flex flex-wrap items-center gap-2 sm:gap-2.5 text-[12px] sm:text-[13px]">
                                                                    <TooltipProvider>
                                                                        <Tooltip>
                                                                            <TooltipTrigger asChild>
                                                                                <span className="text-neutral-600 dark:text-neutral-400 truncate max-w-[180px] sm:max-w-[280px] hover:text-neutral-900 dark:hover:text-neutral-200 transition-colors cursor-help font-medium">
                                                                                    {doc.file_key}
                                                                                </span>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent className="text-xs sm:text-sm">
                                                                                <p className="font-medium">Document Key: {doc.file_key}</p>
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                    </TooltipProvider>
                                                                    {doc.file_size !== undefined && (
                                                                        <>
                                                                            <span className="w-1 h-1 bg-neutral-300 dark:bg-neutral-600 rounded-full" aria-hidden="true" />
                                                                            <span className="flex items-center text-neutral-500 dark:text-neutral-400 font-normal">
                                                                                <HardDrive className="w-3 h-3 sm:w-3.5 sm:h-3.5 mr-1 sm:mr-1.5" aria-hidden="true" />
                                                                                {formatFileSize(doc.file_size)}
                                                                            </span>
                                                                        </>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {doc.spaces_url && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                                className="flex-shrink-0 transition-colors font-medium border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm h-8 sm:h-9 mt-2 md:mt-0 ml-auto md:ml-0"
                                                            >
                                                                <a
                                                                    href={doc.spaces_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="flex items-center"
                                                                >
                                                                    <Download className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" aria-hidden="true" />
                                                                    View PDF
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>

                                                    {/* Document Hash */}
                                                    {doc.hash && (
                                                        <div className="mt-3 ml-0 sm:ml-11 max-w-full overflow-hidden">
                                                            <Card className="bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                                                <CardContent className="p-2 sm:p-3">
                                                                    <div className="flex items-center gap-2">
                                                                        <div className="p-1 sm:p-1.5 bg-neutral-200 dark:bg-neutral-700 rounded-md">
                                                                            <Lock className="w-3 h-3 sm:w-3.5 sm:h-3.5 text-primary" />
                                                                        </div>
                                                                        <TooltipProvider>
                                                                            <Tooltip>
                                                                                <TooltipTrigger asChild>
                                                                                    <button
                                                                                        className="group text-[10px] sm:text-xs font-mono text-neutral-600 dark:text-neutral-300 hover:text-primary transition-colors break-all text-left flex-1 truncate cursor-pointer"
                                                                                        onClick={async () => {
                                                                                            try {
                                                                                                if (doc.hash) {
                                                                                                    await navigator.clipboard.writeText(doc.hash);
                                                                                                    toast.success('Hash copied to clipboard', {
                                                                                                        duration: 3000,
                                                                                                        action: {
                                                                                                            label: "Dismiss",
                                                                                                            onClick: () => toast.dismiss()
                                                                                                        }
                                                                                                    });
                                                                                                }
                                                                                            } catch (error) {
                                                                                                console.error('Failed to copy hash:', error);
                                                                                                toast.error('Failed to copy hash. Please try again or copy manually.', {
                                                                                                    duration: 5000,
                                                                                                    action: {
                                                                                                        label: "Dismiss",
                                                                                                        onClick: () => toast.dismiss()
                                                                                                    }
                                                                                                });
                                                                                            }
                                                                                        }}
                                                                                        aria-label="Copy document hash to clipboard"
                                                                                    >
                                                                                        <div className="flex flex-col sm:flex-row sm:items-center w-full">
                                                                                            <code className="opacity-80 group-hover:opacity-100 transition-opacity font-mono flex-1 truncate sm:truncate-none">
                                                                                                {doc.hash}
                                                                                            </code>
                                                                                            <span className="mt-1 sm:mt-0 sm:ml-2 text-neutral-500 group-hover:text-primary opacity-70 group-hover:opacity-100 transition-all text-[10px] sm:text-xs whitespace-nowrap">
                                                                                                (Click to copy)
                                                                                            </span>
                                                                                        </div>
                                                                                    </button>
                                                                                </TooltipTrigger>
                                                                                <TooltipContent side="top">
                                                                                    <p className="text-xs">Click to copy document hash</p>
                                                                                </TooltipContent>
                                                                            </Tooltip>
                                                                        </TooltipProvider>
                                                                    </div>
                                                                </CardContent>
                                                            </Card>
                                                        </div>
                                                    )}

                                                    {/* Phase Metadata */}
                                                    {doc.phase_metadata && (
                                                        <div className="mt-3 sm:mt-4 ml-0 sm:ml-11 max-w-full overflow-hidden">
                                                            <Card className="bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                                                <CardHeader className="p-2.5 sm:p-3.5 pb-1 sm:pb-1.5">
                                                                    <CardTitle className="flex items-center text-xs sm:text-sm font-semibold">
                                                                        <FileCheck className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-primary" />
                                                                        Phase Metadata
                                                                    </CardTitle>
                                                                </CardHeader>
                                                                <CardContent className="p-2.5 sm:p-3.5 pt-1 sm:pt-1">
                                                                    <div className="space-y-2.5 sm:space-y-3.5">
                                                                        {Object.entries(doc.phase_metadata || {}).map(([key, value]) => {
                                                                            if (!value) return null;

                                                                            const iconMap = {
                                                                                submission_date: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />,
                                                                                municipal_offices: <Building className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />,
                                                                                signatory_details: <UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />
                                                                            };

                                                                            const labelMap = {
                                                                                submission_date: "Submission Date",
                                                                                municipal_offices: "Municipal Offices",
                                                                                signatory_details: "Signatory Details"
                                                                            };

                                                                            return (
                                                                                <div key={key} className="flex items-start group">
                                                                                    <div className="mr-1.5 sm:mr-2 mt-0.5 flex-shrink-0">
                                                                                        {iconMap[key as keyof typeof iconMap]}
                                                                                    </div>
                                                                                    <div className="flex-1 min-w-0">
                                                                                        <div className="text-xs sm:text-sm">
                                                                                            <span className="font-medium">
                                                                                                {labelMap[key as keyof typeof labelMap]}:
                                                                                            </span>
                                                                                            <div className="mt-0.5 sm:mt-1 text-neutral-600 dark:text-neutral-400 break-words leading-relaxed text-xs sm:text-sm">
                                                                                                {key === 'submission_date'
                                                                                                    ? new Date(value as string).toLocaleDateString(undefined, {
                                                                                                        year: 'numeric',
                                                                                                        month: 'long',
                                                                                                        day: 'numeric'
                                                                                                    })
                                                                                                    : <div className="line-clamp-3 sm:line-clamp-2 group-hover:line-clamp-none transition-all">
                                                                                                        {value as string}
                                                                                                    </div>
                                                                                                }
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </CardContent>
                                                            </Card>
                                                        </div>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </CardContent>
                        </TabsContent>

                        {/* Timeline Tab */}
                        <TabsContent value="timeline" className="p-0 m-0">
                            <CardHeader className="border-b p-4 sm:p-6">
                                <div className="flex items-center space-x-2 sm:space-x-3 mb-1 sm:mb-2">
                                    <div className="p-1.5 sm:p-2 bg-neutral-100 dark:bg-neutral-800 rounded-lg">
                                        <Clock className="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base sm:text-lg">Event Timeline</CardTitle>
                                        <CardDescription className="text-xs sm:text-sm">Track procurement progress and updates</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-4 sm:p-6">
                                {procurement.events.length === 0 ? (
                                    <div className="text-center py-8 sm:py-12">
                                        <Avatar className="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-neutral-100 dark:bg-neutral-800 mb-3 sm:mb-4 border border-neutral-200 dark:border-neutral-700">
                                            <AvatarFallback className="bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                                                <AlertCircle className="w-6 h-6 sm:w-8 sm:h-8" />
                                            </AvatarFallback>
                                        </Avatar>
                                        <h3 className="text-sm sm:text-base font-semibold mb-1 sm:mb-2">No Events Found</h3>
                                        <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 mb-3 sm:mb-4">No events have been recorded for this procurement yet.</p>
                                        <Button size="sm" className="sm:h-10 text-xs sm:text-sm">
                                            <Plus className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
                                            Add Event
                                        </Button>
                                    </div>
                                ) : (
                                    <ol className="relative border-l-2 border-neutral-200 dark:border-neutral-700 ml-1 sm:ml-3 pt-2 space-y-6 sm:space-y-8">
                                        {procurement.events.map((event, index) => (
                                            <li key={event.timestamp || index} className="ml-6 sm:ml-8">
                                                <span className="absolute flex items-center justify-center -left-3 sm:-left-4">
                                                    {getEventIcon(event.event_type)}
                                                </span>
                                                <time className="mb-1 sm:mb-2 text-[10px] sm:text-xs font-medium leading-none text-neutral-500 dark:text-neutral-400 flex items-center">
                                                    <Clock className="w-3 h-3 sm:w-3.5 sm:h-3.5 mr-1 sm:mr-1.5 text-neutral-400 dark:text-neutral-500" />
                                                    {formatDate(event.timestamp)}
                                                </time>
                                                <h3 className="text-sm sm:text-[15px] font-semibold tracking-tight mb-1.5 sm:mb-2">
                                                    {event.event_type}
                                                </h3>
                                                <Card className="bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                                    <CardContent className="p-2.5 sm:p-3.5">
                                                        <p className="text-xs sm:text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                                                            {event.details}
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </TabsContent>
                    </Card>
                </Tabs>
            </div>
        </AppLayout>
    );
}


import { useState, useMemo, JSX, FC } from 'react';
import {
    FileText, Hash, Clock, RefreshCw, Lock, Download, FileCheck,
    CheckCircle, XCircle, Upload, AlertCircle, Calendar, Building, UserRound,
    HardDrive, PhilippinePeso, Users
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { BreadcrumbItem } from '@/types';
import { cn } from '@/lib/utils';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { TooltipProvider, Tooltip, TooltipTrigger, TooltipContent } from "@/components/ui/tooltip";
import { toast } from "sonner";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";
import { Status as StatusInfor } from "@/types/blockchain";
import { SharedData } from '@/types';

const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '/bac-chairman/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '/hope/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
                { title: 'Procurement Details', href: '#' },
            ];
    }
};

const STAGE_ORDER = [
    'Procurement Initiation',
    'Pre-Procurement Conference',
    'Bidding Documents',
    'Pre-Bid Conference',
    'Supplemental Bid Bulletin',
    'Bid Opening',
    'Bid Evaluation',
    'Post-Qualification',
    'BAC Resolution',
    'Notice Of Award',
    'Performance Bond Contract And PO',
    'Notice To Proceed',
    'Monitoring',
    'Completed'
];


interface Document {
    file_key: string;
    document_type: string;
    spaces_url?: string;
    hash?: string;
    file_size?: number;
    stage?: string;
    stage_metadata?: StageMetadata;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    timestamp?: string;
    document_index?: number;
    formatted_date?: string;
}

interface StageMetadata {
    submission_date?: string;
    municipal_offices?: string;
    signatory_details?: string;
    issuance_date?: string;
    evaluator_names?: string;
    evaluation_date?: string;
    bond_amount?: string;
    bid_value?: string;
    bidder_name?: string;
    opening_date?: string;
    report_date?: string;
    report_notes?: string;
    outcome?: string;
    signing_date?: string;
    pr_number?: string;
    pr_purpose?: string;
    requested_by?: string;
    approved_by?: string;
    appropriation?: string;
    funding_source?: string;
    meeting_date?: string;
    participants?: string;
}

interface Event {
    timestamp: string;
    event_type: string;
    details: string | JSX.Element;
    stage?: string;
    document_count?: number;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    category?: string;
    severity?: string;
    formatted_date?: string;
}

interface Status {
    stage: string;
    current_status: string;
    timestamp: string;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    formatted_date?: string;
}

interface TimelineItem {
    timestamp: string;
    formatted_date: string;
    stage: string;
    status: string;
}

interface Procurement {
    id: string;
    title: string;
    status: Status;
    documents: Document[];
    events: Event[];
    timeline?: TimelineItem[];
}


type ProcessedTimelineItem = {
    timestamp: string;
    formatted_date: string;
    type: 'stage_change' | 'event';
    stageOrder: number;
    content: JSX.Element;
    stage?: string;
};


interface ShowProps {
    procurement: Procurement;
    now?: string;
    error?: string;
}


const formatDate = (dateString?: string): string => {
    if (!dateString) return 'Invalid Date';
    try {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        return 'Invalid Date' + e;
    }
};

const formatDateOnly = (dateString?: string | number): string => {
    if (dateString === null || dateString === undefined) return 'Invalid Date';
    try {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric', month: 'long', day: 'numeric'
        });
    } catch (e) {
        return 'Invalid Date' + e;
    }
};

const formatTimeOnly = (dateString?: string): string => {
    if (!dateString) return 'Invalid Time';
    try {
        return new Date(dateString).toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        return 'Invalid Time' + e;
    }
};


type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | null;
interface StatusInfo {
    variant: BadgeVariant;
    icon: JSX.Element;
    label: string;
}

const getStatusInfo = (statusText?: string): StatusInfo => {
    const safeStatus = statusText || 'Unknown Status';

    const statusMap: Record<string, { variant: BadgeVariant; icon: JSX.Element }> = {
        [StatusInfor.PROCUREMENT_SUBMITTED]: { variant: "default", icon: <FileText className="w-4 h-4" /> },
        [StatusInfor.PRE_PROCUREMENT_CONFERENCE_HELD]: { variant: "secondary", icon: <Users className="w-4 h-4" /> },
        [StatusInfor.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: { variant: "outline", icon: <AlertCircle className="w-4 h-4" /> },
        [StatusInfor.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: { variant: "secondary", icon: <FileCheck className="w-4 h-4" /> },
        [StatusInfor.BIDDING_DOCUMENTS_PUBLISHED]: { variant: "secondary", icon: <Upload className="w-4 h-4" /> },
        [StatusInfor.PRE_BID_CONFERENCE_HELD]: { variant: "secondary", icon: <Users className="w-4 h-4" /> },
        [StatusInfor.PRE_BID_CONFERENCE_SKIPPED]: { variant: "outline", icon: <AlertCircle className="w-4 h-4" /> },
        [StatusInfor.PRE_BID_CONFERENCE_COMPLETED]: { variant: "secondary", icon: <FileCheck className="w-4 h-4" /> },
        [StatusInfor.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: { variant: "default", icon: <RefreshCw className="w-4 h-4" /> },
        [StatusInfor.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: { variant: "secondary", icon: <FileCheck className="w-4 h-4" /> },
        [StatusInfor.BIDS_OPENED]: { variant: "outline", icon: <FileText className="w-4 h-4" /> },
        [StatusInfor.BIDS_EVALUATED]: { variant: "default", icon: <CheckCircle className="w-4 h-4" /> },
        [StatusInfor.POST_QUALIFICATION_VERIFIED]: { variant: "secondary", icon: <CheckCircle className="w-4 h-4" /> },
        [StatusInfor.POST_QUALIFICATION_FAILED]: { variant: "destructive", icon: <XCircle className="w-4 h-4" /> },
        [StatusInfor.RESOLUTION_RECORDED]: { variant: "default", icon: <FileText className="w-4 h-4" /> },
        [StatusInfor.AWARDED]: { variant: "secondary", icon: <CheckCircle className="w-4 h-4" /> },
        [StatusInfor.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: { variant: "outline", icon: <FileText className="w-4 h-4" /> },
        [StatusInfor.NTP_RECORDED]: { variant: "default", icon: <Clock className="w-4 h-4" /> },
        [StatusInfor.MONITORING]: { variant: "secondary", icon: <FileCheck className="w-4 h-4" /> },
        [StatusInfor.COMPLETION_DOCUMENTS_UPLOADED]: { variant: "outline", icon: <FileText className="w-4 h-4" /> },
        [StatusInfor.COMPLETED]: { variant: "default", icon: <CheckCircle className="w-4 h-4" /> },
    };

    const defaultStatus = {
        variant: "outline" as const,
        icon: <AlertCircle className="w-4 h-4" />
    };

    const status = statusMap[safeStatus] || defaultStatus;

    return {
        ...status,
        label: safeStatus
    };
};

const getDocumentIcon = (): JSX.Element => {
    return <FileText className="w-6 h-6 text-red-500" />;
};

const formatFileSize = (bytes?: number): string => {
    if (bytes === undefined || bytes === null || isNaN(bytes) || bytes < 0) return 'N/A';
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(i > 1 ? 1 : 0));

    return `${size} ${units[i]}`;
};


const getDocumentstage = (doc: Document): string => {
    if (doc.stage) {
        const stageIdLower = doc.stage.toLowerCase();
        if (stageIdLower.includes('pr') && stageIdLower.includes('initiation')) {
            return 'Procurement Initiation';
        }
        if (stageIdLower === 'completed' || stageIdLower.includes('complet')) {
            return 'Completed';
        }
        const knownstage = STAGE_ORDER.find(p => p.toLowerCase() === stageIdLower);
        if (knownstage) return knownstage;

        return doc.stage;
    }

    return 'Procurement Initiation';
};

const createDocumentCountElement = (count?: number): JSX.Element | null => {
    if (!count || count <= 0) return null;
    return (
        <div className="mt-2 text-xs font-medium px-2 py-1 bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-800/50 text-blue-700 dark:text-blue-300 rounded-md inline-flex items-center">
            <FileText className="w-3.5 h-3.5 mr-1.5" />
            {count} {count === 1 ? 'document' : 'documents'} processed
        </div>
    );
};


interface ProcurementHeaderProps {
    title: string;
    id: string;
    status?: Status;
}
const ProcurementHeader: FC<ProcurementHeaderProps> = ({ title, id, status }) => {
    const statusInfo = getStatusInfo(status?.current_status);
    const stageIndex = status?.stage ? STAGE_ORDER.indexOf(status.stage) + 1 : 0;
    const totalStages = STAGE_ORDER.length;
    const progress = stageIndex > 0 ? (stageIndex / totalStages) * 100 : 0;

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border mb-4">
            {/* Background accent */}
            <div
                className="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent dark:from-primary/10 dark:to-transparent"
                aria-hidden="true"
            />

            {/* Stage progress indicator */}
            {status?.stage && (
                <div className="absolute bottom-0 left-0 right-0 h-1.5 bg-neutral-100 dark:bg-neutral-800">
                    <div
                        className="h-full bg-gradient-to-r from-primary/80 to-primary transition-all duration-500 ease-out"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            )}

            <CardHeader className="relative z-10 p-5 sm:p-7">
                <div className="space-y-4">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        {/* Left side - Title and ID */}
                        <div className="space-y-3">
                            <div>
                                <CardTitle className="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight leading-tight">
                                    {title}
                                </CardTitle>
                                <div className="flex items-center mt-2 text-[14px] sm:text-[15px] text-neutral-600 dark:text-neutral-400">
                                    <div className="flex items-center">
                                        <Hash className="w-4 h-4 mr-2 text-primary shrink-0" />
                                        <span className="font-semibold mr-2">ID:</span>
                                        <code className="font-mono bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded text-xs sm:text-sm tracking-wide truncate max-w-[150px] sm:max-w-xs md:max-w-sm">
                                            {id}
                                        </code>
                                    </div>
                                </div>
                            </div>

                            {status?.timestamp && (
                                <div className="inline-flex items-center px-3 py-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-[14px]">
                                    <Calendar className="w-3.5 h-3.5 mr-2 text-primary shrink-0" />
                                    <span className="font-medium mr-2">Last Updated:</span>
                                    <time dateTime={status.timestamp} className="text-neutral-600 dark:text-neutral-400">
                                        {formatDate(status.timestamp)}
                                    </time>
                                </div>
                            )}
                        </div>

                        {/* Right side - Status badges with improved visual design */}
                        <div className="flex flex-col items-start lg:items-end gap-3">
                            {status?.stage && (
                                <div className="flex flex-col gap-1.5">
                                    <div className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                                        Current Stage
                                    </div>
                                    <Badge
                                        variant="default"
                                        className="flex items-center gap-2 px-3 py-1.5 text-base bg-primary/10 text-primary border border-primary/20 font-semibold"
                                    >
                                        <Clock className="w-4 h-4" />
                                        {status.stage}
                                    </Badge>
                                    <div className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                        Stage {stageIndex} of {totalStages}
                                    </div>
                                </div>
                            )}

                            {status?.current_status && (
                                <div className="flex flex-col gap-1.5">
                                    <div className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                                        Status
                                    </div>
                                    <Badge
                                        variant={statusInfo.variant}
                                        className="flex items-center gap-2 px-3 py-1.5 text-base"
                                    >
                                        {statusInfo.icon}
                                        <span className="font-medium">{statusInfo.label}</span>
                                    </Badge>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </CardHeader>
        </Card>
    );
};


interface MetadataItemProps {
    icon: JSX.Element;
    label: string;
    value?: string | number | null;
    highlight?: boolean;
}
const MetadataItem: FC<MetadataItemProps> = ({ icon, label, value, highlight = false }) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    return (
        <div className={cn(
            "flex items-start group p-2 rounded-md transition-colors duration-200 ease-in-out",
            highlight ? "bg-blue-50/70 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40" :
                "hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
        )}>
            <div className={cn(
                "mr-2 sm:mr-2.5 mt-0.5 flex-shrink-0 p-1.5 rounded-md",
                highlight ? "text-blue-600 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/40" :
                    "text-primary bg-primary/10"
            )}>
                {icon}
            </div>
            <div className="flex-1 min-w-0">
                <span className="font-medium text-neutral-700 dark:text-neutral-300 text-xs uppercase tracking-wide">{label}</span>
                <div className="mt-1 text-neutral-800 dark:text-neutral-200 break-words leading-relaxed font-medium">
                    <div className="line-clamp-2 group-hover:line-clamp-none transition-all duration-200 ease-in-out">
                        {value}
                    </div>
                </div>
            </div>
        </div>
    );
};

interface DocumentMetadataProps {
    metadata?: StageMetadata | null;
}
const DocumentMetadata: FC<DocumentMetadataProps> = ({ metadata }) => {
    if (!metadata || Object.values(metadata).every(v => !v)) {
        return null;
    }

    const metadataMap: Array<{ key: keyof StageMetadata; label: string; icon: JSX.Element; format?: (val: string | number | undefined) => string }> = [
        { key: 'pr_number', label: 'PR Number', icon: <FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'pr_purpose', label: 'PR Purpose', icon: <FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'requested_by', label: 'Requested By', icon: <UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'approved_by', label: 'Approved By', icon: <UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'appropriation', label: 'Appropriation', icon: <PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: (v) => `₱ ${v}` },
        { key: 'funding_source', label: 'Funding Source', icon: <PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'meeting_date', label: 'Meeting Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'participants', label: 'Participants', icon: <Users className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'submission_date', label: 'Submission Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'issuance_date', label: 'Issuance Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'opening_date', label: 'Opening Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'bidder_name', label: 'Bidder Name', icon: <UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'bid_value', label: 'Bid Value', icon: <PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: (v) => `₱ ${v}` },
        { key: 'evaluation_date', label: 'Evaluation Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'evaluator_names', label: 'Evaluator Names', icon: <Users className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'outcome', label: 'Verification Outcome', icon: metadata?.outcome === 'Verified' ? <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-500" /> : <XCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-500" /> },
        { key: 'signatory_details', label: 'Signatory Details', icon: <UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'bond_amount', label: 'Bond Amount', icon: <PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: (v) => `₱ ${v}` },
        { key: 'signing_date', label: 'Signing Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'report_date', label: 'Report Date', icon: <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4" />, format: formatDateOnly },
        { key: 'report_notes', label: 'Report Notes', icon: <FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
        { key: 'municipal_offices', label: 'Municipal Offices', icon: <Building className="w-3.5 h-3.5 sm:w-4 sm:h-4" /> },
    ];

    return (
        <div className="mt-3.5 ml-0 sm:ml-[58px] max-w-full overflow-hidden">
            <Card className="bg-gradient-to-r from-neutral-50/80 to-neutral-50/80 dark:from-neutral-800/60 dark:to-neutral-800/60 border border-neutral-200/80 dark:border-neutral-700/80 shadow-sm hover:shadow transition-all duration-200">
                <CardHeader className="p-3 sm:p-4 pb-1.5 sm:pb-2">
                    <CardTitle className="flex items-center text-xs sm:text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                        <FileCheck className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-primary" />
                        Document Metadata
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-3 sm:p-4 pt-0">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
                        {metadataMap.map(({ key, label, icon, format }) => (
                            <MetadataItem
                                key={key}
                                icon={icon}
                                label={label}
                                value={metadata[key] ? (format ? format(metadata[key]) : String(metadata[key])) : undefined}
                            />
                        ))}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};


interface DocumentItemProps {
    doc: Document;
}
const DocumentItem: FC<DocumentItemProps> = ({ doc }) => {
    const handleCopyHash = async () => {
        if (!doc.hash) return;
        try {
            await navigator.clipboard.writeText(doc.hash);
            toast.success('Hash copied to clipboard', { duration: 3000 });
        } catch (error) {
            toast.error('Failed to copy hash.', {
                description: String(error),
                duration: 5000
            });
        }
    };

    return (
        <li className="group p-4 sm:p-5 hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 transition-all duration-200 ease-in-out border-l-2 border-transparent hover:border-primary">
            <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div className="flex items-start flex-1 min-w-0">
                    <div className="p-2.5 sm:p-3 bg-gradient-to-br from-neutral-100 to-neutral-50 dark:from-neutral-800 dark:to-neutral-800/70 rounded-lg border border-neutral-200 dark:border-neutral-700/80 shadow-sm flex-shrink-0 mr-3.5 sm:mr-4 transition-all duration-200 ease-in-out group-hover:shadow group-hover:border-neutral-300 dark:group-hover:border-neutral-600">
                        {getDocumentIcon()}
                    </div>
                    <div className="flex-1 min-w-0 pt-0.5">
                        <h4 className="text-sm sm:text-base font-medium tracking-tight mb-1.5 truncate group-hover:text-primary transition-colors duration-200" title={doc.document_type}>
                            {doc.document_type || 'Unnamed Document'}
                        </h4>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[12px] sm:text-[13px] text-neutral-500 dark:text-neutral-400">
                            <TooltipProvider delayDuration={300}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex items-center font-mono text-xs bg-neutral-100 dark:bg-neutral-700/50 px-1.5 py-0.5 rounded cursor-help max-w-[180px] sm:max-w-[250px] truncate">
                                            <Hash className="w-3 h-3 mr-1 text-neutral-500 dark:text-neutral-400" />
                                            {doc.file_key}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="max-w-xs">
                                        <p className="text-xs font-medium">Document Key:</p>
                                        <p className="text-xs break-all">{doc.file_key}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            {doc.file_size !== undefined && (
                                <span className="flex items-center">
                                    <HardDrive className="w-3 h-3 sm:w-3.5 sm:h-3.5 mr-1" aria-hidden="true" />
                                    {formatFileSize(doc.file_size)}
                                </span>
                            )}
                            {doc.timestamp && (
                                <span className="flex items-center">
                                    <Calendar className="w-3 h-3 sm:w-3.5 sm:h-3.5 mr-1" aria-hidden="true" />
                                    {formatDateOnly(doc.timestamp)}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    className="flex-shrink-0 transition-all font-medium border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm h-8 sm:h-9 mt-2 md:mt-0 self-start md:self-center shadow-sm hover:shadow group-hover:border-primary/30 group-hover:bg-white dark:group-hover:bg-neutral-800"
                >
                    <a
                        href={doc.spaces_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center"
                    >
                        <Download className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5" aria-hidden="true" />
                        View PDF
                    </a>
                </Button>
            </div>

            {doc.hash && (
                <div className="mt-3.5 ml-0 sm:ml-[58px] max-w-full overflow-hidden">
                    <Card className="bg-gradient-to-r from-blue-50/80 to-neutral-50/80 dark:from-blue-900/20 dark:to-neutral-800/60 border border-blue-100/80 dark:border-blue-800/30 shadow-sm hover:shadow transition-all duration-200">
                        <CardContent className="p-3 sm:p-4 flex items-center justify-between">
                            <div className="flex items-center gap-2.5 flex-1 min-w-0">
                                <div className="p-1.5 bg-blue-100/80 dark:bg-blue-900/40 rounded-md border border-blue-200 dark:border-blue-800/50 shadow-sm">
                                    <Lock className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-[10px] uppercase tracking-wider text-neutral-500 dark:text-neutral-400 font-medium mb-0.5">Document Hash</p>
                                    <code className="font-mono text-xs sm:text-sm text-neutral-700 dark:text-neutral-300 truncate block">
                                        {doc.hash}
                                    </code>
                                </div>
                            </div>
                            <TooltipProvider delayDuration={300}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 px-2.5 text-xs text-neutral-600 hover:text-primary dark:text-neutral-400 dark:hover:text-primary-light"
                                            onClick={handleCopyHash}
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mr-1.5">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1-2 2v1"></path>
                                            </svg>
                                            Copy Hash
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="text-xs">
                                        Copy document hash to clipboard
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </CardContent>
                    </Card>
                </div>
            )}

            <DocumentMetadata metadata={doc.stage_metadata} />
        </li>
    );
};


interface DocumentSectionProps {
    documentsBystage: Record<string, Document[]>;
    sortedstageKeys: string[];
    totalDocuments: number;
}
const DocumentSection: FC<DocumentSectionProps> = ({ documentsBystage, sortedstageKeys, totalDocuments }) => {
    if (totalDocuments === 0) {
        return (
            <CardContent className="p-0">
                <div className="p-6 sm:p-12 text-center">
                    <Avatar className="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-neutral-100 dark:bg-neutral-800 mb-3 sm:mb-4 border border-neutral-200 dark:border-neutral-700">
                        <AvatarFallback className="bg-transparent text-neutral-400 dark:text-neutral-500">
                            <FileText className="w-6 h-6 sm:w-8 sm:h-8" />
                        </AvatarFallback>
                    </Avatar>
                    <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No documents uploaded yet.</p>
                </div>
            </CardContent>
        );
    }

    if (sortedstageKeys.length === 0) {
        return (
            <CardContent className="p-6">
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Categorization Issue</AlertTitle>
                    <AlertDescription>
                        Documents exist but could not be categorized by stage. Please check the data.
                    </AlertDescription>
                </Alert>
            </CardContent>
        )
    }

    return (
        <>
            <CardHeader className="border-b p-4 sm:p-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                    <div className="flex items-center space-x-2 sm:space-x-3">
                        <div className="p-1.5 sm:p-2 bg-neutral-100 dark:bg-neutral-800 rounded-lg">
                            <FileText className="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                        </div>
                        <div>
                            <CardTitle className="text-base sm:text-lg">Procurement Documents</CardTitle>
                            <CardDescription className="text-xs sm:text-sm">Documents organized by stage</CardDescription>
                        </div>
                    </div>
                    <Badge variant="outline" className="text-xs sm:text-sm self-start sm:self-center">
                        {totalDocuments} {totalDocuments === 1 ? 'Document' : 'Documents'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-hidden">
                    {sortedstageKeys.map(stage => (
                        <div key={stage} className="border-b border-neutral-200 dark:border-neutral-700 last:border-b-0">
                            <div className="p-3 sm:p-4 bg-neutral-50 dark:bg-neutral-800/50 sticky top-0 z-10 backdrop-blur-sm">
                                <h3 className="font-semibold text-sm sm:text-base flex items-center text-neutral-700 dark:text-neutral-200">
                                    <FileCheck className="mr-2 h-4 w-4 text-primary flex-shrink-0" />
                                    {stage} ({documentsBystage[stage].length})
                                </h3>
                            </div>
                            <ul className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                {documentsBystage[stage].map((doc) => (
                                    <DocumentItem key={doc.file_key || doc.hash || doc.document_type} doc={doc} />
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </CardContent>
        </>
    );
};


export default function ShowProcurement({ procurement, now, error }: ShowProps) {
    const [activeTab, setActiveTab] = useState('documents');

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    const documentsBystage = useMemo(() => {
        if (!procurement?.documents) return {};

        const grouped = procurement.documents.reduce((acc: Record<string, Document[]>, doc) => {
            const stage = getDocumentstage(doc);
            if (!acc[stage]) {
                acc[stage] = [];
            }
            acc[stage].push(doc);
            return acc;
        }, {});

        Object.keys(grouped).forEach(stage => {
            const uniqueDocs = new Map<string, Document>();
            grouped[stage]
                .sort((a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0))
                .forEach(doc => {
                    const key = doc.document_type || doc.file_key;
                    if (!uniqueDocs.has(key)) {
                        uniqueDocs.set(key, doc);
                    }
                });
            grouped[stage] = Array.from(uniqueDocs.values())
                .sort((a, b) => (a.timestamp ? new Date(a.timestamp).getTime() : 0) - (b.timestamp ? new Date(b.timestamp).getTime() : 0));
        });


        return grouped;
    }, [procurement?.documents]);

    const sortedstageKeys = useMemo(() => {
        const stageKeys = Object.keys(documentsBystage);
        return stageKeys.sort((a, b) => {
            const aIndex = STAGE_ORDER.indexOf(a);
            const bIndex = STAGE_ORDER.indexOf(b);

            if (aIndex === -1 && bIndex === -1) return a.localeCompare(b);
            if (aIndex === -1) return 1;
            if (bIndex === -1) return -1;
            return aIndex - bIndex;
        });
    }, [documentsBystage]);

    const totalDocuments = useMemo(() => procurement?.documents?.length ?? 0, [procurement?.documents]);

    const timelineItemsByDate = useMemo(() => {
        const combinedItems: Array<Omit<ProcessedTimelineItem, 'content' | 'stageOrder'> & { raw: TimelineItem | Event }> = [];

        (procurement?.timeline ?? []).forEach(item => {
            combinedItems.push({
                timestamp: item.timestamp,
                formatted_date: formatDate(item.timestamp),
                type: 'stage_change',
                stage: item.stage,
                raw: item,
            });
        });

        (procurement?.events ?? []).forEach(event => {
            combinedItems.push({
                timestamp: event.timestamp,
                formatted_date: formatDate(event.timestamp),
                type: 'event',
                stage: event.stage,
                raw: event,
            });
        });

        combinedItems.sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());

        const itemsByDate: Record<string, ProcessedTimelineItem[]> = {};

        combinedItems.forEach((item) => {
            const date = formatDateOnly(item.timestamp);
            const stageIndex = item.stage ? STAGE_ORDER.findIndex(p => p.toLowerCase() === item.stage?.toLowerCase()) : 999;

            let itemContent: JSX.Element;
            if (item.type === 'stage_change') {
                const stageItem = item.raw as TimelineItem;
                const statusInfo = getStatusInfo(stageItem.status);
                itemContent = (
                    <div className="relative group">
                        <div className="flex justify-between items-start">
                            <div className="flex-shrink-0 flex flex-col items-center mr-4">
                                <div className={cn(
                                    "flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full text-white text-xs font-bold shadow-md transition-all duration-300 z-10",
                                    "bg-gradient-to-br from-primary to-blue-500 dark:from-primary/80 dark:to-blue-600",
                                    "group-hover:scale-110 group-hover:shadow-primary/30"
                                )}>
                                    {stageIndex + 1 > 0 && stageIndex + 1 < 999 ? stageIndex + 1 : '?'}
                                </div>
                                <div className="w-0.5 h-24 bg-gradient-to-b from-primary/40 to-primary/20 dark:from-primary/30 dark:to-primary/10"></div>
                            </div>
                            <div className="flex-1 pt-0.5 transition-all duration-300">
                                <div className="mb-1.5 flex items-center gap-2 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                                    <time dateTime={stageItem.timestamp} className="font-medium bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded">
                                        {formatTimeOnly(stageItem.timestamp)}
                                    </time>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm sm:text-base font-semibold tracking-tight text-neutral-800 dark:text-neutral-100 group-hover:text-primary transition-colors">
                                            Stage Transition
                                        </h3>
                                        <Badge variant="default" className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            {stageItem.stage}
                                        </Badge>
                                    </div>
                                    <Card className="bg-gradient-to-r from-blue-50/70 to-white dark:from-blue-900/20 dark:to-gray-800 shadow-sm border border-blue-200 dark:border-blue-800/50 transition-all duration-300 group-hover:shadow-md group-hover:border-blue-300 dark:group-hover:border-blue-700/50">
                                        <CardHeader className="p-3 sm:p-4 pb-0 flex flex-row items-center justify-between gap-2">
                                            <CardTitle className="text-sm sm:text-base font-medium">
                                                Status: <span className="font-semibold">{stageItem.status}</span>
                                            </CardTitle>
                                            <Badge variant={statusInfo.variant} className="px-2 py-0.5 text-xs">
                                                {statusInfo.icon}
                                            </Badge>
                                        </CardHeader>
                                        <CardContent className="p-3 sm:p-4 pt-2">
                                            <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                                Procurement moved to <strong className="text-blue-700 dark:text-blue-400">{stageItem.stage}</strong> stage
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            } else {
                const eventItem = item.raw as Event;
                let eventDetails: string | JSX.Element = eventItem.details;
                const docCountElement = createDocumentCountElement(eventItem.document_count);

                if (docCountElement) {
                    eventDetails = <>{eventItem.details}{docCountElement}</>;
                }

                itemContent = (
                    <div className="relative group">
                        <div className="flex justify-between items-start">
                            <div className="flex-shrink-0 flex flex-col items-center mr-4">
                                <div className={cn(
                                    "flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full text-white text-xs font-bold shadow-md transition-all duration-300 z-10",
                                    "bg-gradient-to-br from-neutral-400 to-neutral-500 dark:from-neutral-500 dark:to-neutral-600",
                                    "group-hover:scale-110 group-hover:shadow-primary/30"
                                )}>
                                    <FileText className="h-3.5 w-3.5" />
                                </div>
                                <div className="w-0.5 h-24 bg-gradient-to-b from-primary/40 to-primary/20 dark:from-primary/30 dark:to-primary/10"></div>
                            </div>
                            <div className="flex-1 pt-0.5 transition-all duration-300">
                                <div className="mb-1.5 flex items-center gap-2 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                                    <time dateTime={eventItem.timestamp} className="font-medium bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded">
                                        {formatTimeOnly(eventItem.timestamp)}
                                    </time>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm sm:text-base font-semibold tracking-tight text-neutral-800 dark:text-neutral-100 group-hover:text-primary transition-colors capitalize">
                                            {eventItem.event_type.replace(/_/g, ' ')}
                                        </h3>
                                        {eventItem.stage && (
                                            <Badge variant="outline" className="text-[10px] sm:text-xs">{eventItem.stage}</Badge>
                                        )}
                                        {eventItem.category && (
                                            <Badge variant="secondary" className="text-[10px] sm:text-xs capitalize">{eventItem.category}</Badge>
                                        )}
                                    </div>
                                    <Card className="bg-gradient-to-r from-neutral-50/70 to-white dark:from-neutral-800/30 dark:to-gray-800 shadow-sm border border-neutral-200 dark:border-neutral-700/80 transition-all duration-300 group-hover:shadow-md">
                                        <CardContent className="p-3 sm:p-4 text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                            {eventDetails}
                                        </CardContent>
                                    </Card>
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
                content: itemContent
            });
        });

        return itemsByDate;
    }, [procurement?.timeline, procurement?.events]);


    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Error - Procurement Details" />
                <div className="p-4 sm:p-6">
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Error Loading Procurement</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                </div>
            </AppLayout>
        );
    }

    if (!procurement) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Loading Procurement..." />
                <div className="p-4 sm:p-6 text-center text-neutral-500">Loading details...</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Procurement: ${procurement.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4">

                <ProcurementHeader
                    title={procurement.title}
                    id={procurement.id}
                    status={procurement.status} // This is now safely optional
                />

                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="mb-4 grid grid-cols-2 w-full max-w-[320px]">
                        <TabsTrigger value="documents" className="flex items-center gap-1.5 sm:gap-2">
                            <FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                            Documents
                        </TabsTrigger>
                        <TabsTrigger value="timeline" className="flex items-center gap-1.5 sm:gap-2">
                            <Clock className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                            Timeline
                        </TabsTrigger>
                    </TabsList>

                    <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border">
                        <TabsContent value="documents" className="p-0 m-0">
                            <DocumentSection
                                documentsBystage={documentsBystage}
                                sortedstageKeys={sortedstageKeys}
                                totalDocuments={totalDocuments}
                            />
                        </TabsContent>

                        <TabsContent value="timeline" className="p-0 m-0">
                            <CardHeader className="border-b p-4 sm:p-6 bg-gradient-to-r from-primary/5 to-transparent dark:from-primary/10 dark:to-transparent">
                                <div className="flex items-center space-x-2 sm:space-x-3">
                                    <div className="p-1.5 sm:p-2 bg-primary/10 rounded-lg">
                                        <Clock className="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base sm:text-lg">Event Timeline</CardTitle>
                                        <CardDescription className="text-xs sm:text-sm">Chronological record of procurement events and stage changes</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="relative">
                                    {/* Timeline content - by date */}
                                    <div className="space-y-4 py-6 px-4 sm:px-6">
                                        {Object.keys(timelineItemsByDate).map((date) => (
                                            <div key={date} className="mb-12 last:mb-6 relative">
                                                {/* Date header */}
                                                <div className="sticky top-0 z-10 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm py-3 -mx-4 sm:-mx-6 px-4 sm:px-6 mb-6">
                                                    <div className="flex items-center">
                                                        <div className="h-0.5 w-6 bg-primary/40 mr-3"></div>
                                                        <Badge variant="outline" className="text-sm font-medium bg-white dark:bg-gray-800 shadow-sm border-primary/20 text-primary dark:text-primary-light px-3 py-1.5">
                                                            <Calendar className="mr-2 h-4 w-4" />
                                                            {date}
                                                        </Badge>
                                                        <div className="h-0.5 flex-1 bg-gradient-to-r from-primary/40 to-transparent ml-3"></div>
                                                    </div>
                                                </div>

                                                {/* Timeline items for this date */}
                                                <div className="space-y-8">
                                                    {timelineItemsByDate[date].map((item, itemIndex) => (
                                                        <div key={`${item.timestamp}-${itemIndex}`} className="relative group animate-fadeIn">
                                                            {item.content}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}

                                        {/* Timeline end marker */}
                                        <div className="relative py-6 text-center">
                                            <div className="absolute inset-0 flex items-center" aria-hidden="true">
                                                <div className="w-full border-t border-dashed border-neutral-200 dark:border-neutral-700"></div>
                                            </div>
                                            <div className="relative flex justify-center">
                                                <div className="bg-white dark:bg-gray-900 px-4 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
                                                    End of Timeline
                                                </div>
                                            </div>
                                        </div>

                                        {/* Current time indicator */}
                                        {now && (
                                            <div className="relative mt-2 pt-3 pb-4 px-4 bg-primary/5 dark:bg-primary/10 rounded-lg border border-primary/20 dark:border-primary/30">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-5 w-5 rounded-full bg-primary animate-pulse"></div>
                                                    <div>
                                                        <p className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Current Time</p>
                                                        <p className="text-sm font-semibold text-primary">
                                                            {formatDate(now)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </TabsContent>
                    </Card>
                </Tabs>
            </div>
        </AppLayout>
    );
}
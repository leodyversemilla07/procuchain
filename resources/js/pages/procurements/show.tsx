import { useState, useMemo, JSX, Fragment, FC } from 'react';
import {
    FileText, Hash, Clock, Plus, RefreshCw, Lock, Download, FileCheck,
    CheckCircle, XCircle, Upload, AlertCircle, Calendar, Building, UserRound,
    HardDrive, PhilippinePeso, Users
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
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
import { Status as StatusInfor} from "@/types/blockchain";

const BREADCRUMBS: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
    { title: 'Procurement Details', href: '#' },
];


const STAGE_ORDER = [
    'Procurement Initiation',
    'Pre-Procurement',
    'Bid Invitation',
    'Bid Opening',
    'Bid Evaluation',
    'Post-Qualification',
    'BAC Resolution',
    'Notice Of Award',
    'Performance Bond',
    'Contract And PO',
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
        [StatusInfor.PRE_PROCUREMENT_COMPLETED]: { variant: "secondary", icon: <FileCheck className="w-4 h-4" /> },
        [StatusInfor.BIDDING_DOCUMENTS_PUBLISHED]: { variant: "secondary", icon: <Upload className="w-4 h-4" /> },
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

const getTimelineIcon = (type: string, category?: string, documentCount?: number): { icon: JSX.Element; bgColor: string; textColor: string; } => {
    const typeLower = type.toLowerCase();

    if (isDocumentUploadEvent(type, category, documentCount)) {
        return { icon: <FileCheck className="w-4 h-4" />, bgColor: '#f0f9ff', textColor: '#0284c7' };
    }

    if (typeLower.includes('create') || typeLower.includes('submit') || typeLower.includes('initiat')) {
        return { icon: <Plus className="w-4 h-4" />, bgColor: '#dcfce7', textColor: '#16a34a' };
    }
    if (typeLower.includes('update') || typeLower.includes('change') || typeLower.includes('modify')) {
        return { icon: <RefreshCw className="w-4 h-4" />, bgColor: '#dbeafe', textColor: '#2563eb' };
    }
    if (typeLower.includes('approve') || typeLower.includes('confirm') || typeLower.includes('complete') || typeLower.includes('award') || typeLower.includes('verify')) {
        return { icon: <CheckCircle className="w-4 h-4" />, bgColor: '#d1fae5', textColor: '#059669' };
    }
    if (typeLower.includes('reject') || typeLower.includes('decline') || typeLower.includes('fail')) {
        return { icon: <XCircle className="w-4 h-4" />, bgColor: '#fee2e2', textColor: '#dc2626' };
    }
    if (typeLower.includes('publish') || typeLower.includes('open')) {
        return { icon: <Upload className="w-4 h-4" />, bgColor: '#f3e8ff', textColor: '#9333ea' };
    }

    return { icon: <AlertCircle className="w-4 h-4" />, bgColor: '#f3f4f6', textColor: '#6b7280' };
};

const isDocumentUploadEvent = (eventType: string, category?: string, documentCount?: number): boolean => {
    const typeLower = eventType.toLowerCase();
    return typeLower.includes('document_upload') ||
        typeLower.includes('upload') ||
        typeLower.includes('document processed') ||
        (category?.toLowerCase() === 'document_management' && typeof documentCount === 'number' && documentCount > 0);
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

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border mb-4">
            <CardHeader className="relative z-10 p-4 sm:p-6">
                <div className="space-y-3 sm:space-y-4">
                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 sm:gap-4">
                        <div className="space-y-2 sm:space-y-3">
                            <CardTitle className="text-xl sm:text-2xl md:text-3xl font-semibold tracking-tight leading-tight">
                                {title}
                            </CardTitle>
                            <CardDescription className="space-y-2 text-neutral-600 dark:text-neutral-400">
                                <div className="flex items-center text-[14px] sm:text-[15px]">
                                    <Hash className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 sm:mr-2.5 text-primary shrink-0" />
                                    <span className="font-semibold mr-2 sm:mr-2.5">ID:</span>
                                    <code className="font-mono bg-neutral-100 dark:bg-neutral-800 px-1.5 sm:px-2 py-0.5 rounded text-xs sm:text-sm tracking-wide truncate max-w-[150px] sm:max-w-xs md:max-w-sm">
                                        {id}
                                    </code>
                                </div>
                                {status?.timestamp && (
                                    <div className="flex items-center text-[14px] sm:text-[15px]">
                                        <Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 sm:mr-2.5 text-primary shrink-0" />
                                        <span className="font-semibold mr-2 sm:mr-2.5">Last Update:</span>
                                        <time dateTime={status.timestamp} className="text-sm sm:text-base">{formatDate(status.timestamp)}</time>
                                    </div>
                                )}
                            </CardDescription>
                        </div>
                        <div className="flex flex-col items-start lg:items-end gap-2 mt-2 sm:mt-0">
                            {status?.stage && (
                                <Badge 
                                    variant="default"
                                    className="flex items-center px-3 py-1.5 text-sm sm:text-base bg-primary/10 text-primary border-primary/20 font-semibold"
                                >
                                    <Clock className="w-4 h-4 sm:w-5 sm:h-5 mr-2" />
                                    Stage: {status.stage}
                                </Badge>
                            )}
                            {status?.current_status && (
                                <Badge
                                    variant={statusInfo.variant}
                                    className="flex items-center px-2 sm:px-2.5 py-0.5 text-xs"
                                >
                                    <span className="mr-1.5">{statusInfo.icon}</span>
                                    <span className="font-medium">Status: {statusInfo.label}</span>
                                </Badge>
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
}
const MetadataItem: FC<MetadataItemProps> = ({ icon, label, value }) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    return (
        <div className="flex items-start group text-xs sm:text-sm">
            <div className="mr-1.5 sm:mr-2 mt-0.5 flex-shrink-0 text-primary">
                {icon}
            </div>
            <div className="flex-1 min-w-0">
                <span className="font-medium text-neutral-800 dark:text-neutral-200">{label}:</span>
                <div className="mt-0.5 text-neutral-600 dark:text-neutral-400 break-words leading-relaxed">
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
        <div className="mt-3 sm:mt-4 ml-0 sm:ml-[46px] max-w-full overflow-hidden">
            <Card className="bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 shadow-sm">
                <CardHeader className="p-2.5 sm:p-3.5 pb-1.5 sm:pb-2">
                    <CardTitle className="flex items-center text-xs sm:text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                        <FileCheck className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-primary" />
                        Document Metadata
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-2.5 sm:p-3.5 pt-0">
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
            console.error('Failed to copy hash:', error);
            toast.error('Failed to copy hash.', { duration: 5000 });
        }
    };

    return (
        <li className="p-4 sm:p-5 hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors duration-150 ease-in-out">
            <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div className="flex items-start group flex-1 min-w-0">
                    <div className="p-2 sm:p-2.5 bg-neutral-100 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 flex-shrink-0 mr-3 sm:mr-3.5">
                        {getDocumentIcon()}
                    </div>
                    <div className="flex-1 min-w-0 pt-0.5">
                        <h4 className="text-sm sm:text-base font-medium tracking-tight mb-1 truncate" title={doc.document_type}>
                            {doc.document_type || 'Unnamed Document'}
                        </h4>
                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[12px] sm:text-[13px] text-neutral-500 dark:text-neutral-400">
                            <TooltipProvider delayDuration={300}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="font-mono text-xs bg-neutral-100 dark:bg-neutral-700/50 px-1.5 py-0.5 rounded cursor-help max-w-[180px] sm:max-w-[250px] truncate">
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
                        </div>
                    </div>
                </div>

                {doc.spaces_url && (
                    <Button
                        variant="outline"
                        size="sm"
                        asChild
                        className="flex-shrink-0 transition-colors font-medium border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm h-8 sm:h-9 mt-2 md:mt-0 self-start md:self-center"
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
                )}
            </div>

            {doc.hash && (
                <div className="mt-3 ml-0 sm:ml-[46px] max-w-full overflow-hidden">
                    <Card className="bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 shadow-sm hover:shadow transition-all duration-200">
                        <CardContent className="p-2.5 sm:p-3.5 flex items-center justify-between">
                            <div className="flex items-center gap-2 flex-1 min-w-0">
                                <div className="p-1.5 bg-blue-50 dark:bg-blue-900/30 rounded-md border border-blue-100 dark:border-blue-800/50">
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
                                            className="h-8 px-2 text-xs text-neutral-600 hover:text-primary dark:text-neutral-400 dark:hover:text-primary-light"
                                            onClick={handleCopyHash}
                                        >
                                            <span className="sr-only">Copy hash</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mr-1.5">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1-2 2v1"></path>
                                            </svg>
                                            Copy
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


interface TimelineItemRendererProps {
    item: ProcessedTimelineItem;
    isLast: boolean;
}
const TimelineItemRenderer: FC<TimelineItemRendererProps> = ({ item }) => {
    const { content } = item;

    return <Fragment>{content}</Fragment>;
};


interface TimelineSectionProps {
    timelineItemsByDate: Record<string, ProcessedTimelineItem[]>;
    now?: string;
}
const TimelineSection: FC<TimelineSectionProps> = ({ timelineItemsByDate, now }) => {
    const dates = useMemo(() => Object.keys(timelineItemsByDate), [timelineItemsByDate]);

    if (dates.length === 0) {
        return (
            <CardContent className="p-0">
                <div className="p-6 sm:p-12 text-center">
                    <Avatar className="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-neutral-100 dark:bg-neutral-800 mb-3 sm:mb-4 border border-neutral-200 dark:border-neutral-700">
                        <AvatarFallback className="bg-transparent text-neutral-400 dark:text-neutral-500">
                            <Clock className="w-6 h-6 sm:w-8 sm:h-8" />
                        </AvatarFallback>
                    </Avatar>
                    <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No timeline events recorded yet.</p>
                </div>
            </CardContent>
        );
    }

    return (
        <>
            <CardHeader className="border-b p-4 sm:p-6">
                <div className="flex items-center space-x-2 sm:space-x-3">
                    <div className="p-1.5 sm:p-2 bg-neutral-100 dark:bg-neutral-800 rounded-lg">
                        <Clock className="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                    </div>
                    <div>
                        <CardTitle className="text-base sm:text-lg">Event Timeline</CardTitle>
                        <CardDescription className="text-xs sm:text-sm">Chronological record of procurement events and stage changes</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-4 sm:p-6">
                <div className="relative pl-6 sm:pl-8">
                    <div aria-hidden="true" className="absolute left-[calc(1.5rem+1px)] sm:left-[calc(2rem+1px)] top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary/80 via-primary/50 to-primary/20 z-0 -translate-x-1/2"></div>

                    {dates.map((date, dateIndex) => (
                        <div key={date} className="mb-8 last:mb-0">
                            {dates.length > 1 && (
                                <div className="sticky top-0 z-10 -ml-6 sm:-ml-8 mb-6 py-2 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm transition-all duration-300">
                                    <div className="ml-6 sm:ml-8">
                                        <Badge variant="secondary" className="text-sm font-semibold shadow-sm">
                                            <Calendar className="mr-2 h-4 w-4" />
                                            {date}
                                        </Badge>
                                    </div>
                                </div>
                            )}

                            <div className="space-y-8">
                                {timelineItemsByDate[date].map((item, itemIndex) => (
                                    <TimelineItemRenderer
                                        key={`${item.timestamp}-${itemIndex}`}
                                        item={item}
                                        isLast={dateIndex === dates.length - 1 && itemIndex === timelineItemsByDate[date].length - 1}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}

                    <div className="relative pt-4 group">
                        <div className="absolute left-[1.5rem] sm:left-[2rem] top-4 -translate-x-1/2 z-10">
                            <div className="h-5 w-5 rounded-full bg-neutral-300 dark:bg-neutral-600 border-4 border-white dark:border-gray-900 transition-all duration-300 group-hover:bg-neutral-400 dark:group-hover:bg-neutral-500"></div>
                        </div>
                        <div className="ml-4 sm:ml-6 pt-[2px]">
                            <div className="text-xs text-neutral-500 dark:text-neutral-400 italic font-medium transition-colors duration-300 group-hover:text-neutral-600 dark:group-hover:text-neutral-300">
                                End of timeline
                            </div>
                        </div>
                    </div>

                    {now && (
                        <div className="relative mt-6 pt-2">
                            <div className="absolute left-[1.5rem] sm:left-[2rem] top-2 -translate-x-1/2 z-20">
                                <div className="h-5 w-5 rounded-full bg-primary border-4 border-white dark:border-gray-900 animate-pulse"></div>
                            </div>
                            <div className="ml-4 sm:ml-6">
                                <div className="text-xs font-medium text-primary flex items-center">
                                    <Clock className="w-3.5 h-3.5 mr-1.5 animate-pulse" />
                                    Current time: {formatDate(now)}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>
        </>
    );
};


export default function Show({ procurement, now, error }: ShowProps) {
    const [activeTab, setActiveTab] = useState('documents');


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
        let lastStage: string | undefined = undefined;

        combinedItems.forEach((item) => {
            const date = formatDateOnly(item.timestamp);
            const stageIndex = item.stage ? STAGE_ORDER.findIndex(p => p.toLowerCase() === item.stage?.toLowerCase()) : 999;
            const currentStage = item.stage;
            const showstageHeader = item.stage && item.stage !== lastStage;
            lastStage = item.stage;

            if (!itemsByDate[date]) {
                itemsByDate[date] = [];

                lastStage = undefined;
            }

            let itemContent: JSX.Element;
            if (item.type === 'stage_change') {
                const stageItem = item.raw as TimelineItem;
                const statusInfo = getStatusInfo(stageItem.status);
                itemContent = (
                    <div className="relative group">
                        <div className="absolute left-[1.5rem] sm:left-[2rem] top-1 transform -translate-x-1/2 z-10">
                            <div className={cn(
                                "h-8 w-8 sm:h-9 sm:w-9 rounded-full ring-4 ring-white dark:ring-gray-900",
                                "flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg",
                                "bg-blue-100 dark:bg-blue-900/50"
                            )}>
                                <RefreshCw className="h-4 w-4 sm:h-5 sm:w-5 text-blue-600 dark:text-blue-400 transition-transform duration-500 group-hover:rotate-180" />
                            </div>
                        </div>
                        <div className="ml-4 sm:ml-6 pl-4 sm:pl-5 pt-0.5 transition-all duration-300 group-hover:translate-x-1">
                            <div className="mb-1.5 flex flex-col sm:flex-row sm:items-center gap-x-2 gap-y-0.5 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                                <time dateTime={stageItem.timestamp}>{formatTimeOnly(stageItem.timestamp)}</time>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h3 className="text-sm sm:text-base font-semibold tracking-tight text-neutral-800 dark:text-neutral-100 group-hover:text-primary transition-colors">
                                        stage Transition
                                    </h3>
                                    <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        {stageItem.stage}
                                    </Badge>
                                </div>
                                <Card className="bg-white dark:bg-gray-800 shadow-sm border border-neutral-200 dark:border-neutral-700 transition-all duration-300 group-hover:shadow-md group-hover:border-primary/20">
                                    <CardHeader className="p-3 sm:p-4 pb-0 flex flex-row items-center justify-between gap-2">
                                        <CardTitle className="text-sm sm:text-base font-medium">
                                            Status: <span className="font-semibold" style={{ color: statusInfo.variant === 'destructive' ? '#dc2626' : 'inherit' }}>{stageItem.status}</span>
                                        </CardTitle>
                                        <Badge variant={statusInfo.variant} className="px-1.5 py-0 text-xs">
                                            {statusInfo.icon}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent className="p-3 sm:p-4 pt-2">
                                        <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                            Procurement moved to <strong>{stageItem.stage}</strong> stage.
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                );
            } else {
                const eventItem = item.raw as Event;
                const iconInfo = getTimelineIcon(eventItem.event_type, eventItem.category, eventItem.document_count);
                let eventDetails: string | JSX.Element = eventItem.details;
                const docCountElement = createDocumentCountElement(eventItem.document_count);

                if (docCountElement) {
                    eventDetails = <>{eventItem.details}{docCountElement}</>;
                }

                itemContent = (
                    <div className="relative group">
                        <div className="absolute left-[1.5rem] sm:left-[2rem] top-1 transform -translate-x-1/2 z-10">
                            <div className={cn(
                                "h-8 w-8 sm:h-9 sm:w-9 rounded-full ring-4 ring-white dark:ring-gray-900",
                                "flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg"
                            )} style={{ backgroundColor: iconInfo.bgColor, color: iconInfo.textColor }}>
                                {iconInfo.icon}
                            </div>
                        </div>
                        <div className="ml-4 sm:ml-6 pl-4 sm:pl-5 pt-0.5 transition-all duration-300 group-hover:translate-x-1">
                            <div className="mb-1.5 flex flex-col sm:flex-row sm:items-center gap-x-2 gap-y-0.5 text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                                <time dateTime={eventItem.timestamp}>{formatTimeOnly(eventItem.timestamp)}</time>
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

                                <Card className="bg-white dark:bg-gray-800 shadow-sm border border-neutral-200 dark:border-neutral-700 transition-all duration-300 group-hover:shadow-md group-hover:border-primary/20">
                                    <CardContent className="p-3 sm:p-4 text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                        {eventDetails}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                );
            }

            let stageHeader: JSX.Element | null = null;
            if (showstageHeader && currentStage) {
                const stageNum = stageIndex + 1;
                stageHeader = (
                    <div className="relative my-6 group -ml-6 sm:-ml-8">
                        <div className="absolute left-[1.5rem] sm:left-[2rem] top-1/2 transform -translate-x-1/2 -translate-y-1/2 z-20">
                            <div className={cn(
                                "flex items-center justify-center h-7 w-7 rounded-full text-white text-xs font-bold shadow-md transition-all duration-300",
                                "bg-gradient-to-br from-primary to-blue-500 dark:from-primary/80 dark:to-blue-600",
                                "group-hover:scale-110 group-hover:shadow-primary/30"
                            )}>
                                {stageNum > 0 && stageNum < 999 ? stageNum : '?'}
                            </div>
                        </div>
                        <div className="ml-6 sm:ml-8 pl-4 sm:pl-5">
                            <div className="h-px bg-gradient-to-r from-primary/50 via-primary/20 to-transparent dark:from-primary/40 dark:via-primary/20 dark:to-transparent w-full relative">
                                <span className={cn(
                                    "absolute -top-2.5 left-0 bg-white dark:bg-gray-900 pr-3 text-xs font-semibold tracking-wider uppercase",
                                    "text-primary dark:text-primary-light transition-all duration-300 group-hover:text-primary/90 group-hover:translate-y-[-1px]"
                                )}>
                                    stage: {currentStage}
                                </span>
                            </div>
                        </div>
                    </div>
                );
            }


            itemsByDate[date].push({
                ...item,
                stageOrder: stageIndex,
                content: <>{stageHeader}{itemContent}</>
            });
        });


        return itemsByDate;
    }, [procurement?.timeline, procurement?.events]);


    if (error) {
        return (
            <AppLayout breadcrumbs={BREADCRUMBS}>
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
            <AppLayout breadcrumbs={BREADCRUMBS}>
                <Head title="Loading Procurement..." />
                <div className="p-4 sm:p-6 text-center text-neutral-500">Loading details...</div>
            </AppLayout>
        );
    }


    return (
        <AppLayout breadcrumbs={BREADCRUMBS}>
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
                            <TimelineSection
                                timelineItemsByDate={timelineItemsByDate}
                                now={now}
                            />
                        </TabsContent>
                    </Card>
                </Tabs>
            </div>
        </AppLayout>
    );
}
import {
    AlertCircle,
    Building,
    Calendar,
    CheckCircle,
    Clock,
    Eye,
    FileCheck,
    FileText,
    HardDrive,
    Hash,
    Lock,
    PoundSterlingIcon as PhilippinePeso,
    RefreshCw,
    TrendingUp,
    Upload,
    UserRound,
    Users,
    XCircle,
} from 'lucide-react';
import { useMemo, useState, type FC, type JSX } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

// ============================================================================
// TYPES AND INTERFACES
// ============================================================================

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface StageMetadata {
    submission_date?: string;
    municipal_offices?: string;
    signatory_details?: string;
    issuance_date?: string;
    document_type?: string;
    validity_period?: {
        start_date: string;
        end_date: string;
    };
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
    bulletin_number?: string;
    bulletin_title?: string;
    issue_date?: string;
    completion_date?: string;
    completion_notes?: string;
}

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

type BadgeVariant = 'default' | 'destructive' | 'outline' | 'secondary' | null;

interface StatusInfo {
    variant: BadgeVariant;
    icon: JSX.Element;
    label: string;
}

interface ShowProps {
    procurement: Procurement;
    now?: string;
    error?: string;
}

// ============================================================================
// CONSTANTS
// ============================================================================

const Status = {
    PROCUREMENT_SUBMITTED: 'PROCUREMENT_SUBMITTED',
    PRE_PROCUREMENT_CONFERENCE_HELD: 'PRE_PROCUREMENT_CONFERENCE_HELD',
    PRE_PROCUREMENT_CONFERENCE_SKIPPED: 'PRE_PROCUREMENT_CONFERENCE_SKIPPED',
    PRE_PROCUREMENT_CONFERENCE_COMPLETED: 'PRE_PROCUREMENT_CONFERENCE_COMPLETED',
    BIDDING_DOCUMENTS_PUBLISHED: 'BIDDING_DOCUMENTS_PUBLISHED',
    PRE_BID_CONFERENCE_HELD: 'PRE_BID_CONFERENCE_HELD',
    PRE_BID_CONFERENCE_SKIPPED: 'PRE_BID_CONFERENCE_SKIPPED',
    PRE_BID_CONFERENCE_COMPLETED: 'PRE_BID_CONFERENCE_COMPLETED',
    SUPPLEMENTAL_BID_BULLETINS_ONGOING: 'SUPPLEMENTAL_BID_BULLETINS_ONGOING',
    SUPPLEMENTAL_BID_BULLETINS_COMPLETED: 'SUPPLEMENTAL_BID_BULLETINS_COMPLETED',
    BIDS_OPENED: 'BIDS_OPENED',
    BIDS_EVALUATED: 'BIDS_EVALUATED',
    POST_QUALIFICATION_VERIFIED: 'POST_QUALIFICATION_VERIFIED',
    POST_QUALIFICATION_FAILED: 'POST_QUALIFICATION_FAILED',
    RESOLUTION_RECORDED: 'RESOLUTION_RECORDED',
    AWARDED: 'AWARDED',
    PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED: 'PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED',
    NTP_RECORDED: 'NTP_RECORDED',
    MONITORING_COMPLETED: 'MONITORING_COMPLETED',
    COMPLETION_DOCUMENTS_UPLOADED: 'COMPLETION_DOCUMENTS_UPLOADED',
    COMPLETED: 'COMPLETED',
} as const;

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
    'Notice of Award',
    'Performance Bond, Contract and PO',
    'Notice to Proceed',
    'Monitoring',
    'Completed',
];

const STAGE_DESCRIPTIONS: Record<string, string> = {
    'Procurement Initiation': 'Initial request and approval of procurement',
    'Pre-Procurement Conference': 'Planning and preparation before bidding',
    'Bidding Documents': 'Publication and release of bidding requirements',
    'Pre-Bid Conference': 'Meeting with prospective bidders to clarify requirements',
    'Supplemental Bid Bulletin': 'Additional information or clarification for bidders',
    'Bid Opening': 'Public opening and recording of submitted bids',
    'Bid Evaluation': 'Technical and financial assessment of bids',
    'Post-Qualification': "Verification of winning bidder's qualifications",
    'BAC Resolution': 'BAC recommendation of award to winning bidder',
    'Notice of Award': 'Official notification to winning bidder',
    'Performance Bond, Contract and PO': 'Finalization of contract documents',
    'Notice to Proceed': 'Authorization to begin project implementation',
    Monitoring: 'Oversight of project implementation',
    Completed: 'All procurement activities have been completed',
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

const formatFileSize = (bytes?: number): string => {
    if (bytes === undefined || bytes === null || isNaN(bytes) || bytes < 0) return 'N/A';
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = Number.parseFloat((bytes / Math.pow(1024, i)).toFixed(i > 1 ? 1 : 0));

    return `${size} ${units[i]}`;
};

const formatStageName = (stage: string): string => {
    if (!stage) return 'Procurement Initiation';

    const stageIdLower = stage.toLowerCase();

    const stageMapping: Record<string, string> = {
        procurementinitiation: 'Procurement Initiation',
        preprocurementconference: 'Pre-Procurement Conference',
        biddingdocuments: 'Bidding Documents',
        prebidconference: 'Pre-Bid Conference',
        supplementalbidbulletin: 'Supplemental Bid Bulletin',
        bidopening: 'Bid Opening',
        bidevaluation: 'Bid Evaluation',
        postqualification: 'Post-Qualification',
        bacresolution: 'BAC Resolution',
        noticeofaward: 'Notice of Award',
        performancebondcontractandpo: 'Performance Bond, Contract and PO',
        noticetoproceed: 'Notice to Proceed',
        ntp: 'Notice to Proceed',
        monitoring: 'Monitoring',
        completion: 'Completed',
        completed: 'Completed',
    };

    if (stageMapping[stageIdLower]) {
        return stageMapping[stageIdLower];
    }

    // Handle partial matches for flexibility
    if (stageIdLower.includes('procurement') && stageIdLower.includes('initiation')) {
        return 'Procurement Initiation';
    }
    if (stageIdLower.includes('preprocurement') || stageIdLower.includes('pre-procurement')) {
        return 'Pre-Procurement Conference';
    }
    if (stageIdLower.includes('bidding') && stageIdLower.includes('documents')) {
        return 'Bidding Documents';
    }
    if (stageIdLower.includes('prebid') || stageIdLower.includes('pre-bid')) {
        return 'Pre-Bid Conference';
    }
    if (stageIdLower.includes('supplemental') && stageIdLower.includes('bid')) {
        return 'Supplemental Bid Bulletin';
    }
    if (stageIdLower.includes('bid') && stageIdLower.includes('opening')) {
        return 'Bid Opening';
    }
    if (stageIdLower.includes('bid') && stageIdLower.includes('evaluation')) {
        return 'Bid Evaluation';
    }
    if (stageIdLower.includes('post') && stageIdLower.includes('qualification')) {
        return 'Post-Qualification';
    }
    if (stageIdLower.includes('bac') && stageIdLower.includes('resolution')) {
        return 'BAC Resolution';
    }
    if (stageIdLower.includes('notice') && stageIdLower.includes('award')) {
        return 'Notice of Award';
    }
    if (stageIdLower.includes('performance') && stageIdLower.includes('bond')) {
        return 'Performance Bond, Contract and PO';
    }
    if (stageIdLower.includes('notice') && stageIdLower.includes('proceed')) {
        return 'Notice to Proceed';
    }
    if (stageIdLower.includes('monitoring')) {
        return 'Monitoring';
    }
    if (stageIdLower === 'completed' || stageIdLower.includes('complet')) {
        return 'Completed';
    }

    const knownStage = STAGE_ORDER.find((p) => p.toLowerCase() === stageIdLower);
    if (knownStage) return knownStage;

    const titleCase = stage
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, (str) => str.toUpperCase())
        .trim();

    return titleCase;
};

const getDocumentStage = (doc: Document): string => {
    return formatStageName(doc.stage || 'Procurement Initiation');
};

const shortenHash = (hash?: string, startLength = 5, endLength = 5): string => {
    if (!hash) return 'N/A';
    if (hash.length <= startLength + endLength) return hash;
    return `${hash.substring(0, startLength)}...${hash.substring(hash.length - endLength)}`;
};

const formatDate = (dateString?: string): string => {
    if (!dateString) return 'Invalid Date';
    try {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    } catch (e) {
        return `Invalid Date ${e}`;
    }
};

const formatDateOnly = (dateString?: string | number): string => {
    if (dateString === null || dateString === undefined) return 'Invalid Date';
    try {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch (e) {
        return `Invalid Date ${e}`;
    }
};

const formatTimeOnly = (dateString?: string): string => {
    if (!dateString) return 'Invalid Time';
    try {
        return new Date(dateString).toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    } catch (e) {
        return `Invalid Time ${e}`;
    }
};

const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '/bac-secretariat/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Bids and Awards Committee Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '/bac-chairman/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Head of Procuring Entity Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '/hope/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Procurement List', href: '/admin/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '/procurements-list' },
                { title: 'Procurement Details', href: '#' },
            ];
    }
};

const getStatusInfo = (statusText?: string): StatusInfo => {
    const safeStatus = statusText || 'Unknown Status';

    const statusMap: Record<string, { variant: BadgeVariant; icon: JSX.Element }> = {
        PROCUREMENT_SUBMITTED: { variant: 'default', icon: <FileText className="h-4 w-4" /> },
        PRE_PROCUREMENT_CONFERENCE_HELD: { variant: 'secondary', icon: <Users className="h-4 w-4" /> },
        PRE_PROCUREMENT_CONFERENCE_SKIPPED: { variant: 'outline', icon: <AlertCircle className="h-4 w-4" /> },
        PRE_PROCUREMENT_CONFERENCE_COMPLETED: { variant: 'secondary', icon: <FileCheck className="h-4 w-4" /> },
        BIDDING_DOCUMENTS_PUBLISHED: { variant: 'secondary', icon: <Upload className="h-4 w-4" /> },
        PRE_BID_CONFERENCE_HELD: { variant: 'secondary', icon: <Users className="h-4 w-4" /> },
        PRE_BID_CONFERENCE_SKIPPED: { variant: 'outline', icon: <AlertCircle className="h-4 w-4" /> },
        PRE_BID_CONFERENCE_COMPLETED: { variant: 'secondary', icon: <FileCheck className="h-4 w-4" /> },
        SUPPLEMENTAL_BID_BULLETINS_ONGOING: { variant: 'default', icon: <RefreshCw className="h-4 w-4" /> },
        SUPPLEMENTAL_BID_BULLETINS_COMPLETED: { variant: 'secondary', icon: <FileCheck className="h-4 w-4" /> },
        BIDS_OPENED: { variant: 'outline', icon: <FileText className="h-4 w-4" /> },
        BIDS_EVALUATED: { variant: 'default', icon: <CheckCircle className="h-4 w-4" /> },
        POST_QUALIFICATION_VERIFIED: { variant: 'secondary', icon: <CheckCircle className="h-4 w-4" /> },
        POST_QUALIFICATION_FAILED: { variant: 'destructive', icon: <XCircle className="h-4 w-4" /> },
        RESOLUTION_RECORDED: { variant: 'default', icon: <FileText className="h-4 w-4" /> },
        AWARDED: { variant: 'secondary', icon: <CheckCircle className="h-4 w-4" /> },
        PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED: { variant: 'outline', icon: <FileText className="h-4 w-4" /> },
        NTP_RECORDED: { variant: 'default', icon: <Clock className="h-4 w-4" /> },
        MONITORING_COMPLETED: { variant: 'secondary', icon: <FileCheck className="h-4 w-4" /> },
        COMPLETION_DOCUMENTS_UPLOADED: { variant: 'outline', icon: <FileText className="h-4 w-4" /> },
        COMPLETED: { variant: 'default', icon: <CheckCircle className="h-4 w-4" /> },
    };

    const defaultStatus = {
        variant: 'outline' as const,
        icon: <AlertCircle className="h-4 w-4" />,
    };

    const status = statusMap[safeStatus] || defaultStatus;

    return {
        ...status,
        label: safeStatus,
    };
};

const getDocumentIcon = (): JSX.Element => {
    return <FileText className="text-destructive h-6 w-6" />;
};

// ============================================================================
// COMPONENT INTERFACES
// ============================================================================

interface DocumentMetadataProps {
    metadata?: StageMetadata | null;
}

interface DocumentItemProps {
    doc: Document;
}

interface MetadataItemProps {
    icon: JSX.Element;
    label: string;
    value?: string | number | null;
    highlight?: boolean;
}

interface ProcurementHeaderProps {
    title: string;
    id: string;
    status?: Status;
}

// ============================================================================
// SUB-COMPONENTS
// ============================================================================

const MetadataItem: FC<MetadataItemProps> = ({ icon, label, value, highlight = false }) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    return (
        <div className={`flex items-start gap-3 border-b p-3 last:border-b-0 ${highlight ? 'bg-primary/5 border-primary/20' : ''}`}>
            <div className="text-muted-foreground mt-0.5">{icon}</div>
            <div className="min-w-0 flex-1">
                <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{label}</div>
                <div className={`text-sm break-words ${highlight ? 'text-primary font-semibold' : 'text-foreground'}`}>{value}</div>
            </div>
        </div>
    );
};

const DocumentProcessedCount: FC<{ count: number }> = ({ count }) => {
    if (count === 0) return null;

    return (
        <div className="bg-muted border-border text-muted-foreground mt-2 inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
            <FileText className="mr-1.5 h-3.5 w-3.5" />
            {count} {count === 1 ? 'document' : 'documents'} processed
        </div>
    );
};

const DocumentMetadata: FC<DocumentMetadataProps> = ({ metadata }) => {
    if (!metadata || Object.values(metadata).every((v) => !v)) {
        return null;
    }

    const metadataMap: Array<{
        key: keyof StageMetadata;
        label: string;
        icon: JSX.Element;
        format?: (val: string | number | undefined) => string;
    }> = [
        { key: 'pr_number', label: 'PR Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'pr_purpose', label: 'PR Purpose', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'requested_by', label: 'Requested By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'approved_by', label: 'Approved By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'appropriation',
            label: 'Appropriation',
            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: (v) => `₱ ${v}`,
        },
        { key: 'funding_source', label: 'Funding Source', icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'meeting_date',
            label: 'Meeting Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        { key: 'participants', label: 'Participants', icon: <Users className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'submission_date',
            label: 'Submission Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        {
            key: 'issuance_date',
            label: 'Issuance Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        {
            key: 'opening_date',
            label: 'Opening Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        { key: 'bidder_name', label: 'Bidder Name', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'bid_value',
            label: 'Bid Value',
            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: (v) => `₱ ${v}`,
        },
        {
            key: 'evaluation_date',
            label: 'Evaluation Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
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
            format: (v) => `₱ ${v}`,
        },
        {
            key: 'signing_date',
            label: 'Signing Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        {
            key: 'report_date',
            label: 'Report Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        { key: 'report_notes', label: 'Report Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'municipal_offices', label: 'Municipal Offices', icon: <Building className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'bulletin_number', label: 'Bulletin Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'bulletin_title', label: 'Bulletin Title', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'issue_date',
            label: 'Issue Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        {
            key: 'completion_date',
            label: 'Completion Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            format: formatDateOnly,
        },
        { key: 'completion_notes', label: 'Completion Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
    ];

    const renderMetadataItem = (
        key: keyof StageMetadata,
        item: { label: string; icon: JSX.Element; format?: (val: string | number | undefined) => string },
    ) => {
        if (key === 'validity_period' && metadata.validity_period) {
            return (
                <div key={key} className="col-span-2">
                    <div className="group flex items-start rounded-md p-2 transition-colors duration-200 ease-in-out hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                        <div className="text-primary bg-primary/10 mt-0.5 mr-2 flex-shrink-0 rounded-md p-1.5 sm:mr-2.5">
                            <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <span className="text-xs font-medium tracking-wide text-neutral-700 uppercase dark:text-neutral-300">
                                Validity Period
                            </span>
                            <div className="mt-1 leading-relaxed font-medium break-words text-neutral-800 dark:text-neutral-200">
                                <div className="line-clamp-2 transition-all duration-200 ease-in-out group-hover:line-clamp-none">
                                    {`${formatDateOnly(metadata.validity_period.start_date)} - ${formatDateOnly(metadata.validity_period.end_date)}`}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        if ((key === 'bidder_name' || key === 'bid_value') && metadata.document_type === 'Bid Document') {
            return (
                <MetadataItem
                    key={`${key}-${metadata[key]}`}
                    icon={item.icon}
                    label={item.label}
                    value={item.format ? item.format(metadata[key] as string) : (metadata[key] as string)}
                    highlight={true}
                />
            );
        }

        if (metadata[key]) {
            const value = item.format ? item.format(metadata[key] as string) : metadata[key];
            return <MetadataItem key={key} icon={item.icon} label={item.label} value={value as string} />;
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
                    {metadata.document_type === 'Bid Document' && metadata.opening_date && (
                        <MetadataItem
                            icon={<Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />}
                            label="Opening Date"
                            value={formatDateOnly(metadata.opening_date)}
                            highlight={true}
                        />
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
};

const DocumentItem: FC<DocumentItemProps> = ({ doc }) => {
    const handleCopyHash = async () => {
        if (!doc.hash) return;
        try {
            await navigator.clipboard.writeText(doc.hash);
            toast.success('Hash copied to clipboard');
        } catch (error) {
            toast.error('Failed to copy hash: ' + String(error));
        }
    };

    return (
        <li className="border-b p-4 last:border-b-0">
            <div className="flex flex-col gap-4">
                <div className="flex items-start gap-3">
                    <div className="rounded-lg border p-2">{getDocumentIcon()}</div>
                    <div className="min-w-0 flex-1">
                        <h4 className="mb-2 font-medium" title={doc.document_type}>
                            {doc.document_type || 'Unnamed Document'}
                        </h4>
                        <div className="text-muted-foreground flex flex-wrap gap-4 text-sm">
                            <span className="flex items-center gap-1">
                                <Hash className="h-4 w-4" />
                                {shortenHash(doc.file_key, 6, 4)}
                            </span>
                            {doc.file_size !== undefined && (
                                <span className="flex items-center gap-1">
                                    <HardDrive className="h-4 w-4" />
                                    {formatFileSize(doc.file_size)}
                                </span>
                            )}
                            {doc.timestamp && (
                                <span className="flex items-center gap-1">
                                    <Calendar className="h-4 w-4" />
                                    {formatDateOnly(doc.timestamp)}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    <div className={cn('space-y-2', 'flex-1')}>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="group-hover:border-primary/30 group-hover:bg-background h-8 flex-shrink-0 text-xs font-medium shadow-sm transition-all hover:shadow sm:h-9 sm:text-sm"
                            >
                                <Link href={`/pdf-viewer/${encodeURIComponent(doc.file_key)}`} className="flex items-center">
                                    <TrendingUp className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                    View with Analytics
                                </Link>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="group-hover:border-primary/30 group-hover:bg-background h-8 flex-shrink-0 text-xs font-medium shadow-sm transition-all hover:shadow sm:h-9 sm:text-sm"
                            >
                                <a
                                    href={`/secure-file/${encodeURIComponent(doc.file_key)}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center"
                                >
                                    <Eye className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                    Quick View
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Hash Section */}
                <div className="rounded-lg border p-3">
                    <div className="flex items-center justify-between">
                        <div className="flex min-w-0 flex-1 items-center gap-2">
                            <Lock className="text-muted-foreground h-4 w-4" />
                            <div className="min-w-0 flex-1">
                                <div className="text-muted-foreground mb-1 text-xs">Document Hash</div>
                                <code className="font-mono text-sm">{shortenHash(doc.hash)}</code>
                            </div>
                        </div>
                        <Button variant="ghost" size="sm" onClick={handleCopyHash}>
                            Copy
                        </Button>
                    </div>
                </div>

                {/* Metadata Section */}
                {doc.stage_metadata && <DocumentMetadata metadata={doc.stage_metadata} />}
            </div>
        </li>
    );
};

const LastUpdatedTimestamp: FC<{ timestamp?: string }> = ({ timestamp }) => {
    if (!timestamp) return null;

    return (
        <div className="bg-muted inline-flex max-w-full items-center overflow-hidden rounded-full px-2 py-1 text-[12px] sm:px-3 sm:py-1.5 sm:text-[14px]">
            <Calendar className="text-primary mr-1.5 h-3 w-3 shrink-0 sm:mr-2 sm:h-3.5 sm:w-3.5" />
            <span className="mr-1 font-medium whitespace-nowrap sm:mr-2">Last Updated:</span>
            <time dateTime={timestamp} className="text-muted-foreground truncate">
                {formatDate(timestamp)}
            </time>
        </div>
    );
};

const StageDisplay: FC<{ stage: string; stageIndex: number; totalStages: number }> = ({ stage, stageIndex, totalStages }) => {
    const formattedStage = formatStageName(stage);
    const stageDescription = STAGE_DESCRIPTIONS[formattedStage];

    return (
        <div className="flex flex-col gap-2">
            <div className="text-muted-foreground text-sm">Current Stage</div>
            <Badge variant="secondary" className="w-fit">
                <Clock className="mr-2 h-4 w-4" />
                {formattedStage}
            </Badge>
            {stageDescription && <div className="text-muted-foreground text-xs italic">{stageDescription}</div>}
            <div className="text-muted-foreground text-xs">
                Stage {stageIndex} of {totalStages}
            </div>
        </div>
    );
};

const StatusBadge: FC<{ status: string }> = ({ status }) => {
    const statusInfo = getStatusInfo(status);

    return (
        <div className="flex flex-col gap-2">
            <div className="text-muted-foreground text-sm">Status</div>
            <Badge variant={statusInfo.variant} className="w-fit">
                {statusInfo.icon}
                <span className="ml-2">{statusInfo.label}</span>
            </Badge>
        </div>
    );
};

const ProcurementHeader: FC<ProcurementHeaderProps> = ({ title, id, status }) => {
    const stageIndex = status?.stage ? STAGE_ORDER.indexOf(status.stage) + 1 : 0;
    const totalStages = STAGE_ORDER.length;
    const progress = stageIndex > 0 ? (stageIndex / totalStages) * 100 : 0;

    return (
        <Card className="mb-6 border">
            <CardHeader className="pb-4">
                <div className="flex flex-col gap-4">
                    <div>
                        <CardTitle className="mb-2 text-xl font-semibold">{title}</CardTitle>
                        <CardDescription className="text-sm">Procurement ID: {id}</CardDescription>
                    </div>

                    {/* Progress Bar */}
                    {progress > 0 && (
                        <div className="w-full">
                            <div className="text-muted-foreground mb-2 flex justify-between text-sm">
                                <span>Overall Progress</span>
                                <span>{progress.toFixed(0)}%</span>
                            </div>
                            <div className="bg-muted h-2 w-full rounded-full">
                                <div className="bg-primary h-2 rounded-full transition-all duration-300" style={{ width: `${progress}%` }} />
                            </div>
                        </div>
                    )}

                    <div className="flex flex-col gap-4 sm:flex-row">
                        {status?.stage && <StageDisplay stage={status.stage} stageIndex={stageIndex} totalStages={totalStages} />}
                        {status?.current_status && <StatusBadge status={status.current_status} />}
                        {status?.timestamp && <LastUpdatedTimestamp timestamp={status.timestamp} />}
                    </div>
                </div>
            </CardHeader>
        </Card>
    );
};

// ============================================================================
// CUSTOM HOOKS
// ============================================================================

const useDocumentsByStage = (documents?: Document[]) => {
    return useMemo(() => {
        if (!documents) return {};

        const grouped = documents.reduce((acc: Record<string, Document[]>, doc) => {
            const stage = getDocumentStage(doc);
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
            const aIndex = STAGE_ORDER.indexOf(a);
            const bIndex = STAGE_ORDER.indexOf(b);

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
                formatted_date: formatDate(item.timestamp),
                type: 'stage_change',
                stage: item.stage,
                raw: item,
            });
        });
        (events ?? []).forEach((event) => {
            combinedItems.push({
                timestamp: event.timestamp,
                formatted_date: formatDate(event.timestamp),
                type: 'event',
                stage: event.stage,
                raw: event,
            });
        });

        // Sort timeline items with latest first (descending order)
        combinedItems.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

        const itemsByDate: Record<string, ProcessedTimelineItem[]> = {};

        combinedItems.forEach((item) => {
            const date = formatDateOnly(item.timestamp);
            const stageIndex = item.stage ? STAGE_ORDER.findIndex((p) => p.toLowerCase() === item.stage?.toLowerCase()) : 999;

            let itemContent: JSX.Element;
            if (item.type === 'stage_change') {
                const stageItem = item.raw as TimelineItem;
                const statusInfo = getStatusInfo(stageItem.status);
                itemContent = (
                    <div className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium">
                                {stageIndex + 1 > 0 && stageIndex + 1 < 999 ? stageIndex + 1 : '?'}
                            </div>
                            <div className="bg-border mt-2 h-16 w-px"></div>
                        </div>
                        <div className="flex-1 pb-6">
                            <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                                <time dateTime={stageItem.timestamp}>{formatTimeOnly(stageItem.timestamp)}</time>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium">Stage Transition</h3>
                                    <Badge variant="secondary" className="text-xs">
                                        {formatStageName(stageItem.stage)}
                                    </Badge>
                                </div>
                                <div className="bg-muted rounded-lg border p-3">
                                    <div className="mb-1 flex items-center justify-between">
                                        <span className="text-sm font-medium">Status: {stageItem.status}</span>
                                        <Badge variant={statusInfo.variant} className="text-xs">
                                            {statusInfo.icon}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Procurement moved to <strong>{formatStageName(stageItem.stage)}</strong> stage
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            } else {
                const eventItem = item.raw as Event;
                let eventDetails: string | JSX.Element = eventItem.details;
                const docCountElement = eventItem.document_count ? DocumentProcessedCount({ count: eventItem.document_count }) : null;

                if (docCountElement) {
                    eventDetails = (
                        <>
                            {eventItem.details}
                            {docCountElement}
                        </>
                    );
                }

                itemContent = (
                    <div className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full border">
                                <FileText className="text-muted-foreground h-4 w-4" />
                            </div>
                            <div className="bg-border mt-2 h-16 w-px"></div>
                        </div>
                        <div className="flex-1 pb-6">
                            <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                                <time dateTime={eventItem.timestamp}>{formatTimeOnly(eventItem.timestamp)}</time>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium capitalize">{eventItem.event_type.replace(/_/g, ' ')}</h3>
                                    {eventItem.stage && (
                                        <Badge variant="outline" className="text-xs">
                                            {formatStageName(eventItem.stage)}
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

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const breadcrumbs = getBreadcrumbs(userRole);

    const documentsByStage = useDocumentsByStage(procurement?.documents);
    const sortedStageKeys = useSortedStageKeys(documentsByStage);
    const totalDocuments = useMemo(() => procurement?.documents?.length ?? 0, [procurement?.documents]);
    const timelineItemsByDate = useTimelineItems(procurement?.timeline, procurement?.events);

    if (currentError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="p-4 sm:p-6">
                    <div className="border-destructive/50 bg-destructive/10 rounded-lg border p-4">
                        <div className="flex items-center gap-2">
                            <AlertCircle className="text-destructive h-4 w-4" />
                            <h3 className="text-destructive font-medium">Error Loading Procurement</h3>
                        </div>
                        <p className="text-destructive/80 mt-2 text-sm">{currentError}</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (!procurement) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="text-muted-foreground p-4 text-center sm:p-6">Loading details...</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head>
                <title>{procurement.title}</title>
            </Head>
            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <ProcurementHeader title={procurement.title} id={procurement.id} status={procurement.status} />

                <div className="grid w-full grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Documents Column */}
                    <div className="space-y-4">
                        <div className="mb-4 flex items-center space-x-2">
                            <div className="bg-muted rounded-lg p-1.5 sm:p-2">
                                <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                            </div>
                            <h2 className="text-lg font-semibold">Documents</h2>
                        </div>
                        <Card className="border shadow-sm">
                            {totalDocuments === 0 ? (
                                <CardContent className="p-12 text-center">
                                    <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                    <p className="text-muted-foreground">No documents uploaded yet.</p>
                                </CardContent>
                            ) : (
                                <>
                                    <CardHeader className="border-b">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <FileText className="text-primary h-5 w-5" />
                                                <div>
                                                    <CardTitle>Procurement Documents</CardTitle>
                                                    <CardDescription>Documents organized by stage</CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant="outline">
                                                {totalDocuments} {totalDocuments === 1 ? 'Document' : 'Documents'}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        {sortedStageKeys.map((stage, stageIndex) => {
                                            const isLatestStage = stageIndex === 0;

                                            return (
                                                <div key={stage} className="border-b last:border-b-0">
                                                    <div className="bg-muted/50 border-b p-4">
                                                        <div className="flex items-center justify-between">
                                                            <h3 className="flex items-center gap-2 font-semibold">
                                                                <FileCheck className="h-4 w-4" />
                                                                {stage} ({documentsByStage[stage].length})
                                                                {isLatestStage && (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        Latest
                                                                    </Badge>
                                                                )}
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <ul>
                                                        {documentsByStage[stage].map((doc, docIndex) => (
                                                            <DocumentItem key={`${doc.file_key}-${docIndex}`} doc={doc} />
                                                        ))}
                                                    </ul>
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </>
                            )}
                        </Card>
                    </div>

                    {/* Timeline Column */}
                    <div className="space-y-4">
                        <div className="mb-4 flex items-center space-x-2">
                            <div className="bg-muted rounded-lg p-1.5 sm:p-2">
                                <Clock className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                            </div>
                            <h2 className="text-lg font-semibold">Timeline</h2>
                        </div>
                        <Card className="border">
                            <CardHeader className="border-b">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Clock className="text-primary h-5 w-5" />
                                        <div>
                                            <CardTitle>Event Timeline</CardTitle>
                                            <CardDescription>Documents organized by order of creation</CardDescription>
                                        </div>
                                    </div>
                                    <Badge variant="outline" className="text-xs">
                                        Latest First
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="p-4">
                                <div className="space-y-6">
                                    {Object.keys(timelineItemsByDate)
                                        .sort((a, b) => new Date(b).getTime() - new Date(a).getTime())
                                        .map((date, dateIndex) => {
                                            const isFirstDate = dateIndex === 0;

                                            return (
                                                <div key={date} className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b py-2">
                                                        <Calendar className="text-muted-foreground h-4 w-4" />
                                                        <span className="text-sm font-medium">{date}</span>
                                                        {isFirstDate && (
                                                            <Badge variant="secondary" className="text-xs">
                                                                Latest
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    <div className="space-y-4">
                                                        {timelineItemsByDate[date]
                                                            .sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
                                                            .map((item, itemIndex) => (
                                                                <div key={`${item.timestamp}-${itemIndex}`}>{item.content}</div>
                                                            ))}
                                                    </div>
                                                </div>
                                            );
                                        })}

                                    <div className="border-t py-4 text-center">
                                        <span className="text-muted-foreground text-sm">Beginning of Timeline</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

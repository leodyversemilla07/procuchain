import { useState, useEffect, JSX, Fragment } from 'react';
import {
    FileText, Hash, Clock, Plus, RefreshCw, Lock, Download, FileCheck,
    CheckCircle, XCircle, Upload, AlertCircle, Calendar, Building, UserRound, HardDrive, PhilippinePeso, Users
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { TooltipProvider, Tooltip, TooltipTrigger, TooltipContent } from "@/components/ui/tooltip";
import { toast } from "sonner";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/bac-chairman/dashboard' },
    { title: 'Procurement List', href: '/bac-chairman/procurements-list' },
    { title: 'Procurement Details', href: '#' },
];

interface Document {
    file_key: string;
    document_type: string;
    spaces_url?: string;
    hash?: string;
    file_size?: number;
    phase_identifier?: string;
    phase_metadata?: {
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
    };
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
    phase_identifier?: string;
    document_count?: number;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    category?: string;
    severity?: string;
    formatted_date?: string;
}

interface State {
    phase_identifier: string;
    current_state: string;
    timestamp: string;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    formatted_date?: string;
}

interface TimelineItem {
    timestamp: string;
    formatted_date: string;
    phase: string;
    state: string;
}

interface PhaseSummary {
    name: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
    document_count: number;
    event_count: number;
    latest_state: string | null;
}

interface Procurement {
    id: string;
    title: string;
    state: State;
    documents: Document[];
    events: Event[];
    timeline?: TimelineItem[];
    documents_by_phase?: Record<string, Document[]>;
    events_by_phase?: Record<string, Event[]>;
    phase_summary?: Record<string, PhaseSummary>;
    phase_history?: Record<string, TimelineItem[]>;
    current_phase?: string;
    phases?: string[];
}

const phaseOrder = [
    'PR Initiation',
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
    'Other Documents'
];

interface ShowProps {
    procurement: Procurement;
    now?: string;
    error?: string;
}


function formatDate(dateString: string) {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusColor(state: string): { variant: "default" | "destructive" | "outline" | "secondary" | null, icon: JSX.Element } {
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
        }
    };

    const defaultStatus = {
        variant: "outline" as const,
        icon: <AlertCircle className="w-4 h-4 mr-1.5" />
    };

    return states[state.toLowerCase()] || defaultStatus;
}

export default function Show({ procurement, now, error }: ShowProps) {
    const [activeTab, setActiveTab] = useState('documents');

    useEffect(() => {
        console.log("Procurement data details:", {
            id: procurement.id,
            title: procurement.title,
            phases: procurement.phases,
            currentPhase: procurement.current_phase,
            totalDocuments: procurement.documents.length,
            availablePhases: procurement.documents
                .map(d => d.phase_identifier)
                .filter(Boolean)
                .filter((v, i, a) => a.indexOf(v) === i)
        });

        if (procurement.documents.length > 0) {
            console.log("Sample documents:", procurement.documents.slice(0, 3));
        }

        if (procurement.documents_by_phase) {
            console.log("Documents by phase keys:", Object.keys(procurement.documents_by_phase));
        }
    }, [procurement]);

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
        return <FileText className="w-6 h-6 text-red-500" />;
    };

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

    const phaseOrder = procurement.phases || [
        'PR Initiation',
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
        'Other Documents'
    ];

    const normalizePhase = (phase: string): string => {
        if (!phase) return '';
        return phase.toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    };

    const getDocumentPhase = (doc: Document): string => {
        if (doc.phase_identifier) return doc.phase_identifier;

        const docTypeLower = (doc.document_type || '').toLowerCase();

        if (docTypeLower.includes('purchase') ||
            docTypeLower.includes('pr') ||
            docTypeLower === 'aip' ||
            docTypeLower === 'certificate of availability of funds' ||
            docTypeLower === 'caf' ||
            docTypeLower.includes('annual investment plan')) {
            return 'PR Initiation';
        }

        if (doc.file_key) {
            const filePath = doc.file_key.toLowerCase();
            if (filePath.includes('/prinitiation/') ||
                filePath.includes('pr-initiation') ||
                filePath.includes('pr_initiation') ||
                filePath.includes('-purchaserequest') ||
                filePath.includes('-pr-')) {
                return 'PR Initiation';
            }
        }

        return 'Other Documents';
    };

    let docsByPhase = procurement.documents_by_phase || {};

    if (Object.keys(docsByPhase).length === 0 && procurement.documents.length > 0) {
        docsByPhase = procurement.documents.reduce((groups: Record<string, Document[]>, doc) => {
            const phase = getDocumentPhase(doc);
            if (!groups[phase]) groups[phase] = [];
            groups[phase].push(doc);
            return groups;
        }, {});
    }

    if (!docsByPhase['PR Initiation'] && procurement.documents.length > 0) {
        const prInitiationDocs = procurement.documents.filter(doc => {
            const docType = (doc.document_type || '').toLowerCase();
            const fileKey = (doc.file_key || '').toLowerCase();

            return docType.includes('purchase') ||
                docType.includes('pr') ||
                fileKey.includes('purchase') ||
                fileKey.includes('/pr') ||
                fileKey.includes('-pr');
        });

        if (prInitiationDocs.length > 0) {
            docsByPhase['PR Initiation'] = prInitiationDocs;
            console.log(`Found ${prInitiationDocs.length} PR Initiation docs through special detection`);
        }
    }

    console.log("Documents grouped by phase:", Object.keys(docsByPhase).map(phase =>
        `${phase}: ${docsByPhase[phase]?.length || 0}`
    ));

    const sortedPhases = Object.keys(docsByPhase).sort(
        (a, b) => {
            const aIndex = phaseOrder.findIndex(p => normalizePhase(p) === normalizePhase(a));
            const bIndex = phaseOrder.findIndex(p => normalizePhase(p) === normalizePhase(b));

            if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;

            if (aIndex !== -1) return -1;
            if (bIndex !== -1) return 1;

            return a.localeCompare(b);
        }
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement Details" />
            <div className="flex h-full flex-1 flex-col gap-3 sm:gap-4 p-2 sm:p-4 rounded-xl">
                {error ? (
                    <Alert variant="destructive" className="mb-4">
                        <AlertCircle className="h-4 w-4 mr-2" />
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>
                            {error}
                        </AlertDescription>
                    </Alert>
                ) : (
                    <>
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
                                            </div>
                                        ) : sortedPhases.length === 0 ? (
                                            <div className="p-6 sm:p-12 text-center">
                                                <AlertCircle className="mx-auto h-12 w-12 sm:h-16 sm:w-16 text-amber-500 mb-3 sm:mb-4" />
                                                <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-400 mb-3">
                                                    Documents available but not categorized by phase
                                                </p>
                                                <div className="max-w-lg mx-auto">
                                                    <Button
                                                        variant="outline"
                                                        onClick={() => {
                                                            const reprocessedDocs = procurement.documents.reduce((groups: Record<string, Document[]>, doc) => {
                                                                const phase = getDocumentPhase(doc);
                                                                if (!groups[phase]) groups[phase] = [];
                                                                groups[phase].push(doc);
                                                                return groups;
                                                            }, {});

                                                            console.log("Manually re-processed documents:", reprocessedDocs);
                                                        }}
                                                        className="mb-4"
                                                    >
                                                        <RefreshCw className="w-4 h-4 mr-2" />
                                                        Re-categorize Documents
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="overflow-visible">
                                                {sortedPhases.map(phase => (
                                                    <div key={phase} className="border-b border-neutral-200 dark:border-neutral-700 last:border-b-0">
                                                        <div className="p-4 bg-neutral-50 dark:bg-neutral-800/50">
                                                            <h3 className="font-medium text-sm flex items-center">
                                                                <FileText className="mr-2 h-4 w-4 text-primary" />
                                                                {phase} Phase Documents ({docsByPhase[phase].length})
                                                            </h3>
                                                        </div>
                                                        <ul className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                                            {docsByPhase[phase].map((doc, index) => (
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

                                                                    {doc.phase_metadata && Object.keys(doc.phase_metadata).some(key => !!doc.phase_metadata![key as keyof typeof doc.phase_metadata]) && (
                                                                        <div className="mt-3 sm:mt-4 ml-0 sm:ml-11 max-w-full overflow-hidden">
                                                                            <Card className="bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                                                                <CardHeader className="p-2.5 sm:p-3.5 pb-1 sm:pb-1.5">
                                                                                    <CardTitle className="flex items-center text-xs sm:text-sm font-semibold">
                                                                                        <FileCheck className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-primary" />
                                                                                        Document Metadata
                                                                                    </CardTitle>
                                                                                </CardHeader>
                                                                                <CardContent className="p-2.5 sm:p-3.5 pt-1 sm:pt-1">
                                                                                    <div className="space-y-2.5 sm:space-y-3.5">
                                                                                        {doc.phase_metadata.submission_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Submission Date"
                                                                                                value={new Date(doc.phase_metadata.submission_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.issuance_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Issuance Date"
                                                                                                value={new Date(doc.phase_metadata.issuance_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.evaluation_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Evaluation Date"
                                                                                                value={new Date(doc.phase_metadata.evaluation_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.report_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Report Date"
                                                                                                value={new Date(doc.phase_metadata.report_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.signing_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Signing Date"
                                                                                                value={new Date(doc.phase_metadata.signing_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.opening_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Opening Date"
                                                                                                value={new Date(doc.phase_metadata.opening_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.municipal_offices && (
                                                                                            <MetadataItem
                                                                                                icon={<Building className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Municipal Offices"
                                                                                                value={doc.phase_metadata.municipal_offices}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.signatory_details && (
                                                                                            <MetadataItem
                                                                                                icon={<UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Signatory Details"
                                                                                                value={doc.phase_metadata.signatory_details}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.evaluator_names && (
                                                                                            <MetadataItem
                                                                                                icon={<Users className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Evaluator Names"
                                                                                                value={doc.phase_metadata.evaluator_names}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.bond_amount && (
                                                                                            <MetadataItem
                                                                                                icon={<PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Bond Amount"
                                                                                                value={`₱ ${doc.phase_metadata.bond_amount}`}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.bid_value && (
                                                                                            <MetadataItem
                                                                                                icon={<PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Bid Value"
                                                                                                value={`₱ ${doc.phase_metadata.bid_value}`}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.bidder_name && (
                                                                                            <MetadataItem
                                                                                                icon={<UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Bidder Name"
                                                                                                value={doc.phase_metadata.bidder_name}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.report_notes && (
                                                                                            <MetadataItem
                                                                                                icon={<FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Report Notes"
                                                                                                value={doc.phase_metadata.report_notes}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.outcome && (
                                                                                            <MetadataItem
                                                                                                icon={doc.phase_metadata.outcome === 'Verified' ?
                                                                                                    <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-500" /> :
                                                                                                    <XCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-500" />
                                                                                                }
                                                                                                label="Verification Outcome"
                                                                                                value={doc.phase_metadata.outcome}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.pr_number && (
                                                                                            <MetadataItem
                                                                                                icon={<FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="PR Number"
                                                                                                value={doc.phase_metadata.pr_number}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.pr_purpose && (
                                                                                            <MetadataItem
                                                                                                icon={<FileText className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="PR Purpose"
                                                                                                value={doc.phase_metadata.pr_purpose}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.requested_by && (
                                                                                            <MetadataItem
                                                                                                icon={<UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Requested By"
                                                                                                value={doc.phase_metadata.requested_by}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.approved_by && (
                                                                                            <MetadataItem
                                                                                                icon={<UserRound className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Approved By"
                                                                                                value={doc.phase_metadata.approved_by}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.appropriation && (
                                                                                            <MetadataItem
                                                                                                icon={<PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Appropriation"
                                                                                                value={doc.phase_metadata.appropriation}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.funding_source && (
                                                                                            <MetadataItem
                                                                                                icon={<PhilippinePeso className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Funding Source"
                                                                                                value={doc.phase_metadata.funding_source}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.meeting_date && (
                                                                                            <MetadataItem
                                                                                                icon={<Calendar className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Meeting Date"
                                                                                                value={new Date(doc.phase_metadata.meeting_date).toLocaleDateString(undefined, {
                                                                                                    year: 'numeric', month: 'long', day: 'numeric'
                                                                                                })}
                                                                                            />
                                                                                        )}

                                                                                        {doc.phase_metadata.participants && (
                                                                                            <MetadataItem
                                                                                                icon={<Users className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary" />}
                                                                                                label="Participants"
                                                                                                value={doc.phase_metadata.participants}
                                                                                            />
                                                                                        )}
                                                                                    </div>
                                                                                </CardContent>
                                                                            </Card>
                                                                        </div>
                                                                    )}
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </TabsContent>

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
                                        {(procurement.events.length === 0 && (!procurement.timeline || procurement.timeline.length === 0)) ? (
                                            <div className="text-center py-8 sm:py-12">
                                                <Avatar className="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-neutral-100 dark:bg-neutral-800 mb-3 sm:mb-4 border border-neutral-200 dark:border-neutral-700">
                                                    <AvatarFallback className="bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                                                        <AlertCircle className="w-6 h-6 sm:w-8 sm:h-8" />
                                                    </AvatarFallback>
                                                </Avatar>
                                                <h3 className="text-sm sm:text-base font-semibold mb-1 sm:mb-2">No Events Found</h3>
                                                <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 mb-3 sm:mb-4">No events have been recorded for this procurement yet.</p>
                                            </div>
                                        ) : (
                                            <div className="relative pl-6 sm:pl-8">
                                                <div className="absolute left-6 sm:left-8 top-0 bottom-8 w-[2px] bg-gradient-to-b from-primary via-primary/60 to-primary/20 z-0"></div>

                                                {renderTimeline(procurement, now)}
                                            </div>
                                        )}
                                    </CardContent>
                                </TabsContent>
                            </Card>
                        </Tabs>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

const MetadataItem = ({ icon, label, value }: { icon: JSX.Element, label: string, value: string }) => {
    return (
        <div className="flex items-start group">
            <div className="mr-1.5 sm:mr-2 mt-0.5 flex-shrink-0">
                {icon}
            </div>
            <div className="flex-1 min-w-0">
                <div className="text-xs sm:text-sm">
                    <span className="font-medium">
                        {label}:
                    </span>
                    <div className="mt-0.5 sm:mt-1 text-neutral-600 dark:text-neutral-400 break-words leading-relaxed text-xs sm:text-sm">
                        <div className="line-clamp-3 sm:line-clamp-2 group-hover:line-clamp-none transition-all">
                            {value}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

const getEventIconContent = (type: string) => {
    const typeLower = type.toLowerCase();

    if (typeLower.includes('create') || typeLower.includes('new')) {
        return <Plus className="w-4 h-4" />;
    }

    if (typeLower.includes('update') || typeLower.includes('change')) {
        return <RefreshCw className="w-4 h-4" />;
    }

    if (typeLower.includes('approve') || typeLower.includes('confirm')) {
        return <CheckCircle className="w-4 h-4" />;
    }

    if (typeLower.includes('reject') || typeLower.includes('decline')) {
        return <XCircle className="w-4 h-4" />;
    }

    if (typeLower.includes('document') || typeLower.includes('upload')) {
        return <FileText className="w-4 h-4" />;
    }

    return <AlertCircle className="w-4 h-4" />;
};

const getEventIconColor = (type: string) => {
    const typeLower = type.toLowerCase();

    if (typeLower.includes('create') || typeLower.includes('new')) {
        return { bgColor: '#dcfce7', textColor: '#16a34a' };
    }

    if (typeLower.includes('update') || typeLower.includes('change')) {
        return { bgColor: '#dbeafe', textColor: '#2563eb' };
    }

    if (typeLower.includes('approve') || typeLower.includes('confirm')) {
        return { bgColor: '#d1fae5', textColor: '#059669' };
    }

    if (typeLower.includes('reject') || typeLower.includes('decline')) {
        return { bgColor: '#fee2e2', textColor: '#dc2626' };
    }

    if (typeLower.includes('document') || typeLower.includes('upload')) {
        return { bgColor: '#f3e8ff', textColor: '#9333ea' };
    }

    return { bgColor: '#f3f4f6', textColor: '#6b7280' };
};

const isDocumentUploadEvent = (eventType: string, category?: string, documentCount?: number): boolean => {
    return eventType.toLowerCase().includes('document_upload') ||
        eventType.toLowerCase().includes('upload') ||
        (category === 'workflow' && typeof documentCount === 'number' && documentCount > 0);
};

const createDocumentCountElement = (count?: number): JSX.Element | null => {
    if (!count || count <= 0) return null;

    return (
        <div className="mt-2 text-xs font-medium px-2 py-1 bg-blue-50 border border-blue-100 rounded-md inline-flex items-center">
            <FileText className="w-3.5 h-3.5 mr-1.5 text-blue-500" />
            {count} {count === 1 ? 'document' : 'documents'} processed
        </div>
    );
};

const TIMELINE_ICON_STYLES = {
    documentUpload: {
        bgColor: '#f0f9ff',
        textColor: '#0284c7'
    }
};

const renderTimeline = (procurement: Procurement, now?: string) => {
    const allTimelineItems: Array<{
        timestamp: string;
        formatted_date: string;
        type: 'phase_change' | 'event';
        phaseOrder: number;
        content: JSX.Element;
        phase_identifier?: string;
    }> = [];

    if (procurement.timeline) {
        procurement.timeline.forEach((item, index) => {
            const phaseIndex = phaseOrder.findIndex(
                p => p.toLowerCase() === item.phase.toLowerCase()
            );

            allTimelineItems.push({
                timestamp: item.timestamp,
                formatted_date: item.formatted_date,
                type: 'phase_change',
                phaseOrder: phaseIndex !== -1 ? phaseIndex : 999,
                phase_identifier: item.phase,
                content: (
                    <div key={`state-${item.timestamp}-${index}`} className="relative mb-8 pb-2 group">
                        <div className="absolute -left-[18px] sm:-left-[20px] top-0 z-10 transform -translate-x-1/2">
                            <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-full ring-4 ring-white dark:ring-gray-900 bg-blue-100 
                                flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg">
                                <RefreshCw className="h-4 w-4 sm:h-5 sm:w-5 text-blue-600 transition-transform duration-500 group-hover:rotate-180" />
                            </div>
                        </div>

                        <div className="ml-4 sm:ml-6 pt-1 transition-all duration-300 group-hover:translate-x-1">
                            <div className="mb-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                                <time className="text-xs sm:text-sm font-medium text-neutral-500 dark:text-neutral-400 flex items-center transition-colors group-hover:text-primary">
                                    <Calendar className="inline-flex w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-neutral-400 dark:text-neutral-500 transition-colors group-hover:text-primary" />
                                    {new Date(item.timestamp).toLocaleDateString(undefined, {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    })}
                                </time>
                                <span className="hidden sm:inline-block text-neutral-300 dark:text-neutral-700">•</span>
                                <time className="text-xs sm:text-sm font-medium text-neutral-500 dark:text-neutral-400 flex items-center transition-colors group-hover:text-primary">
                                    <Clock className="inline-flex w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-neutral-400 dark:text-neutral-500 transition-colors group-hover:text-primary" />
                                    {new Date(item.timestamp).toLocaleTimeString(undefined, {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </time>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center gap-2.5">
                                    <h3 className="text-sm sm:text-base font-semibold tracking-tight transition-colors group-hover:text-primary">
                                        Phase Transition
                                    </h3>
                                    <Badge className="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 
                                        transition-all duration-300 group-hover:bg-blue-100 group-hover:border-blue-300">
                                        {item.phase}
                                    </Badge>
                                </div>

                                <Card className="bg-white dark:bg-gray-800 shadow-sm border border-neutral-200 dark:border-neutral-700 
                                    transition-all duration-300 group-hover:shadow-md group-hover:border-primary/20">
                                    <CardHeader className="p-3 sm:p-4 pb-0 flex flex-row items-center justify-between gap-2">
                                        <CardTitle className="text-sm sm:text-base font-medium">
                                            Status changed to: <span className="font-semibold text-primary">{item.state}</span>
                                        </CardTitle>
                                        <Badge variant={getStatusColor(item.state).variant}
                                            className="text-[10px] sm:text-[11px] h-5 px-2 transition-all duration-300 group-hover:scale-105">
                                            {getStatusColor(item.state).icon}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent className="p-3 sm:p-4 pt-2">
                                        <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                            The procurement has progressed to the <strong>{item.phase}</strong> phase with status <strong>{item.state}</strong>
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                )
            });
        });
    }

    procurement.events.forEach((event, index) => {
        const phaseIndex = event.phase_identifier ?
            phaseOrder.findIndex(
                p => p.toLowerCase() === (event.phase_identifier?.toLowerCase() ?? '')
            ) : 999;

        let eventDetails = event.details;
        let eventIcon = getEventIconContent(event.event_type);
        let eventIconStyles = getEventIconColor(event.event_type);

        if (isDocumentUploadEvent(event.event_type, event.category, event.document_count)) {
            eventIconStyles = TIMELINE_ICON_STYLES.documentUpload;
            eventIcon = <FileCheck className="w-4 h-4" />;

            const documentCountElement = createDocumentCountElement(event.document_count);
            if (documentCountElement) {
                eventDetails = (
                    <>
                        {event.details}
                        {documentCountElement}
                    </>
                );
            }
        }

        allTimelineItems.push({
            timestamp: event.timestamp,
            formatted_date: event.formatted_date || formatDate(event.timestamp),
            type: 'event',
            phaseOrder: phaseIndex !== -1 ? phaseIndex : 999,
            phase_identifier: event.phase_identifier,
            content: (
                <div key={`event-${event.timestamp}-${index}`} className="relative mb-8 pb-2 group">
                    <div className="absolute -left-[18px] sm:-left-[20px] top-0 z-10 transform -translate-x-1/2">
                        <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-full ring-4 ring-white dark:ring-gray-900 
                            flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg"
                            style={{ backgroundColor: eventIconStyles.bgColor, color: eventIconStyles.textColor }}>
                            {eventIcon}
                        </div>
                    </div>

                    <div className="ml-4 sm:ml-6 pt-1 transition-all duration-300 group-hover:translate-x-1">
                        <div className="mb-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <time className="text-xs sm:text-sm font-medium text-neutral-500 dark:text-neutral-400 flex items-center transition-colors group-hover:text-primary">
                                <Calendar className="inline-flex w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-neutral-400 dark:text-neutral-500 transition-colors group-hover:text-primary" />
                                {new Date(event.timestamp).toLocaleDateString(undefined, {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric'
                                })}
                            </time>
                            <span className="hidden sm:inline-block text-neutral-300 dark:text-neutral-700">•</span>
                            <time className="text-xs sm:text-sm font-medium text-neutral-500 dark:text-neutral-400 flex items-center transition-colors group-hover:text-primary">
                                <Clock className="inline-flex w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 text-neutral-400 dark:text-neutral-500 transition-colors group-hover:text-primary" />
                                {new Date(event.timestamp).toLocaleTimeString(undefined, {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </time>
                        </div>

                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="text-sm sm:text-base font-semibold tracking-tight transition-colors group-hover:text-primary">
                                    {event.event_type.replace(/_/g, ' ')}
                                </h3>
                                <Badge variant="outline" className="text-[10px] sm:text-xs transition-all duration-300 
                                    group-hover:border-primary/30 group-hover:bg-primary/5">
                                    {event.phase_identifier}
                                </Badge>
                                <Badge variant="secondary" className="text-[10px] sm:text-xs capitalize transition-all duration-300 
                                    group-hover:bg-secondary/80">
                                    {event.category}
                                </Badge>
                            </div>

                            <Card className="bg-white dark:bg-gray-800 shadow-sm border border-neutral-200 dark:border-neutral-700
                                transition-all duration-300 group-hover:shadow-md group-hover:border-primary/20">
                                <CardContent className="p-3 sm:p-4">
                                    <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                        {eventDetails}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            )
        });
    });

    allTimelineItems.sort((a, b) => {
        return new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime();
    });

    const itemsByDate: Record<string, typeof allTimelineItems> = {};
    allTimelineItems.forEach(item => {
        const date = new Date(item.timestamp).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        if (!itemsByDate[date]) itemsByDate[date] = [];
        itemsByDate[date].push(item);
    });

    return (
        <div className="relative">
            {Object.entries(itemsByDate).map(([date, items]) => (
                <div key={date} className="mb-12">
                    {Object.keys(itemsByDate).length > 1 && (
                        <div className="sticky top-0 z-20 mb-6 py-2 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm transition-all duration-300 hover:bg-white/100 dark:hover:bg-gray-900/100">
                            <div className="inline-flex items-center rounded-full bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary shadow-sm transition-all duration-300 hover:bg-primary/20 hover:shadow">
                                <Calendar className="mr-2 h-4 w-4" />
                                {date}
                            </div>
                        </div>
                    )}

                    <div>
                        {items.map((item, i) => (
                            <Fragment key={`${date}-${i}`}>
                                {i > 0 && item.phase_identifier && item.phase_identifier !== items[i - 1].phase_identifier && (
                                    <div className="relative my-12 group">
                                        <div className="absolute -left-[18px] sm:-left-[20px] top-1/2 transform -translate-x-1/2 -translate-y-1/2 z-20">
                                            <div className="flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-r from-primary to-primary/80 
                                                text-white text-xs font-bold shadow-md transition-all duration-500 
                                                group-hover:shadow-primary/30 group-hover:scale-110">
                                                {phaseOrder.findIndex(p => p.toLowerCase() === item.phase_identifier?.toLowerCase()) + 1}
                                            </div>
                                        </div>

                                        <div className="ml-4 sm:ml-6">
                                            <div className="h-0.5 bg-gradient-to-r from-primary via-primary/50 to-transparent dark:from-primary dark:via-primary/40 dark:to-transparent w-full relative">
                                                <span className="absolute -top-3 left-0 bg-white dark:bg-gray-900 pr-3 text-xs font-semibold tracking-wider text-primary transform transition-all duration-300 
                                                    group-hover:text-primary/90 group-hover:translate-y-[-1px]">
                                                    NEW PHASE: <span className="font-bold tracking-wide">{item.phase_identifier}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {item.content}
                            </Fragment>
                        ))}
                    </div>
                </div>
            ))}

            <div className="relative pt-4 group">
                <div className="absolute -left-[18px] sm:-left-[20px] transform -translate-x-1/2 z-10">
                    <div className="h-6 w-6 rounded-full bg-neutral-200 dark:bg-neutral-700 border-4 border-white dark:border-gray-900
                        transition-all duration-500 group-hover:bg-neutral-300 dark:group-hover:bg-neutral-600"></div>
                </div>
                <div className="ml-4 sm:ml-6">
                    <div className="text-xs text-neutral-500 dark:text-neutral-400 italic font-medium transition-all duration-300 
                        group-hover:text-neutral-600 dark:group-hover:text-neutral-300">
                        End of timeline
                    </div>
                </div>
            </div>

            {now && (
                <div className="relative mt-6 pt-2">
                    <div className="absolute -left-[18px] sm:-left-[20px] transform -translate-x-1/2 z-20">
                        <div className="h-6 w-6 rounded-full bg-primary border-4 border-white dark:border-gray-900 animate-pulse"></div>
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
    );
};

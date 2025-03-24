import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useEffect, useState } from 'react';
import { toast } from "sonner";
import {
    FileText,
    CheckCircle,
    Clock,
    BarChart3,
    FileIcon,
    CheckIcon,
    FileUpIcon,
    ActivityIcon,
    PlusIcon,
    ExternalLinkIcon,
    Bell,
    ArrowRight,
    FileTextIcon
} from "lucide-react";
import { PhaseIdentifier, ProcurementState } from '@/types/blockchain';

// Let's assume these interfaces are imported from types
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    recentProcurements: {
        id: string;
        title: string;
        phase: PhaseIdentifier;
        state: ProcurementState;
    }[];
    recentActivities: {
        id: string;
        title: string;
        action: string;
        date: string;
        user: string;
        phase?: string;
    }[];
    priorityActions: {
        id: string;
        title: string;
        action: string;
        route: string;
    }[];
    stats: {
        ongoingProjects: number;
        pendingActions: number;
        completedBiddings: number;
        totalDocuments: number;
    };
    error?: string;
    [key: string]: unknown; // Add index signature to satisfy PageProps constraint
}

export default function Dashboard() {
    const { recentProcurements = [], recentActivities = [], priorityActions = [], stats, error } = usePage<DashboardProps>().props;
    const [showDebug, setShowDebug] = useState(false);

    // For demonstration, we'll show toast notification on component mount
    useEffect(() => {
        if (error) {
            toast.error("Error loading dashboard", {
                description: error,
                duration: 5000,
            });
        } else {
            toast.success("Welcome to BAC Secretariat Dashboard", {
                description: "View recent procurement activities and pending tasks",
                duration: 3000,
            });
        }

        // Log data to console for debugging
        console.log("Dashboard data loaded:", {
            recentProcurements: recentProcurements,
            recentActivities: recentActivities,
            priorityActions: priorityActions,
            stats: stats,
            activities_count: recentActivities.length,
        });
    }, [error, recentProcurements, recentActivities, priorityActions, stats]);

    // State badge styling function based on procurement state
    const getStateBadgeStyle = (state: ProcurementState): string => {
        switch (state) {
            case 'PR Submitted':
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900';
            case 'Pre-Procurement Completed':
                return 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300 dark:border-green-900';
            case 'Bid Invitation Published':
                return 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
            case 'Bids Opened':
                return 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900';
            case 'Bids Evaluated':
                return 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900';
            case 'Post-Qualification Verified':
                return 'bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900';
            case 'Resolution Recorded':
                return 'bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-900';
            case 'Awarded':
                return 'bg-pink-50 text-pink-700 border border-pink-200 hover:bg-pink-100 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-900';
            case 'Performance Bond Recorded':
                return 'bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900';
            case 'Contract And PO Recorded':
                return 'bg-lime-50 text-lime-700 border border-lime-200 hover:bg-lime-100 dark:bg-lime-950/40 dark:text-lime-300 dark:border-lime-900';
            case 'NTP Recorded':
                return 'bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-900';
            case 'Monitoring':
                return 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
            default:
                return 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-950 dark:text-gray-300 dark:border-gray-800';
        }
    };

    const getPhaseBadgeStyle = (phase: PhaseIdentifier): string => {
        switch (phase) {
            case 'PR Initiation':
                return 'bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800';
            case 'Pre-Procurement':
                return 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800';
            case 'Bid Invitation':
                return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
            case 'Bid Opening':
                return 'bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800';
            case 'Bid Evaluation':
                return 'bg-cyan-50 text-cyan-700 border border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-300 dark:border-cyan-800';
            case 'Post-Qualification':
                return 'bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-800';
            case 'BAC Resolution':
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800';
            case 'Notice Of Award':
                return 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
            case 'Performance Bond':
                return 'bg-lime-50 text-lime-700 border border-lime-200 dark:bg-lime-900/30 dark:text-lime-300 dark:border-lime-800';
            case 'Contract And PO':
                return 'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800';
            case 'Notice To Proceeed':
                return 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800';
            case 'Monitoring':
                return 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800';
            default:
                return 'bg-gray-50 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
        }
    };

    const formatRelativeDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} day ago`;

        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    };

    const getActionIcon = (action: string) => {
        const actionLower = action.toLowerCase();

        if (actionLower.includes('upload') || actionLower.includes('document'))
            return <FileUpIcon className="h-3.5 w-3.5" />;
        if (actionLower.includes('phase') || actionLower.includes('transition'))
            return <ArrowRight className="h-3.5 w-3.5" />;
        if (actionLower.includes('pre-procurement'))
            return <FileTextIcon className="h-3.5 w-3.5" />;
        if (actionLower.includes('decision'))
            return <CheckCircle className="h-3.5 w-3.5" />;
        if (actionLower.includes('publish'))
            return <ExternalLinkIcon className="h-3.5 w-3.5" />;
        if (actionLower.includes('complet'))
            return <CheckIcon className="h-3.5 w-3.5" />;
        if (actionLower.includes('submit') || actionLower.includes('add'))
            return <PlusIcon className="h-3.5 w-3.5" />;
        if (actionLower.includes('review') || actionLower.includes('evaluate'))
            return <FileTextIcon className="h-3.5 w-3.5" />;

        return <ActivityIcon className="h-3.5 w-3.5" />;
    };

    const getActionBadgeStyle = (action: string): string => {
        action = action.toLowerCase();
        if (action.includes('upload') || action.includes('document'))
            return 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
        if (action.includes('submit') || action.includes('add'))
            return 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
        if (action.includes('approve') || action.includes('complete') || action.includes('awarded'))
            return 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800';
        if (action.includes('review') || action.includes('evaluate') || action.includes('verification'))
            return 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800';
        return 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
    };

    // Before the return statement, add a debug component
    const DebugPanel = () => {
        if (!showDebug) return null;

        return (
            <div className="bg-gray-100 dark:bg-gray-900 p-4 mb-8 rounded-md border border-gray-200 dark:border-gray-700">
                <h3 className="text-lg font-semibold mb-2">Debug Information</h3>
                <div className="space-y-4">
                    <div>
                        <h4 className="text-sm font-medium mb-1">Recent Activities ({recentActivities.length})</h4>
                        <pre className="bg-white dark:bg-gray-800 p-2 rounded text-xs overflow-auto max-h-60">
                            {JSON.stringify(recentActivities, null, 2)}
                        </pre>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="BAC Secretariat Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-8 p-6">
                {/* Header Section */}
                <div className="border-b pb-5">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">BAC Secretariat Dashboard</h1>
                            <p className="text-muted-foreground mt-1">
                                Overview of procurement activities and tasks
                                <Button variant="link" className="ml-2 text-xs p-0 h-auto" onClick={() => setShowDebug(!showDebug)}>
                                    {showDebug ? 'Hide Debug' : 'Debug'}
                                </Button>
                            </p>
                        </div>
                        <Button asChild size="default">
                            <Link href="/bac-secretariat/procurement/pr-initiation">
                                <PlusIcon className="h-4 w-4 mr-2" />
                                New Procurement
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Debug Panel */}
                <DebugPanel />

                {/* Key Metrics */}
                <div>
                    <h2 className="text-lg font-semibold mb-4">Procurement Summary</h2>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Card className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{stats?.ongoingProjects || 0}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">Ongoing Projects</p>
                                    </div>
                                    <div className="bg-blue-50 dark:bg-blue-900/20 p-2 rounded-full">
                                        <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{stats?.pendingActions || 0}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">Pending Actions</p>
                                    </div>
                                    <div className="bg-amber-50 dark:bg-amber-900/20 p-2 rounded-full">
                                        <Bell className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{stats?.completedBiddings || 0}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">Completed Biddings</p>
                                    </div>
                                    <div className="bg-green-50 dark:bg-green-900/20 p-2 rounded-full">
                                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{stats?.totalDocuments || 0}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">Total Documents</p>
                                    </div>
                                    <div className="bg-gray-50 dark:bg-gray-800 p-2 rounded-full">
                                        <FileIcon className="h-5 w-5 text-gray-600 dark:text-gray-400" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Content Grid Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Left Column - Priority Actions */}
                    <div className="space-y-8">
                        <div>
                            <h2 className="text-lg font-semibold flex items-center mb-4">
                                <Bell className="h-4 w-4 mr-2 text-amber-500" />
                                Priority Actions
                            </h2>
                            <div className="space-y-4">
                                {priorityActions.length > 0 ? priorityActions.map((action, index) => (
                                    <Card key={index} className="border-l-4 border-l-amber-500 shadow-sm">
                                        <CardContent className="p-4">
                                            <h3 className="font-medium">{action.action}</h3>
                                            <p className="text-sm text-muted-foreground my-2">For: {action.id}</p>
                                            <Button variant="secondary" size="sm" asChild className="w-full mt-2">
                                                <Link href={action.route}>
                                                    Take Action
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                )) : (
                                    <Card className="shadow-sm">
                                        <CardContent className="p-4 text-center py-8">
                                            <CheckIcon className="mx-auto h-8 w-8 text-green-500 mb-2" />
                                            <p>No pending actions</p>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div>
                            <h2 className="text-lg font-semibold flex items-center mb-4">
                                <ActivityIcon className="h-4 w-4 mr-2 text-primary" />
                                Quick Actions
                            </h2>
                            <div className="grid grid-cols-2 gap-3">
                                <Button variant="outline" asChild className="h-auto py-4 flex flex-col items-center justify-center gap-2 shadow-sm">
                                    <Link href="/bac-secretariat/procurement/pr-initiation">
                                        <PlusIcon className="h-4 w-4" />
                                        <span className="text-xs">New Purchase Request</span>
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild className="h-auto py-4 flex flex-col items-center justify-center gap-2 shadow-sm">
                                    <Link href="/bac-secretariat/procurements-list">
                                        <FileUpIcon className="h-4 w-4" />
                                        <span className="text-xs">Procurements List</span>
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild className="h-auto py-4 flex flex-col items-center justify-center gap-2 shadow-sm">
                                    <Link href="/bac-secretariat/bid-invitation">
                                        <FileText className="h-4 w-4" />
                                        <span className="text-xs">Bid Invitation</span>
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild className="h-auto py-4 flex flex-col items-center justify-center gap-2 shadow-sm">
                                    <Link href="/bac-secretariat/reports">
                                        <BarChart3 className="h-4 w-4" />
                                        <span className="text-xs">Reports</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        {/* Recent Activities */}
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-semibold flex items-center">
                                    <Clock className="h-4 w-4 mr-2 text-purple-500" />
                                    Recent Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                </h2>
                                <Link href="/bac-secretariat/procurements-list" className="text-xs text-primary hover:underline flex items-center">
                                    View all <ArrowRight className="h-3 w-3 ml-1" />
                                </Link>
                            </div>
                            <Card className="shadow-sm">
                                <CardContent className="p-4">
                                    <div className="space-y-3">
                                        {recentActivities.length > 0 ? (
                                            recentActivities.map((activity, index) => (
                                                <div key={index} className={`${index < recentActivities.length - 1 ? "border-b pb-3" : ""}`}>
                                                    <div className="flex items-center justify-between">
                                                        <Link href={`/bac-secretariat/procurements-list/${activity.id}`}
                                                            className="font-medium text-primary hover:underline text-sm max-w-[70%] truncate">
                                                            {activity.title || `Procurement #${activity.id}`}
                                                        </Link>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatRelativeDate(activity.date)}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1.5 flex items-center justify-between">
                                                        <div className="flex items-center">
                                                            <Badge variant="outline"
                                                                className={`${getActionBadgeStyle(activity.action)} text-xs mr-2 flex items-center gap-1`}>
                                                                {getActionIcon(activity.action)}
                                                                <span>{activity.action}</span>
                                                            </Badge>
                                                            {activity.phase && (
                                                                <span className="text-xs text-muted-foreground ml-1">
                                                                    in {activity.phase} phase
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">by {activity.user}</span>
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <div className="text-center py-8">
                                                <Clock className="mx-auto h-8 w-8 text-muted-foreground opacity-20 mb-2" />
                                                <p>No recent activities found</p>
                                                <p className="text-xs text-muted-foreground mt-2">
                                                    Activities will appear here when procurement actions are taken.<br />
                                                    Try refreshing if you've recently performed actions.
                                                </p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-4"
                                                    onClick={() => window.location.reload()}
                                                >
                                                    Refresh Data
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Right Column - Recent Procurements */}
                    <div className="lg:col-span-2">
                        <div className="space-y-8">
                            <div>
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-lg font-semibold flex items-center">
                                        <FileText className="h-4 w-4 mr-2 text-blue-500" />
                                        Recent Procurements
                                    </h2>
                                    <Link href="/bac-secretariat/procurements-list" className="text-xs text-primary hover:underline flex items-center">
                                        View all <ArrowRight className="h-3 w-3 ml-1" />
                                    </Link>
                                </div>

                                <Card className="shadow-sm">
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>ID</TableHead>
                                                    <TableHead>Title</TableHead>
                                                    <TableHead>Phase</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead className="text-right">Actions</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {recentProcurements.length > 0 ? recentProcurements.map(procurement => (
                                                    <TableRow key={procurement.id}>
                                                        <TableCell className="font-medium">{procurement.id}</TableCell>
                                                        <TableCell className="max-w-[140px] truncate" title={procurement.title}>{procurement.title}</TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline" className={getPhaseBadgeStyle(procurement.phase)}>
                                                                {procurement.phase}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline" className={getStateBadgeStyle(procurement.state)}>
                                                                {procurement.state === 'Pre-Procurement Completed' && <CheckIcon className="h-3 w-3 mr-1" />}
                                                                {procurement.state === 'Bids Opened' && <FileIcon className="h-3 w-3 mr-1" />}
                                                                {procurement.state === 'Awarded' && <CheckCircle className="h-3 w-3 mr-1" />}
                                                                <span className="truncate max-w-[100px]" title={procurement.state}>
                                                                    {procurement.state}
                                                                </span>
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button variant="ghost" size="sm" asChild className="h-8 px-2">
                                                                <Link href={`/bac-secretariat/procurements-list/${procurement.id}`}>
                                                                    View
                                                                </Link>
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                )) : (
                                                    <TableRow>
                                                        <TableCell colSpan={5} className="text-center py-8">
                                                            No procurement data available
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Blockchain verification info - Simplified footer */}
                <div className="text-xs text-muted-foreground flex items-center justify-center border-t pt-4">
                    <ExternalLinkIcon className="h-3 w-3 mr-1" />
                    <span>All procurement data is verified on blockchain for transparency</span>
                </div>
            </div>
        </AppLayout>
    );
}
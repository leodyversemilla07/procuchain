import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from "sonner";
import { ArrowRight, Clock, FileText, CheckCircle, FileIcon, EyeIcon } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { getActionIcon, getActionBadgeStyle } from '@/lib/action-utils';
import type { BreadcrumbItem, SharedData} from '@/types';
import { Status, Stage } from '@/types/blockchain';

interface DashboardStats {
    ongoingProjects: number;
    completedBiddings: number;
    totalDocuments: number;
}

interface RecentActivity {
    id: string;
    title: string;
    action: string;
    date: string;
    user: string;
    stage?: string;
}

interface RecentProcurement {
    id: string;
    title: string;
    stage: Stage;
    status: Status;
}

interface DashboardProps extends SharedData {
    recentProcurements: RecentProcurement[];
    recentActivities: RecentActivity[];
    stats: DashboardStats;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Head of Procuring Entity Dashboard',
        href: route('hope.dashboard'),
    },
];

export default function HOPEDashboard() {
    const { recentProcurements = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

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

    // Define all possible cards
    const allCards = [
        {
            label: "Ongoing Projects",
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: "text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
        },
        {
            label: "Completed Biddings",
            value: stats?.completedBiddings || 0,
            icon: CheckCircle,
            colors: "text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20"
        },
        {
            label: "Total Documents",
            value: stats?.totalDocuments || 0,
            icon: FileIcon,
            colors: "text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800"
        }
    ];

    // Filter cards based on the current user's role
    const cardsToShow = allCards;

    // Determine grid columns based on the number of cards to show
    const gridColsClass = cardsToShow.length === 4 ? "md:grid-cols-4" : "md:grid-cols-3";

    useEffect(() => {
        if (error) {
            toast.error("Error loading dashboard", {
                description: error,
                duration: 5000,
            });
        }
    }, [error]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="HOPE Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Head of Procuring Entity Dashboard</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        High-level overview of procurement status and activities
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className={`grid grid-cols-1 ${gridColsClass} gap-4`}>
                    {cardsToShow.map(({ label, value, icon: Icon, colors }) => (
                        <Card key={label} className="shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-3xl font-bold">{value}</p>
                                        <p className="text-sm text-muted-foreground mt-0.5">{label}</p>
                                    </div>
                                    <div className={`p-2 rounded-full ${colors}`}>
                                        <Icon className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-1 space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                                    <Clock className="h-4 w-4 md:h-5 md:w-5 mr-2 text-purple-500" />
                                    Recent Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                </CardTitle>
                                <Link href={route('hope.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {recentActivities.length === 0 ? (
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
                                ) : (
                                    <div className="space-y-3">
                                        {recentActivities.map((activity, index) => {
                                            const ActionIcon = getActionIcon(activity.action);
                                            return (
                                                <div key={index} className={`${index < recentActivities.length - 1 ? "border-b pb-3" : ""}`}>
                                                    <div className="flex items-center justify-between">
                                                        <Link
                                                            href={`/bac-secretariat/procurements-list/${activity.id}`}
                                                            className="font-medium text-primary hover:underline text-sm max-w-[70%] truncate"
                                                        >
                                                            {activity.title || `Procurement #${activity.id}`}
                                                        </Link>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatRelativeDate(activity.date)}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1.5 flex items-center justify-between">
                                                        <div className="flex items-center">
                                                            <Badge
                                                                variant="outline"
                                                                className={`${getActionBadgeStyle(activity.action)} text-xs mr-2 flex items-center gap-1`}
                                                            >
                                                                <ActionIcon className="h-3.5 w-3.5" />
                                                                <span>{activity.action}</span>
                                                            </Badge>
                                                            {activity.stage && (
                                                                <span className="text-xs text-muted-foreground ml-1">
                                                                    in {activity.stage} stage
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">by {activity.user}</span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                                    <FileText className="h-4 w-4 md:h-5 md:w-5 mr-2 text-blue-500" />
                                    Recent Procurements
                                </CardTitle>
                                <Link href={route('hope.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>ID</TableHead>
                                            <TableHead>Title</TableHead>
                                            <TableHead>Stage</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentProcurements.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-center py-8">
                                                    No procurement data available
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            recentProcurements.map(procurement => (
                                                <TableRow key={procurement.id}>
                                                    <TableCell className="font-medium">{procurement.id}</TableCell>
                                                    <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                                        {procurement.title}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge>
                                                            {procurement.stage}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">
                                                            {procurement.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    asChild
                                                                    className="h-8 px-2"
                                                                >
                                                                    <Link href={route('hope.procurements.show', { id: procurement.id })}>
                                                                        <EyeIcon className="h-4 w-4" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>View Procurement Details</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

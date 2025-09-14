import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from "@/components/ui/button";
import { useEffect } from 'react';
import { toast } from "sonner";
import { PlusIcon, Bell, ArrowRight, Clock, FileText, ActivityIcon, CheckCircle, FileIcon, CheckIcon, FileUpIcon, EyeIcon } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type { User, SharedData } from "@/types";

// Type definitions from dashboard.ts
import { Stage, Status } from '@/types/blockchain';

interface DashboardStats {
    ongoingProjects: number;
    pendingActions: number;
    completedBiddings: number;
    totalDocuments: number;
}

interface PriorityAction {
    id: string;
    title: string;
    action: string;
    route: string;
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

const breadcrumbs = [
    {
        title: 'Bids and Awards Committee Secretariat Dashboard',
        href: '/bac-secretariat/dashboard',
    },
];



export default function Dashboard() {
    const pageProps = usePage<DashboardProps>().props;
    const { recentProcurements = [], recentActivities = [], priorityActions, stats, error } = pageProps as DashboardProps;
    const { auth } = pageProps as unknown as { auth: { user: User } };
    const userRole = auth.user?.role;

    useEffect(() => {
        if (error) {
            toast.error("Error loading dashboard", {
                description: error,
                duration: 5000,
            });
        }
    }, [error]);

    // Utility functions

    const getProcurementShowRoute = (procurementId: string) => {
        switch (userRole) {
            case 'bac_secretariat':
                return `/bac-secretariat/procurements/${procurementId}`;
            case 'bac_chairman':
                return `/bac-chairman/procurements/${procurementId}`;
            case 'hope':
                return `/hope/procurements/${procurementId}`;
            case 'admin':
                return `/admin/procurements/${procurementId}`;
            default:
                console.warn('Unknown user role for procurement link:', userRole);
                return '#';
        }
    };

    const getStatusIcon = (status: string) => {
        if (status === 'Pre-Procurement Conference Completed') return <CheckIcon className="h-3 w-3 mr-1" />;
        if (status === 'Bids Opened') return <FileIcon className="h-3 w-3 mr-1" />;
        if (status === 'Awarded') return <CheckCircle className="h-3 w-3 mr-1" />;
        return null;
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

    // Component sections
    const renderStatsCards = () => {
        const allCards = [
            {
                label: "Ongoing Projects",
                value: stats?.ongoingProjects || 0,
                icon: FileText,
                colors: "text-primary bg-primary/10"
            },
            {
                label: "Pending Actions",
                value: stats?.pendingActions || 0,
                icon: Bell,
                colors: "text-secondary bg-secondary/10",
                roles: ['bac_secretariat']
            },
            {
                label: "Completed Biddings",
                value: stats?.completedBiddings || 0,
                icon: CheckCircle,
                colors: "text-primary bg-primary/10"
            },
            {
                label: "Total Documents",
                value: stats?.totalDocuments || 0,
                icon: FileIcon,
                colors: "text-muted-foreground bg-muted/10"
            }
        ];

        const cardsToShow = allCards.filter(card => !card.roles || card.roles.includes(userRole));
        const gridColsClass = cardsToShow.length === 4 ? "md:grid-cols-4" : "md:grid-cols-3";

        return (
            <div className={`grid grid-cols-1 sm:grid-cols-2 ${gridColsClass} gap-4`}>
                {cardsToShow.map((card, index) => {
                    const IconComponent = card.icon;
                    return (
                        <Card key={index} className="shadow-sm">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">{card.label}</p>
                                        <p className="text-2xl font-bold">{card.value}</p>
                                    </div>
                                    <div className={`p-2 rounded-full ${card.colors}`}>
                                        <IconComponent className="h-5 w-5" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        );
    };

    const renderPriorityActions = () => {
        if ((priorityActions as PriorityAction[]).length === 0) {
            return (
                <Card className="shadow-sm">
                    <CardContent className="p-4 text-center py-8">
                        <CheckIcon className="mx-auto h-8 w-8 text-primary mb-2" />
                        <p>No pending actions</p>
                    </CardContent>
                </Card>
            );
        }

        return (
            <div className="space-y-4">
                {(priorityActions as PriorityAction[]).map((action: PriorityAction, index: number) => (
                    <Card key={index} className="border-l-4 border-l-primary shadow-sm">
                        <CardContent className="p-4">
                            <h3 className="font-medium">{action.action}</h3>
                            <p className="text-sm text-muted-foreground my-2">For: {action.id}</p>
                            <Button variant="secondary" size="sm" asChild className="w-full mt-2">
                                <Link href={action.route}>Take Action</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    };

    const renderRecentActivities = () => {
        if (recentActivities.length === 0) {
            return (
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
            );
        }

        return (
            <div className="space-y-3">
                {recentActivities.map((activity, index) => {
                    const ActionIcon = activity.action.includes('Created') ? FileText :
                        activity.action.includes('Updated') ? CheckCircle :
                            FileIcon;

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
                                        variant="secondary"
                                        className="text-xs mr-2 flex items-center gap-1"
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
        );
    };

    const renderRecentProcurements = () => {
        if (recentProcurements.length === 0) {
            return (
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
                        <TableRow>
                            <TableCell colSpan={5} className="text-center py-8">
                                No procurement data available
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            );
        }

        return (
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
                    {recentProcurements.map(procurement => (
                        <TableRow key={procurement.id}>
                            <TableCell className="font-medium">{procurement.id}</TableCell>
                            <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                {procurement.title}
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">
                                    {procurement.stage}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">
                                    {getStatusIcon(procurement.status)}
                                    <span className="truncate max-w-[100px]" title={procurement.status}>
                                        {procurement.status}
                                    </span>
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
                                            <Link href={getProcurementShowRoute(procurement.id)}>
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
                    ))}
                </TableBody>
            </Table>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="BAC Secretariat Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <ActivityIcon className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Bids and Awards Committee Secretariat Dashboard</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        Overview of procurement activities and tasks
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    asChild
                                >
                                    <Link href="/bac-secretariat/procurements-list">
                                        <FileUpIcon className="h-4 w-4 mr-2" />
                                        Procurements List
                                    </Link>
                                </Button>

                                <Button
                                    asChild
                                    size="sm"
                                >
                                    <Link href="/bac-secretariat/procurement/procurement-initiation">
                                        <PlusIcon className="h-4 w-4 mr-2" />
                                        New Procurement
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Stats Cards Section */}
                {renderStatsCards()}

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Priority Actions */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                                    <Bell className="h-4 w-4 md:h-5 md:w-5 mr-2 text-primary" />
                                    Priority Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {renderPriorityActions()}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column */}
                    <div className="lg:col-span-2">
                        {/* Recent Activities moved here */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base md:text-lg font-semibold flex items-center">
                                    <Clock className="h-4 w-4 md:h-5 md:w-5 mr-2 text-primary" />
                                    Recent Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                </CardTitle>
                                <Link href="/bac-secretariat/procurements-list" className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {renderRecentActivities()}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent Procurements Section */}
                {renderRecentProcurements()}
            </div>
        </AppLayout>
    );
}

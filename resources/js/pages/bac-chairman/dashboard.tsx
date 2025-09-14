import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from "sonner";
import { ArrowRight, Clock, FileText, Bell, CheckCircle, FileIcon } from "lucide-react";
import type { DashboardProps } from '@/types/dashboard';
import { RecentActivities } from '@/components/dashboard/recent-activities';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { User, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bids and Awards Committee Chairman Dashboard',
        href: route('bac-chairman.dashboard'),
    },
];

export default function Dashboard() {
    const { recentProcurements = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;
    const { auth } = usePage().props as unknown as { auth: { user: User } };
    const userRole = auth.user?.role;

    // Define all possible cards
    const allCards = [
        {
            label: "Ongoing Projects",
            value: stats?.ongoingProjects || 0,
            icon: FileText,
            colors: "text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20"
        },
        {
            label: "Pending Actions",
            value: stats?.pendingActions || 0,
            icon: Bell,
            colors: "text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20",
            roles: ['bac_secretariat'] // Only show for bac_secretariat
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
    const cardsToShow = allCards.filter(card => !card.roles || card.roles.includes(userRole));

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
            <Head title="BAC Chairman Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="p-2 bg-primary/10 rounded-lg">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-foreground">Bids and Awards Committee Chairman Dashboard</h1>
                                    <p className="text-muted-foreground text-sm mt-1">
                                        Overview of procurement activities and committee tasks
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
                                <Link href={route('bac-chairman.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <RecentActivities activities={recentActivities} />
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
                                <Link href={route('bac-chairman.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <RecentProcurementsTable procurements={recentProcurements} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

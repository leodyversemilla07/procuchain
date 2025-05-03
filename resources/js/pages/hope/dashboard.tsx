import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from "sonner";
import { ArrowRight, Clock, FileText, ExternalLinkIcon } from "lucide-react";
import type { DashboardProps } from '@/types/dashboard';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { RecentActivities } from '@/components/dashboard/recent-activities';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'HOPE Dashboard',
        href: route('hope.dashboard'),
    },
];

export default function Dashboard() {
    const { recentProcurements = [], recentActivities = [], stats, error } = usePage<DashboardProps>().props;

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
                <div className="border-b pb-4 mb-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">HOPE Dashboard</h1>
                            <p className="text-muted-foreground mt-1 text-sm md:text-base">
                                High-level overview of procurement status and activities
                            </p>
                        </div>
                    </div>
                </div>

                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg md:text-xl font-semibold">Procurement Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <StatsCards stats={stats} />
                    </CardContent>
                </Card>

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
                                <Link href={route('hope.procurements-list.index')} className="text-xs md:text-sm text-primary hover:underline flex items-center shrink-0 ml-2">
                                    View all <ArrowRight className="h-3 w-3 md:h-4 md:w-4 ml-1" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <RecentProcurementsTable procurements={recentProcurements} />
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div className="text-xs md:text-sm text-muted-foreground flex items-center justify-center border-t pt-4 mt-4">
                    <ExternalLinkIcon className="h-3 w-3 md:h-4 md:w-4 mr-1.5" />
                    <span>All procurement data is verified on blockchain for transparency</span>
                </div>
            </div>
        </AppLayout>
    );
}

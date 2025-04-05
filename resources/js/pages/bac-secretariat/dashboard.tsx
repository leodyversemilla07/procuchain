import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from "@/components/ui/button";
import { useEffect } from 'react';
import { toast } from "sonner";
import { PlusIcon, Bell, ArrowRight, Clock, FileText, ExternalLinkIcon, ActivityIcon } from "lucide-react";
import type { DashboardProps } from '@/types/dashboard';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { PriorityActions } from '@/components/dashboard/priority-actions';
import { QuickActions } from '@/components/dashboard/quick-actions';
import { RecentActivities } from '@/components/dashboard/recent-activities';
import { RecentProcurementsTable } from '@/components/dashboard/recent-procurements-table';

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { recentProcurements = [], recentActivities = [], priorityActions = [], stats, error } = usePage<DashboardProps>().props;

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
            <Head title="BAC Secretariat Dashboard" />

            <div className="flex h-full flex-1 flex-col space-y-8 p-6">
                {/* Header */}
                <div className="border-b pb-5">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">BAC Secretariat Dashboard</h1>
                            <p className="text-muted-foreground mt-1">
                                Overview of procurement activities and tasks
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

                {/* Stats Summary */}
                <div>
                    <h2 className="text-lg font-semibold mb-4">Procurement Summary</h2>
                    <StatsCards stats={stats} />
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Left Column */}
                    <div className="space-y-8">
                        <div>
                            <h2 className="text-lg font-semibold flex items-center mb-4">
                                <Bell className="h-4 w-4 mr-2 text-amber-500" />
                                Priority Actions
                            </h2>
                            <PriorityActions actions={priorityActions} />
                        </div>

                        <div>
                            <h2 className="text-lg font-semibold flex items-center mb-4">
                                <ActivityIcon className="h-4 w-4 mr-2 text-primary" />
                                Quick Actions
                            </h2>
                            <QuickActions />
                        </div>

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
                            <RecentActivities activities={recentActivities} />
                        </div>
                    </div>

                    {/* Right Column */}
                    <div className="lg:col-span-2">
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
                            <RecentProcurementsTable procurements={recentProcurements} />
                        </div>
                    </div>
                </div>

                <div className="text-xs text-muted-foreground flex items-center justify-center border-t pt-4">
                    <ExternalLinkIcon className="h-3 w-3 mr-1" />
                    <span>All procurement data is verified on blockchain for transparency</span>
                </div>
            </div>
        </AppLayout>
    );
}
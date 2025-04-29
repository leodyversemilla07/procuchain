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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'; // Assuming Card components are available

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

            <div className="flex h-full flex-1 flex-col space-y-8 p-4 md:p-8"> {/* Adjusted padding */}
                {/* Header */}
                <div className="border-b pb-6 mb-6"> {/* Increased bottom padding and margin */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">BAC Secretariat Dashboard</h1> {/* Larger heading */}
                            <p className="text-muted-foreground mt-1">
                                Overview of procurement activities and tasks
                            </p>
                        </div>
                        <Button asChild size="default">
                            <Link href="/bac-secretariat/procurement/procurement-initiation">
                                <PlusIcon className="h-4 w-4 mr-2" />
                                New Procurement
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Summary */}
                <Card> {/* Wrap stats in a Card */}
                    <CardHeader>
                        <CardTitle className="text-xl font-semibold">Procurement Summary</CardTitle> {/* Adjusted heading size */}
                    </CardHeader>
                    <CardContent>
                        <StatsCards stats={stats} />
                    </CardContent>
                </Card>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6"> {/* Adjusted gap */}
                    {/* Left Column */}
                    <div className="space-y-6"> {/* Adjusted spacing */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-lg font-semibold flex items-center">
                                    <Bell className="h-5 w-5 mr-2 text-amber-500" /> {/* Slightly larger icon */}
                                    Priority Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PriorityActions actions={priorityActions} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-lg font-semibold flex items-center">
                                    <ActivityIcon className="h-5 w-5 mr-2 text-primary" /> {/* Slightly larger icon */}
                                    Quick Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <QuickActions />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-lg font-semibold flex items-center">
                                    <Clock className="h-5 w-5 mr-2 text-purple-500" /> {/* Slightly larger icon */}
                                    Recent Activities {recentActivities.length > 0 && `(${recentActivities.length})`}
                                </CardTitle>
                                <Link href="/bac-secretariat/procurements-list" className="text-sm text-primary hover:underline flex items-center"> {/* Slightly larger text */}
                                    View all <ArrowRight className="h-4 w-4 ml-1" /> {/* Slightly larger icon */}
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <RecentActivities activities={recentActivities} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-lg font-semibold flex items-center">
                                    <FileText className="h-5 w-5 mr-2 text-blue-500" /> {/* Slightly larger icon */}
                                    Recent Procurements
                                </CardTitle>
                                <Link href="/bac-secretariat/procurements-list" className="text-sm text-primary hover:underline flex items-center"> {/* Slightly larger text */}
                                    View all <ArrowRight className="h-4 w-4 ml-1" /> {/* Slightly larger icon */}
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <RecentProcurementsTable procurements={recentProcurements} />
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div className="text-sm text-muted-foreground flex items-center justify-center border-t pt-6 mt-4"> {/* Adjusted spacing and text size */}
                    <ExternalLinkIcon className="h-4 w-4 mr-1.5" /> {/* Adjusted icon size and margin */}
                    <span>All procurement data is verified on blockchain for transparency</span>
                </div>
            </div>
        </AppLayout>
    );
}
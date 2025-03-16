import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from "@/components/ui/badge"; // Add this import at the top of your file

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <div className="absolute inset-0 flex items-center justify-center">
                            <h2 className="text-lg font-semibold">Welcome to Dashboard</h2>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <div className="absolute inset-0 flex flex-col items-center justify-center p-4">
                            <span className="text-2xl font-bold text-primary">12</span>
                            <span className="text-sm text-neutral-600 dark:text-neutral-400">Active Projects</span>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <div className="absolute inset-0 flex flex-col items-center justify-center p-4">
                            <span className="text-2xl font-bold text-green-600">8</span>
                            <span className="text-sm text-neutral-600 dark:text-neutral-400">Completed Projects</span>
                        </div>
                    </div>
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 overflow-hidden rounded-xl border p-6">
                    <h2 className="mb-4 text-xl font-semibold">Recent Activities</h2>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Project</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Budget</TableHead>
                                    <TableHead>Assigned To</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow>
                                    <TableCell>Office Supplies Procurement</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">In Progress</Badge>
                                    </TableCell>
                                    <TableCell>Mar 15, 2024</TableCell>
                                    <TableCell>₱150,000</TableCell>
                                    <TableCell>John Smith</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>IT Equipment Bidding</TableCell>
                                    <TableCell>
                                        <Badge variant="default">Completed</Badge>
                                    </TableCell>
                                    <TableCell>Mar 12, 2024</TableCell>
                                    <TableCell>₱500,000</TableCell>
                                    <TableCell>Maria Garcia</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Vehicle Maintenance Contract</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">Pending</Badge>
                                    </TableCell>
                                    <TableCell>Mar 10, 2024</TableCell>
                                    <TableCell>₱300,000</TableCell>
                                    <TableCell>Robert Lee</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Building Renovation Project</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">In Progress</Badge>
                                    </TableCell>
                                    <TableCell>Mar 8, 2024</TableCell>
                                    <TableCell>₱2,500,000</TableCell>
                                    <TableCell>Sarah Chen</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Security Services Contract</TableCell>
                                    <TableCell>
                                        <Badge variant="destructive">Delayed</Badge>
                                    </TableCell>
                                    <TableCell>Mar 5, 2024</TableCell>
                                    <TableCell>₱800,000</TableCell>
                                    <TableCell>David Wilson</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Canteen Service Provider</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">Pending</Badge>
                                    </TableCell>
                                    <TableCell>Mar 3, 2024</TableCell>
                                    <TableCell>₱450,000</TableCell>
                                    <TableCell>Anna Santos</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Medical Supplies Procurement</TableCell>
                                    <TableCell>
                                        <Badge variant="default">Completed</Badge>
                                    </TableCell>
                                    <TableCell>Mar 1, 2024</TableCell>
                                    <TableCell>₱750,000</TableCell>
                                    <TableCell>Michael Chang</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Laboratory Equipment</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">In Progress</Badge>
                                    </TableCell>
                                    <TableCell>Feb 28, 2024</TableCell>
                                    <TableCell>₱1,200,000</TableCell>
                                    <TableCell>Lisa Rodriguez</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

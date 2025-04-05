import { Card, CardContent } from "@/components/ui/card";
import { FileText, Bell, CheckCircle, FileIcon } from "lucide-react";
import type { DashboardStats } from "@/types/dashboard";

interface StatsCardsProps {
    stats: DashboardStats;
}

export function StatsCards({ stats }: StatsCardsProps) {
    const cards = [
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
            colors: "text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20"
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

    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {cards.map(({ label, value, icon: Icon, colors }) => (
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
    );
}
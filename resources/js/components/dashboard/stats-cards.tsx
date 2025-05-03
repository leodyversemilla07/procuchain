import { Card, CardContent } from "@/components/ui/card";
import { FileText, Bell, CheckCircle, FileIcon } from "lucide-react";
import type { DashboardStats } from "@/types/dashboard";
import { usePage } from "@inertiajs/react"; // Import usePage
import type { User } from "@/types"; // Import User type

interface StatsCardsProps {
    stats: DashboardStats;
}

export function StatsCards({ stats }: StatsCardsProps) {
    const { auth } = usePage().props as unknown as { auth: { user: User } }; // Get user from props
    const userRole = auth.user?.role; // Assuming role is directly on the user object

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

    return (
        <div className={`grid grid-cols-1 ${gridColsClass} gap-4`}> {/* Adjusted grid columns dynamically */}
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
    );
}
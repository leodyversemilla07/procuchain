import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import { PlusIcon, FileUpIcon, FileText, BarChart3 } from "lucide-react";

export function QuickActions() {
    const actions = [
        {
            href: "/bac-secretariat/procurement/pr-initiation",
            icon: PlusIcon,
            label: "New Purchase Request"
        },
        {
            href: "/bac-secretariat/procurements-list",
            icon: FileUpIcon,
            label: "Procurements List"
        },
        {
            href: "/bac-secretariat/bid-invitation",
            icon: FileText,
            label: "Bid Invitation"
        },
        {
            href: "/bac-secretariat/reports",
            icon: BarChart3,
            label: "Reports"
        }
    ];

    return (
        <div className="grid grid-cols-2 gap-3">
            {actions.map(({ href, icon: Icon, label }) => (
                <Button
                    key={href}
                    variant="outline"
                    asChild
                    className="h-auto py-4 flex flex-col items-center justify-center gap-2 shadow-sm"
                >
                    <Link href={href}>
                        <Icon className="h-4 w-4" />
                        <span className="text-xs">{label}</span>
                    </Link>
                </Button>
            ))}
        </div>
    );
}
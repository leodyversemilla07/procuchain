import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import { PlusIcon, FileUpIcon } from "lucide-react";

export function QuickActions() {
    const actions = [
        {
            href: "/bac-secretariat/procurement/procurement-initiation",
            icon: PlusIcon,
            label: "Procurement Initiation"
        },
        {
            href: "/bac-secretariat/procurements-list",
            icon: FileUpIcon,
            label: "Procurements List"
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

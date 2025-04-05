import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { CheckIcon } from "lucide-react";
import { Link } from "@inertiajs/react";
import type { PriorityAction } from "@/types/dashboard";

interface PriorityActionsProps {
    actions: PriorityAction[];
}

export function PriorityActions({ actions }: PriorityActionsProps) {
    if (actions.length === 0) {
        return (
            <Card className="shadow-sm">
                <CardContent className="p-4 text-center py-8">
                    <CheckIcon className="mx-auto h-8 w-8 text-green-500 mb-2" />
                    <p>No pending actions</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {actions.map((action, index) => (
                <Card key={index} className="border-l-4 border-l-amber-500 shadow-sm">
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
}
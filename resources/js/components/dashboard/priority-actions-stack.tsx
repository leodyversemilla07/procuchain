import { EmptyState } from '@/components/empty-state';
import { ErrorState } from '@/components/error-state';
import type { ErrorStateProps } from '@/components/error-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { LucideIcon } from 'lucide-react';
import { CheckIcon } from 'lucide-react';
import { Link } from '@inertiajs/react';

export interface PriorityActionItem {
    id: string;
    action: string;
    route: string;
}

export interface PriorityActionsStackProps {
    actions: PriorityActionItem[];
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    actionLabel?: string;
    errorState?: ErrorStateProps;
}

const DEFAULT_EMPTY_TITLE = "No pending actions";
const DEFAULT_EMPTY_DESCRIPTION = "You're all caught up. We'll notify you when there's something that needs attention.";
const DEFAULT_ACTION_LABEL = 'Take Action';

export const PriorityActionsStack = ({
    actions,
    emptyStateIcon = CheckIcon,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    actionLabel = DEFAULT_ACTION_LABEL,
    errorState,
}: PriorityActionsStackProps) => {
    if (errorState) {
        return <ErrorState {...errorState} />;
    }

    if (!actions || actions.length === 0) {
        return <EmptyState icon={emptyStateIcon} title={emptyStateTitle} description={emptyStateDescription} />;
    }

    return (
        <div className="space-y-4">
            {actions.map((action) => (
                <Card key={`${action.id}-${action.action}`} className="border-l-primary border-l-4 shadow-sm">
                    <CardContent className="p-4">
                        <h3 className="font-medium">{action.action}</h3>
                        <p className="text-muted-foreground my-2 text-sm">For: {action.id}</p>
                        <Button variant="secondary" size="sm" asChild className="mt-2 w-full">
                            <Link href={action.route}>{actionLabel}</Link>
                        </Button>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};

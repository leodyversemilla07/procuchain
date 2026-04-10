import type { ErrorStateProps } from '@/components/error-state';
import { ErrorState } from '@/components/error-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CheckIcon } from 'lucide-react';

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

const DEFAULT_EMPTY_TITLE = 'No pending actions';
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

    // Ensure actions is an array
    const safeActions = Array.isArray(actions) ? actions : [];

    if (safeActions.length === 0) {
        const Icon = emptyStateIcon;
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Icon className="h-8 w-8" />
                    </EmptyMedia>
                </EmptyHeader>
                <EmptyTitle>{emptyStateTitle}</EmptyTitle>
                <EmptyDescription>{emptyStateDescription}</EmptyDescription>
            </Empty>
        );
    }

    return (
        <div className="space-y-3 sm:space-y-4">
            {safeActions.map((action) => (
                <Card
                    key={`${action.id}-${action.action}`}
                    className="border-l-primary group border-l-4 shadow-sm transition-all duration-300 hover:shadow-md"
                >
                    <CardContent className="p-3 sm:p-4">
                        <h3 className="group-hover:text-primary truncate text-sm font-medium transition-colors duration-200 sm:text-base">
                            {action.action}
                        </h3>
                        <p className="text-muted-foreground my-1.5 truncate text-xs sm:my-2 sm:text-sm">For: {action.id}</p>
                        <Button
                            variant="secondary"
                            size="sm"
                            className="mt-2 w-full transition-all duration-200 hover:scale-[1.02]"
                            render={<Link href={action.route} />}
                        >
                            {actionLabel}
                        </Button>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};

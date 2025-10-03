import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';

export interface EmptyStateProps {
    icon?: LucideIcon;
    title?: string;
    description?: string;
    className?: string;
    iconClassName?: string;
    iconContainerClassName?: string;
    titleClassName?: string;
    descriptionClassName?: string;
    illustration?: ReactNode;
    actions?: ReactNode;
    footer?: ReactNode;
    spacingClassName?: string;
}

export const EmptyState = ({
    icon: Icon = Inbox,
    title,
    description,
    className,
    iconClassName,
    iconContainerClassName = 'bg-muted text-muted-foreground',
    titleClassName = 'text-foreground text-sm font-medium',
    descriptionClassName = 'text-muted-foreground mx-auto max-w-[260px] text-xs',
    illustration,
    actions,
    footer,
    spacingClassName = 'flex flex-col items-center justify-center gap-3 py-10 text-center',
}: EmptyStateProps) => {
    return (
        <div className={cn(spacingClassName, className)}>
            {illustration ? (
                <div className="flex flex-col items-center justify-center">{illustration}</div>
            ) : (
                <div className={cn('flex h-12 w-12 items-center justify-center rounded-full', iconContainerClassName)}>
                    <Icon className={cn('h-5 w-5', iconClassName)} />
                </div>
            )}
            <div className="space-y-1">
                {title ? <p className={cn(titleClassName)}>{title}</p> : null}
                {description ? <p className={cn(descriptionClassName)}>{description}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center justify-center gap-2">{actions}</div> : null}
            {footer ? <div className="text-muted-foreground/80 text-xs">{footer}</div> : null}
        </div>
    );
};


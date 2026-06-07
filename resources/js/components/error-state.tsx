import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { AlertCircle, Info, TriangleAlert, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';

type ErrorStateTone = 'default' | 'destructive' | 'warning' | 'info';

const TONE_STYLES: Record<ErrorStateTone, string> = {
    default: 'bg-destructive/10 text-destructive',
    destructive: 'bg-destructive/10 text-destructive',
    warning: 'bg-muted/500/10 text-muted-foreground dark:text-muted-foreground',
    info: 'bg-primary/10 text-primary',
};

const TONE_ICONS: Record<ErrorStateTone, LucideIcon> = {
    default: AlertCircle,
    destructive: XCircle,
    warning: TriangleAlert,
    info: Info,
};

export interface ErrorStateProps {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    tone?: ErrorStateTone;
    retryLabel?: string;
    onRetry?: () => void;
    actions?: ReactNode;
    className?: string;
    footer?: ReactNode;
}

const DEFAULT_TITLE = 'Unable to load data';
const DEFAULT_DESCRIPTION = 'Something went wrong while fetching this information. Please try again or contact support if the issue persists.';
const DEFAULT_RETRY_LABEL = 'Retry';

export const ErrorState = ({
    title = DEFAULT_TITLE,
    description = DEFAULT_DESCRIPTION,
    icon,
    tone = 'default',
    retryLabel = DEFAULT_RETRY_LABEL,
    onRetry,
    actions,
    className,
    footer,
}: ErrorStateProps) => {
    const Icon = icon || TONE_ICONS[tone];
    const containerToneClasses = TONE_STYLES[tone];
    const hasActions = Boolean(onRetry || actions);

    return (
        <div className={cn('flex flex-col items-center justify-center gap-4 py-12 text-center', className)}>
            <div className={cn('flex h-12 w-12 items-center justify-center rounded-full', containerToneClasses)}>
                <Icon className="h-6 w-6" />
            </div>
            <div className="flex flex-col gap-1">
                <p className="text-foreground text-sm font-semibold">{title}</p>
                {description ? <p className="text-muted-foreground mx-auto max-w-[320px] text-xs leading-relaxed">{description}</p> : null}
            </div>
            {hasActions ? (
                <div className="flex flex-wrap items-center justify-center gap-2">
                    {onRetry ? (
                        <Button onClick={onRetry} size="sm">
                            {retryLabel}
                        </Button>
                    ) : null}
                    {actions}
                </div>
            ) : null}
            {footer ? <div className="text-muted-foreground/80 text-xs">{footer}</div> : null}
        </div>
    );
};

export type { ErrorStateTone };

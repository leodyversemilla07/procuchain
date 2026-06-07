import { cn } from '@/lib/utils';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, AlertTriangle, CheckCircle, Clock, XCircle } from 'lucide-react';

export type VerificationStatusType = 'verified' | 'failed' | 'pending' | 'not_verified' | 'warnings';

interface VerificationStatusProps {
    status: VerificationStatusType;
    lastVerified?: string;
    onClick?: () => void;
    size?: 'sm' | 'md' | 'lg';
    showLabel?: boolean;
}

const statusConfig: Record<VerificationStatusType, { icon: typeof CheckCircle; color: string; bgColor: string; borderColor: string; label: string }> =
    {
        verified: {
            icon: CheckCircle,
            color: 'text-primary dark:text-primary',
            bgColor: 'bg-primary/10 dark:bg-primary/10',
            borderColor: 'border-green-200 dark:border-green-800',
            label: 'Verified',
        },
        failed: {
            icon: XCircle,
            color: 'text-destructive dark:text-destructive',
            bgColor: 'bg-destructive/10 dark:bg-destructive/10',
            borderColor: 'border-red-200 dark:border-red-800',
            label: 'Failed',
        },
        pending: {
            icon: Clock,
            color: 'text-muted-foreground dark:text-muted-foreground',
            bgColor: 'bg-muted/50 dark:bg-muted/50',
            borderColor: 'border-yellow-200 dark:border-yellow-800',
            label: 'Pending',
        },
        not_verified: {
            icon: AlertCircle,
            color: 'text-muted-foreground dark:text-muted-foreground',
            bgColor: 'bg-muted/50 dark:bg-muted',
            borderColor: 'border-border dark:border-gray-700',
            label: 'Not Verified',
        },
        warnings: {
            icon: AlertTriangle,
            color: 'text-muted-foreground dark:text-muted-foreground',
            bgColor: 'bg-muted/50 dark:bg-muted/50',
            borderColor: 'border-amber-200 dark:border-amber-800',
            label: 'Warnings',
        },
    };

const sizeClasses = {
    sm: {
        icon: 'h-4 w-4',
        text: 'text-xs',
        padding: 'px-2 py-1',
    },
    md: {
        icon: 'h-5 w-5',
        text: 'text-sm',
        padding: 'px-3 py-1.5',
    },
    lg: {
        icon: 'h-6 w-6',
        text: 'text-base',
        padding: 'px-4 py-2',
    },
};

export function VerificationStatus({ status, lastVerified, onClick, size = 'md', showLabel = true }: VerificationStatusProps) {
    const config = statusConfig[status];
    const sizeClass = sizeClasses[size];
    const Icon = config.icon;

    const content = (
        <div
            className={cn(
                'inline-flex items-center gap-2 rounded-full border',
                config.bgColor,
                config.borderColor,
                sizeClass.padding,
                onClick && 'cursor-pointer transition-opacity hover:opacity-80',
            )}
        >
            <Icon className={cn(sizeClass.icon, config.color)} />
            {showLabel && <span className={cn('font-medium', sizeClass.text, config.color)}>{config.label}</span>}
            {lastVerified && (
                <span className={cn('text-muted-foreground', sizeClass.text)}>
                    {formatDistanceToNow(new Date(lastVerified), { addSuffix: true })}
                </span>
            )}
        </div>
    );

    if (onClick) {
        return (
            <button onClick={onClick} type="button" className="focus:ring-primary rounded-full focus:ring-2 focus:ring-offset-2 focus:outline-none">
                {content}
            </button>
        );
    }

    return content;
}

export default VerificationStatus;

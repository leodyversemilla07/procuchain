import { AlertCircle, CheckCircle, Clock, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatDistanceToNow } from 'date-fns';

export type VerificationStatusType = 'verified' | 'failed' | 'pending' | 'not_verified';

interface VerificationStatusProps {
    status: VerificationStatusType;
    lastVerified?: string;
    onClick?: () => void;
    size?: 'sm' | 'md' | 'lg';
    showLabel?: boolean;
}

const statusConfig = {
    verified: {
        icon: CheckCircle,
        color: 'text-green-600 dark:text-green-400',
        bgColor: 'bg-green-50 dark:bg-green-950',
        borderColor: 'border-green-200 dark:border-green-800',
        label: 'Verified',
    },
    failed: {
        icon: XCircle,
        color: 'text-red-600 dark:text-red-400',
        bgColor: 'bg-red-50 dark:bg-red-950',
        borderColor: 'border-red-200 dark:border-red-800',
        label: 'Failed',
    },
    pending: {
        icon: Clock,
        color: 'text-yellow-600 dark:text-yellow-400',
        bgColor: 'bg-yellow-50 dark:bg-yellow-950',
        borderColor: 'border-yellow-200 dark:border-yellow-800',
        label: 'Pending',
    },
    not_verified: {
        icon: AlertCircle,
        color: 'text-gray-400 dark:text-gray-500',
        bgColor: 'bg-gray-50 dark:bg-gray-900',
        borderColor: 'border-gray-200 dark:border-gray-700',
        label: 'Not Verified',
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

export function VerificationStatus({
    status,
    lastVerified,
    onClick,
    size = 'md',
    showLabel = true,
}: VerificationStatusProps) {
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
                onClick && 'cursor-pointer hover:opacity-80 transition-opacity',
            )}
        >
            <Icon className={cn(sizeClass.icon, config.color)} />
            {showLabel && (
                <span className={cn('font-medium', sizeClass.text, config.color)}>
                    {config.label}
                </span>
            )}
            {lastVerified && (
                <span className={cn('text-muted-foreground', sizeClass.text)}>
                    {formatDistanceToNow(new Date(lastVerified), { addSuffix: true })}
                </span>
            )}
        </div>
    );

    if (onClick) {
        return (
            <button onClick={onClick} type="button" className="focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full">
                {content}
            </button>
        );
    }

    return content;
}

export default VerificationStatus;

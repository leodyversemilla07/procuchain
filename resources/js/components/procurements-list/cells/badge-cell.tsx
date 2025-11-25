import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useIsTruncated } from '@/hooks/use-is-truncated';
import { cn } from '@/lib/utils';
import { useRef } from 'react';

interface BadgeCellProps<T extends string> {
    value: T;
    getStyle: (value: T) => string;
    formatLabel?: (value: string) => string;
}

const defaultFormatLabel = (value: string): string => {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

export const BadgeCell = <T extends string>({ value, getStyle, formatLabel = defaultFormatLabel }: BadgeCellProps<T>) => {
    const textRef = useRef<HTMLSpanElement>(null);
    const displayValue = formatLabel(value);
    const isTruncated = useIsTruncated(textRef, displayValue);

    const badge = (
        <Badge
            variant="outline"
            className={cn(
                getStyle(value),
                'inline-flex items-center gap-1.5 overflow-hidden px-2 py-0.5 text-ellipsis whitespace-nowrap',
                'border font-medium shadow-sm transition-all duration-150',
                'max-w-[180px]',
            )}
            aria-label={displayValue}
        >
            <span ref={textRef} className="min-w-0 truncate" title={displayValue}>
                {displayValue}
            </span>
        </Badge>
    );

    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent className="font-medium">{displayValue}</TooltipContent>
        </Tooltip>
    ) : (
        badge
    );
};

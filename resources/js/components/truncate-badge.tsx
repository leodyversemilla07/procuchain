import { Badge, type badgeVariants } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { VariantProps } from 'class-variance-authority';
import type { FC, ReactNode } from 'react';

/**
 * TruncateBadge — a Badge that truncates long text at a word boundary.
 *
 * Instead of CSS `text-overflow: ellipsis` (which cuts mid-word producing
 * "...urement Conference"), this component truncates to a configurable
 * max character count and appends "…" at the last complete word.
 *
 * Usage:
 *   <TruncateBadge variant="outline" maxChars={20}>{stage}</TruncateBadge>
 *   <TruncateBadge variant="secondary" icon={<Tag className="h-3 w-3" />} maxChars={22}>{label}</TruncateBadge>
 */
interface TruncateBadgeProps extends VariantProps<typeof badgeVariants> {
    children: string | undefined | null;
    maxChars?: number;
    className?: string;
    title?: string;
    icon?: ReactNode;
}

function truncateAtWord(text: string, maxChars: number): { display: string; wasTruncated: boolean } {
    if (text.length <= maxChars) return { display: text, wasTruncated: false };

    // Cut at maxChars, then walk back to the last space for a clean break
    let cut = text.slice(0, maxChars);
    const lastSpace = cut.lastIndexOf(' ');
    if (lastSpace > 0) {
        cut = cut.slice(0, lastSpace);
    }

    return { display: cut + '…', wasTruncated: true };
}

export const TruncateBadge: FC<TruncateBadgeProps> = ({ children, maxChars = 18, className, title, variant, icon }) => {
    const text = children ?? '';
    const { display, wasTruncated } = truncateAtWord(text, maxChars);

    return (
        <Badge variant={variant} className={cn('max-w-[10rem] sm:max-w-none', className)} title={title ?? (wasTruncated ? text : undefined)}>
            {icon}
            {display}
        </Badge>
    );
};

import { AlertCircle, ArrowUpCircle, FileCheck, FileText } from 'lucide-react';
import type { FC, JSX } from 'react';

import { TruncateBadge } from '@/components/truncate-badge';
import { cn } from '@/lib/utils';
import type { Event, TimelineItem } from '@/types';

interface TimelineEventItemProps {
    item: TimelineItem | Event;
    type: 'stage_change' | 'event';
}

export const TimelineEventItem: FC<TimelineEventItemProps> = ({ item, type }) => {
    const formattedTimeOnly = 'formatted_time_only' in item ? item.formatted_time_only : '';

    // Determine Icon and Styles
    let Icon: React.ComponentType<{ className?: string }>;
    let iconClassName = 'text-muted-foreground';
    let borderClassName = 'border-muted-foreground/20';

    if (type === 'stage_change') {
        Icon = ArrowUpCircle;
        iconClassName = 'text-primary';
        borderClassName = 'border-primary';
    } else {
        const eventItem = item as Event;
        if (eventItem.document_count && eventItem.document_count > 0) {
            Icon = FileCheck;
            iconClassName = 'text-primary';
            borderClassName = 'border-blue-200 dark:border-blue-800';
        } else if (eventItem.event_type === 'correction') {
            Icon = AlertCircle;
            iconClassName = 'text-muted-foreground';
            borderClassName = 'border-amber-200 dark:border-amber-800';
        } else {
            Icon = FileText;
        }
    }

    // Render Content Logic
    const renderContent = () => {
        if (type === 'stage_change') {
            const stageItem = item as TimelineItem;
            return (
                <div className="flex min-w-0 flex-col gap-1">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <span className="text-foreground shrink-0 text-sm font-semibold">Stage Update</span>
                        <TruncateBadge variant="default" className="hover:bg-primary text-xs" maxChars={20}>
                            {stageItem.stage_formatted || stageItem.stage}
                        </TruncateBadge>
                    </div>
                    <p className="text-muted-foreground min-w-0 text-sm">
                        Status: <span className="text-foreground/80 truncate font-medium">{stageItem.status_formatted || stageItem.status}</span>
                    </p>
                </div>
            );
        }

        const eventItem = item as Event;
        let eventDetails: string | JSX.Element = eventItem.details;

        if (eventItem.document_count && eventItem.document_count > 0) {
            eventDetails = (
                <div className="flex flex-col gap-1.5">
                    <p>{eventItem.details}</p>
                    <div className="text-muted-foreground bg-muted/50 flex w-fit items-center gap-1.5 rounded-md px-2 py-1 text-xs">
                        <FileText />
                        <span>
                            {eventItem.document_count} {eventItem.document_count === 1 ? 'document' : 'documents'} processed
                        </span>
                    </div>
                </div>
            );
        }

        return (
            <div className="flex min-w-0 flex-col gap-1">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <span className="text-foreground shrink-0 text-sm font-semibold">
                        {eventItem.event_type_formatted || eventItem.event_type.replace(/_/g, ' ')}
                    </span>
                    {eventItem.category && eventItem.category !== 'stage_transition' && (
                        <TruncateBadge variant="outline" className="text-muted-foreground h-5 px-1.5 text-[10px]" maxChars={18}>
                            {eventItem.category_formatted || eventItem.category}
                        </TruncateBadge>
                    )}
                </div>
                <div className="text-muted-foreground text-sm">{eventDetails}</div>
            </div>
        );
    };

    return (
        <div className="group relative py-1 pl-8 sm:pl-10">
            {/* Timeline Dot/Icon */}
            <div
                className={cn(
                    'bg-background ring-background group-hover:ring-muted/50 absolute top-1.5 left-0 z-10 flex h-8 w-8 -translate-x-1/2 items-center justify-center rounded-full border-2 ring-4 transition-colors duration-200',
                    borderClassName,
                )}
            >
                <Icon className={iconClassName} />
            </div>

            {/* Content Container */}
            <div className="flex flex-col gap-1">
                {/* Time Stamp (Above title for better scanning) */}
                <time className="text-muted-foreground/70 mb-0.5 font-mono text-xs">{formattedTimeOnly}</time>

                {/* Main Content */}
                {renderContent()}
            </div>
        </div>
    );
};

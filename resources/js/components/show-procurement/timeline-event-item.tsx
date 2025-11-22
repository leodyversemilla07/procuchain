import { FileText } from 'lucide-react';
import type { FC, JSX } from 'react';

import { Badge } from '@/components/ui/badge';
import type { Event, TimelineItem } from '@/types';

interface TimelineEventItemProps {
    item: TimelineItem | Event;
    type: 'stage_change' | 'event';
    stageOrder: number;
}

export const TimelineEventItem: FC<TimelineEventItemProps> = ({ item, type, stageOrder }) => {
    const formattedTimeOnly = 'formatted_time_only' in item ? item.formatted_time_only : '';

    if (type === 'stage_change') {
        const stageItem = item as TimelineItem;
        return (
            <div className="flex gap-2 sm:gap-3">
                <div className="flex flex-col items-center">
                    <div className="bg-primary text-primary-foreground flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-medium sm:h-8 sm:w-8 sm:text-sm">
                        {stageOrder !== 999 ? stageOrder + 1 : '?'}
                    </div>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-muted-foreground mb-1 flex items-center gap-2 text-xs sm:mb-2 sm:text-sm">
                        <time dateTime={stageItem.timestamp}>{formattedTimeOnly}</time>
                    </div>
                    <div className="space-y-1 sm:space-y-2">
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary" className="text-xs">
                                {stageItem.stage_formatted || stageItem.stage}
                            </Badge>
                        </div>
                        <div className="bg-muted rounded-lg border p-2 sm:p-3">
                            <div className="mb-1 flex items-center justify-between">
                                <span className="text-xs font-medium sm:text-sm">
                                    {stageItem.status_formatted || stageItem.status}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs sm:text-sm">
                                Procurement moved to <strong>{stageItem.stage_formatted || stageItem.stage}</strong> stage
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    // Event type
    const eventItem = item as Event;
    let eventDetails: string | JSX.Element = eventItem.details;

    if (eventItem.document_count && eventItem.document_count > 0) {
        eventDetails = (
            <>
                {eventItem.details}
                <div className="bg-muted border-border text-muted-foreground mt-2 inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                    <FileText className="mr-1.5 h-3.5 w-3.5" />
                    {eventItem.document_count} {eventItem.document_count === 1 ? 'document' : 'documents'} processed
                </div>
            </>
        );
    }

    return (
        <div className="flex gap-2 sm:gap-3">
            <div className="flex flex-col items-center">
                <div className="bg-muted flex h-6 w-6 shrink-0 items-center justify-center rounded-full border sm:h-8 sm:w-8">
                    <FileText className="text-muted-foreground h-3 w-3 sm:h-4 sm:w-4" />
                </div>
            </div>
            <div className="min-w-0 flex-1">
                <div className="text-muted-foreground mb-1 flex items-center gap-2 text-xs sm:mb-2 sm:text-sm">
                    <time dateTime={eventItem.timestamp}>{formattedTimeOnly}</time>
                </div>
                <div className="space-y-1 sm:space-y-2">
                    <div className="flex flex-wrap items-center gap-1 sm:gap-2">
                        <h3 className="text-sm font-medium sm:text-base">{eventItem.event_type_formatted || eventItem.event_type.replace(/_/g, ' ')}</h3>
                        {eventItem.stage && (
                            <Badge variant="outline" className="text-xs">
                                {eventItem.stage_formatted || eventItem.stage}
                            </Badge>
                        )}
                        {eventItem.category && eventItem.category_formatted && eventItem.category !== 'stage_transition' && (
                            <Badge variant="secondary" className="text-xs">
                                {eventItem.category_formatted}
                            </Badge>
                        )}
                    </div>
                    <div className="bg-muted rounded-lg border p-2 sm:p-3">
                        <div className="text-muted-foreground text-xs sm:text-sm">{eventDetails}</div>
                    </div>
                </div>
            </div>
        </div>
    );
};

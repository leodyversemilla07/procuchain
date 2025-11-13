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
            <div className="flex gap-3">
                <div className="flex flex-col items-center">
                    <div className="bg-primary text-primary-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                        {stageOrder !== 999 ? stageOrder + 1 : '?'}
                    </div>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                        <time dateTime={stageItem.timestamp}>{formattedTimeOnly}</time>
                    </div>
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <h3 className="font-medium">Stage Transition</h3>
                            <Badge variant="secondary" className="text-xs">
                                {stageItem.stage_formatted || stageItem.stage}
                            </Badge>
                        </div>
                        <div className="bg-muted rounded-lg border p-3">
                            <div className="mb-1 flex items-center justify-between">
                                <span className="text-sm font-medium">
                                    Status: {stageItem.status_formatted || stageItem.status}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-sm">
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
        <div className="flex gap-3">
            <div className="flex flex-col items-center">
                <div className="bg-muted flex h-8 w-8 shrink-0 items-center justify-center rounded-full border">
                    <FileText className="text-muted-foreground h-4 w-4" />
                </div>
            </div>
            <div className="min-w-0 flex-1">
                <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                    <time dateTime={eventItem.timestamp}>{formattedTimeOnly}</time>
                </div>
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <h3 className="font-medium capitalize">{eventItem.event_type.replace(/_/g, ' ')}</h3>
                        {eventItem.stage && (
                            <Badge variant="outline" className="text-xs">
                                {eventItem.stage_formatted || eventItem.stage}
                            </Badge>
                        )}
                        {eventItem.category && (
                            <Badge variant="secondary" className="text-xs capitalize">
                                {eventItem.category}
                            </Badge>
                        )}
                    </div>
                    <div className="bg-muted rounded-lg border p-3">
                        <div className="text-muted-foreground text-sm">{eventDetails}</div>
                    </div>
                </div>
            </div>
        </div>
    );
};

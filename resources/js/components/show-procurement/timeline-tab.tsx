import { Calendar, CheckCircle, Clock } from 'lucide-react';
import { useMemo } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardFooter, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import type { Event, TimelineItem } from '@/types';
import { TimelineEventItem } from './timeline-event-item';

interface TimelineTabProps {
    timeline?: TimelineItem[];
    events?: Event[];
}

export function TimelineTab({ timeline, events }: TimelineTabProps) {
    const timelineItemsByDate = useMemo(() => {
        const combinedItems: Array<{
            timestamp: string;
            formatted_date_only: string;
            formatted_time_only: string;
            type: 'stage_change' | 'event';
            stageOrder: number;
            raw: TimelineItem | Event;
        }> = [];

        (timeline ?? []).forEach((item) => {
            combinedItems.push({
                timestamp: item.timestamp,
                formatted_date_only: item.formatted_date_only || '',
                formatted_time_only: item.formatted_time_only || '',
                type: 'stage_change',
                stageOrder: item.stage_order ?? 999,
                raw: item,
            });
        });

        (events ?? []).forEach((event) => {
            combinedItems.push({
                timestamp: event.timestamp,
                formatted_date_only: event.formatted_date_only || '',
                formatted_time_only: event.formatted_time_only || '',
                type: 'event',
                stageOrder: event.stage_order ?? 999,
                raw: event,
            });
        });

        // Sort timeline items with latest first (descending order)
        combinedItems.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

        const itemsByDate: Record<string, typeof combinedItems> = {};

        combinedItems.forEach((item) => {
            const date = item.formatted_date_only;
            if (!itemsByDate[date]) {
                itemsByDate[date] = [];
            }
            itemsByDate[date].push(item);
        });

        return itemsByDate;
    }, [timeline, events]);

    if (Object.keys(timelineItemsByDate).length === 0) {
        return (
            <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
                <CardContent className="p-0">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Clock className="text-muted-foreground" />
                            </EmptyMedia>
                            <EmptyTitle>No Timeline Events</EmptyTitle>
                            <EmptyDescription>
                                Timeline events will appear here as the procurement progresses.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
            <CardHeader>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 sm:h-10 sm:w-10">
                            <Clock className="h-4 w-4 text-primary sm:h-5 sm:w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <CardTitle className="text-base sm:text-lg">Event Timeline</CardTitle>
                            <CardDescription className="text-xs sm:text-sm">
                                Chronological history of procurement events
                            </CardDescription>
                        </div>
                    </div>
                    <Badge variant="outline" className="w-fit font-medium">
                        Latest First
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="space-y-0" role="list" aria-label="Timeline events">
                    {Object.keys(timelineItemsByDate)
                        .sort((a, b) => new Date(b).getTime() - new Date(a).getTime())
                        .map((date, dateIndex) => {
                            const isFirstDate = dateIndex === 0;

                            return (
                                <section
                                    key={date}
                                    className="border-b last:border-b-0"
                                    role="listitem"
                                >
                                    <div className="sticky top-0 z-10 border-b bg-muted/80 p-3 backdrop-blur-sm sm:p-4">
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-primary" aria-hidden="true" />
                                            <time
                                                dateTime={date}
                                                className="text-sm font-semibold sm:text-base"
                                            >
                                                {date}
                                            </time>
                                            {isFirstDate && (
                                                <Badge variant="default" className="text-xs">
                                                    Latest
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-0">
                                        {timelineItemsByDate[date]
                                            .sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
                                            .map((item, itemIndex) => (
                                                <div
                                                    key={`${item.timestamp}-${itemIndex}`}
                                                    className="border-b p-3 last:border-b-0 sm:p-4"
                                                >
                                                    <TimelineEventItem 
                                                        item={item.raw} 
                                                        type={item.type} 
                                                        stageOrder={item.stageOrder} 
                                                    />
                                                </div>
                                            ))}
                                    </div>
                                </section>
                            );
                        })}
                </div>
            </CardContent>
            <CardFooter className="justify-center border-t py-4 sm:py-6">
                <span className="inline-flex items-center gap-2 text-xs text-muted-foreground sm:text-sm">
                    <CheckCircle className="h-3 w-3 sm:h-4 sm:w-4" aria-hidden="true" />
                    Beginning of Timeline
                </span>
            </CardFooter>
        </Card>
    );
}

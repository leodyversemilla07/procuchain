import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ProcurementListItem, Stage } from '@/types/blockchain';
import { KanbanCard } from '@/components/procurements-list/kanban-card';
import { getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { cn } from '@/lib/utils';
import { Layers } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface KanbanBoardProps {
    procurements: ProcurementListItem[];
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinModal?: (procurement: ProcurementListItem) => void;
}

export const KanbanBoard = ({
    procurements,
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenSupplementalBidBulletinModal,
}: KanbanBoardProps) => {
    const orderedStages = Object.values(Stage);
    const [windowWidth, setWindowWidth] = useState<number>(typeof window !== 'undefined' ? window.innerWidth : 0);

    useEffect(() => {
        const handleResize = () => {
            setWindowWidth(window.innerWidth);
        };

        window.addEventListener('resize', handleResize);
        handleResize();

        return () => {
            window.removeEventListener('resize', handleResize);
        };
    }, []);

    // Group procurements by stage
    const procurementsByStage = procurements.reduce((acc, proc) => {
        const stage = proc.stage as Stage;
        if (!acc[stage]) {
            acc[stage] = [];
        }
        acc[stage].push(proc);
        return acc;
    }, {} as Record<Stage, ProcurementListItem[]>);

    // Filter the ordered stages to only include those with procurements
    const stagesToDisplay = orderedStages.filter(stage => procurementsByStage[stage]?.length > 0);

    // Calculate max height based on viewport size
    const getMaxColumnHeight = () => {
        if (windowWidth >= 1536) return 'calc(100vh - 10rem)'; // 2xl
        if (windowWidth >= 1280) return 'calc(100vh - 11rem)'; // xl
        if (windowWidth >= 1024) return 'calc(100vh - 12rem)'; // lg
        if (windowWidth >= 768) return 'calc(100vh - 10rem)';  // md
        return 'calc(100vh - 8rem)';  // sm and below
    };

    return (
        <div className="pb-4 w-full box-border h-full overflow-hidden">
            {/* Mobile and Tablet view - Responsive grid */}
            <div className="lg:hidden">
                <ScrollArea className="h-[calc(100vh-12rem)] w-full">
                    <div className="grid gap-3 p-3 auto-rows-min grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                        {stagesToDisplay.map(stage => (
                            <div
                                key={stage}
                                className={cn(
                                    "flex flex-col",
                                    "bg-card/80 backdrop-blur-sm",
                                    "rounded-lg border shadow-sm hover:shadow-md transition-all duration-200",
                                    "min-h-0" // Important for proper flexbox behavior
                                )}
                            >
                                {/* Column Header */}
                                <div className="bg-card/95 backdrop-blur-sm border-b p-3 rounded-t-lg flex-shrink-0">
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2 flex-1 min-w-0">
                                            <Layers className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className="flex-1 min-w-0">
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    getStageBadgeStyle(stage),
                                                                    "text-xs font-medium px-2 py-1 w-full max-w-full"
                                                                )}
                                                            >
                                                                <span className="truncate block">{stage}</span>
                                                            </Badge>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" className="max-w-xs">
                                                        <div className="text-center">{stage}</div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <div className="bg-muted text-muted-foreground rounded-full px-2 py-1 
                                                      text-xs font-medium min-w-[2rem] text-center flex-shrink-0">
                                            {procurementsByStage[stage].length}
                                        </div>
                                    </div>
                                </div>

                                {/* Column Body - Scrollable */}
                                <div className="p-3 flex-1 min-h-0 max-h-[300px] sm:max-h-[400px]">
                                    <ScrollArea className="h-full w-full">
                                        <div className="space-y-2 pr-1">
                                            {procurementsByStage[stage].map(procurement => (
                                                <KanbanCard
                                                    key={procurement.id}
                                                    procurement={procurement}
                                                    onOpenPreProcurementModal={onOpenPreProcurementModal}
                                                    onOpenPreBidModal={onOpenPreBidModal}
                                                    onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                                                />
                                            ))}
                                        </div>
                                    </ScrollArea>
                                </div>
                            </div>
                        ))}
                    </div>
                </ScrollArea>
            </div>

            {/* Desktop view - Full height columns */}
            <div className="hidden lg:block h-full overflow-hidden">
                <div className={cn(
                    "grid gap-4 h-full auto-rows-fr",
                    "grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5",
                    "px-4 overflow-hidden"
                )}>
                    {stagesToDisplay.map(stage => (
                        <div
                            key={stage}
                            style={{ maxHeight: getMaxColumnHeight() }}
                            className={cn(
                                "flex flex-col h-full",
                                "bg-card/80 backdrop-blur-sm",
                                "rounded-lg border shadow-sm hover:shadow-md transition-all duration-200",
                                "min-h-0 overflow-hidden"
                            )}
                        >
                            {/* Column Header */}
                            <div className="bg-card/95 backdrop-blur-sm border-b p-3 rounded-t-lg flex-shrink-0">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 flex-1 min-w-0">
                                        <Layers className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className="flex-1 min-w-0">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                getStageBadgeStyle(stage),
                                                                "text-xs font-medium px-2 py-1 w-full"
                                                            )}
                                                        >
                                                            <span className="truncate block">{stage}</span>
                                                        </Badge>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" className="max-w-xs">
                                                    <div className="text-center">{stage}</div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <div className="bg-muted text-muted-foreground rounded-full px-2 py-1 
                                                  text-xs font-medium min-w-[2rem] text-center flex-shrink-0">
                                        {procurementsByStage[stage].length}
                                    </div>
                                </div>
                            </div>

                            {/* Column Body - Scrollable */}
                            <div className="p-3 flex-1 min-h-0">
                                <ScrollArea className="h-full w-full">
                                    <div className="space-y-2 pr-2">
                                        {procurementsByStage[stage].map(procurement => (
                                            <KanbanCard
                                                key={procurement.id}
                                                procurement={procurement}
                                                onOpenPreProcurementModal={onOpenPreProcurementModal}
                                                onOpenPreBidModal={onOpenPreBidModal}
                                                onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                                            />
                                        ))}
                                    </div>
                                </ScrollArea>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

import { Badge } from '@/components/ui/badge';
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
        <div className="pb-4 w-full box-border h-full overflow-y-auto">
            {/* Mobile view for small screens (vertical stack) */}
            <div className="md:hidden flex flex-col space-y-3 px-2 sm:px-3">
                {stagesToDisplay.map(stage => (
                    <div
                        key={stage}
                        className={cn(
                            "w-full flex flex-col",
                            "bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm",
                            "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                            "shadow-sm hover:shadow-md transition-all duration-200"
                        )}
                    >
                        <div className="sticky top-0 z-10 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm 
                                      border-b border-gray-200/90 dark:border-gray-700/80 p-2.5 sm:p-3 rounded-t-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2 flex-1">
                                    <div className="flex items-center flex-1">
                                        <Layers className="h-4 w-4 text-gray-500 dark:text-gray-400 mr-2 flex-shrink-0" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className="max-w-[100px] sm:max-w-[120px]">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                getStageBadgeStyle(stage),
                                                                "whitespace-nowrap text-xs font-medium px-2 py-1 w-full"
                                                            )}
                                                        >
                                                            <span className="truncate block">{stage}</span>
                                                        </Badge>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">{stage}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <div className="bg-gray-100 text-gray-700 dark:bg-gray-700/80 dark:text-gray-300 
                                                  rounded-full px-2 py-0.5 text-xs font-medium min-w-[2rem] text-center flex-shrink-0">
                                        {procurementsByStage[stage].length}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Column Body */}
                        <div className="p-2.5 sm:p-3 flex-grow">
                            <div className="space-y-2">
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
                        </div>
                    </div>
                ))}
            </div>

            {/* Desktop view with wrapping columns */}
            <div className="hidden md:block h-full">
                <div className={cn(
                    "grid gap-3 lg:gap-4 auto-rows-fr",
                    "grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5",
                    "px-3 lg:px-4"
                )}>
                    {stagesToDisplay.map(stage => (
                        <div
                            key={stage}
                            style={{ maxHeight: getMaxColumnHeight() }}
                            className={cn(
                                "flex flex-col h-full",
                                "bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm",
                                "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                                "shadow-sm hover:shadow-md transition-all duration-200"
                            )}
                        >
                            <div className="sticky top-0 z-10 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm 
                                          border-b border-gray-200/90 dark:border-gray-700/80 p-2.5 lg:p-3 rounded-t-lg">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 flex-1">
                                        <div className="flex items-center flex-1">
                                            <Layers className="h-4 w-4 text-gray-500 dark:text-gray-400 mr-2 flex-shrink-0" />
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className="max-w-[120px] lg:max-w-[140px]">
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    getStageBadgeStyle(stage),
                                                                    "whitespace-nowrap text-xs font-medium px-2 py-1 w-full"
                                                                )}
                                                            >
                                                                <span className="truncate block">{stage}</span>
                                                            </Badge>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top">{stage}</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <div className="bg-gray-100 text-gray-700 dark:bg-gray-700/80 dark:text-gray-300 
                                                      rounded-full px-2 py-0.5 text-xs font-medium min-w-[2rem] text-center flex-shrink-0">
                                            {procurementsByStage[stage].length}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Column Body */}
                            <div className="p-2.5 lg:p-3 flex-grow overflow-y-auto">
                                <div className="space-y-2">
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
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};
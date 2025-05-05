import { Badge } from '@/components/ui/badge';
import { ProcurementListItem, Stage } from '@/types/blockchain';
import { KanbanCard } from '@/components/procurements-list/kanban-card';
import { getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { cn } from '@/lib/utils';
import { Layers } from 'lucide-react';
import { useEffect, useState } from 'react';

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
        if (windowWidth >= 1024) return 'calc(100vh - 14rem)';
        if (windowWidth >= 768) return 'calc(100vh - 12rem)';
        return 'calc(100vh - 10rem)';
    };

    // Responsive spacing
    const getSpacing = () => {
        if (windowWidth >= 1024) return 'gap-4 px-4';
        if (windowWidth >= 640) return 'gap-3 px-3';
        return 'gap-2 px-2';
    };

    return (
        <div className="pb-4 md:pb-6 w-full box-border h-full overflow-y-auto">
            {/* Mobile view for small screens (vertical stack) */}
            <div className="md:hidden flex flex-col space-y-3 sm:space-y-4 px-2 sm:px-4">
                {stagesToDisplay.map(stage => (
                    <div
                        key={stage}
                        className={cn(
                            "w-full flex flex-col",
                            "bg-gray-50/80 dark:bg-gray-800/30 backdrop-blur-sm",
                            "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                            "shadow-sm hover:shadow transition-all duration-200"
                        )}
                    >
                        <div className="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-sm 
                                      border-b border-gray-200/90 dark:border-gray-700/80 p-2 sm:p-3 rounded-t-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1.5 sm:gap-2">
                                    <div className="flex items-center">
                                        <Layers className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400 mr-1.5 sm:mr-2" />
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                getStageBadgeStyle(stage),
                                                "whitespace-nowrap text-xs font-medium"
                                            )}
                                        >
                                            {stage}
                                        </Badge>
                                    </div>
                                    <div className="bg-gray-200/80 text-gray-700 dark:bg-gray-700/80 dark:text-gray-300 
                                                 rounded-full px-1.5 sm:px-2 py-0.5 text-xs font-medium min-w-[1.5rem] sm:min-w-[1.75rem] text-center">
                                        {procurementsByStage[stage].length}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Column Body */}
                        <div className="p-2 flex-grow">
                            <div className="space-y-2 py-1">
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
                    "grid gap-4 auto-rows-fr",
                    "grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5",
                    getSpacing()
                )}>
                    {stagesToDisplay.map(stage => (
                        <div
                            key={stage}
                            style={{ maxHeight: getMaxColumnHeight() }}
                            className={cn(
                                "flex flex-col h-full",
                                "bg-gray-50/80 dark:bg-gray-800/30 backdrop-blur-sm",
                                "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                                "shadow-sm hover:shadow transition-all duration-200"
                            )}
                        >
                            <div className="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-sm 
                                          border-b border-gray-200/90 dark:border-gray-700/80 p-3 rounded-t-lg">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="flex items-center">
                                            <Layers className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400 mr-2" />
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    getStageBadgeStyle(stage),
                                                    "whitespace-nowrap text-xs font-medium"
                                                )}
                                            >
                                                {stage}
                                            </Badge>
                                        </div>
                                        <div className="bg-gray-200/80 text-gray-700 dark:bg-gray-700/80 dark:text-gray-300 
                                                     rounded-full px-2 py-0.5 text-xs font-medium min-w-[1.75rem] text-center">
                                            {procurementsByStage[stage].length}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Column Body */}
                            <div className="p-2 overflow-y-auto flex-grow custom-scrollbar">
                                <div className="space-y-2 py-1">
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
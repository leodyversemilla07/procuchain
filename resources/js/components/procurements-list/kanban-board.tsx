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
    // Define the canonical order of stages based on the enum
    const orderedStages = Object.values(Stage);
    const [windowWidth, setWindowWidth] = useState<number>(typeof window !== 'undefined' ? window.innerWidth : 0);

    // Track window resize for more precise responsive behavior
    useEffect(() => {
        const handleResize = () => {
            setWindowWidth(window.innerWidth);
        };
        
        window.addEventListener('resize', handleResize);
        handleResize(); // Initial call
        
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

    // Dynamically calculate column sizes based on screen width
    const getColumnWidth = () => {
        if (windowWidth >= 1536) return 'minmax(20rem, 24rem)'; // 2xl
        if (windowWidth >= 1280) return 'minmax(18rem, 22rem)'; // xl
        return 'minmax(16rem, 20rem)'; // lg and lower
    };

    // Calculate max height based on viewport size
    const getMaxColumnHeight = () => {
        // More space for content on larger screens
        if (windowWidth >= 1024) {
            return 'calc(100vh - 12rem)'; 
        }
        return 'calc(100vh - 10rem)'; 
    };

    // Responsive spacing
    const getSpacing = () => {
        if (windowWidth >= 1024) return 'gap-4 px-4';
        if (windowWidth >= 640) return 'gap-3 px-3';
        return 'gap-2 px-2';
    };
    
    return (
        <div className="pb-4 md:pb-6 overflow-x-hidden w-full box-border">
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
                        {/* Column Header */}
                        <div className="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-sm border-b border-gray-200/90 dark:border-gray-700/80 p-2 sm:p-3 rounded-t-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1 sm:gap-2">
                                    <div className="flex items-center">
                                        <Layers className="h-3 w-3 sm:h-3.5 sm:w-3.5 text-gray-500 dark:text-gray-400 mr-1 sm:mr-2" />
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
                        
                        {/* Column Body - Cards Container */}
                        <div className="p-1.5 sm:p-2 overflow-y-auto flex-grow" style={{ maxHeight: getMaxColumnHeight() }}>
                            <div className="space-y-2 sm:space-y-3 py-1">
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

            {/* Tablet view (2 columns) */}
            <div className="hidden md:block lg:hidden overflow-x-auto pb-2 px-2 sm:px-4">
                <div className="grid auto-rows-fr gap-3" 
                     style={{ 
                         gridTemplateColumns: 'repeat(auto-fit, minmax(16rem, 1fr))',
                         gridAutoFlow: 'column',
                         minWidth: 'max-content',
                     }}>
                    {stagesToDisplay.map(stage => (
                        <div 
                            key={stage} 
                            className={cn(
                                "flex flex-col h-full",
                                "bg-gray-50/80 dark:bg-gray-800/30 backdrop-blur-sm",
                                "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                                "shadow-sm hover:shadow transition-all duration-200",
                                "min-w-[16rem] max-w-[20rem] w-full"
                            )}
                        >
                            {/* Column Header */}
                            <div className="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-sm border-b border-gray-200/90 dark:border-gray-700/80 p-3 rounded-t-lg">
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
                            
                            {/* Column Body - Cards Container */}
                            <div className="p-2 overflow-y-auto flex-grow custom-scrollbar" style={{ maxHeight: getMaxColumnHeight() }}>
                                <div className="space-y-3 py-1">
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

            {/* Desktop view with responsive grid layout */}
            <div className="hidden lg:block overflow-x-auto px-2 sm:px-4">
                <div className={cn("grid auto-rows-fr", getSpacing())}
                     style={{ 
                         gridTemplateColumns: `repeat(auto-fill, ${getColumnWidth()})`,
                         minWidth: 'max-content',
                     }}>
                    {stagesToDisplay.map(stage => (
                        <div 
                            key={stage} 
                            className={cn(
                                "flex flex-col h-full",
                                "bg-gray-50/80 dark:bg-gray-800/30 backdrop-blur-sm",
                                "rounded-lg border border-gray-200/90 dark:border-gray-700/60",
                                "shadow-sm hover:shadow transition-all duration-200"
                            )}
                        >
                            {/* Column Header */}
                            <div className="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-800/95 backdrop-blur-sm border-b border-gray-200/90 dark:border-gray-700/80 p-3 rounded-t-lg">
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
                            
                            {/* Column Body - Cards Container */}
                            <div className="p-2 overflow-y-auto flex-grow custom-scrollbar" style={{ maxHeight: getMaxColumnHeight() }}>
                                <div className="space-y-2 py-1 lg:space-y-3">
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
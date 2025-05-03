import { Badge } from '@/components/ui/badge';
import { ProcurementListItem, Stage } from '@/types/blockchain';
import { KanbanCard } from '@/components/procurements-list/kanban-card';
import { getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { cn } from '@/lib/utils';
import { Layers } from 'lucide-react';

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

    return (
        <div className="pb-6">
            {/* Mobile view with vertical stack */}
            <div className="lg:hidden flex flex-col space-y-4 px-1 sm:px-4">
                {stagesToDisplay.map(stage => (
                    <div 
                        key={stage} 
                        className={cn(
                            "w-full flex flex-col", // Removed sm:w-[280px] to ensure full width on all mobile screens
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
                        <div className="p-2 overflow-y-auto flex-grow max-h-[calc(100vh-220px)] custom-scrollbar">
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

            {/* Desktop view with auto-fit grid layout and fixed card width */}
            <div className="hidden lg:block overflow-x-hidden px-1 sm:px-4">
                <div className="grid auto-rows-fr gap-4" 
                     style={{ 
                         gridTemplateColumns: 'repeat(auto-fit, 280px)',
                         justifyContent: 'start'
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
                            <div className="p-2 overflow-y-auto flex-grow max-h-[calc(100vh-220px)] custom-scrollbar">
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
        </div>
    );
};
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ProcurementListItem, Stage } from '@/types/blockchain';
import { KanbanCard } from '@/components/procurements-list/kanban-card';
import { getStageBadgeStyle } from '@/lib/procurements-list-utils';

interface KanbanBoardProps {
    procurements: ProcurementListItem[];
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal?: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog?: (procurement: ProcurementListItem) => void;
}

export const KanbanBoard = ({
    procurements,
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenMarkCompleteDialog,
}: KanbanBoardProps) => {
    const stages = [...new Set(procurements.map(proc => proc.stage))] as Stage[];
    const procurementsByStage: Record<string, ProcurementListItem[]> = {};
    stages.forEach(stage => {
        procurementsByStage[stage] = procurements.filter(proc => proc.stage === stage);
    });

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 overflow-x-auto">
            {stages.map(stage => (
                <Card key={stage} className="border-sidebar-border/70 dark:border-sidebar-border rounded-lg p-3 min-w-[240px]">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="font-medium text-sm flex items-center gap-2 dark:text-gray-200">
                            <Badge variant="outline" className={`${getStageBadgeStyle(stage)} whitespace-nowrap`}>
                                {stage}
                            </Badge>
                            <span className="ml-1 bg-gray-200 text-gray-700 rounded-full px-2 py-0.5 text-xs dark:bg-gray-700 dark:text-gray-300">
                                {procurementsByStage[stage].length}
                            </span>
                        </h3>
                    </div>
                    <div className="space-y-2">
                        {procurementsByStage[stage].map(procurement => (
                            <KanbanCard
                                key={procurement.id}
                                procurement={procurement}
                                onOpenPreProcurementModal={onOpenPreProcurementModal}
                                onOpenPreBidModal={onOpenPreBidModal}
                                onOpenMarkCompleteDialog={onOpenMarkCompleteDialog}
                            />
                        ))}
                    </div>
                </Card>
            ))}
        </div>
    );
};
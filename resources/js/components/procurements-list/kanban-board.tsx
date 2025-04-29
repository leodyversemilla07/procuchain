import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ProcurementListItem, Stage } from '@/types/blockchain';
import { KanbanCard } from '@/components/procurements-list/kanban-card';
import { getStageBadgeStyle } from '@/lib/procurements-list-utils';

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
        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 overflow-x-auto">
            {/* Map over the ordered and filtered stages */}
            {stagesToDisplay.map(stage => (
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
                        {/* Render procurements for the current stage */}
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
                </Card>
            ))}
        </div>
    );
};
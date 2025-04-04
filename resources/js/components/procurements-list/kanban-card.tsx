import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileIcon, CalendarIcon } from 'lucide-react';
import { ProcurementListItem } from '@/types/blockchain';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { getStatusBadgeStyle } from '@/lib/procurements-list-utils';

interface KanbanCardProps {
    procurement: ProcurementListItem;
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog?: (procurement: ProcurementListItem) => void;
}

export const KanbanCard = ({
    procurement,
    onOpenPreProcurementModal,
    onOpenMarkCompleteDialog,
}: KanbanCardProps) => {
    return (
        <Card className="mb-2 cursor-pointer hover:border-blue-600 dark:hover:border-blue-500 transition-all duration-200 shadow-sm border-sidebar-border/70 dark:border-sidebar-border">
            <CardContent className="p-3">
                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-1">
                        <div className="max-w-[80px] flex-shrink-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" className="bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800 text-xs w-full">
                                            <span className="truncate inline-block max-w-full">{procurement.id}</span>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{procurement.id}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                        <div className="flex-shrink-0 max-w-[130px] w-full">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" className={`${getStatusBadgeStyle(procurement.current_status)} text-xs truncate w-full inline-flex justify-end`}>
                                            <span className="truncate">{procurement.current_status}</span>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{procurement.current_status}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                    <Link href={`/procurement/${procurement.id}`} className="block">
                        <h3 className="font-medium text-sm truncate hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400">{procurement.title}</h3>
                    </Link>
                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <div className="flex items-center gap-1">
                            <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400" />
                            <span>{procurement.document_count} docs</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                            <span>{procurement.last_updated}</span>
                        </div>
                    </div>
                    <ActionButtons
                        procurement={procurement}
                        variant="kanban"
                        onOpenPreProcurementModal={onOpenPreProcurementModal}
                        onOpenMarkCompleteDialog={onOpenMarkCompleteDialog}
                    />
                </div>
            </CardContent>
        </Card>
    );
};
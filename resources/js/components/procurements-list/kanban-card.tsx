import { Link, usePage } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileIcon, CalendarIcon } from 'lucide-react';
import { ProcurementListItem } from '@/types/blockchain';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { getStatusBadgeStyle } from '@/lib/procurements-list-utils';
import { SharedData } from '@/types';
import { cn } from '@/lib/utils';

interface KanbanCardProps {
    procurement: ProcurementListItem;
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinModal?: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog?: (procurement: ProcurementListItem) => void;
}

export const KanbanCard = ({
    procurement,
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenSupplementalBidBulletinModal,
}: KanbanCardProps) => {
    const { auth } = usePage<SharedData>().props;
    const userRole = (auth?.user?.role || 'guest').replace('_', '-');
    const baseRoute = `/${userRole}/procurements-list/${procurement.id}`;

    return (
        <Card className="mb-2 cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md 
                         shadow-sm border-sidebar-border/70 
                         dark:border-sidebar-border overflow-hidden">
            <CardContent className="p-2 sm:p-3">
                <div className="space-y-2 sm:space-y-2.5">
                    {/* Top - ID Badge and Status */}
                    <div className="flex flex-col xs:flex-row items-start xs:items-center justify-between gap-1.5 sm:gap-2">
                        {/* ID Badge */}
                        <div className="w-full xs:w-1/2 flex-shrink-0 min-w-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" className="bg-blue-50/80 text-blue-700 border border-blue-200/80 
                                                                         dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800/80 
                                                                         text-xs w-full font-medium py-1">
                                            <span className="truncate inline-block max-w-full">{procurement.id}</span>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="font-mono">{procurement.id}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                        {/* Status Badge */}
                        <div className="w-full xs:w-1/2 min-w-0 mt-1 xs:mt-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline"
                                            className={cn(
                                                getStatusBadgeStyle(procurement.current_status),
                                                "text-xs w-full inline-flex items-center font-medium py-1"
                                            )}>
                                            <div className="truncate max-w-full">{procurement.current_status}</div>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{procurement.current_status}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>

                    {/* Title with Link */}
                    <Link href={baseRoute} className="block group">
                        <h3 className="font-medium text-xs sm:text-sm line-clamp-2 group-hover:text-blue-600 
                                     dark:text-gray-100 dark:group-hover:text-blue-400 transition-colors">
                            {procurement.title}
                        </h3>
                    </Link>

                    {/* Info Row */}
                    <div className="flex flex-col xs:flex-row items-start xs:items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-0.5 gap-y-2">
                        <div className="flex items-center gap-1.5">
                            <div className="flex items-center rounded-full bg-blue-50 dark:bg-blue-900/20 p-1 pr-2">
                                <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400 mr-0.5" />
                                <span className="font-medium">{procurement.document_count}</span>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                            <span className="font-medium text-right">{procurement.last_updated}</span>
                        </div>
                    </div>

                    {/* Divider before actions */}
                    <div className="border-t border-gray-100 dark:border-gray-800 mt-2 pt-2"></div>

                    {/* Action Buttons */}
                    <div className="flex flex-wrap gap-2">
                        <ActionButtons
                            procurement={procurement}
                            variant="kanban"
                            onOpenPreProcurementModal={onOpenPreProcurementModal}
                            onOpenPreBidModal={onOpenPreBidModal}
                            onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};
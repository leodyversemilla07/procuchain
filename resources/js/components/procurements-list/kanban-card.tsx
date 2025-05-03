import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileIcon, CalendarIcon } from 'lucide-react';
import { ProcurementListItem } from '@/types/blockchain';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { getStatusBadgeStyle } from '@/lib/procurements-list-utils';
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
    return (
        <Card className="mb-2 cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md 
                         shadow-sm border-sidebar-border/70 
                         dark:border-sidebar-border overflow-hidden">
            <CardContent className="p-3">
                <div className="space-y-2.5">
                    {/* Top - ID Badge and Status */}
                    <div className="flex items-center justify-between gap-2">
                        {/* ID Badge */}
                        <div className="w-[70px] flex-shrink-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" className="bg-blue-50/80 text-blue-700 border border-blue-200/80 
                                                                         dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800/80 
                                                                         text-xs w-full font-medium">
                                            <span className="truncate inline-block max-w-full">{procurement.id}</span>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="font-mono">{procurement.id}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                        {/* Status Badge */}
                        <div className="flex-1 min-w-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" 
                                              className={cn(
                                                getStatusBadgeStyle(procurement.current_status), 
                                                "text-xs w-full inline-flex items-center font-medium"
                                              )}>
                                            <div className="truncate max-w-[110px]">{procurement.current_status}</div>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{procurement.current_status}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                    
                    {/* Title with Link */}
                    <Link href={`/procurement/${procurement.id}`} className="block group">
                        <h3 className="font-medium text-sm line-clamp-2 group-hover:text-blue-600 
                                     dark:text-gray-100 dark:group-hover:text-blue-400 transition-colors">
                            {procurement.title}
                        </h3>
                    </Link>
                    
                    {/* Info Row */}
                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-0.5">
                        <div className="flex items-center gap-1.5">
                            <div className="flex items-center rounded-full bg-blue-50 dark:bg-blue-900/20 p-0.5 pr-2">
                                <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400 mr-0.5" />
                                <span className="font-medium">{procurement.document_count}</span>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                            <span className="font-medium">{procurement.last_updated}</span>
                        </div>
                    </div>
                    
                    {/* Divider before actions */}
                    <div className="border-t border-gray-100 dark:border-gray-800 pt-1"></div>
                    
                    {/* Action Buttons */}
                    <ActionButtons
                        procurement={procurement}
                        variant="kanban"
                        onOpenPreProcurementModal={onOpenPreProcurementModal}
                        onOpenPreBidModal={onOpenPreBidModal}
                        onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                    />
                </div>
            </CardContent>
        </Card>
    );
};
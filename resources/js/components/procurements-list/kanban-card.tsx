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
    const baseRoute = `/${userRole}/procurements-list/${procurement.id}`;    return (
        <Card className="mb-2 cursor-pointer hover:border-primary/50 hover:shadow-md 
                         shadow-sm border-border overflow-hidden
                         transition-all duration-200 bg-card">
            <CardContent className="p-3">
                <div className="space-y-3">
                    {/* Top - ID Badge and Status */}
                    <div className="flex items-center justify-between gap-2">
                        {/* ID Badge */}
                        <div className="flex-1 min-w-0">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Badge variant="outline" className="bg-primary/10 text-primary border-primary/20 
                                                                         text-xs w-full font-medium py-1 px-2">
                                            <span className="truncate block">{procurement.id}</span>
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
                                                "text-xs w-full inline-flex items-center font-medium py-1 px-2"
                                            )}>
                                            <span className="truncate block">{procurement.current_status}</span>
                                        </Badge>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{procurement.current_status}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>

                    {/* Title with Link */}
                    <Link href={baseRoute} className="block group">
                        <h3 className="font-medium text-sm line-clamp-2 group-hover:text-primary 
                                     text-foreground transition-colors">
                            {procurement.title}
                        </h3>
                    </Link>

                    {/* Info Row */}
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                            <div className="flex items-center rounded-full bg-muted px-2 py-1">
                                <FileIcon className="h-3 w-3 text-muted-foreground mr-1" />
                                <span className="font-medium">{procurement.document_count}</span>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            <CalendarIcon className="h-3 w-3 text-muted-foreground" />
                            <span className="font-medium">{procurement.last_updated}</span>
                        </div>
                    </div>

                    {/* Divider before actions */}
                    <div className="border-t border-border pt-2">
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
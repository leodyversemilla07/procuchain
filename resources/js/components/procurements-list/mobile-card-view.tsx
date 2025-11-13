import { Link } from '@inertiajs/react';
import { CalendarIcon, FileIcon, MoreVertical } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { ProcurementListItem } from '@/types';
import type { Stage, Status } from '@/types';
import { getStageBadgeStyle, getStatusBadgeStyle } from '@/constants/procurement-badges';
import { cn } from '@/lib/utils';
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import { show as adminShow } from '@/routes/admin/procurements';

interface MobileCardViewProps {
    procurement: ProcurementListItem;
    selected: boolean;
    onSelect: (checked: boolean) => void;
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
    userRole: string;
}

const formatLabel = (value: string): string => {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

const formatDate = (date: string): string => {
    const formattedDate = new Date(date);
    return !isNaN(formattedDate.getTime())
        ? formattedDate.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          })
        : date;
};

export const MobileCardView = ({
    procurement,
    selected,
    onSelect,
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
    userRole,
}: MobileCardViewProps) => {
    const getProcurementUrl = (id: string) => {
        switch (userRole) {
            case 'bac_secretariat':
                return bacSecretariatShow.url(id);
            case 'bac_chairman':
                return bacChairmanShow.url(id);
            case 'hope':
                return hopeShow.url(id);
            case 'admin':
                return adminShow.url(id);
            default:
                return `/procurements-list/${id}`;
        }
    };

    return (
        <Card 
            className={cn(
                'transition-all duration-200',
                selected && 'ring-2 ring-primary bg-muted/50'
            )}
        >
            <CardContent className="p-4">
                <div className="flex items-start gap-3">
                    {/* Selection Checkbox */}
                    <div className="pt-1">
                        <Checkbox
                            checked={selected}
                            onCheckedChange={onSelect}
                            aria-label={`Select procurement ${procurement.id}`}
                            className="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                        />
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0 space-y-3">
                        {/* ID and Actions */}
                        <div className="flex items-start justify-between gap-2">
                            <Link 
                                href={getProcurementUrl(procurement.id)}
                                className="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                aria-label={`View procurement ${procurement.id}`}
                            >
                                <span className="rounded border border-blue-100 bg-blue-50 px-2 py-1 font-mono text-xs dark:border-blue-800/60 dark:bg-blue-900/30">
                                    {procurement.id}
                                </span>
                            </Link>

                            {/* Actions Dropdown */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className="h-8 w-8 p-0"
                                        aria-label="Open actions menu"
                                    >
                                        <MoreVertical className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <ActionButtons
                                        procurement={procurement}
                                        onOpenPreProcurementDialog={onOpenPreProcurementDialog}
                                        onOpenPreBidDialog={onOpenPreBidDialog}
                                        onOpenSupplementalBidBulletinDialog={onOpenSupplementalBidBulletinDialog}
                                    />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        {/* Title */}
                        <div>
                            <Link 
                                href={getProcurementUrl(procurement.id)}
                                className="font-medium text-gray-900 hover:text-blue-600 hover:underline dark:text-gray-100 line-clamp-2"
                            >
                                {procurement.title}
                            </Link>
                        </div>

                        {/* Badges */}
                        <div className="flex flex-wrap gap-2">
                            <Badge
                                variant="outline"
                                className={cn(
                                    getStageBadgeStyle(procurement.stage as Stage),
                                    'border font-medium shadow-sm text-xs'
                                )}
                            >
                                {formatLabel(procurement.stage)}
                            </Badge>
                            <Badge
                                variant="outline"
                                className={cn(
                                    getStatusBadgeStyle(procurement.current_status as Status),
                                    'border font-medium shadow-sm text-xs'
                                )}
                            >
                                {formatLabel(procurement.current_status)}
                            </Badge>
                        </div>

                        {/* Meta Info */}
                        <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                            <div className="flex items-center gap-1.5">
                                <FileIcon className="h-3.5 w-3.5" aria-hidden="true" />
                                <span>{procurement.document_count} docs</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <CalendarIcon className="h-3.5 w-3.5" aria-hidden="true" />
                                <time dateTime={procurement.last_updated}>
                                    {formatDate(procurement.last_updated)}
                                </time>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

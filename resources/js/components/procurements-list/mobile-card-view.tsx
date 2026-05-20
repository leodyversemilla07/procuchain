import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { Badge } from '@/components/ui/badge';
import { TruncateBadge } from '@/components/truncate-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { getStageBadgeStyle, getStatusBadgeStyle } from '@/constants/procurement-badges';
import { cn } from '@/lib/utils';
import { show as adminShow } from '@/routes/admin/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import type { Stage, Status } from '@/types';
import { ProcurementListItem } from '@/types';
import { Link } from '@inertiajs/react';
import { CalendarIcon, FileIcon, MoreVertical } from 'lucide-react';

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
        <Card className={cn('transition-all duration-200', selected && 'ring-primary bg-muted/50 ring-2')}>
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
                    <div className="min-w-0 flex-1 space-y-3">
                        {/* ID and Actions */}
                        <div className="flex items-start justify-between gap-2">
                            <Link
                                href={getProcurementUrl(procurement.id)}
                                className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                aria-label={`View procurement ${procurement.id}`}
                            >
                                <span className="rounded border border-blue-100 bg-blue-50 px-2 py-1 font-mono text-xs dark:border-blue-800/60 dark:bg-blue-900/30">
                                    {procurement.id}
                                </span>
                            </Link>

                            {/* Actions Dropdown */}
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={<Button variant="ghost" size="sm" className="h-8 w-8 p-0" aria-label="Open actions menu" />}
                                >
                                    <MoreVertical className="h-4 w-4" />
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
                                className="line-clamp-2 font-medium text-gray-900 hover:text-blue-600 hover:underline dark:text-gray-100"
                            >
                                {procurement.title}
                            </Link>
                        </div>

 {/* Stage and Status with Labels */}
 <div className="min-w-0 space-y-2">
 <div className="flex min-w-0 items-center gap-2">
 <span className="text-muted-foreground shrink-0 text-xs font-medium">Stage:</span>
 <TruncateBadge
 variant="outline"
 className={cn(getStageBadgeStyle(procurement.stage as Stage), 'border text-xs font-medium shadow-sm')}
 maxChars={20}
 >
 {formatLabel(procurement.stage)}
 </TruncateBadge>
 </div>
 <div className="flex min-w-0 items-center gap-2">
 <span className="text-muted-foreground shrink-0 text-xs font-medium">Status:</span>
 <TruncateBadge
 variant="outline"
 className={cn(getStatusBadgeStyle(procurement.current_status as Status), 'border text-xs font-medium shadow-sm')}
 maxChars={16}
 >
 {formatLabel(procurement.current_status)}
 </TruncateBadge>
 </div>
 {procurement.procurement_mode_label && (
 <div className="flex min-w-0 items-center gap-2">
 <span className="text-muted-foreground shrink-0 text-xs font-medium">Mode:</span>
 <span className="min-w-0 truncate text-xs font-medium">{procurement.procurement_mode_label}</span>
 </div>
 )}
                        </div>

                        {/* Meta Info */}
                        <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                            <div className="flex items-center gap-1.5">
                                <FileIcon className="h-3.5 w-3.5" aria-hidden="true" />
                                <span>{procurement.document_count} docs</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <CalendarIcon className="h-3.5 w-3.5" aria-hidden="true" />
                                <time dateTime={procurement.last_updated}>{formatDate(procurement.last_updated)}</time>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

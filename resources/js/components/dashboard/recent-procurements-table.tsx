import { EmptyState } from '@/components/empty-state';
import { ErrorState } from '@/components/error-state';
import type { ErrorStateProps } from '@/components/error-state';
import { Badge, badgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, EyeIcon, FileText } from 'lucide-react';
import { Link } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';

type BadgeVariant = VariantProps<typeof badgeVariants>['variant'];

export interface RecentProcurementItem {
    id: string | number;
    title: string;
    stage: string;
    status: string;
}

export interface RecentProcurementsTableProps {
    procurements: RecentProcurementItem[];
    getViewProcurementHref: (procurement: RecentProcurementItem) => string;
    viewAllHref?: string;
    viewAllLabel?: string;
    className?: string;
    title?: string;
    icon?: LucideIcon;
    emptyStateIcon?: LucideIcon;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    stageBadgeVariant?: BadgeVariant;
    statusBadgeVariant?: BadgeVariant;
    actionTooltip?: string;
    errorState?: ErrorStateProps;
}

const DEFAULT_TITLE = 'Recent Procurements';
const DEFAULT_VIEW_ALL_LABEL = 'View all';
const DEFAULT_EMPTY_TITLE = 'No procurement records yet';
const DEFAULT_EMPTY_DESCRIPTION = 'Recent procurements will appear here after they are created.';
const DEFAULT_ACTION_TOOLTIP = 'View Procurement Details';

export const RecentProcurementsTable = ({
    procurements,
    getViewProcurementHref,
    viewAllHref,
    viewAllLabel = DEFAULT_VIEW_ALL_LABEL,
    className,
    title = DEFAULT_TITLE,
    icon: Icon = FileText,
    emptyStateIcon = FileText,
    emptyStateTitle = DEFAULT_EMPTY_TITLE,
    emptyStateDescription = DEFAULT_EMPTY_DESCRIPTION,
    stageBadgeVariant,
    statusBadgeVariant = 'outline',
    actionTooltip = DEFAULT_ACTION_TOOLTIP,
    errorState,
}: RecentProcurementsTableProps) => {
    const hasProcurements = procurements.length > 0;

    return (
        <Card className={cn(className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="flex items-center text-base font-semibold md:text-lg">
                    <Icon className="text-primary mr-2 h-4 w-4 md:h-5 md:w-5" />
                    {title}
                    {hasProcurements ? ` (${procurements.length})` : ''}
                </CardTitle>
                {hasProcurements && viewAllHref ? (
                    <Link href={viewAllHref} className="text-primary ml-2 flex shrink-0 items-center text-xs hover:underline md:text-sm">
                        {viewAllLabel} <ArrowRight className="ml-1 h-3 w-3 md:h-4 md:w-4" />
                    </Link>
                ) : null}
            </CardHeader>
            <CardContent>
                {errorState ? (
                    <ErrorState {...errorState} />
                ) : !hasProcurements ? (
                    <div className="flex flex-col items-center">
                        <EmptyState icon={emptyStateIcon} title={emptyStateTitle} description={emptyStateDescription} />
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead>Stage</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {procurements.map((procurement) => (
                                <TableRow key={procurement.id}>
                                    <TableCell className="font-medium">{procurement.id}</TableCell>
                                    <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                        {procurement.title}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={stageBadgeVariant}>{procurement.stage}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusBadgeVariant}>{procurement.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="ghost" size="sm" asChild className="h-8 px-2">
                                                    <Link href={getViewProcurementHref(procurement)}>
                                                        <EyeIcon className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{actionTooltip}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
};

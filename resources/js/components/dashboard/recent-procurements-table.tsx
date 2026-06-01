import type { ErrorStateProps } from '@/components/error-state';
import { ErrorState } from '@/components/error-state';
import { TruncateBadge } from '@/components/truncate-badge';
import { badgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight, EyeIcon, FileText } from 'lucide-react';

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
const DEFAULT_EMPTY_TITLE = 'No procurement records yet';
const DEFAULT_EMPTY_DESCRIPTION = 'Recent procurements will appear here after they are created.';
const DEFAULT_ACTION_TOOLTIP = 'View Procurement Details';

export const RecentProcurementsTable = ({
    procurements,
    getViewProcurementHref,
    viewAllHref,
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
    // Ensure procurements is an array
    const safeProcurements = Array.isArray(procurements) ? procurements : [];
    const hasProcurements = safeProcurements.length > 0;

    return (
        <Card className={cn('shadow-sm transition-shadow duration-300 hover:shadow-md', className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="flex items-center text-base font-semibold md:text-lg">
                    <Icon className="text-primary mr-2 h-4 w-4 transition-transform duration-200 group-hover:scale-110 md:h-5 md:w-5" />
                    {title}
                    {hasProcurements ? ` (${safeProcurements.length})` : ''}
                </CardTitle>
                {hasProcurements && viewAllHref ? (
                    <Link
                        href={viewAllHref}
                        className="text-primary ml-2 flex shrink-0 items-center text-xs transition-all duration-200 hover:translate-x-1 hover:underline md:text-sm"
                        prefetch="hover"
                        cacheFor="1m"
                    >
                        View all
                        <ChevronRight className="ml-1 h-3 w-3 md:h-4 md:w-4" />
                    </Link>
                ) : null}
            </CardHeader>
            <CardContent>
                {errorState ? (
                    <ErrorState {...errorState} />
                ) : !hasProcurements ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {(() => {
                                    const Icon = emptyStateIcon;
                                    return <Icon className="h-8 w-8" />;
                                })()}
                            </EmptyMedia>
                        </EmptyHeader>
                        <EmptyTitle>{emptyStateTitle}</EmptyTitle>
                        <EmptyDescription>{emptyStateDescription}</EmptyDescription>
                    </Empty>
                ) : (
                    <>
                        {/* Mobile card layout */}
                        <div className="space-y-3 sm:hidden">
                            {safeProcurements.map((procurement) => (
                                <div
                                    key={procurement.id}
                                    className="bg-muted/30 hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 transition-colors duration-200"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-muted-foreground text-xs font-medium">{procurement.id}</span>
                                            <span className="max-w-[160px] truncate text-sm font-medium" title={procurement.title}>
                                                {procurement.title}
                                            </span>
                                        </div>
                                        <div className="mt-1.5 flex min-w-0 flex-wrap items-center gap-1.5">
                                            <TruncateBadge
                                                variant={stageBadgeVariant}
                                                className="text-[10px] transition-all duration-200 hover:shadow-sm"
                                                maxChars={18}
                                            >
                                                {procurement.stage}
                                            </TruncateBadge>
                                            <TruncateBadge
                                                variant={statusBadgeVariant}
                                                className="text-[10px] transition-all duration-200 hover:shadow-sm"
                                                maxChars={14}
                                            >
                                                {procurement.status}
                                            </TruncateBadge>
                                        </div>
                                    </div>
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 w-8 p-0 transition-all duration-200 hover:scale-110"
                                                    render={<Link href={getViewProcurementHref(procurement)} prefetch="hover" cacheFor="1m" />}
                                                />
                                            }
                                        >
                                            <EyeIcon className="h-4 w-4" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{actionTooltip}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            ))}
                        </div>

                        {/* Desktop table layout */}
                        <div className="hidden overflow-x-auto sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="text-xs whitespace-nowrap sm:text-sm">ID</TableHead>
                                        <TableHead className="text-xs whitespace-nowrap sm:text-sm">Title</TableHead>
                                        <TableHead className="text-xs whitespace-nowrap sm:text-sm">Stage</TableHead>
                                        <TableHead className="text-xs whitespace-nowrap sm:text-sm">Status</TableHead>
                                        <TableHead className="text-right text-xs whitespace-nowrap sm:text-sm">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {safeProcurements.map((procurement) => (
                                        <TableRow key={procurement.id} className="hover:bg-muted/50 transition-colors duration-200">
                                            <TableCell className="font-medium">{procurement.id}</TableCell>
                                            <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                                {procurement.title}
                                            </TableCell>
                                            <TableCell>
                                                <TruncateBadge
                                                    variant={stageBadgeVariant}
                                                    className="transition-all duration-200 hover:shadow-sm"
                                                    maxChars={18}
                                                >
                                                    {procurement.stage}
                                                </TruncateBadge>
                                            </TableCell>
                                            <TableCell>
                                                <TruncateBadge
                                                    variant={statusBadgeVariant}
                                                    className="transition-all duration-200 hover:shadow-sm"
                                                    maxChars={14}
                                                >
                                                    {procurement.status}
                                                </TruncateBadge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Tooltip>
                                                    <TooltipTrigger
                                                        render={
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 transition-all duration-200 hover:scale-110"
                                                                render={
                                                                    <Link href={getViewProcurementHref(procurement)} prefetch="hover" cacheFor="1m" />
                                                                }
                                                            />
                                                        }
                                                    >
                                                        <EyeIcon className="h-4 w-4" />
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
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
};

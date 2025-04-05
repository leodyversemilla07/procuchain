import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CalendarIcon, FileIcon } from 'lucide-react';
import { getStatusBadgeStyle, getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { cn } from '@/lib/utils';

export const IdCell = ({ id }: { id: string }) => (
    <div className="font-medium text-blue-600 dark:text-blue-400">
        <Link href={`procurements-list/${id}`} className="hover:underline">
            {id}
        </Link>
    </div>
);

export const TitleCell = ({ procurement }: { procurement: ProcurementListItem }) => (
    <div className="max-w-[200px] truncate font-medium">
        <Link href={`procurements-list/${procurement.id}`} className="hover:text-blue-600 hover:underline">
            {procurement.title}
        </Link>
    </div>
);

export const BadgeCell = <T extends string>({
    value,
    getStyle
}: {
    value: T,
    getStyle: (value: T) => string
}) => (
    <Tooltip>
        <TooltipTrigger asChild>
            <div className="w-full max-w-[150px]">
                <Badge
                    variant="outline"
                    className={cn(
                        getStyle(value),
                        "w-full inline-flex overflow-hidden"
                    )}
                >
                    <span className="truncate">{value}</span>
                </Badge>
            </div>
        </TooltipTrigger>
        <TooltipContent>
            {value}
        </TooltipContent>
    </Tooltip>
);

export const StageCell = ({ stage }: { stage: Stage }) => (
    <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} />
);

export const StatusCell = ({ status }: { status: Status }) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} />
);

export const DocumentCountCell = ({ count }: { count: number }) => (
    <div className="flex items-center gap-1">
        <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400" />
        <span className="font-medium">{count}</span>
    </div>
);

export const LastUpdatedCell = ({ date }: { date: string }) => (
    <div className="flex items-center gap-1">
        <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
        <span className="text-sm">{date}</span>
    </div>
);
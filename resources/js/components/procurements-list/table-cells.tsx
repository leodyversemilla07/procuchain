import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CalendarIcon, FileIcon, CheckCircle, Clock, AlertCircle, Milestone, FileText, Award, PlayCircle, Monitor, Check, ListChecks, FileCheck, FileQuestion, HelpCircle } from 'lucide-react';
import { getStatusBadgeStyle, getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { cn } from '@/lib/utils';
import { useRef, useEffect, useState } from 'react';

export const IdCell = ({ id }: { id: string }) => (
    <div className="font-medium text-blue-600 dark:text-blue-400">
        <Link href={`procurements-list/${id}`} className="hover:underline transition-all duration-150 flex items-center">
            <span className="font-mono text-xs bg-blue-50 dark:bg-blue-900/30 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800/60">
                {id}
            </span>
        </Link>
    </div>
);

export const TitleCell = ({ procurement }: { procurement: ProcurementListItem }) => (
    <div className="max-w-[280px] truncate font-medium">
        <Link
            href={`procurements-list/${procurement.id}`}
            className="hover:text-blue-600 hover:underline transition-colors duration-150 text-gray-900 dark:text-gray-100"
        >
            {procurement.title}
        </Link>
    </div>
);

export const BadgeCell = <T extends string>({
    value,
    getStyle,
    icon
}: {
    value: T,
    getStyle: (value: T) => string,
    icon?: React.ReactNode
}) => {
    const textRef = useRef<HTMLSpanElement>(null);
    const [isTruncated, setIsTruncated] = useState(false);

    useEffect(() => {
        const checkTruncation = () => {
            const el = textRef.current;
            if (el) {
                setIsTruncated(el.scrollWidth > el.clientWidth);
            }
        };
        checkTruncation();
        window.addEventListener('resize', checkTruncation);
        return () => window.removeEventListener('resize', checkTruncation);
    }, [value]);

    const badge = (
        <Badge
            variant="outline"
            className={cn(
                getStyle(value),
                "inline-flex items-center gap-1.5 overflow-hidden text-ellipsis whitespace-nowrap max-w-full px-2 py-0.5",
                "shadow-sm border transition-all duration-150 font-medium"
            )}
        >
            {icon && <span className="flex-shrink-0">{icon}</span>}
            <span ref={textRef} className="truncate min-w-0">{value}</span>
        </Badge>
    );

    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent className="font-medium">{value}</TooltipContent>
        </Tooltip>
    ) : badge;
};

const stageIcons: Record<Stage, React.ReactNode> = {
    [Stage.PROCUREMENT_INITIATION]: <FileText className="h-3 w-3" />,
    [Stage.PRE_PROCUREMENT_CONFERENCE]: <Milestone className="h-3 w-3" />,
    [Stage.BIDDING_DOCUMENTS]: <FileText className="h-3 w-3" />,
    [Stage.PRE_BID_CONFERENCE]: <Milestone className="h-3 w-3" />,
    [Stage.SUPPLEMENTAL_BID_BULLETIN]: <FileQuestion className="h-3 w-3" />,
    [Stage.BID_OPENING]: <ListChecks className="h-3 w-3" />,
    [Stage.BID_EVALUATION]: <ListChecks className="h-3 w-3" />,
    [Stage.POST_QUALIFICATION]: <FileCheck className="h-3 w-3" />,
    [Stage.BAC_RESOLUTION]: <FileCheck className="h-3 w-3" />,
    [Stage.NOTICE_OF_AWARD]: <Award className="h-3 w-3" />,
    [Stage.PERFORMANCE_BOND_CONTRACT_AND_PO]: <FileText className="h-3 w-3" />,
    [Stage.NOTICE_TO_PROCEED]: <PlayCircle className="h-3 w-3" />,
    [Stage.MONITORING]: <Monitor className="h-3 w-3" />,
    [Stage.COMPLETION]: <CheckCircle className="h-3 w-3" />,
    [Stage.COMPLETED]: <CheckCircle className="h-3 w-3" />
};

const statusIcons: Record<Status, React.ReactNode> = {
    [Status.PROCUREMENT_SUBMITTED]: <Check className="h-3 w-3" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />,
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: <FileText className="h-3 w-3" />,
    [Status.PRE_BID_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />,
    [Status.PRE_BID_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />,
    [Status.PRE_BID_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: <Clock className="h-3 w-3" />,
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: <CheckCircle className="h-3 w-3" />,
    [Status.BIDS_OPENED]: <ListChecks className="h-3 w-3" />,
    [Status.BIDS_EVALUATED]: <ListChecks className="h-3 w-3" />,
    [Status.POST_QUALIFICATION_VERIFIED]: <FileCheck className="h-3 w-3" />,
    [Status.POST_QUALIFICATION_FAILED]: <AlertCircle className="h-3 w-3" />,
    [Status.RESOLUTION_RECORDED]: <FileCheck className="h-3 w-3" />,
    [Status.AWARDED]: <Award className="h-3 w-3" />,
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: <FileCheck className="h-3 w-3" />,
    [Status.NTP_RECORDED]: <PlayCircle className="h-3 w-3" />,
    [Status.MONITORING_COMPLETED]: <Monitor className="h-3 w-3" />,
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: <FileCheck className="h-3 w-3" />,
    [Status.COMPLETED]: <CheckCircle className="h-3 w-3" />
};

export const StageCell = ({ stage }: { stage: Stage }) => (
    <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} icon={stageIcons[stage]} />
);

export const StatusCell = ({ status }: { status: Status }) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} icon={statusIcons[status] || <HelpCircle className="h-3 w-3" />} />
);

export const DocumentCountCell = ({ count }: { count: number }) => (
    <div className="flex items-center gap-1.5">
        <div className="flex items-center bg-blue-50 dark:bg-blue-900/20 rounded-full pl-1 pr-2 py-0.5">
            <FileIcon className="h-3.5 w-3.5 text-blue-500 dark:text-blue-400 mr-1" />
            <span className="font-medium text-blue-700 dark:text-blue-300 text-xs">{count}</span>
        </div>
    </div>
);

export const LastUpdatedCell = ({ date }: { date: string }) => (
    <div className="flex items-center gap-1.5">
        <CalendarIcon className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
        <span className="text-sm text-gray-600 dark:text-gray-300 font-medium">{date}</span>
    </div>
);

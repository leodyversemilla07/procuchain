import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CalendarIcon, FileIcon, CheckCircle, Clock, AlertCircle, Milestone, FileText, Award, PlayCircle, Monitor, Check, ListChecks, FileCheck, FileQuestion, HelpCircle } from 'lucide-react';
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
    getStyle,
    icon
}: {
    value: T,
    getStyle: (value: T) => string,
    icon?: React.ReactNode
}) => (
    <Tooltip>
        <TooltipTrigger asChild>
            {/* Removed the wrapping div with max-width */}
            <Badge
                variant="outline"
                className={cn(
                    getStyle(value),
                    /* Ensure badge takes available space but truncates */
                    "inline-flex items-center gap-1.5 overflow-hidden text-ellipsis whitespace-nowrap max-w-full"
                )}
            >
                {icon}
                {/* Ensure span allows truncation */}
                <span className="truncate min-w-0">{value}</span>
            </Badge>
        </TooltipTrigger>
        <TooltipContent>
            {value}
        </TooltipContent>
    </Tooltip>
);

// Map Stages to Icons
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
    [Stage.COMPLETED]: <CheckCircle className="h-3 w-3" />, // Assuming COMPLETED is a final stage
};

// Map Statuses to Icons
const statusIcons: Record<Status, React.ReactNode> = {
    [Status.PROCUREMENT_SUBMITTED]: <Check className="h-3 w-3" />, // Changed from PENDING/SUBMITTED
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />, // New
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />, // New, indicate skipped
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />, // New
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: <FileText className="h-3 w-3" />, // Changed from POSTED/DRAFT
    [Status.PRE_BID_CONFERENCE_HELD]: <Milestone className="h-3 w-3" />, // New
    [Status.PRE_BID_CONFERENCE_SKIPPED]: <Milestone className="h-3 w-3 text-gray-500" />, // New, indicate skipped
    [Status.PRE_BID_CONFERENCE_COMPLETED]: <CheckCircle className="h-3 w-3" />, // New
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: <Clock className="h-3 w-3" />, // Changed from ONGOING
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: <CheckCircle className="h-3 w-3" />, // New
    [Status.BIDS_OPENED]: <ListChecks className="h-3 w-3" />, // New
    [Status.BIDS_EVALUATED]: <ListChecks className="h-3 w-3" />, // New
    [Status.POST_QUALIFICATION_VERIFIED]: <FileCheck className="h-3 w-3" />, // New
    [Status.POST_QUALIFICATION_FAILED]: <AlertCircle className="h-3 w-3" />, // Changed from FAILED
    [Status.RESOLUTION_RECORDED]: <FileCheck className="h-3 w-3" />, // New
    [Status.AWARDED]: <Award className="h-3 w-3" />,
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: <FileCheck className="h-3 w-3" />, // Changed from CONTRACT_SIGNED/PO_ISSUED
    [Status.NTP_RECORDED]: <PlayCircle className="h-3 w-3" />, // Changed from NTP_ISSUED
    [Status.MONITORING]: <Monitor className="h-3 w-3" />, // Changed from ONGOING
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: <FileCheck className="h-3 w-3" />, // Changed from DOCUMENTS_UPLOADED
    [Status.COMPLETED]: <CheckCircle className="h-3 w-3" />,
    // Removed unused/incorrect statuses like PENDING, APPROVED, REJECTED, FOR_REVIEW, etc.
    // Added specific icons for conference held/skipped
};

export const StageCell = ({ stage }: { stage: Stage }) => (
    <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} icon={stageIcons[stage]} />
);

export const StatusCell = ({ status }: { status: Status }) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} icon={statusIcons[status] || <HelpCircle className="h-3 w-3" />} />
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

import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileTextIcon, FileUpIcon, BarChart4Icon, CheckIcon } from 'lucide-react';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    variant?: 'table' | 'kanban';
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog?: (procurement: ProcurementListItem) => void;
}

export const ActionButtons = ({
    procurement,
    variant = 'table',
    onOpenPreProcurementModal,
    onOpenMarkCompleteDialog,
}: ActionButtonsProps) => {
    const { id, stage, current_status: status } = procurement;
    const iconSize = variant === 'table' ? 'h-4 w-4' : 'h-3.5 w-3.5';
    const buttonSize = variant === 'table' ? 'h-8 w-8' : 'h-7 w-7';

    return (
        <div className="flex justify-end space-x-1">
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button variant="ghost" size="sm" className={`${buttonSize} p-0 text-blue-600 dark:text-blue-400`}>
                            <Link href={`procurements-list/${id}`}>
                                <FileTextIcon className={iconSize} />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>View Details</TooltipContent>
                </Tooltip>
            </TooltipProvider>

            {stage === Stage.PROCUREMENT_INITIATION && status === Status.PROCUREMENT_SUBMITTED && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className={`${buttonSize} p-0 text-amber-600 dark:text-amber-400`}
                                onClick={() => onOpenPreProcurementModal?.(procurement)}
                            >
                                <FileUpIcon className={iconSize} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Record Pre-Procurement Conference Decision</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {stage === Stage.PRE_PROCUREMENT_CONFERENCE && status === Status.PRE_PROCUREMENT_CONFERENCE_HELD && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" className={`${buttonSize} p-0 text-green-600 dark:text-green-400`}>
                                <Link href={`/bac-secretariat/pre-procurement-conference-upload/${id}`}>
                                    <FileUpIcon className={iconSize} />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Upload Pre-Procurement Conference Documents</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {(stage === Stage.BIDDING_DOCUMENTS && (status === Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED || status === Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED)) && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" className={`${buttonSize} p-0 text-amber-600 dark:text-amber-400`}>
                                <Link href={`/bac-secretariat/bidding-documents-upload/${id}`}>
                                    <FileUpIcon className={iconSize} />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Upload Bidding Documents</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {stage === Stage.BID_OPENING && status === Status.BIDDING_DOCUMENTS_PUBLISHED && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" className={`${buttonSize} p-0 text-blue-600 dark:text-blue-400`}>
                                <Link href={`/bac-secretariat/bid-submission-upload/${id}`}>
                                    <FileUpIcon className={iconSize} />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Upload Bid Submission Documents</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {stage === Stage.BID_EVALUATION && status === Status.BIDS_OPENED && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" className={`${buttonSize} p-0 text-indigo-600 dark:text-indigo-400`}>
                                <Link href={`/bac-secretariat/bid-evaluation-upload/${id}`}>
                                    <BarChart4Icon className={iconSize} />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Upload Bid Evaluation Documents</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {stage === Stage.MONITORING && status === Status.MONITORING && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className={`${buttonSize} p-0 text-green-600 dark:text-green-400`}
                                onClick={() => onOpenMarkCompleteDialog?.(procurement)}
                            >
                                <CheckIcon className={iconSize} />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Mark Procurement as Complete</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}

            {/* Add other conditional buttons as needed */}
        </div>
    );
};
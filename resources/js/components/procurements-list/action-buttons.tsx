import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { FileTextIcon, FileUpIcon, BarChart4Icon, CheckIcon } from 'lucide-react';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    variant?: 'table' | 'kanban';
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal?: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog?: (procurement: ProcurementListItem) => void;
}

interface ActionButtonItemProps {
    icon: React.ReactNode;
    tooltipText: string;
    onClick?: () => void;
    href?: string;
    className?: string;
    buttonSize: string;
}

const ActionButtonItem = ({ icon, tooltipText, onClick, href, className, buttonSize }: ActionButtonItemProps) => {
    const button = (
        <Button
            variant="ghost"
            size="sm"
            className={`${buttonSize} p-0 ${className}`}
            onClick={onClick}
        >
            {icon}
        </Button>
    );

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    {href ? <Link href={href} className="block">{button}</Link> : button}
                </TooltipTrigger>
                <TooltipContent>{tooltipText}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
};

const useButtonSizes = (variant: 'table' | 'kanban') => ({
    iconSize: variant === 'table' ? 'h-4 w-4' : 'h-3.5 w-3.5',
    buttonSize: variant === 'table' ? 'h-8 w-8' : 'h-7 w-7',
});

const useInitialStageButtons = (procurement: ProcurementListItem, iconSize: string, onOpenPreProcurementModal?: (p: ProcurementListItem) => void) => {
    const { stage, current_status: status } = procurement;
    if (stage === Stage.PROCUREMENT_INITIATION && status === Status.PROCUREMENT_SUBMITTED) {
        return [{
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Record Pre-Procurement Conference Decision",
            className: "text-amber-600 dark:text-amber-400",
            onClick: () => onOpenPreProcurementModal?.(procurement)
        }];
    }
    return [];
};

const useDocumentUploadButtons = (procurement: ProcurementListItem, iconSize: string) => {
    const { id, stage, current_status: status } = procurement;
    const configs = [];

    if (stage === Stage.PRE_PROCUREMENT_CONFERENCE && status === Status.PRE_PROCUREMENT_CONFERENCE_HELD) {
        configs.push({
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Upload Pre-Procurement Conference Documents",
            className: "text-green-600 dark:text-green-400",
            href: `/bac-secretariat/pre-procurement-conference-upload/${id}`
        });
    }

    const canUploadBiddingDocuments = status === Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED ||
        status === Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED;

    if (stage === Stage.BIDDING_DOCUMENTS && canUploadBiddingDocuments) {
        configs.push({
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Upload Bidding Documents",
            className: "text-amber-600 dark:text-amber-400",
            href: `/bac-secretariat/bidding-documents-upload/${id}`
        });
    }
    
    if (stage === Stage.PRE_BID_CONFERENCE && status === Status.PRE_BID_CONFERENCE_HELD) {
        configs.push({
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Upload Pre-Bid Conference Documents",
            className: "text-indigo-600 dark:text-indigo-400",
            href: `/bac-secretariat/pre-bid-conference-upload/${id}`
        });
    }

    return configs;
};

const useBidProcessButtons = (procurement: ProcurementListItem, iconSize: string, onOpenPreBidModal?: (p: ProcurementListItem) => void) => {
    const { id, stage, current_status: status } = procurement;
    const configs = [];

    if (stage === Stage.PRE_BID_CONFERENCE && status === Status.BIDDING_DOCUMENTS_PUBLISHED) {
        configs.push({
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Record Pre-Bid Conference Decision",
            className: "text-indigo-600 dark:text-indigo-400",
            onClick: () => onOpenPreBidModal?.(procurement)
        });
    }

    if (stage === Stage.BID_OPENING && status === Status.BIDDING_DOCUMENTS_PUBLISHED) {
        configs.push({
            icon: <FileUpIcon className={iconSize} />,
            tooltipText: "Upload Bid Submission Documents",
            className: "text-blue-600 dark:text-blue-400",
            href: `/bac-secretariat/bid-submission-upload/${id}`
        });
    }

    if (stage === Stage.BID_EVALUATION && status === Status.BIDS_OPENED) {
        configs.push({
            icon: <BarChart4Icon className={iconSize} />,
            tooltipText: "Upload Bid Evaluation Documents",
            className: "text-indigo-600 dark:text-indigo-400",
            href: `/bac-secretariat/bid-evaluation-upload/${id}`
        });
    }

    return configs;
};

const useMonitoringButtons = (procurement: ProcurementListItem, iconSize: string, onOpenMarkCompleteDialog?: (p: ProcurementListItem) => void) => {
    const { stage, current_status: status } = procurement;
    if (stage === Stage.MONITORING && status === Status.MONITORING) {
        return [{
            icon: <CheckIcon className={iconSize} />,
            tooltipText: "Mark Procurement as Complete",
            className: "text-green-600 dark:text-green-400",
            onClick: () => onOpenMarkCompleteDialog?.(procurement)
        }];
    }
    return [];
};

export const ActionButtons = ({
    procurement,
    variant = 'table',
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenMarkCompleteDialog,
}: ActionButtonsProps) => {
    const { id } = procurement;
    const { iconSize, buttonSize } = useButtonSizes(variant);

    const buttonConfigs = [
        ...useInitialStageButtons(procurement, iconSize, onOpenPreProcurementModal),
        ...useDocumentUploadButtons(procurement, iconSize),
        ...useBidProcessButtons(procurement, iconSize, onOpenPreBidModal),
        ...useMonitoringButtons(procurement, iconSize, onOpenMarkCompleteDialog)
    ];

    return (
        <div className="flex justify-end space-x-1">
            <ActionButtonItem
                icon={<FileTextIcon className={iconSize} />}
                tooltipText="View Details"
                href={`procurements-list/${id}`}
                className="text-blue-600 dark:text-blue-400"
                buttonSize={buttonSize}
            />
            {buttonConfigs.map((config, index) => (
                <ActionButtonItem
                    key={index}
                    buttonSize={buttonSize}
                    {...config}
                />
            ))}
        </div>
    );
};
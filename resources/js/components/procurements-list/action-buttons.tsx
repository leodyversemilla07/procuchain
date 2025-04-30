import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit2Icon, UploadCloudIcon, BarChart4Icon, EyeIcon } from 'lucide-react';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { SharedData } from '@/types';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    variant?: 'table' | 'kanban';
    onOpenPreProcurementModal?: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinModal?: (procurement: ProcurementListItem) => void;
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

const getButtonConfigs = (
    procurement: ProcurementListItem,
    iconSize: string,
    handlers: {
        onOpenPreProcurementModal?: (p: ProcurementListItem) => void;
        onOpenPreBidModal?: (p: ProcurementListItem) => void;
        onOpenSupplementalBidBulletinModal?: (p: ProcurementListItem) => void;
        onOpenMarkCompleteDialog?: (p: ProcurementListItem) => void;
    }
) => {
    const { id, stage, current_status: status } = procurement;
    const configs = [];

    if (stage === Stage.PROCUREMENT_INITIATION && status === Status.PROCUREMENT_SUBMITTED) {
        configs.push({
            icon: <Edit2Icon className={iconSize} />,
            tooltipText: "Record Pre-Procurement Conference Decision",
            className: "text-amber-600 dark:text-amber-400",
            onClick: () => handlers.onOpenPreProcurementModal?.(procurement)
        });
    }

    if (stage === Stage.PRE_PROCUREMENT_CONFERENCE && status === Status.PRE_PROCUREMENT_CONFERENCE_HELD) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Pre-Procurement Conference Documents",
            className: "text-green-600 dark:text-green-400",
            href: `/bac-secretariat/pre-procurement-conference-upload/${id}`
        });
    }

    const canUploadBiddingDocuments = status === Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED ||
        status === Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED;

    if (stage === Stage.BIDDING_DOCUMENTS && canUploadBiddingDocuments) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Bidding Documents",
            className: "text-amber-600 dark:text-amber-400",
            href: `/bac-secretariat/bidding-documents-upload/${id}`
        });
    }

    if (stage === Stage.PRE_BID_CONFERENCE) {
        if (status === Status.BIDDING_DOCUMENTS_PUBLISHED) {
            configs.push({
                icon: <Edit2Icon className={iconSize} />,
                tooltipText: "Record Pre-Bid Conference Decision",
                className: "text-indigo-600 dark:text-indigo-400",
                onClick: () => handlers.onOpenPreBidModal?.(procurement)
            });
        } else if (status === Status.PRE_BID_CONFERENCE_HELD) {
            configs.push({
                icon: <UploadCloudIcon className={iconSize} />,
                tooltipText: "Upload Pre-Bid Conference Documents",
                className: "text-indigo-600 dark:text-indigo-400",
                href: `/bac-secretariat/pre-bid-conference-upload/${id}`
            });
        }
    }

    if (stage === Stage.BID_OPENING && status === Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Bid Opening Documents",
            className: "text-blue-600 dark:text-blue-400",
            href: `/bac-secretariat/bid-opening-upload/${id}`
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

    if (stage === Stage.POST_QUALIFICATION && status === Status.BIDS_EVALUATED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Post-Qualification Report",
            className: "text-green-700 dark:text-green-400",
            href: `/bac-secretariat/post-qualification-upload/${id}`
        });
    }

    if (stage === Stage.NOTICE_OF_AWARD && status === Status.RESOLUTION_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Notice of Award",
            className: "text-amber-600 dark:text-amber-400",
            href: `/bac-secretariat/noa-upload/${id}`
        });
    }

    if (stage === Stage.SUPPLEMENTAL_BID_BULLETIN) {
        if (status === Status.PRE_BID_CONFERENCE_COMPLETED) {
            configs.push({
                icon: <Edit2Icon className={iconSize} />,
                tooltipText: "Record Supplemental Bid Bulletin Decision",
                className: "text-indigo-600 dark:text-indigo-400",
                onClick: () => handlers.onOpenSupplementalBidBulletinModal?.(procurement)
            });
        } else if (status === Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING) {
            configs.push({
                icon: <UploadCloudIcon className={iconSize} />,
                tooltipText: "Upload Supplemental Bid Bulletin Documents",
                className: "text-blue-600 dark:text-blue-400",
                href: `/bac-secretariat/supplemental-bid-bulletin-upload/${id}`
            });
        }
    }

    if (stage === Stage.PERFORMANCE_BOND_CONTRACT_AND_PO && status === Status.AWARDED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Performance Bond, Contract, and PO",
            className: "text-cyan-600 dark:text-cyan-400",
            href: `/bac-secretariat/performance-bond-contract-po-upload/${id}`
        });
    }

    if (stage === Stage.BAC_RESOLUTION && status === Status.POST_QUALIFICATION_VERIFIED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload BAC Resolution Documents",
            className: "text-purple-700 dark:text-purple-400",
            href: `/bac-secretariat/bac-resolution-upload/${id}`
        });
    }

    if (stage === Stage.NOTICE_TO_PROCEED && status === Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Notice to Proceed",
            className: "text-green-600 dark:text-green-400", // Example color, adjust if needed
            href: `/bac-secretariat/ntp-upload/${id}`
        });
    }

    if (stage === Stage.MONITORING && status === Status.NTP_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Monitoring Documents",
            className: "text-teal-600 dark:text-teal-400", // Example color, adjust if needed
            href: `/bac-secretariat/monitoring-upload/${id}`
        });
    }

    // Add Completion stage button
    if (stage === Stage.COMPLETION && status === Status.MONITORING_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={iconSize} />,
            tooltipText: "Upload Certificate of Completion", // Updated tooltip text
            className: "text-emerald-600 dark:text-emerald-400", // Example color
            href: `/bac-secretariat/completion-upload/${id}`
        });
    }


    return configs;
};

export const ActionButtons = ({
    procurement,
    variant = 'table',
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenSupplementalBidBulletinModal,
}: ActionButtonsProps) => {
    const { id } = procurement;
    const { iconSize, buttonSize } = useButtonSizes(variant);
    const { auth } = usePage<SharedData>().props;
    const isBacSecretariat = auth.user?.role === 'bac_secretariat';

    const buttonConfigs = getButtonConfigs(procurement, iconSize, {
        onOpenPreProcurementModal,
        onOpenPreBidModal,
        onOpenSupplementalBidBulletinModal,
    });

    return (
        <div className="flex justify-end space-x-1">
            <ActionButtonItem
                icon={<EyeIcon className={iconSize} />}
                tooltipText="View Details"
                href={`procurements-list/${id}`}
                className="text-blue-600 dark:text-blue-400"
                buttonSize={buttonSize}
            />
            {isBacSecretariat && buttonConfigs.map((config, index) => (
                <ActionButtonItem
                    key={index}
                    buttonSize={buttonSize}
                    {...config}
                />
            ))}
        </div>
    );
};

import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Edit2Icon, UploadCloudIcon, BarChart4Icon, EyeIcon } from 'lucide-react';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { SharedData } from '@/types';
import { cn } from '@/lib/utils';

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
            className={cn(
                buttonSize,
                "p-0 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors focus:ring-2 focus:ring-blue-500/40",
                className
            )}
            onClick={onClick}
        >
            {icon}
        </Button>
    );

    return (
        <TooltipProvider delayDuration={300}>
            <Tooltip>
                <TooltipTrigger asChild>
                    {href ? 
                        <Link href={href} className="block touch-manipulation">
                            {button}
                        </Link> 
                        : button
                    }
                </TooltipTrigger>
                <TooltipContent 
                    side="bottom" 
                    className="bg-gray-900/95 text-white dark:bg-gray-800 text-xs font-medium py-1 px-2"
                >
                    {tooltipText}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
};

const useButtonSizes = (variant: 'table' | 'kanban') => ({
    iconSize: variant === 'table' ? 'h-4 w-4' : 'h-3.5 w-3.5 xs:h-4 xs:w-4',
    buttonSize: variant === 'table' ? 'h-8 w-8' : 'h-7 w-7 xs:h-8 xs:w-8',
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
            icon: <Edit2Icon className={cn(iconSize, "text-amber-600 dark:text-amber-400")} />,
            tooltipText: "Record Pre-Procurement Conference Decision",
            className: "bg-amber-50 dark:bg-amber-900/20",
            onClick: () => handlers.onOpenPreProcurementModal?.(procurement)
        });
    }

    if (stage === Stage.PRE_PROCUREMENT_CONFERENCE && status === Status.PRE_PROCUREMENT_CONFERENCE_HELD) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-green-600 dark:text-green-400")} />,
            tooltipText: "Upload Pre-Procurement Conference Documents",
            className: "bg-green-50 dark:bg-green-900/20",
            href: `/bac-secretariat/pre-procurement-conference-upload/${id}`
        });
    }

    const canUploadBiddingDocuments = status === Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED ||
        status === Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED;

    if (stage === Stage.BIDDING_DOCUMENTS && canUploadBiddingDocuments) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-amber-600 dark:text-amber-400")} />,
            tooltipText: "Upload Bidding Documents",
            className: "bg-amber-50 dark:bg-amber-900/20",
            href: `/bac-secretariat/bidding-documents-upload/${id}`
        });
    }

    if (stage === Stage.PRE_BID_CONFERENCE) {
        if (status === Status.BIDDING_DOCUMENTS_PUBLISHED) {
            configs.push({
                icon: <Edit2Icon className={cn(iconSize, "text-indigo-600 dark:text-indigo-400")} />,
                tooltipText: "Record Pre-Bid Conference Decision",
                className: "bg-indigo-50 dark:bg-indigo-900/20",
                onClick: () => handlers.onOpenPreBidModal?.(procurement)
            });
        } else if (status === Status.PRE_BID_CONFERENCE_HELD) {
            configs.push({
                icon: <UploadCloudIcon className={cn(iconSize, "text-indigo-600 dark:text-indigo-400")} />,
                tooltipText: "Upload Pre-Bid Conference Documents",
                className: "bg-indigo-50 dark:bg-indigo-900/20",
                href: `/bac-secretariat/pre-bid-conference-upload/${id}`
            });
        }
    }

    if (stage === Stage.BID_OPENING && status === Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-blue-600 dark:text-blue-400")} />,
            tooltipText: "Upload Bid Opening Documents",
            className: "bg-blue-50 dark:bg-blue-900/20",
            href: `/bac-secretariat/bid-opening-upload/${id}`
        });
    }

    if (stage === Stage.BID_EVALUATION && status === Status.BIDS_OPENED) {
        configs.push({
            icon: <BarChart4Icon className={cn(iconSize, "text-indigo-600 dark:text-indigo-400")} />,
            tooltipText: "Upload Bid Evaluation Documents",
            className: "bg-indigo-50 dark:bg-indigo-900/20",
            href: `/bac-secretariat/bid-evaluation-upload/${id}`
        });
    }

    if (stage === Stage.POST_QUALIFICATION && status === Status.BIDS_EVALUATED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-green-700 dark:text-green-400")} />,
            tooltipText: "Upload Post-Qualification Report",
            className: "bg-green-50 dark:bg-green-900/20",
            href: `/bac-secretariat/post-qualification-upload/${id}`
        });
    }

    if (stage === Stage.NOTICE_OF_AWARD && status === Status.RESOLUTION_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-amber-600 dark:text-amber-400")} />,
            tooltipText: "Upload Notice of Award",
            className: "bg-amber-50 dark:bg-amber-900/20",
            href: `/bac-secretariat/noa-upload/${id}`
        });
    }

    if (stage === Stage.SUPPLEMENTAL_BID_BULLETIN) {
        if (status === Status.PRE_BID_CONFERENCE_COMPLETED || status === Status.PRE_BID_CONFERENCE_SKIPPED) {
            configs.push({
            icon: <Edit2Icon className={cn(iconSize, "text-indigo-600 dark:text-indigo-400")} />,
            tooltipText: "Record Supplemental Bid Bulletin Decision",
            className: "bg-indigo-50 dark:bg-indigo-900/20",
            onClick: () => handlers.onOpenSupplementalBidBulletinModal?.(procurement)
            });
        } else if (status === Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING) {
            configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-blue-600 dark:text-blue-400")} />,
            tooltipText: "Upload Supplemental Bid Bulletin Documents",
            className: "bg-blue-50 dark:bg-blue-900/20",
            href: `/bac-secretariat/supplemental-bid-bulletin-upload/${id}`
            });
        }
    }

    if (stage === Stage.PERFORMANCE_BOND_CONTRACT_AND_PO && status === Status.AWARDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-cyan-600 dark:text-cyan-400")} />,
            tooltipText: "Upload Performance Bond, Contract, and PO",
            className: "bg-cyan-50 dark:bg-cyan-900/20",
            href: `/bac-secretariat/performance-bond-contract-po-upload/${id}`
        });
    }

    if (stage === Stage.BAC_RESOLUTION && status === Status.POST_QUALIFICATION_VERIFIED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-purple-700 dark:text-purple-400")} />,
            tooltipText: "Upload BAC Resolution Documents",
            className: "bg-purple-50 dark:bg-purple-900/20",
            href: `/bac-secretariat/bac-resolution-upload/${id}`
        });
    }

    if (stage === Stage.NOTICE_TO_PROCEED && status === Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-green-600 dark:text-green-400")} />,
            tooltipText: "Upload Notice to Proceed",
            className: "bg-green-50 dark:bg-green-900/20", 
            href: `/bac-secretariat/ntp-upload/${id}`
        });
    }

    if (stage === Stage.MONITORING && status === Status.NTP_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-teal-600 dark:text-teal-400")} />,
            tooltipText: "Upload Monitoring Documents",
            className: "bg-teal-50 dark:bg-teal-900/20", 
            href: `/bac-secretariat/monitoring-upload/${id}`
        });
    }

    if (stage === Stage.COMPLETION && status === Status.MONITORING_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, "text-emerald-600 dark:text-emerald-400")} />,
            tooltipText: "Upload Certificate of Completion",
            className: "bg-emerald-50 dark:bg-emerald-900/20",
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
        <div className={cn(
            "flex flex-wrap items-center justify-end gap-1.5 sm:gap-2", 
            variant === "table" ? "mr-2" : ""
        )}>
            <ActionButtonItem
                icon={<EyeIcon className={cn(iconSize, "text-blue-600 dark:text-blue-400")} />}
                tooltipText="View Details"
                href={`procurements-list/${id}`}
                className="bg-blue-50 dark:bg-blue-900/20"
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

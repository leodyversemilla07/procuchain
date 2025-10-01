import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { ProcurementListItem, Stage, Status } from '@/types/blockchain';
import { Link, usePage } from '@inertiajs/react';
import { BarChart4Icon, Edit2Icon, EyeIcon, UploadCloudIcon } from 'lucide-react';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
}

const DropdownActionItem = ({
    icon,
    tooltipText,
    onClick,
    href,
}: {
    icon: React.ReactNode;
    tooltipText: string;
    onClick?: () => void;
    href?: string;
}) => {
    const content = href ? (
        <DropdownMenuItem asChild>
            <Link href={href} className="flex items-center gap-2">
                {icon}
                <span>{tooltipText}</span>
            </Link>
        </DropdownMenuItem>
    ) : (
        <DropdownMenuItem onClick={onClick} className="flex items-center gap-2">
            {icon}
            <span>{tooltipText}</span>
        </DropdownMenuItem>
    );

    return content;
};

const getButtonConfigs = (
    procurement: ProcurementListItem,
    handlers: {
        onOpenPreProcurementDialog?: (p: ProcurementListItem) => void;
        onOpenPreBidDialog?: (p: ProcurementListItem) => void;
        onOpenSupplementalBidBulletinDialog?: (p: ProcurementListItem) => void;
    },
) => {
    const { id, stage, current_status: status } = procurement;
    const iconSize = 'h-4 w-4';
    const configs = [];

    if (stage === Stage.PROCUREMENT_INITIATION && status === Status.PROCUREMENT_SUBMITTED) {
        configs.push({
            icon: <Edit2Icon className={cn(iconSize, 'text-amber-600 dark:text-amber-400')} />,
            tooltipText: 'Record Pre-Procurement Conference Decision',
            className: 'bg-amber-50 dark:bg-amber-900/20',
            onClick: () => handlers.onOpenPreProcurementDialog?.(procurement),
        });
    }

    if (stage === Stage.PRE_PROCUREMENT_CONFERENCE && status === Status.PRE_PROCUREMENT_CONFERENCE_HELD) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-green-600 dark:text-green-400')} />,
            tooltipText: 'Upload Pre-Procurement Conference Documents',
            className: 'bg-green-50 dark:bg-green-900/20',
            href: `/bac-secretariat/pre-procurement-conference-upload/${id}`,
        });
    }

    const canUploadBiddingDocuments = status === Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED || status === Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED;

    if (stage === Stage.BIDDING_DOCUMENTS && canUploadBiddingDocuments) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-amber-600 dark:text-amber-400')} />,
            tooltipText: 'Upload Bidding Documents',
            className: 'bg-amber-50 dark:bg-amber-900/20',
            href: `/bac-secretariat/bidding-documents-upload/${id}`,
        });
    }

    if (stage === Stage.PRE_BID_CONFERENCE) {
        if (status === Status.BIDDING_DOCUMENTS_PUBLISHED) {
            configs.push({
                icon: <Edit2Icon className={cn(iconSize, 'text-indigo-600 dark:text-indigo-400')} />,
                tooltipText: 'Record Pre-Bid Conference Decision',
                className: 'bg-indigo-50 dark:bg-indigo-900/20',
                onClick: () => handlers.onOpenPreBidDialog?.(procurement),
            });
        } else if (status === Status.PRE_BID_CONFERENCE_HELD) {
            configs.push({
                icon: <UploadCloudIcon className={cn(iconSize, 'text-indigo-600 dark:text-indigo-400')} />,
                tooltipText: 'Upload Pre-Bid Conference Documents',
                className: 'bg-indigo-50 dark:bg-indigo-900/20',
                href: `/bac-secretariat/pre-bid-conference-upload/${id}`,
            });
        }
    }

    if (stage === Stage.BID_OPENING && status === Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-blue-600 dark:text-blue-400')} />,
            tooltipText: 'Upload Bid Opening Documents',
            className: 'bg-blue-50 dark:bg-blue-900/20',
            href: `/bac-secretariat/bid-opening-upload/${id}`,
        });
    }

    if (stage === Stage.BID_EVALUATION && status === Status.BIDS_OPENED) {
        configs.push({
            icon: <BarChart4Icon className={cn(iconSize, 'text-indigo-600 dark:text-indigo-400')} />,
            tooltipText: 'Upload Bid Evaluation Documents',
            className: 'bg-indigo-50 dark:bg-indigo-900/20',
            href: `/bac-secretariat/bid-evaluation-upload/${id}`,
        });
    }

    if (stage === Stage.POST_QUALIFICATION && status === Status.BIDS_EVALUATED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-green-700 dark:text-green-400')} />,
            tooltipText: 'Upload Post-Qualification Report',
            className: 'bg-green-50 dark:bg-green-900/20',
            href: `/bac-secretariat/post-qualification-upload/${id}`,
        });
    }

    if (stage === Stage.NOTICE_OF_AWARD && status === Status.RESOLUTION_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-amber-600 dark:text-amber-400')} />,
            tooltipText: 'Upload Notice of Award',
            className: 'bg-amber-50 dark:bg-amber-900/20',
            href: `/bac-secretariat/noa-upload/${id}`,
        });
    }

    if (stage === Stage.SUPPLEMENTAL_BID_BULLETIN) {
        if (status === Status.PRE_BID_CONFERENCE_COMPLETED || status === Status.PRE_BID_CONFERENCE_SKIPPED) {
            configs.push({
                icon: <Edit2Icon className={cn(iconSize, 'text-indigo-600 dark:text-indigo-400')} />,
                tooltipText: 'Record Supplemental Bid Bulletin Decision',
                className: 'bg-indigo-50 dark:bg-indigo-900/20',
                onClick: () => handlers.onOpenSupplementalBidBulletinDialog?.(procurement),
            });
        } else if (status === Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING) {
            configs.push({
                icon: <UploadCloudIcon className={cn(iconSize, 'text-blue-600 dark:text-blue-400')} />,
                tooltipText: 'Upload Supplemental Bid Bulletin Documents',
                className: 'bg-blue-50 dark:bg-blue-900/20',
                href: `/bac-secretariat/supplemental-bid-bulletin-upload/${id}`,
            });
        }
    }

    if (stage === Stage.PERFORMANCE_BOND_CONTRACT_AND_PO && status === Status.AWARDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-cyan-600 dark:text-cyan-400')} />,
            tooltipText: 'Upload Performance Bond, Contract, and PO',
            className: 'bg-cyan-50 dark:bg-cyan-900/20',
            href: `/bac-secretariat/performance-bond-contract-po-upload/${id}`,
        });
    }

    if (stage === Stage.BAC_RESOLUTION && status === Status.POST_QUALIFICATION_VERIFIED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-purple-700 dark:text-purple-400')} />,
            tooltipText: 'Upload BAC Resolution Documents',
            className: 'bg-purple-50 dark:bg-purple-900/20',
            href: `/bac-secretariat/bac-resolution-upload/${id}`,
        });
    }

    if (stage === Stage.NOTICE_TO_PROCEED && status === Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-green-600 dark:text-green-400')} />,
            tooltipText: 'Upload Notice to Proceed',
            className: 'bg-green-50 dark:bg-green-900/20',
            href: `/bac-secretariat/ntp-upload/${id}`,
        });
    }

    if (stage === Stage.MONITORING && status === Status.NTP_RECORDED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-teal-600 dark:text-teal-400')} />,
            tooltipText: 'Upload Monitoring Documents',
            className: 'bg-teal-50 dark:bg-teal-900/20',
            href: `/bac-secretariat/monitoring-upload/${id}`,
        });
    }

    if (stage === Stage.COMPLETION && status === Status.MONITORING_COMPLETED) {
        configs.push({
            icon: <UploadCloudIcon className={cn(iconSize, 'text-emerald-600 dark:text-emerald-400')} />,
            tooltipText: 'Upload Certificate of Completion',
            className: 'bg-emerald-50 dark:bg-emerald-900/20',
            href: `/bac-secretariat/completion-upload/${id}`,
        });
    }

    return configs;
};

export const ActionButtons = ({
    procurement,
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
}: ActionButtonsProps) => {
    const { id } = procurement;
    const { auth } = usePage<SharedData>().props;
    const isBacSecretariat = auth.user?.role === 'bac_secretariat';

    const buttonConfigs = getButtonConfigs(procurement, {
        onOpenPreProcurementDialog,
        onOpenPreBidDialog,
        onOpenSupplementalBidBulletinDialog,
    });

    // Always include View Details action
    const viewDetailsConfig = {
        icon: <EyeIcon className="h-4 w-4 text-blue-600 dark:text-blue-400" />,
        tooltipText: 'View Details',
        href: `procurements-list/${id}`,
    };

    const allConfigs = [viewDetailsConfig, ...(isBacSecretariat ? buttonConfigs : [])];

    return (
        <>
            {allConfigs.map((config, index) => (
                <DropdownActionItem key={index} {...config} />
            ))}
        </>
    );
};

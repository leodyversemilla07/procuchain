import { Edit2Icon, UploadCloudIcon, BarChart4Icon } from 'lucide-react';
import { Stage, Status } from '@/types';
import { cn } from '@/lib/utils';
import { show as preProcurementShow } from '@/routes/bac-secretariat/procurement/pre-procurement';
import { show as biddingShow } from '@/routes/bac-secretariat/procurement/bidding';
import { show as postProcurementShow } from '@/routes/bac-secretariat/procurement/post-procurement';

export interface ActionConfig {
    icon: React.ReactNode;
    tooltipText: string;
    className?: string;
    onClick?: () => void;
    href?: string;
}

interface ActionCondition {
    stage?: Stage | Stage[];
    status?: Status | Status[];
}

interface ActionDefinition {
    condition: ActionCondition;
    icon: typeof Edit2Icon | typeof UploadCloudIcon | typeof BarChart4Icon;
    iconClassName: string;
    tooltipText: string;
    bgClassName: string;
    getHref?: (pr_number: string) => string;
    action?: 'pre-procurement' | 'pre-bid' | 'supplemental-bid-bulletin';
}

const iconSize = 'h-4 w-4';

/**
 * Centralized action configuration registry
 * Maps stage+status combinations to their respective actions
 */
export const ACTION_REGISTRY: ActionDefinition[] = [
    {
        condition: { stage: Stage.PROCUREMENT_INITIATION, status: Status.PROCUREMENT_SUBMITTED },
        icon: Edit2Icon,
        iconClassName: cn(iconSize, 'text-indigo-600 dark:text-indigo-400'),
        tooltipText: 'Record Pre-Procurement Conference Decision',
        bgClassName: 'bg-indigo-50 dark:bg-indigo-900/20',
        action: 'pre-procurement',
    },
    {
        condition: { 
            stage: Stage.PRE_PROCUREMENT_CONFERENCE, 
            status: [Status.PRE_PROCUREMENT_CONFERENCE_HELD] 
        },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-green-600 dark:text-green-400'),
        tooltipText: 'Upload Pre-Procurement Conference Documents',
        bgClassName: 'bg-green-50 dark:bg-green-900/20',
        getHref: (id) => preProcurementShow.url({ pr_number: id, stage: 'pre_procurement_conference' }),
    },
    {
        condition: {
            stage: Stage.BIDDING_DOCUMENTS,
            status: [
                Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED, 
                Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED
            ],
        },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-amber-600 dark:text-amber-400'),
        tooltipText: 'Upload Bidding Documents',
        bgClassName: 'bg-amber-50 dark:bg-amber-900/20',
        getHref: (id) => preProcurementShow.url({ pr_number: id, stage: 'bidding_documents' }),
    },
    {
        condition: { stage: Stage.PRE_BID_CONFERENCE, status: Status.BIDDING_DOCUMENTS_PUBLISHED },
        icon: Edit2Icon,
        iconClassName: cn(iconSize, 'text-indigo-600 dark:text-indigo-400'),
        tooltipText: 'Record Pre-Bid Conference Decision',
        bgClassName: 'bg-indigo-50 dark:bg-indigo-900/20',
        action: 'pre-bid',
    },
    {
        condition: { stage: Stage.PRE_BID_CONFERENCE, status: Status.PRE_BID_CONFERENCE_HELD },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-indigo-600 dark:text-indigo-400'),
        tooltipText: 'Upload Pre-Bid Conference Documents',
        bgClassName: 'bg-indigo-50 dark:bg-indigo-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'pre_bid_conference' }),
    },
    {
        condition: { stage: Stage.BID_OPENING, status: Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-blue-600 dark:text-blue-400'),
        tooltipText: 'Upload Bid Opening Documents',
        bgClassName: 'bg-blue-50 dark:bg-blue-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'bid_opening' }),
    },
    {
        condition: { stage: Stage.BID_EVALUATION, status: Status.BIDS_OPENED },
        icon: BarChart4Icon,
        iconClassName: cn(iconSize, 'text-indigo-600 dark:text-indigo-400'),
        tooltipText: 'Upload Bid Evaluation Documents',
        bgClassName: 'bg-indigo-50 dark:bg-indigo-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'bid_evaluation' }),
    },
    {
        condition: { stage: Stage.POST_QUALIFICATION, status: Status.BIDS_EVALUATED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-green-700 dark:text-green-400'),
        tooltipText: 'Upload Post-Qualification Report',
        bgClassName: 'bg-green-50 dark:bg-green-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'post_qualification' }),
    },
    {
        condition: { stage: Stage.NOTICE_OF_AWARD, status: Status.RESOLUTION_RECORDED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-amber-600 dark:text-amber-400'),
        tooltipText: 'Upload Notice of Award',
        bgClassName: 'bg-amber-50 dark:bg-amber-900/20',
        getHref: (id) => postProcurementShow.url({ pr_number: id, stage: 'notice_of_award' }),
    },
    {
        condition: {
            stage: Stage.SUPPLEMENTAL_BID_BULLETIN,
            status: [Status.PRE_BID_CONFERENCE_COMPLETED, Status.PRE_BID_CONFERENCE_SKIPPED],
        },
        icon: Edit2Icon,
        iconClassName: cn(iconSize, 'text-indigo-600 dark:text-indigo-400'),
        tooltipText: 'Record Supplemental Bid Bulletin Decision',
        bgClassName: 'bg-indigo-50 dark:bg-indigo-900/20',
        action: 'supplemental-bid-bulletin',
    },
    {
        condition: { stage: Stage.SUPPLEMENTAL_BID_BULLETIN, status: Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-blue-600 dark:text-blue-400'),
        tooltipText: 'Upload Supplemental Bid Bulletin Documents',
        bgClassName: 'bg-blue-50 dark:bg-blue-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'supplemental_bid_bulletin' }),
    },
    {
        condition: { stage: Stage.PERFORMANCE_BOND_CONTRACT_AND_PO, status: Status.AWARDED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-cyan-600 dark:text-cyan-400'),
        tooltipText: 'Upload Performance Bond, Contract, and PO',
        bgClassName: 'bg-cyan-50 dark:bg-cyan-900/20',
        getHref: (id) => postProcurementShow.url({ pr_number: id, stage: 'performance_bond_contract_and_po' }),
    },
    {
        condition: { stage: Stage.BAC_RESOLUTION, status: Status.POST_QUALIFICATION_VERIFIED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-purple-700 dark:text-purple-400'),
        tooltipText: 'Upload BAC Resolution Documents',
        bgClassName: 'bg-purple-50 dark:bg-purple-900/20',
        getHref: (id) => biddingShow.url({ pr_number: id, stage: 'bac_resolution' }),
    },
    {
        condition: { stage: Stage.NOTICE_TO_PROCEED, status: Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-green-600 dark:text-green-400'),
        tooltipText: 'Upload Notice to Proceed',
        bgClassName: 'bg-green-50 dark:bg-green-900/20',
        getHref: (id) => postProcurementShow.url({ pr_number: id, stage: 'notice_to_proceed' }),
    },
    {
        condition: { stage: Stage.MONITORING, status: Status.NTP_RECORDED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-teal-600 dark:text-teal-400'),
        tooltipText: 'Upload Monitoring Documents',
        bgClassName: 'bg-teal-50 dark:bg-teal-900/20',
        getHref: (id) => postProcurementShow.url({ pr_number: id, stage: 'monitoring' }),
    },
    {
        condition: { stage: Stage.COMPLETION, status: Status.MONITORING_COMPLETED },
        icon: UploadCloudIcon,
        iconClassName: cn(iconSize, 'text-emerald-600 dark:text-emerald-400'),
        tooltipText: 'Upload Certificate of Completion',
        bgClassName: 'bg-emerald-50 dark:bg-emerald-900/20',
        getHref: (id) => postProcurementShow.url({ pr_number: id, stage: 'completion' }),
    },
];

/**
 * Check if a stage and status match the condition
 */
const matchesCondition = (condition: ActionCondition, stage: Stage, status: Status): boolean => {
    const stageMatches = !condition.stage || 
        (Array.isArray(condition.stage) ? condition.stage.includes(stage) : condition.stage === stage);
    
    const statusMatches = !condition.status || 
        (Array.isArray(condition.status) ? condition.status.includes(status) : condition.status === status);
    
    return stageMatches && statusMatches;
};

/**
 * Get all matching action configurations for a given procurement
 */
export const getActionConfigs = (
    pr_number: string,
    stage: Stage,
    status: Status,
    handlers: {
        onPreProcurement?: () => void;
        onPreBid?: () => void;
        onSupplementalBidBulletin?: () => void;
    }
): ActionConfig[] => {
    return ACTION_REGISTRY
        .filter(def => matchesCondition(def.condition, stage, status))
        .map(def => {
            const Icon = def.icon;
            const config: ActionConfig = {
                icon: <Icon className={def.iconClassName} />,
                tooltipText: def.tooltipText,
                className: def.bgClassName,
            };

            if (def.getHref) {
                config.href = def.getHref(pr_number);
            } else if (def.action) {
                switch (def.action) {
                    case 'pre-procurement':
                        config.onClick = handlers.onPreProcurement;
                        break;
                    case 'pre-bid':
                        config.onClick = handlers.onPreBid;
                        break;
                    case 'supplemental-bid-bulletin':
                        config.onClick = handlers.onSupplementalBidBulletin;
                        break;
                }
            }

            return config;
        });
};

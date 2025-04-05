import {
    FileUpIcon,
    ArrowRight,
    FileTextIcon,
    CheckCircle,
    ExternalLinkIcon,
    CheckIcon,
    PlusIcon,
    ActivityIcon,
} from "lucide-react";

export const ACTION_ICON_MAP = {
    upload: FileUpIcon,
    document: FileUpIcon,
    stage: ArrowRight,
    transition: ArrowRight,
    'pre-procurement': FileTextIcon,
    decision: CheckCircle,
    publish: ExternalLinkIcon,
    complete: CheckIcon,
    submit: PlusIcon,
    add: PlusIcon,
    review: FileTextIcon,
    evaluate: FileTextIcon,
} as const;

export const ACTION_BADGE_STYLE_MAP = {
    document: 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
    submit: 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800',
    approve: 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
    review: 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800',
} as const;

export const getActionIcon = (action: string) => {
    const IconComponent = Object.entries(ACTION_ICON_MAP).find(
        ([key]) => action.toLowerCase().includes(key)
    )?.[1] || ActivityIcon;

    return IconComponent;
};

export const getActionBadgeStyle = (action: string): string => {
    const matchingStyle = Object.entries(ACTION_BADGE_STYLE_MAP).find(
        ([key]) => action.toLowerCase().includes(key)
    )?.[1];

    return matchingStyle || 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
};
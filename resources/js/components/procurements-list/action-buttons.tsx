import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import type { ProcurementListItem } from '@/types';
import type { ProcurementAction } from '@/types/workflow';
import { router } from '@inertiajs/react';
import { AlertCircle, BarChart4Icon, Edit2Icon, EyeIcon, RefreshCw, ShieldCheck, SkipForward, UploadCloudIcon } from 'lucide-react';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
}

/**
 * Maps server-provided icon names to Lucide React icons
 */
const getIconComponent = (iconName: string, variant: string) => {
    const variantColorMap: Record<string, string> = {
        default: 'text-gray-600 dark:text-gray-400',
        blue: 'text-blue-600 dark:text-blue-400',
        green: 'text-green-600 dark:text-green-400',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        amber: 'text-amber-600 dark:text-amber-400',
        purple: 'text-purple-600 dark:text-purple-400',
        cyan: 'text-cyan-600 dark:text-cyan-400',
        teal: 'text-teal-600 dark:text-teal-400',
        emerald: 'text-emerald-600 dark:text-emerald-400',
        warning: 'text-amber-600 dark:text-amber-400',
        success: 'text-green-600 dark:text-green-400',
        outline: 'text-gray-500 dark:text-gray-400',
    };

    const className = variantColorMap[variant] || variantColorMap.default;

    switch (iconName) {
        case 'upload':
            return <UploadCloudIcon className={className} />;
        case 'edit':
            return <Edit2Icon className={className} />;
        case 'chart':
            return <BarChart4Icon className={className} />;
        case 'eye':
            return <EyeIcon className={className} />;
        case 'shield-check':
            return <ShieldCheck className={className} />;
        case 'alert-circle':
            return <AlertCircle className={className} />;
        case 'skip':
            return <SkipForward className={className} />;
        case 'refresh':
            return <RefreshCw className={className} />;
        default:
            return <EyeIcon className={className} />;
    }
};

const DropdownActionItem = ({
    icon,
    tooltipText,
    onClick,
    href,
    isOptional = false,
}: {
    icon: React.ReactNode;
    tooltipText: string;
    onClick?: () => void;
    href?: string;
    isOptional?: boolean;
}) => {
    const handleClick = (e: React.MouseEvent) => {
        if (href) {
            e.preventDefault();
            router.visit(href);
        } else if (onClick) {
            onClick();
        }
    };

    return (
        <DropdownMenuItem onClick={handleClick}>
            {icon}
            <span className={isOptional ? 'text-gray-500 italic' : ''}>{tooltipText}</span>
        </DropdownMenuItem>
    );
};

/**
 * Server-driven action buttons component
 *
 * This component renders action buttons based on server-provided configuration.
 * Actions are determined by the backend based on procurement stage, status, mode,
 * and user role - ensuring business logic stays in one place.
 *
 * Actions are pre-loaded via Inertia for instant display without API calls.
 */
export const ActionButtons = ({
    procurement,
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
}: ActionButtonsProps) => {
    // Use actions directly from Inertia props (pre-loaded from backend)
    const workflow_actions = procurement.workflow_actions || [];
    const static_actions = procurement.static_actions || [];

    /**
     * Maps server action types to click handlers for dialog actions
     */
    const getDialogHandler = (actionType: string | undefined): (() => void) | undefined => {
        switch (actionType) {
            case 'pre-procurement':
                return () => onOpenPreProcurementDialog?.(procurement);
            case 'pre-bid':
                return () => onOpenPreBidDialog?.(procurement);
            case 'supplemental-bid-bulletin':
                return () => onOpenSupplementalBidBulletinDialog?.(procurement);
            default:
                return undefined;
        }
    };

    /**
     * Handles repeat action (POST request to issue another bulletin)
     */
    const handleRepeatAction = (href: string) => {
        router.post(
            href,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    /**
     * Renders a single action from server configuration
     */
    const renderAction = (action: ProcurementAction, index: number) => {
        const icon = getIconComponent(action.icon, action.variant);

        // Determine click handler based on action type
        let onClick: (() => void) | undefined;
        let href: string | undefined = action.href;

        if (action.type === 'dialog') {
            onClick = getDialogHandler(action.action);
            href = undefined; // Don't navigate for dialogs
        } else if (action.type === 'repeat' && action.href) {
            onClick = () => handleRepeatAction(action.href!);
            href = undefined; // Don't navigate for repeat actions
        }
        // For 'upload', 'view', 'verify', and other types, use href for navigation

        return (
            <DropdownActionItem
                key={`${action.type}-${index}`}
                icon={icon}
                tooltipText={action.label}
                href={href}
                onClick={onClick}
                isOptional={action.is_optional}
            />
        );
    };

    // Combine static actions (view, verify) with workflow actions
    const allActions = [...static_actions, ...workflow_actions];

    return <>{allActions.map((action, index) => renderAction(action, index))}</>;
};

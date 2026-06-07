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
    loadingDialog?: 'pre-procurement' | 'pre-bid' | 'supplemental-bid-bulletin' | null;
}

/**
 * Maps server-provided icon names to Lucide React icons
 */
const getIconComponent = (iconName: string, variant: string) => {
    const variantColorMap: Record<string, string> = {
        default: 'text-gray-600 dark:text-muted-foreground',
        blue: 'text-primary dark:text-primary',
        green: 'text-primary dark:text-primary',
        indigo: 'text-primary dark:text-primary',
        amber: 'text-muted-foreground dark:text-muted-foreground',
        purple: 'text-primary dark:text-primary',
        cyan: 'text-cyan-600 dark:text-cyan-400',
        teal: 'text-teal-600 dark:text-teal-400',
        emerald: 'text-primary dark:text-primary',
        warning: 'text-muted-foreground dark:text-muted-foreground',
        success: 'text-primary dark:text-primary',
        outline: 'text-gray-500 dark:text-muted-foreground',
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
    isLoading = false,
}: {
    icon: React.ReactNode;
    tooltipText: string;
    onClick?: () => void;
    href?: string;
    isOptional?: boolean;
    isLoading?: boolean;
}) => {
    const handleClick = (e: React.MouseEvent) => {
        if (isLoading) {
            e.preventDefault();
            return;
        }
        if (href) {
            e.preventDefault();
            router.visit(href);
        } else if (onClick) {
            onClick();
        }
    };

    return (
        <DropdownMenuItem onClick={handleClick} disabled={isLoading}>
            {isLoading ? <RefreshCw className="h-4 w-4 animate-spin" /> : icon}
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
    loadingDialog,
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
        let isLoading = false;

        if (action.type === 'dialog') {
            onClick = getDialogHandler(action.action);
            href = undefined; // Don't navigate for dialogs
            isLoading = loadingDialog === action.action;
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
                isLoading={isLoading}
            />
        );
    };

    // Combine static actions (view, verify) with workflow actions
    const allActions = [...static_actions, ...workflow_actions];

    return <>{allActions.map((action, index) => renderAction(action, index))}</>;
};

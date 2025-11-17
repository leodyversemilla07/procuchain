import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { ProcurementListItem, SharedData, Stage, Status } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, EyeIcon } from 'lucide-react';
import { getActionConfigs } from '@/config/procurement-actions';

// Import Wayfinder route helpers for each role (from /procurements-list routes)
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import { show as adminShow } from '@/routes/admin/procurements';

interface ActionButtonsProps {
    procurement: ProcurementListItem;
    onOpenPreProcurementDialog?: (procurement: ProcurementListItem) => void;
    onOpenPreBidDialog?: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinDialog?: (procurement: ProcurementListItem) => void;
}

// Helper function to get the correct Wayfinder route based on user role
const getProcurementShowUrl = (role: string, id: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return bacSecretariatShow.url(id);
        case 'bac_chairman':
            return bacChairmanShow.url(id);
        case 'hope':
            return hopeShow.url(id);
        case 'admin':
            return adminShow.url(id);
        case 'guest':
            // Guests shouldn't be able to view procurement details
            return '#';
        default:
            // Unknown role, default to admin route
            return adminShow.url(id);
    }
};

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

export const ActionButtons = ({
    procurement,
    onOpenPreProcurementDialog,
    onOpenPreBidDialog,
    onOpenSupplementalBidBulletinDialog,
}: ActionButtonsProps) => {
    const { id, stage, current_status } = procurement;
    const { auth } = usePage<SharedData>().props;
    
    // Extract role from roles array (roles[0]) instead of user.role
    const userRole = auth.roles?.[0] || auth.user?.role || 'guest';
    const isBacSecretariat = userRole === 'bac_secretariat';

    // Get workflow actions from the centralized configuration
    const workflowActions = isBacSecretariat ? getActionConfigs(
        id,
        stage as Stage,
        current_status as Status,
        {
            onPreProcurement: () => onOpenPreProcurementDialog?.(procurement),
            onPreBid: () => onOpenPreBidDialog?.(procurement),
            onSupplementalBidBulletin: () => onOpenSupplementalBidBulletinDialog?.(procurement),
        }
    ) : [];

    // Always include View Details and View Corrections actions
    const viewDetailsConfig = {
        icon: <EyeIcon className="h-4 w-4 text-blue-600 dark:text-blue-400" />,
        tooltipText: 'View Details',
        href: getProcurementShowUrl(userRole, id),
    };

    const viewCorrectionsConfig = {
        icon: <AlertCircle className="h-4 w-4 text-amber-600 dark:text-amber-400" />,
        tooltipText: 'View Corrections',
        href: `/procurements/${id}/corrections`,
    };

    const allConfigs = [viewDetailsConfig, viewCorrectionsConfig, ...workflowActions];

    return (
        <>
            {allConfigs.map((config, index) => (
                <DropdownActionItem key={index} {...config} />
            ))}
        </>
    );
};

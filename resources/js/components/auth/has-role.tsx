import { usePermissions } from '@/hooks/use-permissions';
import type { ReactNode } from 'react';

interface HasRoleProps {
    role: string | string[];
    children: ReactNode;
    fallback?: ReactNode;
}

/**
 * Conditionally render children based on user role
 *
 * @example
 * <HasRole role="admin">
 *   <AdminDashboard />
 * </HasRole>
 *
 * @example
 * <HasRole role={['admin', 'bac_secretariat']}>
 *   <ProcurementActions />
 * </HasRole>
 *
 * @example
 * <HasRole role="bac_chairman" fallback={<p>Access Denied</p>}>
 *   <ApprovalPanel />
 * </HasRole>
 */
export function HasRole({ role, children, fallback = null }: HasRoleProps) {
    const { hasRole } = usePermissions();

    if (!hasRole(role)) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}

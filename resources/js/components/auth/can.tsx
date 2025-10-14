import { usePermissions } from '@/hooks/use-permissions';
import type { ReactNode } from 'react';

interface CanProps {
    permission?: string | string[];
    role?: string | string[];
    children: ReactNode;
    fallback?: ReactNode;
}

/**
 * Conditionally render children based on user permissions or roles
 *
 * @example
 * <Can permission="create procurement">
 *   <Button>Create Procurement</Button>
 * </Can>
 *
 * @example
 * <Can role="admin">
 *   <AdminPanel />
 * </Can>
 *
 * @example
 * <Can permission={['edit procurement', 'delete procurement']}>
 *   <ActionButtons />
 * </Can>
 *
 * @example
 * <Can permission="manage users" fallback={<p>Access Denied</p>}>
 *   <UserManagement />
 * </Can>
 */
export function Can({ permission, role, children, fallback = null }: CanProps) {
    const { hasPermission, hasRole } = usePermissions();

    let canRender = false;

    if (permission) {
        canRender = hasPermission(permission);
    } else if (role) {
        canRender = hasRole(role);
    }

    if (!canRender) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}

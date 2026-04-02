import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface PermissionHelpers {
    hasRole: (role: string | string[]) => boolean;
    hasPermission: (permission: string | string[]) => boolean;
    hasAnyRole: (roles: string[]) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
    can: {
        manageProcurement: boolean;
        approveProcurement: boolean;
        manageDocuments: boolean;
        viewDocuments: boolean;
        manageStages: boolean;
        accessBlockchain: boolean;
        manageUsers: boolean;
    };
    roles: string[];
    permissions: string[];
    isAdmin: boolean;
    isBacSecretariat: boolean;
    isBacChairman: boolean;
    isHope: boolean;
}

export function usePermissions(): PermissionHelpers {
    const { auth } = usePage<SharedData>().props;
    const role = auth.role || auth.user?.role || null;
    const roles = role ? [role] : [];
    const can = auth.can || {
        manageProcurement: false,
        approveProcurement: false,
        manageDocuments: false,
        viewDocuments: false,
        manageStages: false,
        accessBlockchain: false,
        manageUsers: false,
    };
    const permissions = [
        role === 'admin' ? 'view admin dashboard' : null,
        role === 'bac_secretariat' ? 'view bac-secretariat dashboard' : null,
        role === 'bac_chairman' ? 'view bac-chairman dashboard' : null,
        role === 'hope' ? 'view hope dashboard' : null,
        role ? 'view procurement' : null,
        can.manageProcurement ? 'create procurement' : null,
        can.manageUsers ? 'manage users' : null,
        can.manageUsers ? 'edit users' : null,
        can.manageUsers ? 'delete users' : null,
    ].filter((permission): permission is string => permission !== null);

    /**
     * Check if user has a specific role or any of the provided roles
     */
    const hasRole = (role: string | string[]): boolean => {
        if (Array.isArray(role)) {
            return role.some((r) => roles.includes(r));
        }

        return roles.includes(role);
    };

    /**
     * Check if user has a specific permission or any of the provided permissions
     */
    const hasPermission = (permission: string | string[]): boolean => {
        if (Array.isArray(permission)) {
            return permission.some((p) => permissions.includes(p));
        }

        return permissions.includes(permission);
    };

    /**
     * Check if user has any of the provided roles
     */
    const hasAnyRole = (roleList: string[]): boolean => {
        return roleList.some((role) => roles.includes(role));
    };

    /**
     * Check if user has any of the provided permissions
     */
    const hasAnyPermission = (permissionList: string[]): boolean => {
        return permissionList.some((permission) => permissions.includes(permission));
    };

    return {
        hasRole,
        hasPermission,
        hasAnyRole,
        hasAnyPermission,
        can,
        roles,
        permissions,
        isAdmin: roles.includes('admin'),
        isBacSecretariat: roles.includes('bac_secretariat'),
        isBacChairman: roles.includes('bac_chairman'),
        isHope: roles.includes('hope'),
    };
}

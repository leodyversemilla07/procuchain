import type { BreadcrumbItem } from '@/types';
import { UserRole } from '@/types';
import bacSecretariat from '@/routes/bac-secretariat';
import bacChairman from '@/routes/bac-chairman';
import hope from '@/routes/hope';
import admin from '@/routes/admin';

/**
 * Breadcrumb Utilities for the Procurement System
 * 
 * This module provides centralized breadcrumb generation functions that ensure
 * consistent navigation across all pages based on user roles.
 * 
 * @example Basic Usage
 * ```tsx
 * import { getDocumentCorrectionsBreadcrumbs } from '@/utils/breadcrumbs';
 * import { usePage } from '@inertiajs/react';
 * 
 * const { auth } = usePage<SharedData>().props;
 * const userRole = auth?.user?.role;
 * const breadcrumbs = getDocumentCorrectionsBreadcrumbs(userRole, procurement.title);
 * ```
 * 
 * @example Custom Breadcrumbs
 * ```tsx
 * import { buildBreadcrumbs } from '@/utils/breadcrumbs';
 * 
 * const breadcrumbs = buildBreadcrumbs(userRole, [
 *   { title: 'Procurements', href: '/procurements-list' },
 *   { title: 'My Procurement', href: '#' },
 *   { title: 'Custom Page', href: '#' },
 * ]);
 * ```
 */

interface BreadcrumbConfig {
    role?: string;
    procurementTitle?: string;
    pr_number?: string;
    documentTitle?: string;
    customSegments?: BreadcrumbItem[];
}

/**
 * Get dashboard breadcrumb based on user role
 */
export const getDashboardBreadcrumb = (role?: string): BreadcrumbItem => {
    switch (role) {
        case UserRole.BAC_SECRETARIAT:
            return { title: 'BAC Secretariat Dashboard', href: bacSecretariat.dashboard.url() };
        case UserRole.BAC_CHAIRMAN:
            return { title: 'BAC Chairman Dashboard', href: bacChairman.dashboard.url() };
        case UserRole.HOPE:
            return { title: 'HOPE Dashboard', href: hope.dashboard.url() };
        case UserRole.ADMIN:
            return { title: 'Admin Dashboard', href: admin.dashboard.url() };
        default:
            return { title: 'Dashboard', href: '/dashboard' };
    }
};

/**
 * Get procurements list breadcrumb based on user role
 */
export const getProcurementsListBreadcrumb = (role?: string): BreadcrumbItem => {
    switch (role) {
        case UserRole.BAC_SECRETARIAT:
            return { title: 'Procurements', href: bacSecretariat.procurements.index.url() };
        case UserRole.BAC_CHAIRMAN:
            return { title: 'Procurements', href: bacChairman.procurements.index.url() };
        case UserRole.HOPE:
            return { title: 'Procurements', href: hope.procurements.index.url() };
        case UserRole.ADMIN:
            return { title: 'Procurements', href: admin.procurements.index.url() };
        default:
            return { title: 'Procurements', href: '/procurements-list' };
    }
};

/**
 * Get procurement detail breadcrumb based on user role
 */
export const getProcurementDetailBreadcrumb = (role?: string, pr_number?: string): BreadcrumbItem => {
    if (!pr_number) {
        return { title: 'Procurement Details', href: '#' };
    }
    
    switch (role) {
        case UserRole.BAC_SECRETARIAT:
            return { title: 'Procurement Details', href: bacSecretariat.procurements.show.url(pr_number) };
        case UserRole.BAC_CHAIRMAN:
            return { title: 'Procurement Details', href: bacChairman.procurements.show.url(pr_number) };
        case UserRole.HOPE:
            return { title: 'Procurement Details', href: hope.procurements.show.url(pr_number) };
        case UserRole.ADMIN:
            return { title: 'Procurement Details', href: admin.procurements.show.url(pr_number) };
        default:
            return { title: 'Procurement Details', href: `/procurements-list/${pr_number}` };
    }
};

/**
 * Get breadcrumbs for procurement list page
 */
export const getProcurementListBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
    ];
};

/**
 * Get breadcrumbs for procurement detail page
 */
export const getProcurementDetailBreadcrumbs = (
    role?: string,
    procurementTitle?: string
): BreadcrumbItem[] => {
    const procurementName = procurementTitle || 'Procurement';
    
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: procurementName, href: '#' },
    ];
};

/**
 * Get breadcrumbs for document corrections page
 */
export const getDocumentCorrectionsBreadcrumbs = (
    role?: string,
    procurementTitle?: string
): BreadcrumbItem[] => {
    const procurementName = procurementTitle || 'Procurement';
    
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: procurementName, href: '#' },
        { title: 'Document Corrections', href: '#' },
    ];
};

/**
 * Get breadcrumbs for document upload page
 */
export const getDocumentUploadBreadcrumbs = (
    role?: string,
    procurementTitle?: string
): BreadcrumbItem[] => {
    const procurementName = procurementTitle || 'Procurement';
    
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: procurementName, href: '#' },
        { title: 'Upload Documents', href: '#' },
    ];
};

/**
 * Get breadcrumbs for workflow page
 */
export const getWorkflowBreadcrumbs = (
    role?: string,
    procurementTitle?: string
): BreadcrumbItem[] => {
    const procurementName = procurementTitle || 'Procurement';
    
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: procurementName, href: '#' },
        { title: 'Workflow', href: '#' },
    ];
};

/**
 * Get breadcrumbs for procurement initiation page
 */
export const getProcurementInitiationBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: 'Procurement Initiation', href: '#' },
    ];
};

/**
 * Get breadcrumbs for document upload pages (generic)
 */
export const getDocumentUploadPageBreadcrumbs = (
    role?: string,
    procurementTitle?: string,
    stageName?: string
): BreadcrumbItem[] => {
    const procurementName = procurementTitle || 'Procurement';
    const stage = stageName || 'Upload Documents';
    
    return [
        getDashboardBreadcrumb(role),
        getProcurementsListBreadcrumb(role),
        { title: procurementName, href: '#' },
        { title: stage, href: '#' },
    ];
};

/**
 * Get breadcrumbs for settings page
 */
export const getSettingsBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    return [
        getDashboardBreadcrumb(role),
        { title: 'Settings', href: '#' },
    ];
};

/**
 * Get breadcrumbs for users management page
 */
export const getUsersManagementBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    return [
        getDashboardBreadcrumb(role),
        { title: 'User Management', href: '#' },
    ];
};

/**
 * Get custom breadcrumbs with flexible configuration
 */
export const getCustomBreadcrumbs = (config: BreadcrumbConfig): BreadcrumbItem[] => {
    const { role, customSegments = [] } = config;
    
    return [
        getDashboardBreadcrumb(role),
        ...customSegments,
    ];
};

/**
 * Generic breadcrumb builder for any page
 */
export const buildBreadcrumbs = (
    role?: string,
    segments?: Array<{ title: string; href?: string }>
): BreadcrumbItem[] => {
    const breadcrumbs: BreadcrumbItem[] = [getDashboardBreadcrumb(role)];
    
    if (segments && segments.length > 0) {
        segments.forEach(segment => {
            breadcrumbs.push({
                title: segment.title,
                href: segment.href || '#',
            });
        });
    }
    
    return breadcrumbs;
};

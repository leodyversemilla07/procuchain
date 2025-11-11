/**
 * Component Props Types
 * Contains reusable prop interfaces for common components
 */

import type { LucideIcon } from 'lucide-react';

// ============================================================================
// PEOPLE INPUT
// ============================================================================

export interface PersonData {
    name: string;
    affiliation: string;
}

export type AffiliationType = 'position' | 'organization';

// ============================================================================
// PAGINATION
// ============================================================================

export interface PaginationConfig {
    pageIndex: number;
    pageSize: number;
    pageCount: number;
    totalItems: number;
    onPageChange: (pageIndex: number) => void;
    onPageSizeChange?: (pageSize: number) => void;
    pageSizeOptions?: number[];
    className?: string;
}

// ============================================================================
// FILE UPLOAD
// ============================================================================

export interface FileUploadConfig {
    accept?: string;
    maxSizeMB?: number;
    onFileSelect: (file: File | null) => void;
    error?: string;
    required?: boolean;
}

// ============================================================================
// ERROR STATE
// ============================================================================

export type ErrorStateTone = 'default' | 'destructive' | 'warning' | 'info';

export interface ErrorStateConfig {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    tone?: ErrorStateTone;
    action?: {
        label: string;
        onClick: () => void;
    };
    className?: string;
}

// ============================================================================
// SEO
// ============================================================================

export interface SEOConfig {
    title?: string;
    description?: string;
    keywords?: string;
    author?: string;
    ogTitle?: string;
    ogDescription?: string;
    ogImage?: string;
    ogUrl?: string;
    twitterCard?: 'summary' | 'summary_large_image' | 'app' | 'player';
    twitterSite?: string;
    twitterCreator?: string;
    canonicalUrl?: string;
    robots?: string;
    structuredData?: object | object[];
}

// ============================================================================
// CHART
// ============================================================================

export type ChartConfig = {
    [key: string]: {
        label?: string;
        icon?: React.ComponentType;
        color?: string;
        theme?: {
            light?: string;
            dark?: string;
        };
    };
};

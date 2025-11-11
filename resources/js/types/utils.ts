/**
 * Utility Types
 * Contains utility types for hooks, helpers, and common utilities
 */

// ============================================================================
// APPEARANCE / THEME
// ============================================================================

export type Appearance = 'light' | 'dark' | 'system';

// ============================================================================
// CLIPBOARD
// ============================================================================

export type CopiedValue = string | null;

export type CopyFn = (text: string) => Promise<boolean>;

// ============================================================================
// DEVICE DETECTION
// ============================================================================

export interface NavigatorUABrandVersion {
    brand: string;
    version: string;
}

export interface NavigatorUAData {
    brands: NavigatorUABrandVersion[];
    mobile: boolean;
    platform: string;
    platformVersion?: string;
}

export interface NavigatorWithUAData extends Navigator {
    userAgentData?: NavigatorUAData;
}

export interface OSInfo {
    platform: string;
    version?: string;
    isWindows11?: boolean;
}

// ============================================================================
// SEO STRUCTURED DATA
// ============================================================================

export interface StructuredDataOrganization {
    '@context': 'https://schema.org';
    '@type': 'Organization';
    name: string;
    url: string;
    logo: string;
    description?: string;
    contactPoint?: {
        '@type': 'ContactPoint';
        contactType: string;
        email?: string;
    };
}

export interface StructuredDataWebSite {
    '@context': 'https://schema.org';
    '@type': 'WebSite';
    name: string;
    url: string;
    description: string;
    potentialAction?: {
        '@type': 'SearchAction';
        target: string;
        'query-input': string;
    };
}

export interface StructuredDataSoftwareApplication {
    '@context': 'https://schema.org';
    '@type': 'SoftwareApplication';
    name: string;
    applicationCategory: string;
    description: string;
    operatingSystem: string;
    offers?: object;
    featureList?: string[];
    screenshot?: string;
    author?: object;
}

// ============================================================================
// CSV
// ============================================================================

export type CSVValue = string | number | null | undefined;

// ============================================================================
// FORM & VALIDATION
// ============================================================================

export interface ValidationError {
    [key: string]: string | string[];
}

export interface FormErrors {
    [key: string]: string;
}

// ============================================================================
// DATE & TIME
// ============================================================================

export interface DateRange {
    from: Date | undefined;
    to?: Date | undefined;
}

export interface ValidityPeriod {
    start_date: string;
    end_date: string;
}

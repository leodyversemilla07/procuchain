/**
 * SEO Utilities for ProcuChain
 */

export interface StructuredDataOrganization {
    "@context": "https://schema.org";
    "@type": "Organization";
    name: string;
    url: string;
    logo: string;
    description?: string;
    contactPoint?: {
        "@type": "ContactPoint";
        contactType: string;
        email?: string;
    };
}

export interface StructuredDataWebSite {
    "@context": "https://schema.org";
    "@type": "WebSite";
    name: string;
    url: string;
    description: string;
    potentialAction?: {
        "@type": "SearchAction";
        target: string;
        "query-input": string;
    };
}

export interface StructuredDataSoftwareApplication {
    "@context": "https://schema.org";
    "@type": "SoftwareApplication";
    name: string;
    applicationCategory: string;
    description: string;
    operatingSystem: string;
    offers?: object;
    featureList?: string[];
    screenshot?: string;
    author?: object;
}

/**
 * Generate Organization structured data
 */
export function getOrganizationSchema(): StructuredDataOrganization {
    return {
        "@context": "https://schema.org",
        "@type": "Organization",
        name: "ProcuChain",
        url: "https://procuchain.tech",
        logo: "https://procuchain.tech/logo.png",
        description: "Blockchain-powered document management system for government procurement",
        contactPoint: {
            "@type": "ContactPoint",
            contactType: "technical support",
            email: "support@procuchain.tech"
        }
    };
}

/**
 * Generate WebSite structured data
 */
export function getWebSiteSchema(): StructuredDataWebSite {
    return {
        "@context": "https://schema.org",
        "@type": "WebSite",
        name: "ProcuChain",
        url: "https://procuchain.tech",
        description: "Blockchain-powered document management system for Bids and Awards Committee offices",
        potentialAction: {
            "@type": "SearchAction",
            target: "https://procuchain.tech/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    };
}

/**
 * Generate SoftwareApplication structured data
 */
export function getSoftwareApplicationSchema(): StructuredDataSoftwareApplication {
    return {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        name: "ProcuChain",
        applicationCategory: "BusinessApplication",
        description: "A blockchain-powered document management system for Bids and Awards Committee offices, providing secure archiving, storage, monitoring, and tracking of procurement documents.",
        operatingSystem: "Web",
        offers: {
            "@type": "Offer",
            price: "0",
            priceCurrency: "USD"
        },
        featureList: [
            "Blockchain Document Storage",
            "BAC Document Management",
            "Real-Time Monitoring & Tracking",
            "Secure Role-Based Access"
        ],
        screenshot: "https://procuchain.tech/logo.png",
        author: {
            "@type": "Organization",
            name: "Mindoro State University - Bongabong Campus"
        }
    };
}

/**
 * Generate dynamic meta description based on content
 */
export function generateMetaDescription(content: string, maxLength = 160): string {
    if (content.length <= maxLength) return content;
    return content.substring(0, maxLength - 3).trim() + '...';
}

/**
 * Generate keywords from text
 */
export function generateKeywords(text: string, maxKeywords = 10): string {
    const commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were'];
    const words = text.toLowerCase()
        .replace(/[^\w\s]/g, ' ')
        .split(/\s+/)
        .filter(word => word.length > 3 && !commonWords.includes(word));
    
    const uniqueWords = [...new Set(words)];
    return uniqueWords.slice(0, maxKeywords).join(', ');
}

/**
 * Validate and sanitize canonical URL
 */
export function getCanonicalUrl(path: string): string {
    const baseUrl = 'https://procuchain.tech';
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    return `${baseUrl}${cleanPath}`;
}

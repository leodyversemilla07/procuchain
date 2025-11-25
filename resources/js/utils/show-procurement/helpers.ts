import type { Document } from '@/types';
import { STAGE_ORDER } from '@/types';

/**
 * Shortens a hash string for display purposes
 * @param hash - The hash string to shorten
 * @param startLength - Number of characters to show at start (default: 5)
 * @param endLength - Number of characters to show at end (default: 5)
 * @returns Shortened hash string with ellipsis in the middle
 */
export const shortenHash = (hash?: string, startLength = 5, endLength = 5): string => {
    if (!hash) return 'N/A';
    if (hash.length <= startLength + endLength) return hash;
    return `${hash.substring(0, startLength)}...${hash.substring(hash.length - endLength)}`;
};

/**
 * Groups documents by their stage, ensuring proper ordering and uniqueness
 * @param documents - Array of documents to group
 * @returns Object with stage names as keys and arrays of documents as values
 */
export const groupDocumentsByStage = (documents?: Document[]): Record<string, Document[]> => {
    if (!documents) return {};

    const grouped = documents.reduce(
        (acc: Record<string, Document[]>, doc) => {
            const stage = doc.stage_formatted || doc.stage || 'Procurement Initiation';
            if (!acc[stage]) {
                acc[stage] = [];
            }
            acc[stage].push(doc);
            return acc;
        },
        {} as Record<string, Document[]>,
    );

    // Sort and deduplicate documents within each stage
    Object.keys(grouped).forEach((stage) => {
        if (stage === 'Bid Opening' || stage === 'Performance Bond, Contract and PO') {
            // Keep all documents for these stages, sorted by timestamp (newest first)
            grouped[stage] = grouped[stage].sort(
                (a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0),
            );
        } else {
            // For other stages, deduplicate by document type or file key
            const uniqueDocs = new Map<string, Document>();

            grouped[stage]
                .sort((a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0))
                .forEach((doc) => {
                    const key = doc.document_type || doc.file_key;
                    if (!uniqueDocs.has(key)) {
                        uniqueDocs.set(key, doc);
                    }
                });

            grouped[stage] = Array.from(uniqueDocs.values()).sort(
                (a, b) => (b.timestamp ? new Date(b.timestamp).getTime() : 0) - (a.timestamp ? new Date(a.timestamp).getTime() : 0),
            );
        }
    });

    return grouped;
};

/**
 * Sorts stage keys according to the defined STAGE_ORDER (latest first)
 * @param documentsByStage - Object with stage names as keys
 * @returns Array of stage names sorted by stage order (latest/highest index first)
 */
export const sortStageKeys = (documentsByStage: Record<string, Document[]>): string[] => {
    const stageKeys = Object.keys(documentsByStage);
    return stageKeys.sort((a, b) => {
        const aIndex = STAGE_ORDER.indexOf(a as (typeof STAGE_ORDER)[number]);
        const bIndex = STAGE_ORDER.indexOf(b as (typeof STAGE_ORDER)[number]);

        if (aIndex === -1 && bIndex === -1) return a.localeCompare(b);
        if (aIndex === -1) return 1;
        if (bIndex === -1) return -1;

        // Sort descending: higher stage index (later in procurement) appears first
        return bIndex - aIndex;
    });
};

/**
 * Calculates procurement progress percentage based on current stage
 * @param stage - Current stage name
 * @returns Progress percentage (0-100)
 */
export const calculateProgress = (stage?: string): number => {
    if (!stage) return 0;
    const stageIndex = STAGE_ORDER.indexOf(stage as (typeof STAGE_ORDER)[number]) + 1;
    const totalStages = STAGE_ORDER.length;
    return stageIndex > 0 ? (stageIndex / totalStages) * 100 : 0;
};

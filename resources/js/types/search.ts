/**
 * Search Types
 * Contains interfaces for search functionality and search suggestions
 */

// ============================================================================
// SEARCH RESULTS
// ============================================================================

export interface SearchResult {
    id: string;
    title: string;
    stage: string;
    status: string;
    timestamp: string;
    document_count: number;
}

// ============================================================================
// SEARCH SUGGESTIONS
// ============================================================================

export interface SearchSuggestion {
    id: string;
    title: string;
    category: 'procurement' | 'document' | 'stage';
    subtitle?: string;
}

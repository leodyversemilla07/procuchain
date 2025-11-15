/**
 * Document Viewer Types
 * Contains interfaces for PDF viewer, document views, and viewing statistics
 */

// ============================================================================
// DOCUMENT VIEWER
// ============================================================================

export interface ViewerUser {
    name: string;
    role: string;
}

export interface DocumentView {
    id: number;
    user: ViewerUser;
    viewed_at: string;
    viewed_at_human: string;
    ip_address: string;
    view_duration?: number;
    user_address?: string;
}

export interface ViewStats {
    total_views: number;
    unique_viewers: number;
    today_views: number;
    week_views: number;
    month_views: number;
    views_by_role: Record<string, number>;
    views_by_day: Record<string, number>;
    first_viewed?: string;
    last_viewed?: string;
}

export interface PdfDocument {
    pr_number: string;
    procurement_title: string;
    document_type: string;
    document_type_display: string;
    stage: string;
    stage_display: string;
    file_size?: number;
    timestamp: string;
    hash?: string;
    user_address: string;
    current_status?: string;
    status_timestamp?: string;
}

// ============================================================================
// DOCUMENT CORRECTIONS
// ============================================================================

export interface CorrectionRecord {
    id: number;
    document_file_key: string;
    user_id: number;
    user_name: string;
    reason: string;
    original_hash: string;
    new_hash: string;
    metadata_txid: string;
    created_at: string;
    formatted_date: string;
}

export interface CorrectionData {
    document_file_key: string;
    pr_number: string;
    stage: string;
    document_type: string;
    original_hash: string;
    timestamp: string;
    can_correct: boolean;
    corrections: CorrectionRecord[];
}

export interface ProcurementDocument {
    file_key: string;
    document_type: string;
    stage: string;
    hash: string;
    timestamp: string;
}

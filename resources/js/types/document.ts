/**
 * Document-related Types
 * Contains interfaces for document metadata and signatures
 */

export interface DocumentMetadata {
    document_type: string;
    hash: string;
    file_key: string;
    file_size: number;
    submission_date?: string;
    municipal_offices?: string[] | string;
    signatory_details?: Record<string, unknown>;
    [key: string]: unknown;
}

export interface SignatoryDetails {
    name: string;
    position: string;
    office: string;
    signature_date?: string;
    signature_hash?: string;
    [key: string]: unknown;
}

/**
 * Stage-specific metadata that can be attached to documents
 */
export interface StageMetadata {
    submission_date?: string;
    submission_date_formatted?: string;
    municipal_offices?: string;
    signatory_details?: string;
    issuance_date?: string;
    issuance_date_formatted?: string;
    document_type?: string;
    validity_period?: {
        start_date: string;
        start_date_formatted?: string;
        end_date: string;
        end_date_formatted?: string;
    };
    evaluator_names?: string;
    evaluation_date?: string;
    evaluation_date_formatted?: string;
    bond_amount?: string;
    bond_amount_formatted?: string;
    bid_value?: string;
    bid_value_formatted?: string;
    bidder_name?: string;
    opening_date?: string;
    opening_date_formatted?: string;
    report_date?: string;
    report_date_formatted?: string;
    report_notes?: string;
    outcome?: string;
    signing_date?: string;
    signing_date_formatted?: string;
    pr_number?: string;
    pr_purpose?: string;
    requested_by?: string;
    approved_by?: string;
    appropriation?: string;
    appropriation_formatted?: string;
    funding_source?: string;
    meeting_date?: string;
    meeting_date_formatted?: string;
    participants?: string;
    bulletin_number?: string;
    bulletin_title?: string;
    issue_date?: string;
    issue_date_formatted?: string;
    completion_date?: string;
    completion_date_formatted?: string;
    completion_notes?: string;
    [key: string]: unknown;
}

/**
 * Document in the procurement process
 */
export interface Document {
    file_key: string;
    document_type: string;
    document_type_formatted?: string;
    metadata_txid: string;
    hash?: string;
    hash_short?: string;
    hash_medium?: string;
    file_size?: number;
    file_size_formatted?: string;
    stage?: string;
    stage_formatted?: string;
    stage_metadata?: StageMetadata;
    pr_number?: string;
    procurement_title?: string;
    user_address?: string;
    timestamp?: string;
    formatted_date?: string;
    formatted_date_only?: string;
    formatted_time_only?: string;
    spaces_url?: string;
    document_index?: number;
}

/**
 * Event in the procurement process
 */
export interface Event {
    timestamp: string;
    event_type: string;
    details: string;
    stage?: string;
    stage_formatted?: string;
    stage_order?: number;
    document_count?: number;
    pr_number?: string;
    procurement_title?: string;
    user_address?: string;
    category?: string;
    severity?: string;
    formatted_date?: string;
    formatted_date_only?: string;
    formatted_time_only?: string;
}

/**
 * Timeline item for tracking procurement progress
 */
export interface TimelineItem {
    timestamp: string;
    formatted_date: string;
    formatted_date_only?: string;
    formatted_time_only?: string;
    stage: string;
    stage_formatted?: string;
    stage_description?: string;
    stage_order?: number;
    current_status?: string;
    status: string;
    status_formatted?: string;
    pr_number?: string;
    procurement_title?: string;
    user_address?: string;
}


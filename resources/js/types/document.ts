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
    municipal_offices?: string;
    signatory_details?: string;
    issuance_date?: string;
    document_type?: string;
    validity_period?: {
        start_date: string;
        end_date: string;
    };
    evaluator_names?: string;
    evaluation_date?: string;
    bond_amount?: string;
    bid_value?: string;
    bidder_name?: string;
    opening_date?: string;
    report_date?: string;
    report_notes?: string;
    outcome?: string;
    signing_date?: string;
    pr_number?: string;
    pr_purpose?: string;
    requested_by?: string;
    approved_by?: string;
    appropriation?: string;
    funding_source?: string;
    meeting_date?: string;
    participants?: string;
    bulletin_number?: string;
    bulletin_title?: string;
    issue_date?: string;
    completion_date?: string;
    completion_notes?: string;
    [key: string]: unknown;
}

/**
 * Document in the procurement process
 */
export interface Document {
    file_key: string;
    document_type: string;
    metadata_txid: string;
    hash?: string;
    file_size?: number;
    stage?: string;
    stage_metadata?: StageMetadata;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    timestamp?: string;
    document_index?: number;
    formatted_date?: string;
}

/**
 * Event in the procurement process
 */
export interface Event {
    timestamp: string;
    event_type: string;
    details: string;
    stage?: string;
    document_count?: number;
    procurement_id?: string;
    procurement_title?: string;
    user_address?: string;
    category?: string;
    severity?: string;
    formatted_date?: string;
}

/**
 * Timeline item for tracking procurement progress
 */
export interface TimelineItem {
    timestamp: string;
    formatted_date: string;
    stage: string;
    status: string;
}


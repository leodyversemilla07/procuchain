/**
 * Procurement-related Types
 * Contains interfaces for procurement data structures
 */

import type { Status } from './enums';
import type { Document, Event } from './document';

export interface ProcurementListItem {
    id: string;
    title: string;
    stage: string;
    current_status: Status;
    user_address: string;
    timestamp: string;
    document_count: number;
    last_updated: string;
}

export interface Procurement {
    pr_number: string;
    procurement_title: string;
    documents: Document[];
    status: Status;
    events: Event[];
    raw_status?: Record<string, unknown>;
    raw_documents?: Record<string, unknown>[];
    raw_events?: Record<string, unknown>[];
}

export interface PrInitiationResponse {
    success: boolean;
    pr_number: string;
    procurement_title: string;
    document_count: number;
    timestamp: string;
    error_message?: string;
}

export interface ProcurementInitiationMetadata {
    submission_date: string;
    municipal_offices: string[] | string;
    signatory_details: Record<string, unknown>;
}

/**
 * Defines the structure for Purchase Request Document metadata
 */
export interface ProcurementInitiationDocument {
    document_type?: string;
    submission_date?: Date;
    municipal_offices?: string;
    signatory_details?: string;
}

/**
 * Defines the complete Purchase Request Document data including the file and metadata
 */
export interface ProcurementInitiationDocumentData {
    procurement_initiation_document_file: File | null;
    procurement_initiation: ProcurementInitiationDocument;
}

/**
 * Blockchain-related Types
 * Contains interfaces for blockchain data structures
 */

export interface BlockchainProcurementDocument {
    procurement_id: string;
    procurement_title: string;
    stage: string;
    timestamp: string;
    document_index: number;
    document_type: string;
    hash: string;
    file_key: string;
    metadata_txid: string;
    user_address: string;
    file_size: number;
    stage_metadata: Record<string, unknown>;
}

export interface BlockchainProcurementState {
    procurement_id: string;
    procurement_title: string;
    current_status: string;
    stage: string;
    timestamp: string;
    user_address: string;
}

export interface BlockchainProcurementEvent {
    procurement_id: string;
    procurement_title: string;
    event_type: string;
    stage: string;
    timestamp: string;
    user_address: string;
    details: string;
    category: string;
    severity: string;
    document_count: number;
}

export interface StreamPublication {
    key: string;
    data: Document | BlockchainProcurementState | Event;
}

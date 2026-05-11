/**
 * Blockchain-related Types
 * Contains interfaces for blockchain data structures
 */

export interface BlockchainProcurementDocument {
    pr_number: string;
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
    pr_number: string;
    procurement_title: string;
    current_status: string;
    stage: string;
    timestamp: string;
    user_address: string;
}

export interface BlockchainProcurementEvent {
    pr_number: string;
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

export interface LedgerEntry {
    timestamp: string;
    formatted_timestamp: string;
    stream: string;
    stream_display: string;
    key: string;
    pr_number: string;
    action: string;
    summary: string;
    actor_address: string;
    txid: string;
    raw_json: Record<string, unknown>;
    procurement_title: string | null;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    original_txid: string | null;
}

export interface LedgerPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface StreamOption {
    value: string;
    label: string;
}

export interface NodeOption {
  id: string;
  name: string;
  role: string;
}

export interface LedgerFilters {
  pr_number?: string;
  stream?: string;
  date_from?: string;
  date_to?: string;
  search?: string;
  node?: string;
  page?: number;
}

export interface StreamPublication {
    key: string;
    data: Document | BlockchainProcurementState | Event;
}

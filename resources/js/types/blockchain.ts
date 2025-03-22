export enum StreamType {
    DOCUMENTS = 'procurement.documents',
    STATE = 'procurement.state',
    EVENTS = 'procurement.events'
}

export enum PhaseIdentifier {
    PR_INITIATION = 'PR Initiation',
    PRE_PROCUREMENT = 'Pre-Procurement',
    BID_INVITATION = 'Bid Invitation',
    BID_OPENING = 'Bid Opening',
    BID_EVALUATION = 'Bid Evaluation',
    POST_QUALIFICATION = 'Post-Qualification',
    BAC_RESOLUTION = 'BAC Resolution',
    NOTICE_OF_AWARD = 'Notice Of Award',
    PERFORMANCE_BOND = 'Performance Bond',
    CONTRACT_AND_PO = 'Contract And PO',
    NOTICE_TO_PROCEED = 'Notice To Proceeed',
    MONITORING = 'Monitoring',
    COMPLETED = 'Completed'
}

export enum ProcurementState {
    PR_SUBMITTED = 'PR Submitted',
    PRE_PROCUREMENT_SKIPPED = 'Pre-Procurement Skipped',
    PRE_PROCUREMENT_COMPLETED = 'Pre-Procurement Completed',
    BID_INVITATION_PUBLISHED = 'Bid Invitation Published',
    BIDS_OPENED = 'Bids Opened',
    BIDS_EVALUATED = 'Bids Evaluated',
    POST_QUALIFICATION_VERIFIED = 'Post-Qualification Verified',
    RESOLUTION_RECORDED = 'Resolution Recorded',
    AWARDED = 'Awarded',
    PERFORMANCE_BOND_RECORDED = 'Performance Bond Recorded',
    CONTRACT_AND_PO_RECORDED = 'Contract And PO Recorded',
    NTP_RECORDED = 'NTP Recorded',
    MONITORING = 'Monitoring',
    COMPLETED = 'Completed',
}

export enum EventType {
    DOCUMENT_UPLOAD = 'document_upload',
    STATE_CHANGE = 'state_change',
    WORKFLOW_TRANSITION = 'workflow_transition',
    USER_ACTION = 'user_action'
}

export enum EventCategory {
    WORKFLOW = 'workflow',
    DOCUMENT = 'document',
    SYSTEM = 'system',
    USER = 'user'
}

export enum EventSeverity {
    INFO = 'info',
    WARNING = 'warning',
    ERROR = 'error',
    CRITICAL = 'critical'
}

export interface BlockchainProcurementDocument {
    procurement_id: string;
    procurement_title: string;
    phase_identifier: string;
    timestamp: string;
    document_index: number;
    document_type: string;
    hash: string;
    file_key: string;
    user_address: string;
    file_size: number;
    phase_metadata: Record<string, unknown>;
    spaces_url?: string;
}

export interface BlockchainProcurementState {
    procurement_id: string;
    procurement_title: string;
    current_state: string;
    phase_identifier: string;
    timestamp: string;
    user_address: string;
}

export interface BlockchainProcurementEvent {
    procurement_id: string;
    procurement_title: string;
    event_type: string;
    phase_identifier: string;
    timestamp: string;
    user_address: string;
    details: string;
    category: string;
    severity: string;
    document_count: number;
}

export interface ProcurementListItem {
    id: string;
    title: string;
    phase_identifier: string;
    current_state: string;
    user_address: string;
    timestamp: string;
    document_count: number;
    last_updated: string;
}

export interface Procurement {
    procurement_id: string;
    procurement_title: string;
    documents: Document[];
    state: ProcurementState;
    events: Event[];
    raw_state?: Record<string, unknown>;
    raw_documents?: Record<string, unknown>[];
    raw_events?: Record<string, unknown>[];
}

export interface PrInitiationResponse {
    success: boolean;
    procurement_id: string;
    procurement_title: string;
    document_count: number;
    timestamp: string;
    error_message?: string;
}

export interface PrInitiationMetadata {
    submission_date: string;
    municipal_offices: string[] | string;
    signatory_details: Record<string, unknown>;
}

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

export interface StreamPublication {
    key: string;
    data: Document | ProcurementState | Event;
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
 * Defines the structure for Purchase Request Document metadata
 */
export interface PRDocument {
    document_type?: string;
    submission_date?: Date;
    municipal_offices?: string;
    signatory_details?: string;
}

/**
 * Defines the complete Purchase Request Document data including the file and metadata
 */
export interface PRDocumentData {
    pr_document_file: File | null;
    pr_document_metadata: PRDocument;
}

/**
 * Municipal offices options for the select dropdown
 */
export const MUNICIPAL_OFFICES = [
    { value: "MA", label: "Municipal Administrator's Office - MA" },
    { value: "MHRMO", label: "Municipal Human Resource Management Office - MHRMO" },
    { value: "GSO", label: "General Services Office - GSO" },
    { value: "MPDO", label: "Municipal Planning and Development Office - MPDO" },
    { value: "SBO", label: "Sangguniang Bayan Office - SBO" },
    { value: "DILG", label: "DILG Office - DILG" },
    { value: "MAO", label: "Municipal Accounting Office - MAO" },
    { value: "MBO", label: "Municipal Budget Office - MBO" },
    { value: "MTO", label: "Municipal Treasurer's Office - MTO" },
    { value: "MAssO", label: "Municipal Assessor's Office - MAssO" },
    { value: "BPLO", label: "Business Permits and Licensing Office - BPLO" },
    { value: "MCRO", label: "Municipal Civil Registrar's Office - MCRO" },
    { value: "MHO", label: "Municipal Health Office - MHO" },
    { value: "MSWDO", label: "Municipal Social Welfare and Development Office - MSWDO" },
    { value: "MYSD", label: "Municipal Youth and Sports Development - MYSD" },
    { value: "PESO", label: "Public Employment Services Office - PESO" },
    { value: "MAgO", label: "Municipal Agriculture Office - MAgO" },
    { value: "MEO", label: "Municipal Engineering Office - MEO" },
    { value: "MENRO", label: "Municipal Environment and Natural Resources Office - MENRO" },
    { value: "MDRRMO", label: "Municipal Disaster Risk Reduction and Management Office - MDRRMO" },
    { value: "BP", label: "Bamboo Plantation - BP" },
    { value: "SH", label: "Slaughter House - SH" },
    { value: "Tourism", label: "Municipal Tourism Office - Tourism" },
    { value: "GIST", label: "GIST Office - GIST" }
];

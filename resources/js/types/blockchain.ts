import { StartupSnapshotCallbackFn } from 'node:v8';

export enum StreamType {
    DOCUMENTS = 'procurement.documents',
    STATE = 'procurement.status',
    EVENTS = 'procurement.events',
}

export enum Stage {
    PROCUREMENT_INITIATION = 'Procurement Initiation',
    PRE_PROCUREMENT_CONFERENCE = 'Pre-Procurement Conference',
    BIDDING_DOCUMENTS = 'Bidding Documents',
    PRE_BID_CONFERENCE = 'Pre-Bid Conference',
    SUPPLEMENTAL_BID_BULLETIN = 'Supplemental Bid Bulletin',
    BID_OPENING = 'Bid Opening',
    BID_EVALUATION = 'Bid Evaluation',
    POST_QUALIFICATION = 'Post-Qualification',
    BAC_RESOLUTION = 'BAC Resolution',
    NOTICE_OF_AWARD = 'Notice of Award',
    PERFORMANCE_BOND_CONTRACT_AND_PO = 'Performance Bond, Contract and PO',
    NOTICE_TO_PROCEED = 'Notice to Proceed',
    MONITORING = 'Monitoring',
    COMPLETED = 'Completed',
    COMPLETION = 'Completion',
}

export enum Status {
    PROCUREMENT_SUBMITTED = 'Procurement Submitted',
    PRE_PROCUREMENT_CONFERENCE_HELD = 'Pre-Procurement Conference Held',
    PRE_PROCUREMENT_CONFERENCE_SKIPPED = 'Pre-Procurement Conference Skipped',
    PRE_PROCUREMENT_CONFERENCE_COMPLETED = 'Pre-Procurement Conference Completed',
    BIDDING_DOCUMENTS_PUBLISHED = 'Bidding Documents Published',
    PRE_BID_CONFERENCE_HELD = 'Pre-Bid Conference Held',
    PRE_BID_CONFERENCE_SKIPPED = 'Pre-Bid Conference Skipped',
    PRE_BID_CONFERENCE_COMPLETED = 'Pre-Bid Conference Completed',
    SUPPLEMENTAL_BID_BULLETINS_ONGOING = 'Supplemental Bulletins Ongoing',
    SUPPLEMENTAL_BID_BULLETINS_COMPLETED = 'Supplemental Bulletins Completed',
    BIDS_OPENED = 'Bids Opened',
    BIDS_EVALUATED = 'Bids Evaluated',
    POST_QUALIFICATION_VERIFIED = 'Post-Qualification Verified',
    POST_QUALIFICATION_FAILED = 'Post-Qualification Failed',
    RESOLUTION_RECORDED = 'Resolution Recorded',
    AWARDED = 'Awarded',
    PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED = 'Performance Bond, Contract and PO Recorded',
    NTP_RECORDED = 'NTP Recorded',
    MONITORING_COMPLETED = 'Monitoring Completed',
    COMPLETION_DOCUMENTS_UPLOADED = 'Completion Documents Uploaded',
    COMPLETED = 'Completed',
}

export enum EventType {
    DOCUMENT_UPLOAD = 'document_upload',
    STATE_CHANGE = 'state_change',
    WORKFLOW_TRANSITION = 'workflow_transition',
    USER_ACTION = 'user_action',
}

export enum EventCategory {
    WORKFLOW = 'workflow',
    DOCUMENT = 'document',
    SYSTEM = 'system',
    USER = 'user',
}

export enum EventSeverity {
    INFO = 'info',
    WARNING = 'warning',
    ERROR = 'error',
    CRITICAL = 'critical',
}

export interface BlockchainProcurementDocument {
    procurement_id: string;
    procurement_title: string;
    stage: string;
    timestamp: string;
    document_index: number;
    document_type: string;
    hash: string;
    file_key: string;
    user_address: string;
    file_size: number;
    stage_metadata: Record<string, unknown>;
    spaces_url?: string;
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
    procurement_id: string;
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
    procurement_id: string;
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
    data: Document | StartupSnapshotCallbackFn | Event;
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

/**
 * Municipal offices options for the select dropdown
 */
export const MUNICIPAL_OFFICES = [
    { value: "MO - Mayor's Office", label: "MO - Mayor's Office" },
    { value: 'OMA - Office of the Municipal Administrator', label: 'OMA - Office of the Municipal Administrator' },
    { value: "VMO/SBO - Vice Mayor's Office / Sangguniang Bayan Office", label: "VMO/SBO - Vice Mayor's Office / Sangguniang Bayan Office" },
    { value: 'BAC - Bids and Awards Committee Office', label: 'BAC - Bids and Awards Committee Office' },
    { value: "MTO - Municipal Treasurer's Office", label: "MTO - Municipal Treasurer's Office" },
    { value: "MACCO - Municipal Accountant's Office", label: "MACCO - Municipal Accountant's Office" },
    { value: 'MBO - Municipal Budget Office', label: 'MBO - Municipal Budget Office' },
    { value: 'GSO - General Services Office', label: 'GSO - General Services Office' },
    { value: 'MPDO - Municipal Planning and Development Office', label: 'MPDO - Municipal Planning and Development Office' },
    { value: 'MEO - Municipal Engineering Office', label: 'MEO - Municipal Engineering Office' },
    { value: 'HRMO - Human Resource Management Office', label: 'HRMO - Human Resource Management Office' },
    { value: 'MSWDO - Municipal Social Welfare and Development Office', label: 'MSWDO - Municipal Social Welfare and Development Office' },
    { value: 'MHO - Municipal Health Office', label: 'MHO - Municipal Health Office' },
    { value: 'MAGO - Municipal Agriculture Office', label: 'MAGO - Municipal Agriculture Office' },
    {
        value: 'MDDRMO - Municipal Disaster Risk Reduction and Management Office',
        label: 'MDDRMO - Municipal Disaster Risk Reduction and Management Office',
    },
    { value: 'MENRO - Municipal Environment and Natural Resources Office', label: 'MENRO - Municipal Environment and Natural Resources Office' },
    { value: 'BPLO - Business Permits and Licensing Office', label: 'BPLO - Business Permits and Licensing Office' },
    { value: "MCRO - Municipal Civil Registrar's Office", label: "MCRO - Municipal Civil Registrar's Office" },
    { value: "MASSO - Municipal Assessor's Office", label: "MASSO - Municipal Assessor's Office" },
    { value: 'COA - Commission on Audit', label: 'COA - Commission on Audit' },
    { value: 'MARKET - Market Administration Office', label: 'MARKET - Market Administration Office' },
    { value: 'TOURISM - Tourism Office', label: 'TOURISM - Tourism Office' },
    { value: 'PESO - Public Employment Service Office', label: 'PESO - Public Employment Service Office' },
    { value: 'YDS - Youth Development Services', label: 'YDS - Youth Development Services' },
    { value: 'PDAO - Persons with Disability Affairs Office', label: 'PDAO - Persons with Disability Affairs Office' },
    { value: 'OSCA - Office of the Senior Citizens Affairs', label: 'OSCA - Office of the Senior Citizens Affairs' },
    { value: 'COOPERATIVES - Cooperatives Development Office', label: 'COOPERATIVES - Cooperatives Development Office' },
    {
        value: 'KALAHI - Kapit-Bisig Laban sa Kahirapan – Comprehensive and Integrated Delivery of Social Services',
        label: 'KALAHI - Kapit-Bisig Laban sa Kahirapan – Comprehensive and Integrated Delivery of Social Services',
    },
    { value: 'GIST - Gloria Institute of Science and Technology Office', label: 'GIST - Gloria Institute of Science and Technology Office' },
    { value: 'Zoning - Zoning Office', label: 'Zoning - Zoning Office' },
    { value: 'SLAUGHTER - Slaughterhouse Office', label: 'SLAUGHTER - Slaughterhouse Office' },
    { value: 'BAMBOO - Bamboo Plantation', label: 'BAMBOO - Bamboo Plantation' },
];

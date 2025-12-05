/**
 * Enums for the Procurement System
 * Contains all enum definitions used throughout the application
 */

export enum StreamType {
    DOCUMENTS = 'procurement.documents',
    STATE = 'procurement.status',
    EVENTS = 'procurement.events',
    CORRECTIONS = 'procurement.corrections',
    FILE_DATA = 'file.data',
}

export enum Stage {
    PROCUREMENT_INITIATION = 'procurement_initiation',
    PRE_PROCUREMENT_CONFERENCE = 'pre_procurement_conference',
    BIDDING_DOCUMENTS = 'bidding_documents',
    PRE_BID_CONFERENCE = 'pre_bid_conference',
    SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin',
    BID_OPENING = 'bid_opening',
    BID_EVALUATION = 'bid_evaluation',
    POST_QUALIFICATION = 'post_qualification',
    BAC_RESOLUTION = 'bac_resolution',
    NOTICE_OF_AWARD = 'notice_of_award',
    PERFORMANCE_BOND_CONTRACT_AND_PO = 'performance_bond_contract_and_po',
    NOTICE_TO_PROCEED = 'notice_to_proceed',
    MONITORING = 'monitoring',
    COMPLETED = 'completed',
    COMPLETION = 'completion',
}

export enum Status {
    PROCUREMENT_INITIATED = 'procurement_initiated',
    PROCUREMENT_SUBMITTED = 'procurement_submitted',
    PRE_PROCUREMENT_CONFERENCE_HELD = 'pre_procurement_conference_held',
    PRE_PROCUREMENT_CONFERENCE_SKIPPED = 'pre_procurement_conference_skipped',
    PRE_PROCUREMENT_CONFERENCE_COMPLETED = 'pre_procurement_conference_completed',
    BIDDING_DOCUMENTS_PUBLISHED = 'bidding_documents_published',
    PRE_BID_CONFERENCE_HELD = 'pre_bid_conference_held',
    PRE_BID_CONFERENCE_SKIPPED = 'pre_bid_conference_skipped',
    PRE_BID_CONFERENCE_COMPLETED = 'pre_bid_conference_completed',
    SUPPLEMENTAL_BID_BULLETINS_ONGOING = 'supplemental_bulletins_ongoing',
    SUPPLEMENTAL_BID_BULLETINS_COMPLETED = 'supplemental_bulletins_completed',
    BIDS_OPENED = 'bids_opened',
    BIDS_EVALUATED = 'bids_evaluated',
    POST_QUALIFICATION_VERIFIED = 'post_qualification_verified',
    POST_QUALIFICATION_FAILED = 'post_qualification_failed',
    RESOLUTION_RECORDED = 'resolution_recorded',
    AWARDED = 'awarded',
    PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED = 'performance_bond_contract_and_po_recorded',
    NTP_RECORDED = 'ntp_recorded',
    MONITORING_COMPLETED = 'monitoring_completed',
    COMPLETION_DOCUMENTS_UPLOADED = 'completion_documents_uploaded',
    COMPLETED = 'completed',
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

export enum UserRole {
    BAC_SECRETARIAT = 'bac_secretariat',
    BAC_CHAIRMAN = 'bac_chairman',
    HOPE = 'hope',
    ADMIN = 'admin',
}

export enum DocumentType {
    // Procurement Initiation - RA 12009 (NGPA) Compliant Document Types
    PURCHASE_REQUEST = 'purchase_request',
    TECHNICAL_SPECIFICATIONS = 'technical_specifications',
    TERMS_OF_REFERENCE = 'terms_of_reference',
    CERTIFICATE_OF_FUNDS = 'certificate_of_funds',
    PPMP_ENTRY = 'ppmp_entry',
    MARKET_RESEARCH = 'market_research',
    PRICE_SURVEY = 'price_survey',
    APPROVAL_DOCUMENTS = 'approval_documents',
    END_USER_REQUEST = 'end_user_request',
    DEPARTMENT_ENDORSEMENT = 'department_endorsement',
    BUDGET_ALLOCATION = 'budget_allocation',
    PROJECT_PROPOSAL = 'project_proposal',

    // Legacy - Keep for backwards compatibility
    PROCUREMENT_INITIATION_DOCUMENT = 'procurement_initiation_document',

    // Pre-Procurement Conference
    PRE_PROCUREMENT_MINUTES = 'pre_procurement_minutes',
    PRE_PROCUREMENT_ATTENDANCE = 'pre_procurement_attendance',

    // Bidding Documents
    BIDDING_DOCUMENT = 'bidding_document',

    // Pre-Bid Conference
    PRE_BID_MINUTES = 'pre_bid_minutes',
    PRE_BID_ATTENDANCE = 'pre_bid_attendance',

    // Supplemental Bid Bulletin
    SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin',

    // Bid Opening
    BID_DOCUMENT = 'bid_document',

    // Bid Evaluation
    EVALUATION_SUMMARY = 'evaluation_summary',
    ABSTRACT = 'abstract',

    // Post Qualification
    POST_QUALIFICATION_REPORT = 'post_qualification_report',
    TWG_CERTIFICATION = 'twg_certification',
    NOTICE_OF_POST_QUALIFICATION = 'notice_of_post_qualification',

    // BAC Resolution
    BAC_RESOLUTION = 'bac_resolution',

    // Notice of Award
    NOTICE_OF_AWARD = 'notice_of_award',

    // Performance Bond, Contract & PO
    PERFORMANCE_BOND = 'performance_bond',
    CONTRACT = 'contract',
    PURCHASE_ORDER = 'purchase_order',

    // Notice to Proceed
    NOTICE_TO_PROCEED = 'notice_to_proceed',

    // Monitoring
    COMPLIANCE_REPORT = 'compliance_report',

    // Completion
    CERTIFICATE_OF_COMPLETION = 'certificate_of_completion',

    // Unknown/Fallback
    UNKNOWN = 'unknown',
}

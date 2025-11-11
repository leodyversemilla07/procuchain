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

export enum UserRole {
    BAC_SECRETARIAT = 'bac_secretariat',
    BAC_CHAIRMAN = 'bac_chairman',
    HOPE = 'hope',
    ADMIN = 'admin',
}

export enum DocumentType {
    // Procurement Initiation
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

<?php

namespace App\Enums;

/**
 * Document Type Enum
 *
 * Represents all possible document types in the procurement system.
 * Each document type is associated with a specific procurement stage.
 */
enum DocumentTypeEnums: string
{
    // ==================================================================================
    // PHASE 1: PRE-PROCUREMENT (Planning & Preparation) - Stages 1-3
    // ==================================================================================

    // Stage 1: Procurement Initiation (10 documents)
    case PURCHASE_REQUEST = 'purchase_request';
    case PPMP = 'ppmp';
    case APP = 'app';
    case CERTIFICATE_OF_FUNDS = 'certificate_of_funds';
    case APPROVED_BUDGET_CONTRACT = 'approved_budget_contract';
    case TECHNICAL_SPECIFICATIONS = 'technical_specifications';
    case TERMS_OF_REFERENCE = 'terms_of_reference';
    case MARKET_RESEARCH = 'market_research';
    case SANGGUNIANG_BAYAN_RESOLUTION = 'sangguniang_bayan_resolution';
    case ENVIRONMENTAL_COMPLIANCE_CERTIFICATE = 'environmental_compliance_certificate';
    case PROGRAM_OF_WORK = 'program_of_work';
    case END_USER_REQUEST = 'end_user_request';
    case OFFICE_ENDORSEMENT = 'office_endorsement';
    case BUDGET_ALLOCATION = 'budget_allocation';
    case PROJECT_PROPOSAL = 'project_proposal';
    case APPROVAL_DOCUMENTS = 'approval_documents';

    // Stage 2: Pre-Procurement Conference (5 documents)
    case PRE_PROCUREMENT_AGENDA = 'pre_procurement_agenda';
    case PRE_PROCUREMENT_ATTENDANCE = 'pre_procurement_attendance';
    case PRE_PROCUREMENT_MINUTES = 'pre_procurement_minutes';
    case PRE_PROCUREMENT_PRESENTATION = 'pre_procurement_presentation';
    case PRE_PROCUREMENT_QA_LOG = 'pre_procurement_qa_log';

    // Stage 3: Bidding Documents (13 documents)
    case INVITATION_TO_BID = 'invitation_to_bid';
    case BID_DATA_SHEET = 'bid_data_sheet';
    case INSTRUCTIONS_TO_BIDDERS = 'instructions_to_bidders';
    case GENERAL_CONDITIONS_CONTRACT = 'general_conditions_contract';
    case SPECIAL_CONDITIONS_CONTRACT = 'special_conditions_contract';
    case BIDDING_TECHNICAL_SPECIFICATIONS = 'bidding_technical_specifications';
    case BILL_OF_QUANTITIES = 'bill_of_quantities';
    case DRAWINGS_PLANS = 'drawings_plans';
    case BIDDING_FORMS = 'bidding_forms';
    case BAC_RESOLUTION_BIDDING_DOCS = 'bac_resolution_bidding_docs';
    case PHILGEPS_POSTING_RECEIPT = 'philgeps_posting_receipt';
    case NEWSPAPER_ADVERTISEMENT = 'newspaper_advertisement';
    case WEBSITE_POSTING_PROOF = 'website_posting_proof';

    // ==================================================================================
    // PHASE 2: PROCUREMENT (Bidding & Evaluation) - Stages 4-9
    // ==================================================================================

    // Stage 4: Pre-Bid Conference (6 documents)
    case PRE_BID_AGENDA = 'pre_bid_agenda';
    case PRE_BID_ATTENDANCE = 'pre_bid_attendance';
    case PRE_BID_MINUTES = 'pre_bid_minutes';
    case PRE_BID_SIGN_IN = 'pre_bid_sign_in';
    case PRE_BID_RECORDING = 'pre_bid_recording';
    case PRE_BID_PRESENTATION = 'pre_bid_presentation';

    // Stage 5: Supplemental Bid Bulletin (6 documents)
    case SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin';
    case BAC_RESOLUTION_BID_BULLETIN = 'bac_resolution_bid_bulletin';
    case BID_BULLETIN_PHILGEPS = 'bid_bulletin_philgeps';
    case BID_BULLETIN_NOTICE = 'bid_bulletin_notice';
    case BID_BULLETIN_WEBSITE = 'bid_bulletin_website';
    case BID_BULLETIN_ACKNOWLEDGMENTS = 'bid_bulletin_acknowledgments';

    // Stage 6: Bid Opening (12 documents)
    case BID_SUBMISSION_REGISTER = 'bid_submission_register';
    case PHILGEPS_PLATINUM_CERTIFICATE = 'philgeps_platinum_certificate';
    case SEALED_BID_PROPOSALS = 'sealed_bid_proposals';
    case ABSTRACT_OF_BIDS = 'abstract_of_bids';
    case BID_OPENING_MINUTES = 'bid_opening_minutes';
    case BID_OPENING_ATTENDANCE = 'bid_opening_attendance';
    case BIDDERS_ELIGIBILITY_DOCUMENTS = 'bidders_eligibility_documents';
    case BIDDERS_TECHNICAL_PROPOSALS = 'bidders_technical_proposals';
    case BIDDERS_FINANCIAL_PROPOSALS = 'bidders_financial_proposals';
    case BID_SECURITY = 'bid_security';
    case BID_OPENING_RECORDING = 'bid_opening_recording';
    case BID_DOCUMENT = 'bid_document'; // Legacy compatibility

    // Stage 7: Bid Evaluation (9 documents)
    case TWG_RESOLUTION = 'twg_resolution';
    case PRELIMINARY_EXAMINATION_REPORT = 'preliminary_examination_report';
    case TECHNICAL_EVALUATION_REPORT = 'technical_evaluation_report';
    case FINANCIAL_EVALUATION_REPORT = 'financial_evaluation_report';
    case COMPARATIVE_BID_ANALYSIS = 'comparative_bid_analysis';
    case EVALUATION_MEETING_MINUTES = 'evaluation_meeting_minutes';
    case EVALUATION_SUPPORTING_DOCS = 'evaluation_supporting_docs';
    case EVALUATION_CLARIFICATIONS = 'evaluation_clarifications';
    case BAC_RESOLUTION_EVALUATION = 'bac_resolution_evaluation';

    // Stage 8: Post-Qualification (10 documents)
    case POST_QUALIFICATION_REPORT = 'post_qualification_report';
    case SITE_VISIT_REPORT = 'site_visit_report';
    case DOCUMENT_VERIFICATION_CHECKLIST = 'document_verification_checklist';
    case FINANCIAL_CAPACITY_ASSESSMENT = 'financial_capacity_assessment';
    case TECHNICAL_CAPACITY_ASSESSMENT = 'technical_capacity_assessment';
    case AGENCY_CERTIFICATIONS = 'agency_certifications';
    case SITE_VISIT_PHOTOS = 'site_visit_photos';
    case INTERVIEW_NOTES = 'interview_notes';
    case BAC_RESOLUTION_POST_QUALIFICATION = 'bac_resolution_post_qualification';
    case POST_QUALIFICATION_NOTICE = 'post_qualification_notice';

    // Stage 9: BAC Resolution (7 documents)
    case BAC_RESOLUTION_AWARD = 'bac_resolution_award';
    case LCRB_NOTICE = 'lcrb_notice';
    case BID_EVALUATION_PACKAGE = 'bid_evaluation_package';
    case TRANSMITTAL_TO_HOPE = 'transmittal_to_hope';
    case AWARD_PHILGEPS_POSTING = 'award_philgeps_posting';
    case AWARD_WEBSITE_POSTING = 'award_website_posting';
    case AWARD_CONSPICUOUS_POSTING = 'award_conspicuous_posting';

    // ==================================================================================
    // PHASE 3: POST-PROCUREMENT (Award & Implementation) - Stages 10-15
    // ==================================================================================

    // Stage 10: Notice of Award (7 documents)
    case HOPE_APPROVAL = 'hope_approval';
    case NOTICE_OF_AWARD = 'notice_of_award';
    case NOA_RECEIPT_CERTIFICATE = 'noa_receipt_certificate';
    case NOA_PUBLICATION = 'noa_publication';
    case NOTICE_TO_UNSUCCESSFUL_BIDDERS = 'notice_to_unsuccessful_bidders';
    case BID_SECURITY_RETURN = 'bid_security_return';
    case LEGAL_OFFICER_CERTIFICATE = 'legal_officer_certificate';

    // Stage 11: Performance Bond, Contract and PO (14 documents)
    case PERFORMANCE_BOND = 'performance_bond';
    case PERFORMANCE_SECURING_DECLARATION = 'performance_securing_declaration';
    case CONTRACT = 'contract';
    case CONTRACT_ANNEXES = 'contract_annexes';
    case PURCHASE_ORDER = 'purchase_order';
    case JOB_ORDER = 'job_order';
    case CONTRACT_SB_RESOLUTION = 'contract_sb_resolution';
    case CONTRACT_CAF = 'contract_caf';
    case OBLIGATION_REQUEST = 'obligation_request';
    case BUSINESS_DOCUMENTS = 'business_documents';
    case INSURANCE_POLICIES = 'insurance_policies';
    case CONTRACTORS_ALL_RISK = 'contractors_all_risk';
    case WARRANTY_SECURITY = 'warranty_security';
    case CONTRACT_RECEIPT = 'contract_receipt';

    // Stage 12: Notice to Proceed (10 documents)
    case NOTICE_TO_PROCEED = 'notice_to_proceed';
    case NTP_ACKNOWLEDGMENT = 'ntp_acknowledgment';
    case DELIVERY_SCHEDULE = 'delivery_schedule';
    case PERSONNEL_LIST = 'personnel_list';
    case EQUIPMENT_LIST = 'equipment_list';
    case MOBILIZATION_REPORT = 'mobilization_report';
    case PRE_CONSTRUCTION_MINUTES = 'pre_construction_minutes';
    case BARANGAY_ENDORSEMENT = 'barangay_endorsement';
    case CONSTRUCTION_PERMIT = 'construction_permit';
    case SAFETY_PLAN = 'safety_plan';

    // Stage 13: Monitoring (13 documents)
    case PROGRESS_REPORTS = 'progress_reports';
    case MONITORING_REPORTS = 'monitoring_reports';
    case SITE_INSPECTION_REPORTS = 'site_inspection_reports';
    case DELIVERY_RECEIPTS = 'delivery_receipts';
    case INSPECTION_ACCEPTANCE_REPORT = 'inspection_acceptance_report';
    case TIME_EXTENSION_REQUEST = 'time_extension_request';
    case VARIATION_ORDER = 'variation_order';
    case PAYMENT_REQUESTS = 'payment_requests';
    case DISBURSEMENT_VOUCHERS = 'disbursement_vouchers';
    case COMMUNICATION_LOGS = 'communication_logs';
    case WORK_PROGRESS_PHOTOS = 'work_progress_photos';
    case MATERIALS_TESTING = 'materials_testing';
    case AS_BUILT_DOCUMENTATION = 'as_built_documentation';

    // Stage 14: Completion (21 documents)
    case CERTIFICATE_OF_COMPLETION = 'certificate_of_completion';
    case CERTIFICATE_FINAL_ACCEPTANCE = 'certificate_final_acceptance';
    case FINAL_INSPECTION_REPORT = 'final_inspection_report';
    case FINAL_IAR = 'final_iar';
    case AS_BUILT_PLANS = 'as_built_plans';
    case OPERATIONS_MAINTENANCE_MANUALS = 'operations_maintenance_manuals';
    case WARRANTY_DOCUMENTS = 'warranty_documents';
    case TRAINING_CERTIFICATES = 'training_certificates';
    case FINAL_PROGRESS_REPORT = 'final_progress_report';
    case FINAL_PAYMENT_REQUEST = 'final_payment_request';
    case FINAL_BILLING_STATEMENT = 'final_billing_statement';
    case CLEARANCE_WAIVER = 'clearance_waiver';
    case LIQUIDATED_DAMAGES_ASSESSMENT = 'liquidated_damages_assessment';
    case FINAL_DISBURSEMENT_VOUCHER = 'final_disbursement_voucher';
    case TURNOVER_DOCUMENTS = 'turnover_documents';
    case PROJECT_COMPLETION_REPORT = 'project_completion_report';
    case PROPERTY_ACKNOWLEDGMENT_RECEIPT = 'property_acknowledgment_receipt';
    case ACCEPTANCE_TURNOVER_CERTIFICATE = 'acceptance_turnover_certificate';
    case UPDATED_INVENTORY_RECORDS = 'updated_inventory_records';
    case PROCUREMENT_DOCUMENTATION_PACKAGE = 'procurement_documentation_package';
    case PERFORMANCE_EVALUATION = 'performance_evaluation';

    // Stage 15: Completed - Post-Implementation (4 optional documents)
    case POST_IMPLEMENTATION_REVIEW = 'post_implementation_review';
    case COA_AUDIT_DOCUMENTATION = 'coa_audit_documentation';
    case WARRANTY_CLAIM_RECORDS = 'warranty_claim_records';
    case ASSET_MANAGEMENT_RECORDS = 'asset_management_records';

    // ==================================================================================
    // ALTERNATIVE PROCUREMENT METHODS - Small Value & Direct Procurement
    // Per NGPA IRR Rule 4 (RA 12009)
    // ==================================================================================

    // Request for Quotation Stage (SVP, Direct Contracting, Repeat Order, etc.)
    case NOTICE_OF_REQUEST_FOR_QUOTATION = 'notice_of_request_for_quotation';
    case REQUEST_FOR_QUOTATION = 'request_for_quotation';
    case PHILGEPS_BID_NOTICE_ABSTRACT = 'philgeps_bid_notice_abstract';
    case PRICE_QUOTATION = 'price_quotation';
    case SUPPLIER_CANVASS_FORM = 'supplier_canvass_form';
    case QUOTATION_COMPARISON_SHEET = 'quotation_comparison_sheet';

    // Abstract of Quotations Stage
    case ABSTRACT_OF_QUOTATIONS = 'abstract_of_quotations';
    case CERTIFICATE_OF_ACCEPTANCE_OF_QUOTATION = 'certificate_of_acceptance_of_quotation';
    case PHILGEPS_AWARD_NOTICE_ABSTRACT = 'philgeps_award_notice_abstract';
    case LOWEST_QUOTATION_CERTIFICATION = 'lowest_quotation_certification';

    // Legacy/Compatibility Cases (kept for backward compatibility)
    case PPMP_ENTRY = 'ppmp_entry';
    case PRICE_SURVEY = 'price_survey';
    case BUDGET_ESTIMATE = 'budget_estimate';
    case MARKET_STUDY = 'market_study';
    case PROCUREMENT_INITIATION_DOCUMENT = 'procurement_initiation_document';
    case BIDDING_DOCUMENT = 'bidding_document';
    case SCOPE_OF_WORK = 'scope_of_work';
    case BID_EVALUATION_REPORT = 'bid_evaluation_report';
    case EVALUATION_SUMMARY = 'evaluation_summary';
    case ABSTRACT = 'abstract';
    case TWG_CERTIFICATION = 'twg_certification';
    case NOTICE_OF_POST_QUALIFICATION = 'notice_of_post_qualification';
    case BAC_RESOLUTION = 'bac_resolution';
    case PROGRESS_BILLING = 'progress_billing';
    case COMPLIANCE_REPORT = 'compliance_report';
    case PHILGEPS_CERTIFICATE = 'philgeps_certificate';
    case MAYORS_PERMIT = 'mayors_permit';
    case BIR_REGISTRATION = 'bir_registration';
    case TAX_CLEARANCE = 'tax_clearance';

    // Unknown/Fallback
    case UNKNOWN = 'unknown';

    /**
     * Get the user-friendly display name for the document type
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            // Stage 1: Procurement Initiation
            self::PURCHASE_REQUEST => 'Purchase Request (PR)',
            self::PPMP => 'Project Procurement Management Plan (PPMP)',
            self::APP => 'Annual Investment Plan (AIP)',
            self::CERTIFICATE_OF_FUNDS => 'Certificate of Availability of Funds (CAF)',
            self::APPROVED_BUDGET_CONTRACT => 'Approved Budget for the Contract (ABC)',
            self::TECHNICAL_SPECIFICATIONS => 'Technical Specifications',
            self::TERMS_OF_REFERENCE => 'Terms of Reference (TOR)',
            self::MARKET_RESEARCH => 'Market Research/Price Quotations',
            self::SANGGUNIANG_BAYAN_RESOLUTION => 'Sangguniang Bayan Resolution',
            self::ENVIRONMENTAL_COMPLIANCE_CERTIFICATE => 'Environmental Compliance Certificate (ECC)',
            self::PROGRAM_OF_WORK => 'Program of Work (POW)',
            self::END_USER_REQUEST => 'End-User Request Letter',
            self::OFFICE_ENDORSEMENT => 'Office Endorsement',
            self::BUDGET_ALLOCATION => 'Budget Allocation Document',
            self::PROJECT_PROPOSAL => 'Project Proposal',
            self::APPROVAL_DOCUMENTS => 'Approval Documents',

            // Stage 2: Pre-Procurement Conference
            self::PRE_PROCUREMENT_AGENDA => 'Pre-Procurement Conference Agenda',
            self::PRE_PROCUREMENT_ATTENDANCE => 'Pre-Procurement Conference Attendance Sheet',
            self::PRE_PROCUREMENT_MINUTES => 'Pre-Procurement Conference Minutes',
            self::PRE_PROCUREMENT_PRESENTATION => 'Pre-Procurement Presentation Materials',
            self::PRE_PROCUREMENT_QA_LOG => 'Questions and Clarifications Log',

            // Stage 3: Bidding Documents
            self::INVITATION_TO_BID => 'Invitation to Bid (ITB)',
            self::BID_DATA_SHEET => 'Bid Data Sheet (BDS)',
            self::INSTRUCTIONS_TO_BIDDERS => 'Instructions to Bidders',
            self::GENERAL_CONDITIONS_CONTRACT => 'General Conditions of Contract (GCC)',
            self::SPECIAL_CONDITIONS_CONTRACT => 'Special Conditions of Contract (SCC)',
            self::BIDDING_TECHNICAL_SPECIFICATIONS => 'Technical Specifications',
            self::BILL_OF_QUANTITIES => 'Bill of Quantities (BOQ)',
            self::DRAWINGS_PLANS => 'Drawings/Plans',
            self::BIDDING_FORMS => 'Bidding Forms and Formats',
            self::BAC_RESOLUTION_BIDDING_DOCS => 'BAC Resolution Approving Bidding Documents',
            self::PHILGEPS_POSTING_RECEIPT => 'PhilGEPS Posting Receipt',
            self::NEWSPAPER_ADVERTISEMENT => 'Newspaper Advertisement/Affidavit of Publication',
            self::WEBSITE_POSTING_PROOF => 'Website Posting Proof',

            // Stage 4: Pre-Bid Conference
            self::PRE_BID_AGENDA => 'Pre-Bid Conference Agenda',
            self::PRE_BID_ATTENDANCE => 'Pre-Bid Conference Attendance Sheet',
            self::PRE_BID_MINUTES => 'Pre-Bid Conference Minutes',
            self::PRE_BID_SIGN_IN => 'Sign-in Sheet for Bidders',
            self::PRE_BID_RECORDING => 'Audio/Video Recording',
            self::PRE_BID_PRESENTATION => 'PowerPoint Presentation',

            // Stage 5: Supplemental Bid Bulletin
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental/Bid Bulletin',
            self::BAC_RESOLUTION_BID_BULLETIN => 'BAC Resolution Approving Bid Bulletin',
            self::BID_BULLETIN_PHILGEPS => 'PhilGEPS Posting Receipt (Bulletin)',
            self::BID_BULLETIN_NOTICE => 'Notice to All Bidders',
            self::BID_BULLETIN_WEBSITE => 'Website Posting Proof (Bulletin)',
            self::BID_BULLETIN_ACKNOWLEDGMENTS => 'Acknowledgment Receipts from Bidders',

            // Stage 6: Bid Opening
            self::BID_SUBMISSION_REGISTER => 'Bid Submission Register',
            self::PHILGEPS_PLATINUM_CERTIFICATE => 'PhilGEPS Platinum Membership Certificate',
            self::SEALED_BID_PROPOSALS => 'Sealed Bid Envelopes',
            self::ABSTRACT_OF_BIDS => 'Abstract of Bids',
            self::BID_OPENING_MINUTES => 'Minutes of Bid Opening',
            self::BID_OPENING_ATTENDANCE => 'Bid Opening Attendance Sheet',
            self::BIDDERS_ELIGIBILITY_DOCUMENTS => "Bidders' Eligibility Documents",
            self::BIDDERS_TECHNICAL_PROPOSALS => "Bidders' Technical Proposals",
            self::BIDDERS_FINANCIAL_PROPOSALS => "Bidders' Financial Proposals",
            self::BID_SECURITY => 'Bid Security',
            self::BID_OPENING_RECORDING => 'Audio/Video Recording of Bid Opening',
            self::BID_DOCUMENT => 'Bid Document',

            // Stage 7: Bid Evaluation
            self::TWG_RESOLUTION => 'BAC Resolution Creating Technical Working Group (TWG)',
            self::PRELIMINARY_EXAMINATION_REPORT => 'Preliminary Examination Report',
            self::TECHNICAL_EVALUATION_REPORT => 'Detailed Technical Evaluation Report',
            self::FINANCIAL_EVALUATION_REPORT => 'Financial Evaluation Report',
            self::COMPARATIVE_BID_ANALYSIS => 'Comparative Bid Analysis',
            self::EVALUATION_MEETING_MINUTES => 'Minutes of BAC Evaluation Meetings',
            self::EVALUATION_SUPPORTING_DOCS => 'Supporting Evaluation Documents',
            self::EVALUATION_CLARIFICATIONS => 'Notices to Bidders (Clarifications)',
            self::BAC_RESOLUTION_EVALUATION => 'BAC Resolution on Bid Evaluation Results',

            // Stage 8: Post-Qualification
            self::POST_QUALIFICATION_REPORT => 'Post-Qualification Report',
            self::SITE_VISIT_REPORT => 'Site Visit Report',
            self::DOCUMENT_VERIFICATION_CHECKLIST => 'Document Verification Checklist',
            self::FINANCIAL_CAPACITY_ASSESSMENT => 'Financial Capacity Assessment',
            self::TECHNICAL_CAPACITY_ASSESSMENT => 'Technical Capacity Assessment',
            self::AGENCY_CERTIFICATIONS => 'Certification from Relevant Agencies',
            self::SITE_VISIT_PHOTOS => 'Site Visit Photos/Videos',
            self::INTERVIEW_NOTES => 'Interview Notes',
            self::BAC_RESOLUTION_POST_QUALIFICATION => 'BAC Resolution on Post-Qualification',
            self::POST_QUALIFICATION_NOTICE => 'Notice to Lowest Bidder of Post-Qualification Result',

            // Stage 9: BAC Resolution
            self::BAC_RESOLUTION_AWARD => 'BAC Resolution Recommending Award',
            self::LCRB_NOTICE => 'Notice of Lowest Calculated and Responsive Bid (LCRB)',
            self::BID_EVALUATION_PACKAGE => 'Complete Bid Evaluation Documentation Package',
            self::TRANSMITTAL_TO_HOPE => 'Transmittal Letter to Head of Procuring Entity (HoPE)',
            self::AWARD_PHILGEPS_POSTING => 'PhilGEPS Posting of Award Recommendation',
            self::AWARD_WEBSITE_POSTING => 'Posting Proof on LGU Website',
            self::AWARD_CONSPICUOUS_POSTING => 'Certificate of Posting on Conspicuous Place',

            // Stage 10: Notice of Award
            self::HOPE_APPROVAL => 'HoPE Approval of BAC Recommendation',
            self::NOTICE_OF_AWARD => 'Notice of Award (NOA)',
            self::NOA_RECEIPT_CERTIFICATE => 'Certificate of Award Receipt by Winning Bidder',
            self::NOA_PUBLICATION => 'Publication of Notice of Award',
            self::NOTICE_TO_UNSUCCESSFUL_BIDDERS => 'Notice to Unsuccessful Bidders',
            self::BID_SECURITY_RETURN => 'Return of Bid Securities',
            self::LEGAL_OFFICER_CERTIFICATE => 'Certificate from Municipal Legal Officer',

            // Stage 11: Performance Bond, Contract and PO
            self::PERFORMANCE_BOND => 'Performance Security/Bond',
            self::PERFORMANCE_SECURING_DECLARATION => 'Performance Securing Declaration (PSD)',
            self::CONTRACT => 'Contract Agreement',
            self::CONTRACT_ANNEXES => 'Contract Annexes',
            self::PURCHASE_ORDER => 'Purchase Order (PO)',
            self::JOB_ORDER => 'Job Order',
            self::CONTRACT_SB_RESOLUTION => 'Sangguniang Bayan Resolution (Contract Approval)',
            self::CONTRACT_CAF => 'Certificate of Availability of Funds (Contract)',
            self::OBLIGATION_REQUEST => 'Obligation Request/Allotment',
            self::BUSINESS_DOCUMENTS => "Contractor's/Supplier's Business Documents",
            self::INSURANCE_POLICIES => 'Insurance Policies',
            self::CONTRACTORS_ALL_RISK => "Contractor's All Risk (CAR) Insurance",
            self::WARRANTY_SECURITY => 'Warranty Security',
            self::CONTRACT_RECEIPT => 'Receipt of Contract Documents by Contractor',

            // Stage 12: Notice to Proceed
            self::NOTICE_TO_PROCEED => 'Notice to Proceed (NTP)',
            self::NTP_ACKNOWLEDGMENT => 'Acknowledgment of NTP Receipt',
            self::DELIVERY_SCHEDULE => 'Construction/Delivery Schedule',
            self::PERSONNEL_LIST => 'List of Key Personnel',
            self::EQUIPMENT_LIST => 'Equipment List',
            self::MOBILIZATION_REPORT => 'Site Mobilization Report',
            self::PRE_CONSTRUCTION_MINUTES => 'Pre-Construction Meeting Minutes',
            self::BARANGAY_ENDORSEMENT => 'Barangay Endorsement/Clearance',
            self::CONSTRUCTION_PERMIT => 'Building/Construction Permit',
            self::SAFETY_PLAN => 'Health and Safety Plan',

            // Stage 13: Monitoring
            self::PROGRESS_REPORTS => 'Progress Reports',
            self::MONITORING_REPORTS => 'Monitoring Reports',
            self::SITE_INSPECTION_REPORTS => 'Site Inspection Reports',
            self::DELIVERY_RECEIPTS => 'Delivery Receipts',
            self::INSPECTION_ACCEPTANCE_REPORT => 'Inspection and Acceptance Report (IAR)',
            self::TIME_EXTENSION_REQUEST => 'Time Extension Request',
            self::VARIATION_ORDER => 'Variation Order',
            self::PAYMENT_REQUESTS => 'Payment Requests/Progress Billings',
            self::DISBURSEMENT_VOUCHERS => 'Disbursement Vouchers',
            self::COMMUNICATION_LOGS => 'Communication Logs',
            self::WORK_PROGRESS_PHOTOS => 'Photos/Videos of Work in Progress',
            self::MATERIALS_TESTING => 'Materials Testing Results',
            self::AS_BUILT_DOCUMENTATION => 'As-Built/As-Delivered Documentation',

            // Stage 14: Completion
            self::CERTIFICATE_OF_COMPLETION => 'Certificate of Completion',
            self::CERTIFICATE_FINAL_ACCEPTANCE => 'Certificate of Final Acceptance',
            self::FINAL_INSPECTION_REPORT => 'Final Inspection Report',
            self::FINAL_IAR => 'Final Inspection and Acceptance Report (IAR)',
            self::AS_BUILT_PLANS => 'As-Built Plans/Drawings',
            self::OPERATIONS_MAINTENANCE_MANUALS => 'Operations and Maintenance Manuals',
            self::WARRANTY_DOCUMENTS => 'Warranty Documents',
            self::TRAINING_CERTIFICATES => 'Training Completion Certificates',
            self::FINAL_PROGRESS_REPORT => 'Final Progress Report',
            self::FINAL_PAYMENT_REQUEST => 'Final Payment Request',
            self::FINAL_BILLING_STATEMENT => 'Final Billing Statement',
            self::CLEARANCE_WAIVER => 'Clearance/Waiver',
            self::LIQUIDATED_DAMAGES_ASSESSMENT => 'Liquidated Damages Assessment',
            self::FINAL_DISBURSEMENT_VOUCHER => 'Final Disbursement Voucher',
            self::TURNOVER_DOCUMENTS => 'Turnover Documents',
            self::PROJECT_COMPLETION_REPORT => 'Project Completion Report',
            self::PROPERTY_ACKNOWLEDGMENT_RECEIPT => 'Property Acknowledgment Receipt (PAR)',
            self::ACCEPTANCE_TURNOVER_CERTIFICATE => 'Certificate of Acceptance and Turnover',
            self::UPDATED_INVENTORY_RECORDS => 'Updated Inventory Records',
            self::PROCUREMENT_DOCUMENTATION_PACKAGE => 'Procurement Process Documentation Package',
            self::PERFORMANCE_EVALUATION => 'Supplier/Contractor Performance Evaluation',

            // Stage 15: Completed - Post-Implementation
            self::POST_IMPLEMENTATION_REVIEW => 'Post-Implementation Review Report',
            self::COA_AUDIT_DOCUMENTATION => 'COA Audit Documentation',
            self::WARRANTY_CLAIM_RECORDS => 'Warranty Claim Records',
            self::ASSET_MANAGEMENT_RECORDS => 'Updated Asset Management Records',

            // Alternative Procurement Methods (SVP, Direct Contracting, etc.)
            self::NOTICE_OF_REQUEST_FOR_QUOTATION => 'Notice of Request for Quotation',
            self::REQUEST_FOR_QUOTATION => 'Request for Quotation (RFQ)',
            self::PHILGEPS_BID_NOTICE_ABSTRACT => 'PhilGEPS Bid Notice Abstract',
            self::PRICE_QUOTATION => 'Price Quotation',
            self::SUPPLIER_CANVASS_FORM => 'Supplier Canvass Form',
            self::QUOTATION_COMPARISON_SHEET => 'Quotation Comparison Sheet',
            self::ABSTRACT_OF_QUOTATIONS => 'Abstract of Quotations',
            self::CERTIFICATE_OF_ACCEPTANCE_OF_QUOTATION => 'Certificate of Acceptance of Quotation in Canvass',
            self::PHILGEPS_AWARD_NOTICE_ABSTRACT => 'PhilGEPS Award Notice Abstract',
            self::LOWEST_QUOTATION_CERTIFICATION => 'Certification of Lowest Quotation',

            // Legacy/Compatibility
            self::PPMP_ENTRY => 'PPMP Entry',
            self::PRICE_SURVEY => 'Price Survey',
            self::BUDGET_ESTIMATE => 'Budget Estimate',
            self::MARKET_STUDY => 'Market Study',
            self::PROCUREMENT_INITIATION_DOCUMENT => 'Procurement Initiation Document (PDF)',
            self::BIDDING_DOCUMENT => 'Bidding Document',
            self::SCOPE_OF_WORK => 'Scope of Work',
            self::BID_EVALUATION_REPORT => 'Bid Evaluation Report',
            self::EVALUATION_SUMMARY => 'Evaluation Summary',
            self::ABSTRACT => 'Abstract',
            self::TWG_CERTIFICATION => 'TWG Certification',
            self::NOTICE_OF_POST_QUALIFICATION => 'Notice of Post-Qualification',
            self::BAC_RESOLUTION => 'BAC Resolution',
            self::PROGRESS_BILLING => 'Progress Billing',
            self::COMPLIANCE_REPORT => 'Compliance Report',
            self::PHILGEPS_CERTIFICATE => 'PhilGEPS Registration Certificate',
            self::MAYORS_PERMIT => "Mayor's / Business Permit",
            self::BIR_REGISTRATION => 'BIR Certificate of Registration',
            self::TAX_CLEARANCE => 'Tax Clearance Certificate',

            self::UNKNOWN => 'Unknown Document',
        };
    }

    /**
     * Get a short description of the document type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PPMP => 'Annual project procurement plan',
            self::APP => 'Consolidated annual investment plan',
            self::PURCHASE_REQUEST => 'Official purchase request form',
            self::TECHNICAL_SPECIFICATIONS => 'Detailed technical requirements',
            self::BUDGET_ESTIMATE => 'Approved budget allocation',
            self::MARKET_STUDY => 'Market research and price analysis',
            self::PROCUREMENT_INITIATION_DOCUMENT => 'Single PDF containing all procurement initiation documents (PPMP, APP, PR, Market Study, ABC Documentation)',
            self::PRE_PROCUREMENT_MINUTES => 'Minutes from pre-procurement conference',
            self::PRE_PROCUREMENT_ATTENDANCE => 'Attendance record for pre-procurement conference',
            self::SCOPE_OF_WORK => 'Detailed work scope and requirements',
            self::BILL_OF_QUANTITIES => 'Itemized quantities and specifications',
            self::ABSTRACT_OF_BIDS => 'Summary of all submitted bids',
            self::BID_EVALUATION_REPORT => 'Detailed bid evaluation and analysis',
            self::BIDDING_DOCUMENT => 'Official bidding documents',
            self::PRE_BID_MINUTES => 'Minutes from pre-bid conference',
            self::PRE_BID_ATTENDANCE => 'Attendance record for pre-bid conference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental or amended bidding information',
            self::BID_DOCUMENT => 'Submitted bid documents',
            self::EVALUATION_SUMMARY => 'Summary of bid evaluation',
            self::ABSTRACT => 'Abstract of bids',
            self::POST_QUALIFICATION_REPORT => 'Post-qualification assessment report',
            self::TWG_CERTIFICATION => 'Technical Working Group certification',
            self::NOTICE_OF_POST_QUALIFICATION => 'Notice of post-qualification results',
            self::BAC_RESOLUTION => 'Bids and Awards Committee resolution',
            self::NOTICE_OF_AWARD => 'Official notice of contract award',
            self::PERFORMANCE_BOND => 'Performance security bond per NGPA Section 68',
            self::PERFORMANCE_SECURING_DECLARATION => 'Performance Securing Declaration in lieu of bond per NGPA Section 68.2',
            self::PHILGEPS_PLATINUM_CERTIFICATE => 'Valid PhilGEPS Platinum Membership Certificate per NGPA Section 52.1',
            self::CONTRACT => 'Signed contract agreement',
            self::PURCHASE_ORDER => 'Official purchase order',
            self::NOTICE_TO_PROCEED => 'Authorization to begin work',
            self::PROGRESS_BILLING => 'Progress payment documentation',
            self::INSPECTION_ACCEPTANCE_REPORT => 'Delivery inspection and acceptance',
            self::COMPLIANCE_REPORT => 'Project compliance monitoring report',
            self::CERTIFICATE_OF_COMPLETION => 'Certificate of project completion',
            self::PHILGEPS_CERTIFICATE => 'PhilGEPS registration proof',
            self::MAYORS_PERMIT => 'Municipal business permit',
            self::BIR_REGISTRATION => 'Bureau of Internal Revenue registration',
            self::TAX_CLEARANCE => 'Tax compliance certificate',
            self::END_USER_REQUEST => 'Request letter from end-user office detailing specific requirements and justification',
            self::OFFICE_ENDORSEMENT => 'Endorsement letter from requesting office head approving the procurement request',
            self::BUDGET_ALLOCATION => 'Budget allocation document showing line item and appropriation for the procurement',
            self::PROJECT_PROPOSAL => 'Detailed project proposal document outlining objectives, scope, and expected outcomes',
            self::APPROVAL_DOCUMENTS => 'Department head approval, Sanggunian resolution for infrastructure, or higher authority approval for large amounts',
            self::UNKNOWN => 'Unknown or unspecified document type',
            default => $this->getDisplayName(), // Use display name as fallback for any missing cases
        };
    }

    /**
     * Check if document is required for procurement initiation stage
     */
    public function isRequiredForInitiation(): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::TECHNICAL_SPECIFICATIONS,
            self::BUDGET_ESTIMATE => true,
            default => false,
        };
    }

    /**
     * Check if document is required for specific stage
     */
    public function isRequiredForStage(StageEnums $stage): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::TECHNICAL_SPECIFICATIONS,
            self::BUDGET_ESTIMATE => $stage === StageEnums::PROCUREMENT_INITIATION,

            self::BIDDING_DOCUMENT,
            self::ABSTRACT_OF_BIDS,
            self::BID_EVALUATION_REPORT => in_array($stage, [
                StageEnums::BIDDING_PREPARATION,
                StageEnums::BIDDING_EVALUATION,
            ]),

            self::NOTICE_OF_AWARD,
            self::CONTRACT => $stage === StageEnums::CONTRACT_AWARD,

            self::INSPECTION_ACCEPTANCE_REPORT,
            self::CERTIFICATE_OF_COMPLETION => $stage === StageEnums::CONTRACT_COMPLETION,

            default => false,
        };
    }

    /**
     * Try to match a string value to a DocumentTypeEnums case
     * Handles various formats: snake_case, Title Case, kebab-case, etc.
     *
     * @param  string  $value  The value to match
     * @return self|null The matched enum case or null if no match found
     */
    public static function fromString(string $value): ?self
    {
        // Try direct match first
        $enum = self::tryFrom($value);
        if ($enum) {
            return $enum;
        }

        // Normalize the input value (convert to lowercase and replace spaces/hyphens with underscores)
        $normalized = strtolower(str_replace([' ', '-'], '_', $value));

        // Try matching normalized value
        $enum = self::tryFrom($normalized);
        if ($enum) {
            return $enum;
        }

        // Try matching by display name (case-insensitive)
        foreach (self::cases() as $case) {
            if (strtolower($case->getDisplayName()) === strtolower($value)) {
                return $case;
            }
        }

        // Try matching common variations and legacy values
        $mappings = [
            // Stage names that might be used as document types
            'procurement_initiation' => self::PROCUREMENT_INITIATION_DOCUMENT,

            // Common variations
            'minutes' => self::PRE_BID_MINUTES, // Default to most common
            'attendance' => self::PRE_BID_ATTENDANCE, // Default to most common
            'evaluation summary' => self::EVALUATION_SUMMARY,
            'abstract' => self::ABSTRACT,
            'post qualification report' => self::POST_QUALIFICATION_REPORT,
            'twg certification' => self::TWG_CERTIFICATION,
            'notice of post qualification' => self::NOTICE_OF_POST_QUALIFICATION,
            'bac resolution' => self::BAC_RESOLUTION,
            'notice of award' => self::NOTICE_OF_AWARD,
            'performance bond' => self::PERFORMANCE_BOND,
            'contract' => self::CONTRACT,
            'purchase order' => self::PURCHASE_ORDER,
            'notice to proceed' => self::NOTICE_TO_PROCEED,
            'compliance report' => self::COMPLIANCE_REPORT,
            'certificate of completion' => self::CERTIFICATE_OF_COMPLETION,
            'supplemental bid bulletin' => self::SUPPLEMENTAL_BID_BULLETIN,
            'bid document' => self::BID_DOCUMENT,
            'bidding document' => self::BIDDING_DOCUMENT,
        ];

        $lowerValue = strtolower($value);
        if (isset($mappings[$lowerValue])) {
            return $mappings[$lowerValue];
        }

        return null;
    }

    /**
     * Get all cases as an array of values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as an array of display names
     *
     * @return array<string, string> [value => display_name]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getDisplayName();
        }

        return $options;
    }

    /**
     * Check if document is mandatory for all procurements
     */
    public function isMandatory(): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::CERTIFICATE_OF_FUNDS,
            self::PPMP_ENTRY => true,
            default => false,
        };
    }

    /**
     * Check if document is mandatory for specific procurement category
     */
    public function isMandatoryForCategory(ProcurementCategoryEnums $category): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::CERTIFICATE_OF_FUNDS,
            self::PPMP_ENTRY => true,

            self::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategoryEnums::GOODS,
                ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS,
            ]),

            self::TERMS_OF_REFERENCE => $category === ProcurementCategoryEnums::CONSULTING_SERVICES,

            default => false,
        };
    }

    /**
     * Check if document is applicable for specific category
     */
    public function isApplicableForCategory(ProcurementCategoryEnums $category): bool
    {
        return match ($this) {
            self::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategoryEnums::GOODS,
                ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS,
            ]),

            self::TERMS_OF_REFERENCE => $category === ProcurementCategoryEnums::CONSULTING_SERVICES,

            // All other documents apply to all categories
            default => true,
        };
    }

    /**
     * Get all mandatory documents for a specific category
     *
     * @return array<self>
     */
    public static function getMandatoryForCategory(ProcurementCategoryEnums $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isMandatoryForCategory($category)
        );
    }

    /**
     * Get all applicable documents for a specific category
     *
     * @return array<self>
     */
    public static function getApplicableForCategory(ProcurementCategoryEnums $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isApplicableForCategory($category)
        );
    }

    /**
     * Get all mandatory document types (applies to all categories)
     *
     * @return array<self>
     */
    public static function getAllMandatory(): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isMandatory()
        );
    }

    /**
     * Get all optional document types
     *
     * @return array<self>
     */
    public static function getAllOptional(): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => ! $docType->isMandatory()
        );
    }

    /**
     * Get document type requirements summary
     */
    public function getRequirementSummary(): string
    {
        if ($this->isMandatory()) {
            return 'Required for all procurements per RA 9184';
        }

        $categories = [];
        foreach (ProcurementCategoryEnums::cases() as $category) {
            if ($this->isMandatoryForCategory($category)) {
                $categories[] = $category->getDisplayName();
            }
        }

        if (! empty($categories)) {
            return 'Required for: '.implode(', ', $categories);
        }

        return 'Optional supporting document';
    }

    /**
     * Get all document types for procurement initiation stage
     *
     * @return array<self>
     */
    public static function getInitiationDocuments(): array
    {
        return [
            self::PURCHASE_REQUEST,
            self::PPMP,
            self::APP,
            self::CERTIFICATE_OF_FUNDS,
            self::APPROVED_BUDGET_CONTRACT,
            self::TECHNICAL_SPECIFICATIONS,
            self::TERMS_OF_REFERENCE,
            self::MARKET_RESEARCH,
            self::SANGGUNIANG_BAYAN_RESOLUTION,
            self::ENVIRONMENTAL_COMPLIANCE_CERTIFICATE,
            self::PROGRAM_OF_WORK,
            self::END_USER_REQUEST,
            self::OFFICE_ENDORSEMENT,
            self::BUDGET_ALLOCATION,
            self::PROJECT_PROPOSAL,
            self::APPROVAL_DOCUMENTS,
        ];
    }
}

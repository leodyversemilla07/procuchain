declare namespace App.DataTransferObjects {
export type DocumentData = {
prNumber: string;
procurementTitle: string;
userAddress: string;
stage: string;
status: string;
documentType: string;
fileKey: string;
fileName: string;
fileSize: number;
mimeType: string;
hash: string;
dataTxid: string;
metadataTxid: string;
uploadedBy: string;
timestamp: string;
description: string | null;
stageMetadata: Array<any> | null;
};
export type ProcurementData = {
prNumber: string;
ppmpReference: string | null;
title: string;
description: string;
abcAmount: number;
fundingSource: string;
category: App.Enums.ProcurementCategoryEnums;
procurementMode: App.Enums.ProcurementModeEnums;
office: string;
endUser: string | null;
purpose: string;
deliveryLocation: string;
deliveryDate: string;
deliveryTermDays: number | null;
preparedBy: string | null;
bacResolutionNumber: string | null;
bacResolutionDate: string | null;
philgepsReference: string | null;
philgepsPostingDate: string | null;
approvedBy: string | null;
approvalDate: string | null;
status: string;
userId: string;
createdAt: string;
};
export type StatusData = {
prNumber: string;
procurementTitle: string;
stage: string;
currentStatus: string;
userAddress: string;
timestamp: any;
previousStatus: string | null;
metadata: Array<any> | null;
};
}
declare namespace App.Enums {
export type DocumentTypeEnums = 'ppmp' | 'app' | 'purchase_request' | 'technical_specifications' | 'terms_of_reference' | 'certificate_of_funds' | 'ppmp_entry' | 'market_research' | 'price_survey' | 'budget_estimate' | 'market_study' | 'procurement_initiation_document' | 'pre_procurement_minutes' | 'pre_procurement_attendance' | 'bidding_document' | 'scope_of_work' | 'bill_of_quantities' | 'abstract_of_bids' | 'bid_evaluation_report' | 'pre_bid_minutes' | 'pre_bid_attendance' | 'supplemental_bid_bulletin' | 'bid_document' | 'evaluation_summary' | 'abstract' | 'post_qualification_report' | 'twg_certification' | 'notice_of_post_qualification' | 'bac_resolution' | 'notice_of_award' | 'performance_bond' | 'contract' | 'purchase_order' | 'notice_to_proceed' | 'progress_billing' | 'inspection_acceptance_report' | 'certificate_of_completion' | 'compliance_report' | 'philgeps_certificate' | 'mayors_permit' | 'bir_registration' | 'tax_clearance' | 'unknown';
export type ProcurementCategoryEnums = 'goods' | 'infrastructure_projects' | 'consulting_services';
export type ProcurementInitiationDocumentTypeEnums = 'purchase_request' | 'technical_specifications' | 'terms_of_reference' | 'certificate_of_funds' | 'ppmp_entry' | 'market_research' | 'price_survey' | 'approval_documents' | 'end_user_request' | 'department_endorsement' | 'budget_allocation' | 'project_proposal';
export type ProcurementModeEnums = 'public_bidding' | 'limited_source_bidding' | 'direct_contracting' | 'repeat_order' | 'shopping' | 'negotiated_procurement' | 'small_value_procurement' | 'emergency';
export type StageEnums = 'procurement_initiation' | 'pre_procurement_conference' | 'bidding_documents' | 'pre_bid_conference' | 'supplemental_bid_bulletin' | 'bid_opening' | 'bid_evaluation' | 'post_qualification' | 'bac_resolution' | 'notice_of_award' | 'performance_bond_contract_and_po' | 'notice_to_proceed' | 'monitoring' | 'completion' | 'completed';
export type StatusEnums = 'procurement_submitted' | 'pre_procurement_conference_held' | 'pre_procurement_conference_skipped' | 'pre_procurement_conference_completed' | 'bidding_documents_published' | 'bidding_documents_submitted' | 'pre_bid_conference_held' | 'pre_bid_conference_skipped' | 'pre_bid_conference_completed' | 'supplemental_bulletins_ongoing' | 'supplemental_bulletins_completed' | 'bids_opened' | 'bids_evaluated' | 'post_qualification_verified' | 'post_qualification_failed' | 'resolution_recorded' | 'awarded' | 'performance_bond_contract_and_po_recorded' | 'ntp_recorded' | 'monitoring_completed' | 'completion_documents_uploaded' | 'completed' | 'stage_on_hold' | 'stage_cancelled' | 'stage_rejected' | 'stage_pending_correction';
export type StreamEnums = 'procurement.metadata' | 'procurement.documents' | 'procurement.status' | 'procurement.events' | 'procurement.corrections' | 'file.data' | 'file.metadata' | 'file.chunks';
export type UserRoleEnums = 'bac_secretariat' | 'bac_chairman' | 'hope' | 'admin';
}

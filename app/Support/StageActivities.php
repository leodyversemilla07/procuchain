<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StageEnums;

class StageActivities
{
    public static function getKeyActivities(StageEnums $stage): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => [
                'Preparation of Purchase Request (PR)',
                'Project Procurement Management Plan (PPMP)',
                'Annual Investment Plan (AIP) inclusion',
                'Budget allocation and certification',
                'Specification preparation and market study',
            ],
            StageEnums::PRE_PROCUREMENT_CONFERENCE => [
                'Review of procurement documents',
                'Validation of technical specifications',
                'Budget adequacy assessment',
                'Timeline and milestone setting',
                'Readiness confirmation by BAC',
            ],
            StageEnums::BIDDING_DOCUMENTS => [
                'Preparation of Invitation to Bid (ITB)',
                'Instructions to Bidders (IB)',
                'Bid Data Sheet (BDS)',
                'General and Special Conditions of Contract',
                'Technical specifications and drawings',
                'Bill of Quantities / Schedule of Requirements',
            ],
            StageEnums::REQUEST_FOR_QUOTATION => [
                'Preparation of RFQ documents',
                'Selection of suppliers to invite',
                'Distribution of RFQ to at least 3 suppliers',
                'Supplier inquiries and clarifications',
                'Setting of submission deadline',
            ],
            StageEnums::PRE_BID_CONFERENCE => [
                'Presentation of procurement requirements',
                'Response to bidder queries and clarifications',
                'Site visit arrangements (if applicable)',
                'Recording of all queries and responses',
                'Distribution of conference minutes',
            ],
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => [
                'Clarification of ambiguous specifications',
                'Correction of errors in bidding documents',
                'Response to written bidder queries',
                'Extension of bid submission deadline',
                'Amendment to terms and conditions',
            ],
            StageEnums::BID_OPENING => [
                'Verification of sealed bid envelopes',
                'Checking of bid security',
                'Opening of technical and financial proposals',
                'Recording of bid amounts',
                'Preliminary examination of bids',
            ],
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                'Collection of all quotation submissions',
                'Comparison of prices and terms',
                'Verification of supplier eligibility',
                'Determination of lowest calculated quotation',
                'Documentation of evaluation process',
            ],
            StageEnums::BID_EVALUATION => [
                'Detailed evaluation against specifications',
                'Verification of bid computation',
                'Assessment of technical compliance',
                'Financial capability evaluation',
                'Determination of Lowest Calculated Bid (LCB)',
            ],
            StageEnums::POST_QUALIFICATION => [
                'Verification of legal requirements',
                'Technical capability assessment',
                'Financial capability verification',
                'Site inspection (if applicable)',
                'Reference checking',
            ],
            StageEnums::BAC_RESOLUTION => [
                'Declaration of Lowest Calculated Responsive Bid',
                'Recommendation for award',
                'Documentation of BAC decision',
                'Approval by Head of Procuring Entity',
                'Filing of motion for reconsideration period',
            ],
            StageEnums::NOTICE_OF_AWARD => [
                'Preparation and signing of NOA',
                'Notification to winning bidder',
                'Posting on PhilGEPS and agency website',
                'Notice to unsuccessful bidders',
                'Setting deadline for contract signing',
            ],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => [
                'Submission of performance security',
                'Verification of bond authenticity',
                'Contract preparation and notarization',
                'Purchase order issuance',
                'PhilGEPS award notice posting',
            ],
            StageEnums::NOTICE_TO_PROCEED => [
                'Issuance of NTP to winning bidder',
                'Setting of contract effectivity date',
                'Coordination with end-user unit',
                'Mobilization preparation',
                'Timeline confirmation',
            ],
            StageEnums::MONITORING => [
                'Progress tracking and reporting',
                'Quality assurance inspections',
                'Delivery verification',
                'Issue resolution and documentation',
                'Milestone and payment processing',
            ],
            StageEnums::COMPLETION => [
                'Final inspection and acceptance',
                'Preparation of completion report',
                'Final payment processing',
                'Performance evaluation',
                'Contract closeout documentation',
            ],
            StageEnums::COMPLETED => [
                'All deliverables received and accepted',
                'All payments processed',
                'Contract formally closed',
                'Documentation archived',
                'Performance bond released (if applicable)',
            ],
        };
    }
}

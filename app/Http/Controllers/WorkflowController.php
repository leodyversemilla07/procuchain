<?php

namespace App\Http\Controllers;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    /**
     * Display the dynamic procurement workflow page.
     */
    public function __invoke(Request $request): Response
    {
        // Fetch active workflow configurations
        $workflowConfigs = ProcurementWorkflowConfig::active()->get();

        // Fetch active stage document configurations
        $documentConfigs = StageDocumentConfig::active()->get();

        // Structure the data for the frontend
        $workflows = $workflowConfigs->map(function ($config) use ($documentConfigs) {
            $mode = $config->procurement_mode;
            $stages = $config->getStagesAsEnums();

            // Map stages to their details and documents
            $stageDetails = collect($stages)->map(function ($stageEnum) use ($mode, $config, $documentConfigs) {
                // Find document config for this stage/mode
                $docConfig = $documentConfigs->first(function ($doc) use ($stageEnum, $mode) {
                    return $doc->stage === $stageEnum->value && $doc->procurement_mode === $mode;
                });

                // Determine phase based on stage enum
                $phase = $this->getPhaseForStage($stageEnum);

                return [
                    'id' => $stageEnum->value,
                    'name' => $stageEnum->getDisplayName(),
                    'phase' => $phase,
                    'description' => $this->getDescriptionForStage($stageEnum),
                    'optional' => $config->isStageOptional($stageEnum),
                    'repeatable' => $this->isStageRepeatable($stageEnum), // Logic for repeatable stages
                    'details' => $this->getKeyActivitiesForStage($stageEnum),
                    'documents' => $docConfig ? array_merge($docConfig->required_documents ?? [], $docConfig->optional_documents ?? []) : [],
                ];
            });

            return [
                'mode' => $mode,
                'name' => ProcurementModeEnums::tryFrom($mode)?->getDisplayName() ?? $mode,
                'stages' => $stageDetails,
            ];
        });

        return Inertia::render('workflow', [
            'workflows' => $workflows,
        ]);
    }

    /**
     * Get the phase for a given stage.
     */
    private function getPhaseForStage(StageEnums $stage): string
    {
        // This mapping logic should ideally align with your StageEnums or a shared helper
        // Assuming based on current hardcoded data:
        return match (true) {
            in_array($stage, [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StageEnums::BIDDING_DOCUMENTS,
                StageEnums::REQUEST_FOR_QUOTATION,
            ]) => 'pre_procurement',

            in_array($stage, [
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StageEnums::BID_OPENING,
                StageEnums::ABSTRACT_OF_QUOTATIONS,
                StageEnums::BID_EVALUATION,
                StageEnums::POST_QUALIFICATION,
                StageEnums::BAC_RESOLUTION,
            ]) => 'procurement',

            in_array($stage, [
                StageEnums::NOTICE_OF_AWARD,
                StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                StageEnums::NOTICE_TO_PROCEED,
                StageEnums::MONITORING,
                StageEnums::COMPLETION,
                StageEnums::COMPLETED,
            ]) => 'post_procurement',

            default => 'unknown',
        };
    }

    /**
     * Get description for a stage.
     */
    private function getDescriptionForStage(StageEnums $stage): string
    {
        // You might want to move these to the Enum class or a translation file
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => 'Initial stage where procurement requirements are defined and approved',
            StageEnums::PRE_PROCUREMENT_CONFERENCE => 'Optional conference to discuss procurement requirements with stakeholders',
            StageEnums::BIDDING_DOCUMENTS => 'Preparation and publication of official bidding documents',
            StageEnums::REQUEST_FOR_QUOTATION => 'Preparation and sending of RFQ to suppliers',
            StageEnums::PRE_BID_CONFERENCE => 'Conference to clarify bidding requirements and answer bidder questions',
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => 'Issuance of supplemental bulletins to modify or clarify bidding documents',
            StageEnums::BID_OPENING => 'Public opening and recording of submitted bids',
            StageEnums::ABSTRACT_OF_QUOTATIONS => 'Compilation and evaluation of received quotations',
            StageEnums::BID_EVALUATION => 'Technical and financial evaluation of submitted bids',
            StageEnums::POST_QUALIFICATION => "Verification of winning bidder's qualifications and capacity",
            StageEnums::BAC_RESOLUTION => 'Formal resolution by the Bids and Awards Committee',
            StageEnums::NOTICE_OF_AWARD => 'Official notification of contract award to winning bidder',
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => 'Submission of performance bond, contract signing, and purchase order issuance',
            StageEnums::NOTICE_TO_PROCEED => 'Authorization for contractor to begin work or delivery',
            StageEnums::MONITORING => 'Active monitoring of contract implementation',
            StageEnums::COMPLETION => 'Final stage of contract completion and acceptance',
            StageEnums::COMPLETED => 'Procurement process fully completed and closed',
            default => '',
        };
    }

    /**
     * Check if a stage is repeatable.
     */
    private function isStageRepeatable(StageEnums $stage): bool
    {
        return $stage === StageEnums::SUPPLEMENTAL_BID_BULLETIN;
    }

    /**
     * Get key activities for a stage.
     */
    private function getKeyActivitiesForStage(StageEnums $stage): array
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
            default => [],
        };
    }
}

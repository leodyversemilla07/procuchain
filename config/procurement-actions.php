<?php

declare(strict_types=1);

use App\Enums\StageEnums;
use App\Enums\StatusEnums;

return [
    // ===== Universal Actions (All Modes) =====
    // Procurement Initiation - universal first step
    [
        'condition' => [
            'stage' => StageEnums::PROCUREMENT_INITIATION,
            'status' => [
                StatusEnums::PROCUREMENT_INITIATED,
                StatusEnums::PROCUREMENT_SUBMITTED, // SVP mode entry status
            ],
        ],
        'type' => 'upload',
        'label' => 'Upload Procurement Initiation Documents',
        'icon' => 'upload',
        'variant' => 'blue',
        'href_template' => '/bac-secretariat/procurement-initiation/{pr_number}',
    ],

    // ===== Competitive Bidding (CB) Mode Flow =====
    // Pre-Procurement Conference Decision (after Procurement Initiation is complete)
    // For Competitive Bidding mode - shows dialog to decide whether to hold conference
    [
        'condition' => [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'status' => StatusEnums::PROCUREMENT_SUBMITTED,
        ],
        'type' => 'dialog',
        'label' => 'Record Pre-Procurement Conference Decision',
        'icon' => 'edit',
        'variant' => 'indigo',
        'action' => 'pre-procurement',
    ],

    // Pre-Procurement Conference - Upload documents after conference is held
    [
        'condition' => [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
        ],
        'type' => 'upload',
        'label' => 'Upload Pre-Procurement Conference Documents',
        'icon' => 'upload',
        'variant' => 'green',
        'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/pre_procurement_conference',
    ],

    // Pre-Procurement Conference Complete -> Proceed to Bidding Documents
    [
        'condition' => [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Bidding Documents',
        'icon' => 'arrow-right',
        'variant' => 'amber',
        'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/bidding_documents',
    ],

    // Bidding Documents
    [
        'condition' => [
            'stage' => StageEnums::BIDDING_DOCUMENTS,
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,  // Entry status after stage transition
        ],
        'type' => 'upload',
        'label' => 'Upload Bidding Documents',
        'icon' => 'upload',
        'variant' => 'amber',
        'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/bidding_documents',
    ],

    // Pre-Bid Conference
    [
        'condition' => [
            'stage' => StageEnums::PRE_BID_CONFERENCE,
            'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        ],
        'type' => 'dialog',
        'label' => 'Record Pre-Bid Conference Decision',
        'icon' => 'edit',
        'variant' => 'indigo',
        'action' => 'pre-bid',
    ],
    [
        'condition' => [
            'stage' => StageEnums::PRE_BID_CONFERENCE,
            'status' => StatusEnums::PRE_BID_CONFERENCE_HELD,
        ],
        'type' => 'upload',
        'label' => 'Upload Pre-Bid Conference Documents',
        'icon' => 'upload',
        'variant' => 'indigo',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/pre_bid_conference',
    ],

    // Supplemental Bid Bulletin
    [
        'condition' => [
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'status' => [
                StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
                StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
            ],
        ],
        'type' => 'dialog',
        'label' => 'Record Supplemental Bid Bulletin Decision',
        'icon' => 'edit',
        'variant' => 'indigo',
        'action' => 'supplemental-bid-bulletin',
    ],
    [
        'condition' => [
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
        ],
        'type' => 'upload',
        'label' => 'Upload Supplemental Bid Bulletin Documents',
        'icon' => 'upload',
        'variant' => 'blue',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin',
    ],

    // Issue Another Bulletin - Per NGPA IRR, multiple bulletins can be issued
    [
        'condition' => [
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
        ],
        'type' => 'repeat',
        'label' => 'Issue Another Bulletin',
        'icon' => 'refresh',
        'variant' => 'outline',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin/repeat',
        'is_repeatable' => true,
    ],

    // Bid Opening
    // Option to issue another bulletin before proceeding with Bid Opening
    [
        'condition' => [
            'stage' => StageEnums::BID_OPENING,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
        ],
        'type' => 'repeat',
        'label' => 'Issue Another Bulletin (Before Bid Opening)',
        'icon' => 'refresh',
        'variant' => 'outline',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin/repeat',
        'is_repeatable' => true,
    ],
    [
        'condition' => [
            'stage' => StageEnums::BID_OPENING,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
        ],
        'type' => 'upload',
        'label' => 'Upload Bid Opening Documents',
        'icon' => 'upload',
        'variant' => 'blue',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bid_opening',
    ],

    // Bid Opening Complete -> Proceed to Bid Evaluation
    [
        'condition' => [
            'stage' => StageEnums::BID_OPENING,
            'status' => StatusEnums::BIDS_OPENED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Bid Evaluation',
        'icon' => 'arrow-right',
        'variant' => 'indigo',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bid_evaluation',
    ],

    // Bid Evaluation
    [
        'condition' => [
            'stage' => StageEnums::BID_EVALUATION,
            'status' => StatusEnums::BIDS_OPENED,
        ],
        'type' => 'upload',
        'label' => 'Upload Bid Evaluation Documents',
        'icon' => 'chart',
        'variant' => 'indigo',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bid_evaluation',
    ],

    // Bid Evaluation Complete -> Proceed to Post-Qualification
    [
        'condition' => [
            'stage' => StageEnums::BID_EVALUATION,
            'status' => StatusEnums::BIDS_EVALUATED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Post-Qualification',
        'icon' => 'arrow-right',
        'variant' => 'green',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/post_qualification',
    ],

    // Post-Qualification
    [
        'condition' => [
            'stage' => StageEnums::POST_QUALIFICATION,
            'status' => StatusEnums::BIDS_EVALUATED,
        ],
        'type' => 'upload',
        'label' => 'Upload Post-Qualification Report',
        'icon' => 'upload',
        'variant' => 'green',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/post_qualification',
    ],

    // Post-Qualification Complete -> Proceed to BAC Resolution
    [
        'condition' => [
            'stage' => StageEnums::POST_QUALIFICATION,
            'status' => StatusEnums::POST_QUALIFICATION_VERIFIED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to BAC Resolution',
        'icon' => 'arrow-right',
        'variant' => 'purple',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bac_resolution',
    ],

    // BAC Resolution (supports both Competitive Bidding and SVP modes)
    [
        'condition' => [
            'stage' => StageEnums::BAC_RESOLUTION,
            'status' => [
                StatusEnums::POST_QUALIFICATION_VERIFIED,  // Competitive Bidding
                StatusEnums::ABSTRACT_PREPARED,            // SVP and alternative modes
            ],
        ],
        'type' => 'upload',
        'label' => 'Upload BAC Resolution Documents',
        'icon' => 'upload',
        'variant' => 'purple',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bac_resolution',
    ],

    // BAC Resolution Complete -> Proceed to Notice of Award
    // This action triggers auto-stage transition when user navigates to NOA page
    [
        'condition' => [
            'stage' => StageEnums::BAC_RESOLUTION,
            'status' => StatusEnums::RESOLUTION_RECORDED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Notice of Award',
        'icon' => 'arrow-right',
        'variant' => 'amber',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_of_award',
    ],

    // Notice of Award
    // Supports multiple entry statuses for different procurement modes:
    // - RESOLUTION_RECORDED: Standard flow after BAC Resolution
    // - PROCUREMENT_SUBMITTED: Direct Contracting (DC) may skip intermediate stages
    [
        'condition' => [
            'stage' => StageEnums::NOTICE_OF_AWARD,
            'status' => [
                StatusEnums::RESOLUTION_RECORDED,
                StatusEnums::PROCUREMENT_SUBMITTED,  // DC mode edge case
            ],
        ],
        'type' => 'upload',
        'label' => 'Upload Notice of Award',
        'icon' => 'upload',
        'variant' => 'amber',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_of_award',
    ],

    // Notice of Award Complete -> Proceed to Performance Bond
    [
        'condition' => [
            'stage' => StageEnums::NOTICE_OF_AWARD,
            'status' => StatusEnums::AWARDED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Performance Bond & Contract',
        'icon' => 'arrow-right',
        'variant' => 'cyan',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/performance_bond_contract_and_po',
    ],

    // Performance Bond, Contract, and PO
    [
        'condition' => [
            'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            'status' => StatusEnums::AWARDED,
        ],
        'type' => 'upload',
        'label' => 'Upload Performance Bond, Contract, and PO',
        'icon' => 'upload',
        'variant' => 'cyan',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/performance_bond_contract_and_po',
    ],

    // Performance Bond Complete -> Proceed to NTP
    [
        'condition' => [
            'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Notice to Proceed',
        'icon' => 'arrow-right',
        'variant' => 'green',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_to_proceed',
    ],

    // Notice to Proceed
    [
        'condition' => [
            'stage' => StageEnums::NOTICE_TO_PROCEED,
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        ],
        'type' => 'upload',
        'label' => 'Upload Notice to Proceed',
        'icon' => 'upload',
        'variant' => 'green',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_to_proceed',
    ],

    // NTP Complete -> Proceed to Monitoring
    [
        'condition' => [
            'stage' => StageEnums::NOTICE_TO_PROCEED,
            'status' => StatusEnums::NTP_RECORDED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Monitoring',
        'icon' => 'arrow-right',
        'variant' => 'teal',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/monitoring',
    ],

    // Monitoring
    [
        'condition' => [
            'stage' => StageEnums::MONITORING,
            'status' => StatusEnums::NTP_RECORDED,
        ],
        'type' => 'upload',
        'label' => 'Upload Monitoring Documents',
        'icon' => 'upload',
        'variant' => 'teal',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/monitoring',
    ],

    // Monitoring Complete -> Proceed to Completion
    [
        'condition' => [
            'stage' => StageEnums::MONITORING,
            'status' => StatusEnums::MONITORING_COMPLETED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Completion',
        'icon' => 'arrow-right',
        'variant' => 'emerald',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/completion',
    ],

    // Completion - Universal stage (all modes)
    // All 11 procurement modes include MONITORING → COMPLETION in post-procurement phase
    [
        'condition' => [
            'stage' => StageEnums::COMPLETION,
            'status' => StatusEnums::MONITORING_COMPLETED,  // Entry status from previous stage (MONITORING always precedes COMPLETION)
        ],
        'type' => 'upload',
        'label' => 'Upload Certificate of Completion',
        'icon' => 'upload',
        'variant' => 'emerald',
        'href_template' => '/bac-secretariat/post-procurement/{pr_number}/completion',
    ],

    // ===== SVP/Alternative Mode Actions =====
    // Request for Quotation - Initial upload action
    [
        'condition' => [
            'stage' => StageEnums::REQUEST_FOR_QUOTATION,
            'status' => StatusEnums::PROCUREMENT_SUBMITTED, // Entry status when transitioning to RFQ stage
        ],
        'type' => 'upload',
        'label' => 'Upload Request for Quotation',
        'icon' => 'upload',
        'variant' => 'blue',
        'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/request_for_quotation',
    ],

    // RFQ Complete -> Proceed to Abstract of Quotations
    [
        'condition' => [
            'stage' => StageEnums::REQUEST_FOR_QUOTATION,
            'status' => StatusEnums::QUOTATIONS_RECEIVED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to Abstract of Quotations',
        'icon' => 'arrow-right',
        'variant' => 'indigo',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/abstract_of_quotations',
    ],

    // Abstract of Quotations
    [
        'condition' => [
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS,
            'status' => StatusEnums::QUOTATIONS_RECEIVED,
        ],
        'type' => 'upload',
        'label' => 'Upload Abstract of Quotations',
        'icon' => 'upload',
        'variant' => 'indigo',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/abstract_of_quotations',
    ],

    // Abstract of Quotations Complete -> Proceed to BAC Resolution
    [
        'condition' => [
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS,
            'status' => StatusEnums::ABSTRACT_PREPARED,
        ],
        'type' => 'proceed',
        'label' => 'Proceed to BAC Resolution',
        'icon' => 'arrow-right',
        'variant' => 'purple',
        'href_template' => '/bac-secretariat/procurement/{pr_number}/bac_resolution',
    ],
];

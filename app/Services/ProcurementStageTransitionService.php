<?php

namespace App\Services;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;

class ProcurementStageTransitionService
{
    public function getPriorityAction(string $currentStage, string $currentStatus, string $id, string $title): ?array
    {
        $stageAction = $this->determineStageAction($currentStage, $currentStatus);

        if ($stageAction === null) {
            return null;
        }

        return [
            'id' => $id,
            'title' => $title,
            'action' => $stageAction['action'],
            'route' => sprintf($stageAction['routeTemplate'], $id),
        ];
    }

    private function determineStageAction(string $currentStage, string $currentStatus): ?array
    {
        $stageActions = $this->getStageActionsMap();

        foreach ($stageActions as $action) {
            if (! $this->isMatchingStage($action, $currentStage)) {
                continue;
            }

            if ($this->isMatchingStatus($action, $currentStatus)) {
                return $action;
            }
        }

        return null;
    }

    private function isMatchingStage(array $action, string $currentStage): bool
    {
        return $action['stage'] === $currentStage;
    }

    private function isMatchingStatus(array $action, string $currentStatus): bool
    {
        if (isset($action['statusCheck'])) {
            return $action['statusCheck']($currentStatus);
        }

        if (isset($action['status'])) {
            if (is_array($action['status'])) {
                return in_array($currentStatus, $action['status'], true);
            }

            return $action['status'] === $currentStatus;
        }

        return false;
    }

    private function getStageActionsMap(): array
    {
        return [
            $this->getInitiationAction(),
            $this->getPreProcurementAction(),
            $this->getBiddingDocumentsAction(),
            $this->getPreBidConferenceAction(),
            $this->getSupplementalBidBulletinAction(),
            $this->getBidOpeningAction(),
            $this->getBidEvaluationAction(),
            $this->getPostQualificationAction(),
            $this->getBacResolutionAction(),
            $this->getNoticeOfAwardAction(),
            $this->getPerformanceBondAction(),
            $this->getNoticeToProceedAction(),
            $this->getMonitoringAction(),
            $this->getCompletionAction(),
        ];
    }

    private function getInitiationAction(): array
    {
        return [
            'stage' => StageEnums::PROCUREMENT_INITIATION->value,
            'status' => StatusEnums::PROCUREMENT_SUBMITTED->value,
            'action' => 'Continue Procurement Processing',
            'routeTemplate' => '/bac-secretariat/procurements-list',
        ];
    }

    private function getPreProcurementAction(): array
    {
        return [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD->value,
            'action' => 'Upload Pre-Procurement Conference Documents',
            'routeTemplate' => '/bac-secretariat/pre-procurement-conference-upload/%s',
        ];
    }

    private function getBiddingDocumentsAction(): array
    {
        return [
            'stage' => StageEnums::BIDDING_DOCUMENTS->value,
            'status' => [
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->value,
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED->value,
            ],
            'action' => 'Upload Bidding Documents',
            'routeTemplate' => '/bac-secretariat/bidding-documents-upload/%s',
        ];
    }

    private function getPreBidConferenceAction(): array
    {
        return [
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
            'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED->value,
            'action' => 'Upload Pre-Bid Conference Documents',
            'routeTemplate' => '/bac-secretariat/pre-bid-conference-upload/%s',
        ];
    }

    private function getSupplementalBidBulletinAction(): array
    {
        return [
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
            'status' => StatusEnums::PRE_BID_CONFERENCE_COMPLETED->value,
            'action' => 'Upload Supplemental Bid Bulletin Documents',
            'routeTemplate' => '/bac-secretariat/supplemental-bid-bulletin-upload/%s',
        ];
    }

    private function getBidOpeningAction(): array
    {
        return [
            'stage' => StageEnums::BID_OPENING->value,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED->value,
            'action' => 'Upload Bid Opening Documents',
            'routeTemplate' => '/bac-secretariat/bid-opening-upload/%s',
        ];
    }

    private function getBidEvaluationAction(): array
    {
        return [
            'stage' => StageEnums::BID_EVALUATION->value,
            'status' => StatusEnums::BIDS_OPENED->value,
            'action' => 'Upload Bid Evaluation Documents',
            'routeTemplate' => '/bac-secretariat/bid-evaluation-upload/%s',
        ];
    }

    private function getPostQualificationAction(): array
    {
        return [
            'stage' => StageEnums::POST_QUALIFICATION->value,
            'status' => StatusEnums::BIDS_EVALUATED->value,
            'action' => 'Upload Post-Qualification Documents',
            'routeTemplate' => '/bac-secretariat/post-qualification-upload/%s',
        ];
    }

    private function getBacResolutionAction(): array
    {
        return [
            'stage' => StageEnums::BAC_RESOLUTION->value,
            'status' => StatusEnums::POST_QUALIFICATION_VERIFIED->value,
            'action' => 'Record BAC Resolution Documents',
            'routeTemplate' => '/bac-secretariat/bac-resolution-upload/%s',
        ];
    }

    private function getNoticeOfAwardAction(): array
    {
        return [
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
            'status' => StatusEnums::RESOLUTION_RECORDED->value,
            'action' => 'Upload Notice of Award Documents',
            'routeTemplate' => '/bac-secretariat/noa-upload/%s',
        ];
    }

    private function getPerformanceBondAction(): array
    {
        return [
            'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->value,
            'status' => StatusEnums::AWARDED->value,
            'action' => 'Upload Performance Bond, Contract, and PO Documents',
            'routeTemplate' => '/bac-secretariat/performance-bond-contract-po-upload/%s',
        ];
    }

    private function getNoticeToProceedAction(): array
    {
        return [
            'stage' => StageEnums::NOTICE_TO_PROCEED->value,
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED->value,
            'action' => 'Upload Notice to Proceed Documents',
            'routeTemplate' => '/bac-secretariat/ntp-upload/%s',
        ];
    }

    private function getMonitoringAction(): array
    {
        return [
            'stage' => StageEnums::MONITORING->value,
            'status' => null,
            'action' => 'Mark Procurement as Complete',
            'routeTemplate' => '/bac-secretariat/procurements-list',
            'statusCheck' => fn ($status) => $status !== StatusEnums::COMPLETED->value,
        ];
    }

    private function getCompletionAction(): array
    {
        return [
            'stage' => StageEnums::COMPLETION->value,
            'status' => StatusEnums::MONITORING_COMPLETED->value,
            'action' => 'Upload Completion Documents',
            'routeTemplate' => '/bac-secretariat/completion-upload/%s',
        ];
    }
}

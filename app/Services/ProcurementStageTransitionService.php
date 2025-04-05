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
            if (!$this->isMatchingStage($action, $currentStage)) {
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
            $this->getBidOpeningAction(),
            $this->getBidEvaluationAction(),
            $this->getPostQualificationAction(),
            $this->getBacResolutionAction(),
            $this->getNoticeOfAwardAction(),
            $this->getPerformanceBondAction(),
            $this->getNoticeToProceedAction(),
            $this->getMonitoringAction(),
        ];
    }

    private function getInitiationAction(): array
    {
        return [
            'stage' => StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
            'status' => StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
            'action' => 'Continue Procurement Processing',
            'routeTemplate' => '/bac-secretariat/procurements-list',
        ];
    }

    private function getPreProcurementAction(): array
    {
        return [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD->getDisplayName(),
            'action' => 'Upload Pre-Procurement Conference Documents',
            'routeTemplate' => '/bac-secretariat/pre-procurement-conference-upload/%s',
        ];
    }

    private function getBiddingDocumentsAction(): array
    {
        return [
            'stage' => StageEnums::BIDDING_DOCUMENTS->getDisplayName(),
            'status' => [
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->getDisplayName(),
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED->getDisplayName(),
            ],
            'action' => 'Upload Bidding Documents',
            'routeTemplate' => '/bac-secretariat/bid-invitation-upload/%s',
        ];
    }

    private function getBidOpeningAction(): array
    {
        return [
            'stage' => StageEnums::BID_OPENING->getDisplayName(),
            'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED->getDisplayName(),
            'action' => 'Upload Bid Opening Documents',
            'routeTemplate' => '/bac-secretariat/bid-submission-upload/%s',
        ];
    }

    private function getBidEvaluationAction(): array
    {
        return [
            'stage' => StageEnums::BID_EVALUATION->getDisplayName(),
            'status' => StatusEnums::BIDS_OPENED->getDisplayName(),
            'action' => 'Upload Bid Evaluation Documents',
            'routeTemplate' => '/bac-secretariat/bid-evaluation-upload/%s',
        ];
    }

    private function getPostQualificationAction(): array
    {
        return [
            'stage' => StageEnums::POST_QUALIFICATION->getDisplayName(),
            'status' => StatusEnums::BIDS_EVALUATED->getDisplayName(),
            'action' => 'Upload Post-Qualification Documents',
            'routeTemplate' => '/bac-secretariat/post-qualification-upload/%s',
        ];
    }

    private function getBacResolutionAction(): array
    {
        return [
            'stage' => StageEnums::BAC_RESOLUTION->getDisplayName(),
            'status' => StatusEnums::POST_QUALIFICATION_VERIFIED->getDisplayName(),
            'action' => 'Record BAC Resolution Documents',
            'routeTemplate' => '/bac-secretariat/bac-resolution-upload/%s',
        ];
    }

    private function getNoticeOfAwardAction(): array
    {
        return [
            'stage' => StageEnums::NOTICE_OF_AWARD->getDisplayName(),
            'status' => StatusEnums::RESOLUTION_RECORDED->getDisplayName(),
            'action' => 'Upload Notice of Award Documents',
            'routeTemplate' => '/bac-secretariat/noa-upload/%s',
        ];
    }

    private function getPerformanceBondAction(): array
    {
        return [
            'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName(),
            'status' => StatusEnums::AWARDED->getDisplayName(),
            'action' => 'Upload Performance Bond, Contract, and PO Documents',
            'routeTemplate' => '/bac-secretariat/performance-bond-upload/%s',
        ];
    }

    private function getNoticeToProceedAction(): array
    {
        return [
            'stage' => StageEnums::NOTICE_TO_PROCEED->getDisplayName(),
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED->getDisplayName(),
            'action' => 'Upload Notice to Proceed Documents',
            'routeTemplate' => '/bac-secretariat/ntp-upload/%s',
        ];
    }

    private function getMonitoringAction(): array
    {
        return [
            'stage' => StageEnums::MONITORING->getDisplayName(),
            'status' => null,
            'action' => 'Mark Procurement as Complete',
            'routeTemplate' => '/bac-secretariat/procurements-list',
            'statusCheck' => fn($status) => $status !== StatusEnums::COMPLETED->getDisplayName(),
        ];
    }
}
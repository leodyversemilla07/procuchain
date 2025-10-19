<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProcurementPublishingService
{
    public function __construct(
        private DocumentUploadService $documentUploadService,
        private BlockchainOrchestratorService $blockchainOrchestrator,
        private BlockchainEventLoggerService $eventLogger,
        private StatusUpdaterService $statusUpdater,
        private NotificationService $notificationService
    ) {}

    /**
     * Publish documents for a procurement stage
     */
    public function publishDocuments(
        string $procurementId,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $status,
        array $files,
        array $metadata,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();
            $stageFolder = $stage->getStoragePathSegment();

            // Upload documents
            $metadataArray = $this->documentUploadService->uploadAndPrepare(
                $files,
                $metadata,
                $procurementId,
                $procurementTitle,
                $stageFolder
            );

            // Publish to blockchain
            $this->blockchainOrchestrator->publishDocuments(
                $procurementId,
                $procurementTitle,
                $stage->getDisplayName(),
                $status->getDisplayName(),
                $metadataArray,
                $userAddress
            );

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                $procurementId,
                $procurementTitle,
                $stage->getDisplayName(),
                $status->getDisplayName(),
                $timestamp,
                count($metadataArray),
                true
            );

            // Redirect to status page
            return redirect()->route('blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $stage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (Exception $e) {
            Log::error('Error publishing documents', [
                'stage' => $stage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish '.$stage->getDisplayName().' documents: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Publish documents with stage transition
     */
    public function publishWithTransition(
        string $procurementId,
        string $procurementTitle,
        StageEnums $currentStage,
        StageEnums $nextStage,
        StatusEnums $currentStatus,
        StatusEnums $nextStatus,
        array $files,
        array $metadata,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();
            $stageFolder = $currentStage->getStoragePathSegment();

            // Upload documents
            $metadataArray = $this->documentUploadService->uploadAndPrepare(
                $files,
                $metadata,
                $procurementId,
                $procurementTitle,
                $stageFolder
            );

            // Publish documents
            $this->blockchainOrchestrator->publishDocuments(
                $procurementId,
                $procurementTitle,
                $currentStage->getDisplayName(),
                $currentStatus->getDisplayName(),
                $metadataArray,
                $userAddress
            );

            // Handle stage transition
            $this->blockchainOrchestrator->handleStageTransition(
                $procurementId,
                $procurementTitle,
                $currentStatus->getDisplayName(),
                $nextStatus->getDisplayName(),
                $currentStage->getDisplayName(),
                $nextStage->getDisplayName(),
                $userAddress,
                'Proceeding to '.$nextStage->getDisplayName().' after completing '.$currentStage->getDisplayName()
            );

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                $procurementId,
                $procurementTitle,
                $currentStage->getDisplayName(),
                $currentStatus->getDisplayName(),
                $timestamp,
                'completed',
                true,
                $nextStage->getDisplayName()
            );

            return redirect()->route('blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $currentStage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (Exception $e) {
            Log::error('Error publishing documents with transition', [
                'stage' => $currentStage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload '.$currentStage->getDisplayName().' documents: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Handle stage transition without documents
     */
    public function handleTransitionOnly(
        string $procurementId,
        string $procurementTitle,
        StageEnums $currentStage,
        StageEnums $nextStage,
        StatusEnums $fromStatus,
        StatusEnums $toStatus,
        string $reason,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Handle stage transition
            $this->blockchainOrchestrator->handleStageTransition(
                $procurementId,
                $procurementTitle,
                $fromStatus->getDisplayName(),
                $toStatus->getDisplayName(),
                $currentStage->getDisplayName(),
                $nextStage->getDisplayName(),
                $userAddress,
                $reason
            );

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                $procurementId,
                $procurementTitle,
                $currentStage->getDisplayName(),
                $toStatus->getDisplayName(),
                $timestamp,
                $reason,
                true,
                $nextStage->getDisplayName()
            );

            return redirect()->route('blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $currentStage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (Exception $e) {
            Log::error('Error handling stage transition', [
                'stage' => $currentStage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to process '.$currentStage->getDisplayName().' decision: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Update status without transition
     */
    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        StageEnums $stage,
        StatusEnums $status,
        string $details,
        ?string $redirectStageName = null
    ): RedirectResponse {
        try {
            $userAddress = Auth::user()->blockchain_address;
            $timestamp = now()->toIso8601String();

            // Update status
            $this->statusUpdater->updateStatus(
                $procurementId,
                $procurementTitle,
                $status->getDisplayName(),
                $stage->getDisplayName(),
                $userAddress,
                $timestamp
            );

            // Log event
            $this->eventLogger->logEvent(
                $procurementId,
                $procurementTitle,
                $stage->getDisplayName(),
                $details,
                'info',
                'status_update',
                0,
                $userAddress,
                $timestamp
            );

            // Send notifications
            $this->notificationService->notifyStageUpdate(
                $procurementId,
                $procurementTitle,
                $stage->getDisplayName(),
                $status->getDisplayName(),
                $timestamp,
                $details,
                true
            );

            return redirect()->route('blockchain.publishing-status', [
                'id' => $procurementId,
                'stage' => $redirectStageName ?? $stage->getDisplayName(),
                'return_url' => route('bac-secretariat.procurements.show', $procurementId),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating status', [
                'stage' => $stage->getDisplayName(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to process '.$stage->getDisplayName().' decision: '.$e->getMessage(),
            ]);
        }
    }
}

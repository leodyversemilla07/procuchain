<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\DataTransferObjects\ProcurementData;
use App\Enums\StageEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\EventPublisher;
use Carbon\Carbon;
use Exception;

class ProcurementUpdateHandler
{
    public function __construct(
        private readonly EventPublisher $eventPublisher,
        private readonly ProcurementRepository $procurementRepository,
        private readonly DecisionPublisher $decisionPublisher,
    ) {}

    public function executeDeliveryDetails(array $data): array
    {
        $prNumber = $data['pr_number'];
        $userAddress = $data['user_address'];
        $deliveryLocation = $data['delivery_location'];
        $deliveryDate = $data['delivery_date'];
        $deliveryTermDays = (int) $data['delivery_term_days'];

        $procurement = $this->procurementRepository->findByProcurement($prNumber);
        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        $updatedProcurement = new ProcurementData(
            prNumber: $procurement->prNumber,
            appReference: $procurement->appReference,
            title: $procurement->title,
            description: $procurement->description,
            abcAmount: $procurement->abcAmount,
            fundingSource: $procurement->fundingSource,
            category: $procurement->category,
            procurementMode: $procurement->procurementMode,
            office: $procurement->office,
            endUser: $procurement->endUser,
            deliveryLocation: $deliveryLocation,
            deliveryDate: Carbon::parse($deliveryDate),
            deliveryTermDays: $deliveryTermDays,
            preparedBy: $procurement->preparedBy,
            bacResolutionNumber: $procurement->bacResolutionNumber,
            bacResolutionDate: $procurement->bacResolutionDate,
            philgepsReference: $procurement->philgepsReference,
            philgepsPostingDate: $procurement->philgepsPostingDate,
            approvedBy: $procurement->approvedBy,
            approvalDate: $procurement->approvalDate,
            status: $procurement->status,
            userId: $procurement->userId,
            createdAt: $procurement->createdAt,
        );

        $this->procurementRepository->update($updatedProcurement);

        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: StageEnums::NOTICE_TO_PROCEED->value,
            eventType: 'delivery_details_updated',
            category: 'procurement',
            severity: 'info',
            details: sprintf(
                'Delivery details updated: Location: %s, Date: %s, Term: %d days',
                $deliveryLocation,
                $deliveryDate,
                $deliveryTermDays,
            ),
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'delivery_location' => $deliveryLocation,
                'delivery_date' => $deliveryDate,
                'delivery_term_days' => $deliveryTermDays,
            ],
        );

        return ['event_txid' => $eventResult['event_txid'] ?? null];
    }

    public function executeDecision(array $data): array
    {
        $prNumber = $data['pr_number'];
        $userAddress = $data['user_address'];
        $procurement = $this->procurementRepository->findByProcurement($prNumber);

        return $this->decisionPublisher->publishDecision(
            decisionType: $data['decision_type'],
            prNumber: $prNumber,
            procurementTitle: $data['procurement_title'],
            wasHeld: (bool) $data['was_held'],
            userAddress: $userAddress,
            procurement: $procurement,
        );
    }
}

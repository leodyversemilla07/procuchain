<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Enums\StageEnums;
use App\Models\Procurement;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\EventPublisher;
use Carbon\Carbon;
use Exception;

class ProcurementUpdateHandler
{
    public function __construct(
        private readonly EventPublisher $eventPublisher,
        private readonly DecisionPublisher $decisionPublisher,
    ) {}

    public function executeDeliveryDetails(array $data): array
    {
        $prNumber = $data['pr_number'];
        $userAddress = $data['user_address'];
        $deliveryLocation = $data['delivery_location'];
        $deliveryDate = $data['delivery_date'];
        $deliveryTermDays = (int) $data['delivery_term_days'];

        $procurement = Procurement::where('pr_number', $prNumber)->first();
        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        Procurement::where('pr_number', $prNumber)->update([
            'delivery_location' => $deliveryLocation,
            'delivery_date' => Carbon::parse($deliveryDate),
            'delivery_term_days' => $deliveryTermDays,
        ]);

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
        $procurement = Procurement::where('pr_number', $prNumber)->first();

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

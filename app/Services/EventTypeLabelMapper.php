<?php

namespace App\Services;

class EventTypeLabelMapper
{
    private array $labelMap = [
        'document_upload' => 'Uploaded Documents',
        'phase_transition' => 'Phase Transition',
        'publication' => 'Published Documents',
        'procurement completed' => 'Completed Procurement',
    ];

    public function getLabel(string $eventType, string $details = ''): string 
    {
        $eventType = strtolower($eventType);

        if (isset($this->labelMap[$eventType])) {
            return $this->labelMap[$eventType];
        }

        if ($eventType === 'decision' && str_contains(strtolower($details), 'pre-procurement')) {
            return 'Pre-Procurement Decision';
        } elseif ($eventType === 'decision') {
            return 'Decision Made';
        }

        // Format unknown event types
        return ucwords(str_replace('_', ' ', $eventType));
    }
}
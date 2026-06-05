<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Models\Procurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Projects raw blockchain stream payloads into the normalized DB mirror shape.
 *
 * Blockchain JSON is the source-of-truth contract. The database stores a
 * query-friendly projection, so verification must compare DB rows against this
 * projected shape instead of raw chain keys.
 */
class BlockchainPayloadProjector
{
    /**
     * @return array<string, mixed>
     */
    public function projectForTable(array $chainData, string $tableName, ?Model $record = null): array
    {
        $data = $chainData;

        match ($tableName) {
            'procurements' => $this->projectProcurement($data),
            'procurement_stages' => $this->projectStage($data, $record),
            'procurement_documents' => $this->projectDocument($data, $record),
            'procurement_events' => $this->projectEvent($data, $record),
            'procurement_corrections' => $this->projectCorrection($data, $record),
            'procurement_archives' => $this->projectArchive($data, $record),
            'procurement_metadata_corrections' => $this->projectMetadataCorrection($data, $record),
            'files' => $this->projectFile($data),
            default => null,
        };

        $this->normaliseDates($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectProcurement(array &$data): void
    {
        $data['fund_source'] ??= $data['funding_source'] ?? null;
        $data['current_status'] ??= $data['status'] ?? null;
        $data['initiated_at'] ??= $data['created_at'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectStage(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['status'] ??= $data['current_status'] ?? null;
        $data['entered_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectDocument(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['filename'] ??= $data['file_name'] ?? null;
        $data['uploaded_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectEvent(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['occurred_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectCorrection(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['corrected_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectArchive(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['archived_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectMetadataCorrection(array &$data, ?Model $record): void
    {
        $this->projectProcurementForeignKey($data, $record);
        $data['corrected_at'] ??= $data['timestamp'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectFile(array &$data): void
    {
        // file.metadata already publishes keys that mostly match the files table.
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function projectProcurementForeignKey(array &$data, ?Model $record): void
    {
        if (array_key_exists('procurement_id', $data)) {
            return;
        }

        $prNumber = $data['pr_number'] ?? $record?->procurement?->pr_number ?? null;

        $data['procurement_id'] = is_string($prNumber) && $prNumber !== ''
            ? Procurement::where('pr_number', $prNumber)->value('id')
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normaliseDates(array &$data): void
    {
        foreach ($this->dateFields() as $dateField) {
            if (array_key_exists($dateField, $data)) {
                $data[$dateField] = $this->normaliseDateValue($data[$dateField]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function dateFields(): array
    {
        return [
            'initiated_at',
            'created_at',
            'delivery_date',
            'philgeps_posting_date',
            'bac_resolution_date',
            'approval_date',
            'entered_at',
            'completed_at',
            'uploaded_at',
            'occurred_at',
            'corrected_at',
            'archived_at',
            'stored_at',
            'timestamp',
            'original_delivery_date',
            'original_bac_resolution_date',
            'original_approval_date',
            'corrected_delivery_date',
            'corrected_bac_resolution_date',
            'corrected_approval_date',
        ];
    }

    private function normaliseDateValue(mixed $value): mixed
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }
}

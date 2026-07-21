<?php

declare(strict_types=1);

namespace App\Services\Integrity;

use App\Models\File;
use App\Models\Procurement;
use App\Models\ProcurementArchive;
use App\Models\ProcurementCorrection;
use App\Models\ProcurementDocument;
use App\Models\ProcurementEvent;
use App\Models\ProcurementMetadataCorrection;
use App\Models\ProcurementStage;
use App\Services\Concerns\HashesData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RecordHashService
{
    use HashesData;

    private const TABLE_FIELDS = [
        'procurements' => Procurement::class,
        'procurement_stages' => ProcurementStage::class,
        'procurement_documents' => ProcurementDocument::class,
        'procurement_events' => ProcurementEvent::class,
        'procurement_corrections' => ProcurementCorrection::class,
        'procurement_archives' => ProcurementArchive::class,
        'procurement_metadata_corrections' => ProcurementMetadataCorrection::class,
        'Files' => File::class,
    ];

    public function computeRecordHash(Model $record, string $tableName): string
    {
        return $this->computeHash($this->recordToArray($record, $tableName));
    }

    public function recordToArray(Model $record, string $tableName): array
    {
        $modelClass = self::TABLE_FIELDS[$tableName] ?? null;
        if (! $modelClass) {
            return [];
        }

        $fields = $modelClass::getHashableFields();
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->normaliseHashValue($record->{$field} ?? null);
        }

        return $data;
    }

    private function normaliseHashValue(mixed $value): mixed
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}

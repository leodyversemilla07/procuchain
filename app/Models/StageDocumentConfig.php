<?php

namespace App\Models;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stage Document Configuration Model
 *
 * Stores customizable document requirements for each stage/mode combination.
 * Allows admins to configure required and optional documents per stage.
 *
 * @property int $id
 * @property string $stage
 * @property string $procurement_mode
 * @property string $stage_display_name
 * @property array $required_documents
 * @property array|null $optional_documents
 * @property bool $is_active
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $updatedByUser
 */
class StageDocumentConfig extends Model
{
    protected $fillable = [
        'stage',
        'procurement_mode',
        'stage_display_name',
        'required_documents',
        'optional_documents',
        'is_active',
        'updated_by',
        'txid',
        'data_hash',
        'blockchain_synced_at',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'optional_documents' => 'array',
        'is_active' => 'boolean',
        'blockchain_synced_at' => 'datetime',
    ];

    /**
     * Get the user who last updated this configuration.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter by stage.
     */
    public function scopeForStage(Builder $query, string|StageEnums $stage): Builder
    {
        $stageValue = $stage instanceof StageEnums ? $stage->value : $stage;

        return $query->where('stage', $stageValue);
    }

    /**
     * Scope to filter by procurement mode.
     */
    public function scopeForMode(Builder $query, string|ProcurementModeEnums $mode): Builder
    {
        $modeValue = $mode instanceof ProcurementModeEnums ? $mode->value : $mode;

        return $query->where('procurement_mode', $modeValue);
    }

    /**
     * Scope to filter by active configurations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get required documents as DocumentTypeEnums array.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocumentsAsEnums(): array
    {
        if (empty($this->required_documents)) {
            return [];
        }

        return array_filter(
            array_map(
                fn ($doc) => DocumentTypeEnums::tryFrom($doc),
                $this->required_documents
            )
        );
    }

    /**
     * Get optional documents as DocumentTypeEnums array.
     *
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocumentsAsEnums(): array
    {
        if (empty($this->optional_documents)) {
            return [];
        }

        return array_filter(
            array_map(
                fn ($doc) => DocumentTypeEnums::tryFrom($doc),
                $this->optional_documents
            )
        );
    }

    /**
     * Get stage as enum.
     */
    public function getStageEnum(): ?StageEnums
    {
        return StageEnums::tryFrom($this->stage);
    }

    /**
     * Get procurement mode as enum.
     */
    public function getProcurementModeEnum(): ?ProcurementModeEnums
    {
        return ProcurementModeEnums::tryFrom($this->procurement_mode);
    }

    /**
     * Check if a document type is required.
     */
    public function isDocumentRequired(DocumentTypeEnums|string $docType): bool
    {
        $docValue = $docType instanceof DocumentTypeEnums ? $docType->value : $docType;

        return in_array($docValue, $this->required_documents ?? [], true);
    }

    /**
     * Check if a document type is optional.
     */
    public function isDocumentOptional(DocumentTypeEnums|string $docType): bool
    {
        $docValue = $docType instanceof DocumentTypeEnums ? $docType->value : $docType;

        return in_array($docValue, $this->optional_documents ?? [], true);
    }

    /**
     * Set required documents from DocumentTypeEnums array.
     *
     * @param  array<DocumentTypeEnums|string>  $documents
     */
    public function setRequiredDocumentsFromEnums(array $documents): void
    {
        $this->required_documents = array_map(
            fn ($doc) => $doc instanceof DocumentTypeEnums ? $doc->value : $doc,
            $documents
        );
    }

    /**
     * Set optional documents from DocumentTypeEnums array.
     *
     * @param  array<DocumentTypeEnums|string>  $documents
     */
    public function setOptionalDocumentsFromEnums(array $documents): void
    {
        $this->optional_documents = array_map(
            fn ($doc) => $doc instanceof DocumentTypeEnums ? $doc->value : $doc,
            $documents
        );
    }

    /**
     * Get document counts.
     */
    public function getDocumentCounts(): array
    {
        $requiredCount = count($this->required_documents ?? []);
        $optionalCount = count($this->optional_documents ?? []);

        return [
            'required_count' => $requiredCount,
            'optional_count' => $optionalCount,
            'total_count' => $requiredCount + $optionalCount,
        ];
    }
}

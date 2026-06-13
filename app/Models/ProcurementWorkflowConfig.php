<?php

namespace App\Models;

use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Procurement Workflow Configuration Model
 *
 * Stores customizable workflow stages for each procurement mode.
 * Allows admins to configure which stages appear in each mode's workflow.
 *
 * @property int $id
 * @property string $procurement_mode
 * @property string $display_name
 * @property string|null $description
 * @property array $stages
 * @property array|null $optional_stages
 * @property bool $is_active
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $updatedByUser
 */
class ProcurementWorkflowConfig extends Model
{
    protected $fillable = [
        'procurement_mode',
        'display_name',
        'description',
        'stages',
        'optional_stages',
        'is_active',
        'updated_by',
        'txid',
        'data_hash',
        'blockchain_synced_at',
    ];

    protected $casts = [
        'stages' => 'array',
        'optional_stages' => 'array',
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
     * Scope to filter by procurement mode.
     */
    public function scopeForMode(Builder $query, string|ProcurementMode $mode): Builder
    {
        $modeValue = $mode instanceof ProcurementMode ? $mode->value : $mode;

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
     * Get stages as StageEnums array.
     *
     * @return array<StageEnums>
     */
    public function getStagesAsEnums(): array
    {
        if (empty($this->stages)) {
            return [];
        }

        return array_filter(
            array_map(
                fn ($stage) => StageEnums::tryFrom($stage),
                $this->stages
            )
        );
    }

    /**
     * Get optional stages as StageEnums array.
     *
     * @return array<StageEnums>
     */
    public function getOptionalStagesAsEnums(): array
    {
        if (empty($this->optional_stages)) {
            return [];
        }

        return array_filter(
            array_map(
                fn ($stage) => StageEnums::tryFrom($stage),
                $this->optional_stages
            )
        );
    }

    /**
     * Get procurement mode as enum.
     */
    public function getProcurementModeEnum(): ?ProcurementMode
    {
        return ProcurementMode::tryFrom($this->procurement_mode);
    }

    /**
     * Check if a stage exists in this workflow.
     */
    public function hasStage(StageEnums|string $stage): bool
    {
        $stageValue = $stage instanceof StageEnums ? $stage->value : $stage;

        return in_array($stageValue, $this->stages ?? [], true);
    }

    /**
     * Check if a stage is optional in this workflow.
     */
    public function isStageOptional(StageEnums|string $stage): bool
    {
        $stageValue = $stage instanceof StageEnums ? $stage->value : $stage;

        return in_array($stageValue, $this->optional_stages ?? [], true);
    }

    /**
     * Set stages from StageEnums array.
     *
     * @param  array<StageEnums|string>  $stages
     */
    public function setStagesFromEnums(array $stages): void
    {
        $this->stages = array_map(
            fn ($stage) => $stage instanceof StageEnums ? $stage->value : $stage,
            $stages
        );
    }

    /**
     * Set optional stages from StageEnums array.
     *
     * @param  array<StageEnums|string>  $optionalStages
     */
    public function setOptionalStagesFromEnums(array $optionalStages): void
    {
        $this->optional_stages = array_map(
            fn ($stage) => $stage instanceof StageEnums ? $stage->value : $stage,
            $optionalStages
        );
    }
}

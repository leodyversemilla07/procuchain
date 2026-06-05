<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProcurementRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\Enums\StreamEnums;
use App\Models\Procurement;
use App\Models\ProcurementStage;
use App\Services\DashboardCacheKeys;
use App\Services\Manager;
use Illuminate\Support\Collection;

/**
 * Procurement Repository
 *
 * Reads from normalized DB tables.
 * Writes to blockchain via BlockchainWriteJob.
 */
class ProcurementRepository implements ProcurementRepositoryInterface
{
    public function __construct(
        private Manager $multichain
    ) {}

    public function create(ProcurementData $procurement): void
    {
        $this->multichain->publish(
            StreamEnums::METADATA->value,
            $procurement->prNumber,
            ['json' => $procurement->toBlockchainArray()]
        );

        DashboardCacheKeys::clearAllProcurementCaches();
    }

    public function findByProcurement(string $prNumber): ?ProcurementData
    {
        $record = Procurement::where('pr_number', $prNumber)->first();

        if (! $record) {
            return null;
        }

        return ProcurementData::fromBlockchainArray([
            'pr_number' => $record->pr_number,
            'app_reference' => $record->app_reference,
            'title' => $record->title,
            'description' => $record->description,
            'abc_amount' => $record->abc_amount,
            'funding_source' => $record->fund_source,
            'category' => $record->category,
            'procurement_mode' => $record->procurement_mode,
            'office' => $record->office,
            'end_user' => $record->end_user,
            'status' => $record->current_status ?? 'draft',
            'user_id' => $record->user_id,
            'user_address' => $record->user_address,
            'created_at' => $record->initiated_at?->toIso8601String() ?? $record->created_at?->toIso8601String(),
        ]);
    }

    public function all(): Collection
    {
        return Procurement::all();
    }

    public function update(ProcurementData $procurement): void
    {
        // Update in normalized table
        Procurement::where('pr_number', $procurement->prNumber)->update([
            'title' => $procurement->title,
            'description' => $procurement->description,
            'abc_amount' => $procurement->abcAmount,
            'category' => $procurement->category->value,
            'procurement_mode' => $procurement->procurementMode->value,
        ]);
    }

    public function getHistory(string $prNumber): Collection
    {
        // Return stages as history
        return ProcurementStage::whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderBy('entered_at')
            ->get();
    }

    public function exists(string $prNumber): bool
    {
        return Procurement::where('pr_number', $prNumber)->exists();
    }

    public function findManyByProcurement(array $prNumbers): array
    {
        return Procurement::whereIn('pr_number', $prNumbers)
            ->get()
            ->keyBy('pr_number')
            ->toArray();
    }

    public function procurementExists(string $prNumber): bool
    {
        return Procurement::where('pr_number', $prNumber)->exists();
    }
}

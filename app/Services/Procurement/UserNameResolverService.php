<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DataTransferObjects\StatusData;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Collection;

/**
 * Resolves blockchain addresses to user display names with in-memory caching.
 *
 * Extracted from ProcurementFetcherService to follow SRP.
 * Handles batch preloading for performance and single lookups with cache fallback.
 */
final class UserNameResolverService
{
    /**
     * @var array<string, string>
     */
    private array $userCache = [];

    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * Preload user names from StatusData DTOs for performance
     *
     * @param  Collection<int, StatusData>  $statusDtos
     */
    public function preloadFromStatusDtos(Collection $statusDtos): void
    {
        $addresses = $statusDtos->map(fn (StatusData $dto) => $dto->userAddress)
            ->unique()
            ->filter()
            ->toArray();

        $this->preloadAddresses($addresses);
    }

    /**
     * Preload user names from raw stream items
     */
    public function preloadFromRawItems(Collection $items): void
    {
        $addresses = $items->pluck('data.json.user_address')->unique()->filter()->all();
        if (empty($addresses)) {
            return;
        }

        $names = User::whereIn('blockchain_address', $addresses)
            ->pluck('name', 'blockchain_address')
            ->all();

        $this->userCache = array_merge($this->userCache, $names);
    }

    /**
     * Get username from blockchain address
     */
    public function resolve(string $address): string
    {
        return $this->userCache[$address] ?? $this->userService->getUserNameByAddress($address);
    }

    /**
     * Preload user names for a list of blockchain addresses
     *
     * @param  array<int, string>  $addresses
     */
    private function preloadAddresses(array $addresses): void
    {
        $this->userService->preloadUserNames($addresses);

        foreach ($addresses as $address) {
            $this->userCache[$address] = $this->userService->getUserNameByAddress($address);
        }
    }
}

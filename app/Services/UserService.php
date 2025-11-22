<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * User Service
 *
 * Centralized service for user-related operations including
 * blockchain address to user name resolution with caching.
 */
class UserService
{
    private array $userNameCache = [];

    /**
     * Get user name from blockchain address with caching
     *
     * @param  string  $address  Blockchain address
     * @return string User name or 'System' for unknown addresses
     */
    public function getUserNameByAddress(string $address): string
    {
        if (isset($this->userNameCache[$address])) {
            return $this->userNameCache[$address];
        }

        try {
            $name = User::where('blockchain_address', $address)->first()?->name ?? 'System';
        } catch (\Exception $e) {
            Log::warning("Failed to retrieve user name for address: {$address}", [
                'error' => $e->getMessage(),
            ]);
            $name = 'System';
        }

        return $this->userNameCache[$address] = $name;
    }

    /**
     * Preload user names from a collection of addresses
     *
     * @param  array  $addresses  Array of blockchain addresses
     */
    public function preloadUserNames(array $addresses): void
    {
        $uniqueAddresses = array_unique(array_filter($addresses));

        if (empty($uniqueAddresses)) {
            return;
        }

        try {
            $users = User::whereIn('blockchain_address', $uniqueAddresses)
                ->get(['blockchain_address', 'name'])
                ->keyBy('blockchain_address')
                ->map(fn ($user) => $user->name)
                ->toArray();

            $this->userNameCache = array_merge($this->userNameCache, $users);
        } catch (\Exception $e) {
            Log::warning('Failed to preload user names', [
                'error' => $e->getMessage(),
                'address_count' => count($uniqueAddresses),
            ]);
        }
    }

    /**
     * Clear the user name cache
     */
    public function clearCache(): void
    {
        $this->userNameCache = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cached_users' => count($this->userNameCache),
            'addresses' => array_keys($this->userNameCache),
        ];
    }
}

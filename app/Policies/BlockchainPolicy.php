<?php

namespace App\Policies;

use App\Models\User;

/**
 * Blockchain policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 * Gate::define('view-blockchain-explorer', [BlockchainPolicy::class, 'viewExplorer'])
 * Gate::define('view-blockchain-transactions', [BlockchainPolicy::class, 'viewTransactions'])
 * Gate::define('view-blockchain-network', [BlockchainPolicy::class, 'viewNetwork'])
 * Gate::define('reset-blockchain-circuit-breaker', [BlockchainPolicy::class, 'resetCircuitBreaker'])
 * Gate::define('view-shared-ledger', [BlockchainPolicy::class, 'viewSharedLedger'])
 *
 * Usage in controllers:
 * $this->authorize('view-blockchain-explorer');
 */
class BlockchainPolicy
{
    /**
     * Determine whether the user can view the blockchain explorer dashboard.
     */
    public function viewExplorer(User $user): bool
    {
        return $user->can('view blockchain transactions');
    }

    /**
     * Determine whether the user can view blockchain transaction details.
     */
    public function viewTransactions(User $user): bool
    {
        return $user->can('view blockchain transactions');
    }

    /**
     * Determine whether the user can view the node network topology.
     */
    public function viewNetwork(User $user): bool
    {
        return $user->can('view blockchain transactions');
    }

    /**
     * Determine whether the user can reset the blockchain circuit breaker.
     * Only admins should be able to reset circuit breakers.
     */
    public function resetCircuitBreaker(User $user): bool
    {
        return $user->can('publish to blockchain');
    }

    /**
     * Determine whether the user can view the shared ledger.
     * Available to all roles that can view blockchain transactions.
     */
    public function viewSharedLedger(User $user): bool
    {
        return $user->can('view blockchain transactions');
    }
}

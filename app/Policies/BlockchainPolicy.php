<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class BlockchainPolicy
{
    public function viewExplorer(User $user): bool
    {
        return $user->can(Permission::VIEW_BLOCKCHAIN_TRANSACTIONS->value);
    }

    public function viewTransactions(User $user): bool
    {
        return $user->can(Permission::VIEW_BLOCKCHAIN_TRANSACTIONS->value);
    }

    public function viewNetwork(User $user): bool
    {
        return $user->can(Permission::VIEW_BLOCKCHAIN_TRANSACTIONS->value);
    }

    public function resetCircuitBreaker(User $user): bool
    {
        return $user->can(Permission::PUBLISH_TO_BLOCKCHAIN->value);
    }

    public function viewSharedLedger(User $user): bool
    {
        return $user->can(Permission::VIEW_BLOCKCHAIN_TRANSACTIONS->value);
    }
}

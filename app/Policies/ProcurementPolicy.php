<?php

namespace App\Policies;

use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;

/**
 * Procurement policy.
 *
 * Abilities are registered in AppServiceProvider as named Gates:
 *   Gate::define('view-procurement',    [ProcurementPolicy::class, 'view'])
 *   Gate::define('archive-procurement', [ProcurementPolicy::class, 'archive'])
 *   Gate::define('restore-procurement', [ProcurementPolicy::class, 'restore'])
 *   Gate::define('correct-procurement', [ProcurementPolicy::class, 'correct'])
 *   Gate::define('approve-procurement', [ProcurementPolicy::class, 'approve'])
 *   Gate::define('publish-procurement', [ProcurementPolicy::class, 'publish'])
 *
 * Usage in controllers:
 *   $this->authorize('archive-procurement');
 */
class ProcurementPolicy
{
    public function __construct(
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementDataService $procurementDataService,
    ) {}

    /**
     * Determine whether the user can view procurement records.
     */
    public function view(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo('view procurement')) {
            return false;
        }

        if (! $user->isBacSecretariat() || $prNumber === null) {
            return true;
        }

        return $this->canBacSecretariatAccessProcurement($user, $prNumber);
    }

    /**
     * Determine whether the user can initiate / create a procurement.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create procurement');
    }

    /**
     * Determine whether the user can archive a completed procurement.
     * Restricted to BAC Secretariat and Admin only.
     */
    public function archive(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo('manage procurements')) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    /**
     * Determine whether the user can restore an archived procurement.
     * Restricted to BAC Secretariat and Admin only.
     */
    public function restore(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo('manage procurements')) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    /**
     * Determine whether the user can correct procurement metadata on the blockchain.
     */
    public function correct(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo('edit procurement')) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    /**
     * Determine whether the user can approve a procurement or stage transition.
     */
    public function approve(User $user): bool
    {
        return $user->hasPermissionTo('approve procurement');
    }

    /**
     * Determine whether the user can publish procurement data to the blockchain.
     */
    public function publish(User $user): bool
    {
        return $user->hasPermissionTo('publish to blockchain');
    }

    private function canAccessScopedProcurement(User $user, ?string $prNumber = null): bool
    {
        if (! $user->isBacSecretariat() || $prNumber === null) {
            return true;
        }

        return $this->canBacSecretariatAccessProcurement($user, $prNumber);
    }

    private function canBacSecretariatAccessProcurement(User $user, string $prNumber): bool
    {
        $procurement = $this->procurementRepository->findByProcurement($prNumber);

        if ($procurement !== null && $procurement->userId === (string) $user->id) {
            return true;
        }

        $statusItems = $this->procurementDataService->fetchStatusItems($prNumber);

        if ($procurement === null && $statusItems->isEmpty()) {
            return true;
        }

        if (empty($user->blockchain_address)) {
            return false;
        }

        return $statusItems
            ->contains(function ($statusItem) use ($user) {
                $userAddress = (string) data_get($statusItem, 'user_address', data_get($statusItem, 'userAddress', ''));

                return $userAddress === $user->blockchain_address;
            });
    }
}

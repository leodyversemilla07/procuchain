<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\ProcurementDataService;

class ProcurementPolicy
{
    public function __construct(
        private readonly ProcurementRepository $procurementRepository,
        private readonly ProcurementDataService $procurementDataService,
    ) {}

    public function view(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo(Permission::VIEW_PROCUREMENT->value)) {
            return false;
        }

        if (! $user->isBacSecretariat() || $prNumber === null) {
            return true;
        }

        return $this->canBacSecretariatAccessProcurement($user, $prNumber);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CREATE_PROCUREMENT->value);
    }

    public function archive(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo(Permission::MANAGE_PROCUREMENTS->value)) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    public function restore(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo(Permission::MANAGE_PROCUREMENTS->value)) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    public function correct(User $user, ?string $prNumber = null): bool
    {
        if (! $user->hasPermissionTo(Permission::EDIT_PROCUREMENT->value)) {
            return false;
        }

        return $this->canAccessScopedProcurement($user, $prNumber);
    }

    public function approve(User $user): bool
    {
        return $user->hasPermissionTo(Permission::APPROVE_PROCUREMENT->value);
    }

    public function publish(User $user): bool
    {
        return $user->hasPermissionTo(Permission::PUBLISH_TO_BLOCKCHAIN->value);
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

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\UserLoginLog;
use App\Services\BlockchainSyncService;

class UserLoginLogObserver
{
    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(UserLoginLog $loginLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($loginLog->txid !== null) {
            return;
        }

        $key = ($loginLog->user_id ?? 'unknown').'-'.($loginLog->login_at?->timestamp ?? time());

        $this->sync->publish($loginLog, Stream::USER_LOGIN_SESSIONS, $key);
    }
}

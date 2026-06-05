<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\StreamEnums;
use App\Models\UserLoginLog;
use App\Services\BlockchainSyncService;

/**
 * User Login Log Observer — Auto-publish to blockchain on create.
 *
 * Every login/logout event is simultaneously written to MySQL and blockchain.
 * This ensures the login audit trail survives MySQL destruction.
 */
class UserLoginLogObserver
{
    /**
     * Handle the UserLoginLog "created" event.
     */
    public function created(UserLoginLog $loginLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($loginLog->txid !== null) {
            return;
        }

        $key = ($loginLog->user_id ?? 'unknown').'-'.($loginLog->login_at?->timestamp ?? time());

        BlockchainSyncService::publish($loginLog, StreamEnums::USER_LOGIN_SESSIONS, $key);
    }
}

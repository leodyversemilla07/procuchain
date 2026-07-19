<?php

declare(strict_types=1);

namespace App\Observers\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HandlesBlockchainSync
{
    protected function shouldSyncToBlockchain(?Model $model = null): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if ($model !== null && $model->txid !== null) {
            return false;
        }

        return true;
    }
}

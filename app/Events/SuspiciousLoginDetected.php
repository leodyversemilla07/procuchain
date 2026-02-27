<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspiciousLoginDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly array $location,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
    ) {}
}

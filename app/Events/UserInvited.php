<?php

namespace App\Events;

use App\Models\UserInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly UserInvitation $invitation,
        public readonly string $acceptUrl,
    ) {}
}

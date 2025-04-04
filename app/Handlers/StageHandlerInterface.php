<?php

namespace App\Handlers;

use Illuminate\Http\Request;

interface StageHandlerInterface
{
    public function handle(Request $request): array;
}

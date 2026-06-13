<?php

declare(strict_types=1);

namespace App\Services\Concerns;

trait HashesData
{
    public function computeHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

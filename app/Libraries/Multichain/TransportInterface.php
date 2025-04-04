<?php

namespace App\Libraries\Multichain;

interface TransportInterface
{
    public function execute(string $url, array $headers, string $payload): array;
    public function getLastError(): array;
}
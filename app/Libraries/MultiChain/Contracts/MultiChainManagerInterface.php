<?php

namespace App\Libraries\MultiChain\Contracts;

interface MultiChainManagerInterface
{
    public function getinfo(): array;

    public function getnewaddress(): string;

    public function getstreaminfo(string $stream): array;

    public function create(string $type, string $name, bool $open = true, array $options = []): string;

    public function subscribe(string $stream, bool $rescan = true): void;

    public function grant(string $address, string $permissions): void;

    public function importaddress(string $address, string $label = '', bool $rescan = true): void;

    public function validateaddress(string $address): array;
}

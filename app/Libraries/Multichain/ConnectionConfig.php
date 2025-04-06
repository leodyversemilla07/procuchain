<?php

namespace App\Libraries\Multichain;

class ConnectionConfig
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $useSSL;
    private ?string $chainName;
    private bool $verifySSL;

    public function __construct(
        string $host,
        int $port,
        string $username,
        string $password,
        bool $useSSL = false,
        ?string $chainName = null,
        bool $verifySSL = true
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->useSSL = $useSSL;
        $this->chainName = $chainName;
        $this->verifySSL = $verifySSL;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            $config['rpc']['host'],
            (int) $config['rpc']['port'],
            $config['rpc']['username'],
            $config['rpc']['password'],
            $config['use_ssl'] ?? false,
            $config['chain_name'] ?? null,
            $config['verify_ssl'] ?? true
        );
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function useSSL(): bool
    {
        return $this->useSSL;
    }

    public function getChainName(): ?string
    {
        return $this->chainName;
    }

    public function verifySSL(): bool
    {
        return $this->verifySSL;
    }
}
<?php

declare(strict_types=1);

namespace App\Support;

use App\Libraries\MultiChain\Client;

class NodeClientFactory
{
    public static function createNodeClient(array $nodeConfig): Client
    {
        $client = new Client(
            $nodeConfig['private_ip'],
            $nodeConfig['rpc_port'],
            self::getRpcUser(),
            self::getRpcPassword(),
            false
        );
        $client->setoption('chain_name', config('multichain.chain_name'));
        $client->setoption('use_curl', true);
        $client->setoption('verify_ssl', false);

        return $client;
    }

    public static function getRpcUser(): string
    {
        return config('multichain.rpc.username', 'multichainrpc');
    }

    public static function getRpcPassword(): string
    {
        return config('multichain.rpc.password');
    }

    public static function getNodes(): array
    {
        return config('multichain.nodes', []);
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MultiChain RPC Connection
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection details for your MultiChain node's
    | RPC interface. These credentials are used to authenticate API requests
    | to your blockchain node for reading and writing transactions.
    |
    */

    'rpc' => [
        'host' => env('MULTICHAIN_HOST', '127.0.0.1'),
        'port' => env('MULTICHAIN_PORT', 4786),
        'username' => env('MULTICHAIN_USERNAME', 'multichainrpc'),
        'password' => env('MULTICHAIN_PASSWORD', 'default_password_change_me'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chain Configuration
    |--------------------------------------------------------------------------
    |
    | This section contains the basic configuration for your MultiChain
    | blockchain. The chain name identifies which blockchain you're connecting
    | to when multiple chains may be available on the same node.
    |
    */

    'chain_name' => env('MULTICHAIN_CHAIN_NAME', 'procuchain'),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure SSL options for secure connections to your MultiChain node.
    | It's recommended to use SSL in production environments for enhanced
    | security when transmitting blockchain data.
    |
    */

    'use_ssl' => env('MULTICHAIN_USE_SSL', false),
    'verify_ssl' => env('MULTICHAIN_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | Network Configuration
    |--------------------------------------------------------------------------
    |
    | The node address specifies the network location for your MultiChain node
    | that other nodes can use to connect to it for peer-to-peer communication
    | within the blockchain network.
    |
    */

    'node_address' => env('MULTICHAIN_NODE_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure timeouts and retry settings for the MultiChain RPC connection.
    | These settings help manage connection behavior and reliability.
    |
    */

    'connection_timeout' => env('MULTICHAIN_CONNECTION_TIMEOUT', 30),
    'max_retries' => env('MULTICHAIN_MAX_RETRIES', 3),
    
    /*
    |--------------------------------------------------------------------------
    | MultiChain User Addresses
    |--------------------------------------------------------------------------
    |
    | These environment variables store the blockchain addresses for key users
    | in the system. Update your .env file to set these values as needed.
    |
    */
    'addresses' => [
        'bac_secretariat' => env('MULTICHAIN_BAC_SECRETARIAT_ADDRESS', 'default_bac_secretariat_address'),
        'bac_chairman' => env('MULTICHAIN_BAC_CHAIRMAN_ADDRESS', 'default_bac_chairman_address'),
        'hope' => env('MULTICHAIN_HOPE_ADDRESS', 'default_hope_address'),
        'admin' => env('MULTICHAIN_ADMIN_ADDRESS', 'default_admin_address'),
    ],
];

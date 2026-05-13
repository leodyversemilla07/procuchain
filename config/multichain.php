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
        'host' => env('MULTICHAIN_RPC_HOST', '127.0.0.1'),
        'port' => env('MULTICHAIN_RPC_PORT', 4786),
        'username' => env('MULTICHAIN_RPC_USERNAME', 'multichainrpc'),
        'password' => env('MULTICHAIN_RPC_PASSWORD', 'default_password_change_me'),
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
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) to wait for a blockchain RPC response.
    | Lower values prevent request timeouts but may fail on slow networks.
    |
    */

    'timeout' => env('MULTICHAIN_TIMEOUT', 5),

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
 | Node Registry
 |--------------------------------------------------------------------------
 |
 | The list of MultiChain nodes in the network. Each node entry defines
 | its id, display name, role, and connection details (public IP,
 | private IP, P2P port, RPC port). Sourced from Terraform outputs
 | and set via .env so deployments don't require code changes.
 |
 | The key format is: MULTICHAIN_NODE_{ID}_* (e.g. MULTICHAIN_NODE_ADMIN_IP)
 |
 */

    'nodes' => [
        [
            'id' => 'admin',
            'name' => env('MULTICHAIN_NODE_ADMIN_NAME', 'Primary Node'),
            'role' => env('MULTICHAIN_NODE_ADMIN_ROLE', 'Administrator'),
            'ip' => env('MULTICHAIN_NODE_ADMIN_IP', '127.0.0.1'),
            'private_ip' => env('MULTICHAIN_NODE_ADMIN_PRIVATE_IP', '127.0.0.1'),
            'p2p_port' => (int) env('MULTICHAIN_NODE_ADMIN_P2P_PORT', 6835),
            'rpc_port' => (int) env('MULTICHAIN_NODE_ADMIN_RPC_PORT', 6834),
        ],
        [
            'id' => 'bac-secretariat',
            'name' => env('MULTICHAIN_NODE_BAC_SECRETARIAT_NAME', 'BAC Secretariat'),
            'role' => env('MULTICHAIN_NODE_BAC_SECRETARIAT_ROLE', 'Secretariat'),
            'ip' => env('MULTICHAIN_NODE_BAC_SECRETARIAT_IP', '127.0.0.1'),
            'private_ip' => env('MULTICHAIN_NODE_BAC_SECRETARIAT_PRIVATE_IP', '127.0.0.1'),
            'p2p_port' => (int) env('MULTICHAIN_NODE_BAC_SECRETARIAT_P2P_PORT', 6835),
            'rpc_port' => (int) env('MULTICHAIN_NODE_BAC_SECRETARIAT_RPC_PORT', 6834),
        ],
        [
            'id' => 'bac-chairman',
            'name' => env('MULTICHAIN_NODE_BAC_CHAIRMAN_NAME', 'BAC Chairman'),
            'role' => env('MULTICHAIN_NODE_BAC_CHAIRMAN_ROLE', 'Chairman'),
            'ip' => env('MULTICHAIN_NODE_BAC_CHAIRMAN_IP', '127.0.0.1'),
            'private_ip' => env('MULTICHAIN_NODE_BAC_CHAIRMAN_PRIVATE_IP', '127.0.0.1'),
            'p2p_port' => (int) env('MULTICHAIN_NODE_BAC_CHAIRMAN_P2P_PORT', 6835),
            'rpc_port' => (int) env('MULTICHAIN_NODE_BAC_CHAIRMAN_RPC_PORT', 6834),
        ],
        [
            'id' => 'hope',
            'name' => env('MULTICHAIN_NODE_HOPE_NAME', 'HOPE'),
            'role' => env('MULTICHAIN_NODE_HOPE_ROLE', 'HOPE'),
            'ip' => env('MULTICHAIN_NODE_HOPE_IP', '127.0.0.1'),
            'private_ip' => env('MULTICHAIN_NODE_HOPE_PRIVATE_IP', '127.0.0.1'),
            'p2p_port' => (int) env('MULTICHAIN_NODE_HOPE_P2P_PORT', 6835),
            'rpc_port' => (int) env('MULTICHAIN_NODE_HOPE_RPC_PORT', 6834),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure timeouts and retry settings for the MultiChain RPC connection.
    | These settings help manage connection behavior and reliability.
    |
    */

    'connection_timeout' => env('MULTICHAIN_CONNECTION_TIMEOUT', 10),
    'max_retries' => env('MULTICHAIN_MAX_RETRIES', 2),
    'retry_delay' => env('MULTICHAIN_RETRY_DELAY', 1),

    // Web-request specific caps to avoid hitting PHP's 60s max execution time
    // Increased from 3s to 15s to handle large procurement list fetches
    'web_connection_timeout' => env('MULTICHAIN_WEB_CONNECTION_TIMEOUT', 15),
    // Increased from 1 to 2 retries to handle temporary network issues
    'web_max_retries' => env('MULTICHAIN_WEB_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Permission Matrix (Roles)
    |--------------------------------------------------------------------------
    |
    | Central definition of the global + stream permissions granted to each
    | role by the multichain:setup command. Keeping this in config lets us
    | adjust blockchain permissioning without editing the command class.
    |
    */
    'permissions' => [
        'roles' => [
            'admin' => [
                'global' => ['admin', 'send', 'receive', 'create', 'issue', 'mine', 'activate'],
                'stream' => ['admin', 'write', 'read'],
            ],
            'bac_secretariat' => [
                'global' => ['send', 'receive', 'create', 'issue', 'activate'],
                'stream' => ['admin', 'write', 'read'],
            ],
            'bac_chairman' => [
                'global' => ['send', 'receive'],
                'stream' => ['write', 'read'],
            ],
            'hope' => [
                'global' => ['send', 'receive'],
                'stream' => ['write', 'read'],
            ],
        ],
    ],
];

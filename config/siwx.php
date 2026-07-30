<?php

return [
    'domains' => array_filter(explode(',', (string) env('SIWX_ALLOWED_DOMAINS', ''))),
    'namespaces' => ['eip155', 'solana'],
    'nonce_ttl' => env('SIWX_NONCE_TTL', 600),
    'clock_skew' => env('SIWX_CLOCK_SKEW', 300),
    'cache_store' => env('SIWX_CACHE_STORE'),
    'routes' => [
        'enabled' => env('SIWX_ROUTES_ENABLED', true),
        'prefix' => env('SIWX_ROUTES_PREFIX', 'siwx'),
    ],
    'eip1271' => [
        'enabled' => env('SIWX_EIP1271_ENABLED', false),
        'rpc_url' => env('SIWX_RPC_URL'),
    ],
];

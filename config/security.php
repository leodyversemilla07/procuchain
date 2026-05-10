<?php

return [
    'csp' => [
        'development' => [
            'script_src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https:', 'http://127.0.0.1:5173', 'http://localhost:5173'],
            'style_src' => ["'self'", "'unsafe-inline'", 'https:', 'http://127.0.0.1:5173', 'http://localhost:5173'],
            'img_src' => ["'self'", 'data:', 'blob:', 'https:'],
            'connect_src' => ["'self'", 'https:', 'wss:', 'ws://127.0.0.1:5173', 'ws://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:5173'],
            'font_src' => ["'self'", 'data:', 'https:'],
            'worker_src' => ["'self'", 'blob:'],
        ],
        'production' => [
            'script_src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style_src' => ["'self'", "'unsafe-inline'"],
            'img_src' => ["'self'", 'data:', 'blob:'],
            'connect_src' => ["'self'"],
            'font_src' => ["'self'", 'data:'],
            'worker_src' => ["'self'", 'blob:'],
        ],
    ],
];

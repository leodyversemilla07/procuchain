<?php

return [
    'csp' => [
        'development' => [
            'script_src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https:'],
            'style_src' => ["'self'", "'unsafe-inline'", 'https:'],
            'img_src' => ["'self'", 'data:', 'blob:', 'https:'],
            'connect_src' => ["'self'", 'https:', 'wss:'],
            'font_src' => ["'self'", 'data:', 'https:'],
            'worker_src' => ["'self'", 'blob:'],
        ],
        'production' => [
            'script_src' => ["'self'"],
            'style_src' => ["'self'", "'unsafe-inline'"],
            'img_src' => ["'self'", 'data:', 'blob:'],
            'connect_src' => ["'self'"],
            'font_src' => ["'self'", 'data:'],
            'worker_src' => ["'self'", 'blob:'],
        ],
    ],
];

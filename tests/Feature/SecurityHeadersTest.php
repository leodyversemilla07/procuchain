<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;

it('uses the development content security policy outside production', function () {
    app()->detectEnvironment(fn () => 'local');

    $response = app(SecurityHeaders::class)->handle(
        Request::create('/', 'GET'),
        fn () => response('ok'),
    );

    expect($response->headers->get('Content-Security-Policy'))->toContain("'unsafe-eval'")
        ->toContain('http://127.0.0.1:5173')
        ->toContain('http://localhost:5173')
        ->toContain('ws://127.0.0.1:5173')
        ->toContain('ws://localhost:5173')
        ->and($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('uses the stricter production content security policy and hsts in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = app(SecurityHeaders::class)->handle(
        Request::create('/', 'GET'),
        fn () => response('ok'),
    );

    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self'")
        ->not->toContain("'unsafe-eval'")
        ->and($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains; preload');
});

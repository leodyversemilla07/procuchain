<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF middleware for all tests
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Alternative approach for Laravel 12 - disable CSRF protection globally
        if (method_exists($this->app['config'], 'set')) {
            $this->app['config']->set('session.same_site', null);
        }
    }
}

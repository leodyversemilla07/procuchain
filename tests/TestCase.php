<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and roles for all tests
        if (in_array('Illuminate\Foundation\Testing\RefreshDatabase', class_uses_recursive($this))) {
            $this->seedPermissionsAndRoles();
        }

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

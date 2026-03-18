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

        // Disable CSRF and session middleware for all tests
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
    }
}

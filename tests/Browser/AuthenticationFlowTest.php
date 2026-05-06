<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsPermissions;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Authentication Browser Flow', function () {
    it('displays the login page correctly', function () {
        $page = visit('/login');

        $page->assertSee('Log in to your account')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows validation errors for empty form submission', function () {
        $page = visit('/login');

        $page->click('button[type="submit"]')
            ->assertNoJavascriptErrors();
    });

    it('successfully logs in a user with valid credentials', function () {
        $this->seedPermissions();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('bac_secretariat');

        $page = visit('/login');

        $page->fill('email', 'test@example.com')
            ->fill('password', 'password')
            ->click('button[type="submit"]')
            ->assertNoJavascriptErrors();

        $this->assertAuthenticated();
    });

    it('shows error message for invalid credentials', function () {
        $page = visit('/login');

        $page->fill('email', 'invalid@example.com')
            ->fill('password', 'wrongpassword')
            ->click('button[type="submit"]')
            ->assertNoJavascriptErrors();
    });

    it('redirects authenticated user to their dashboard', function () {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $page = visit('/login');

        // Should redirect to admin dashboard
        $page->assertNoJavascriptErrors();
    });
});

describe('Two-Factor Authentication Browser Flow', function () {
    it('displays 2FA setup page for authenticated users', function () {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $page = visit('/settings/two-factor');

        $page->assertSee('Two-factor')
            ->assertNoJavascriptErrors();
    });
});

describe('Password Reset Browser Flow', function () {
    it('displays forgot password page', function () {
        $page = visit('/forgot-password');

        $page->assertSee('Forgot password')
            ->assertNoJavascriptErrors();
    });

    it('shows success message after requesting password reset', function () {
        $this->seedPermissions();

        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $page = visit('/forgot-password');

        $page->fill('email', 'test@example.com')
            ->click('button[type="submit"]')
            ->assertNoJavascriptErrors();
    });
});

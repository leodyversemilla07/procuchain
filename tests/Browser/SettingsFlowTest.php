<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(\Tests\SeedsPermissions::class);

describe('Settings Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    });

    it('displays profile settings page', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/profile');

        $page->assertSee('Profile')
            ->assertSee($this->user->name)
            ->assertSee($this->user->email)
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('allows updating profile information', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/profile');

        $page->fill('name', 'Updated Name')
            ->click('button[type="submit"]')
            ->assertNoJavascriptErrors();
    });

    it('displays password settings page', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/password');

        $page->assertSee('Password')
            ->assertNoJavascriptErrors();
    });

    it('displays appearance settings page', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/appearance');

        $page->assertSee('Appearance')
            ->assertNoJavascriptErrors();
    });
});

describe('Notification Settings Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    });

    it('displays notifications page', function () {
        $this->actingAs($this->user);

        $page = visit('/notifications');

        $page->assertSee('Notification')
            ->assertNoJavascriptErrors();
    });
});

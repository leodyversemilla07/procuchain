<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsPermissions;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Settings Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    });

    it('displays proFile settings page', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/proFile');

        $page->assertSee('ProFile information')
            ->assertSee('Email address')
            ->assertSee('Blockchain Address')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('allows editing proFile information fields', function () {
        $this->actingAs($this->user);

        $page = visit('/settings/proFile');

        $page->fill('name', 'Updated Name')
            ->fill('email', 'updated@example.test')
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

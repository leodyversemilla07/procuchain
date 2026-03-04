<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\AuditLogger;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

// ─── Viewing the audit log ────────────────────────────────────────────────────

it('admin can view the audit log page', function () {
    AuditLog::create([
        'user_id' => $this->admin->id,
        'action' => 'user.created',
        'subject_type' => 'user',
        'subject_id' => '42',
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/audit-log')
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('admin/audit-log')
            ->has('logs')
            ->has('distinctActions')
        );
});

it('non-admin is forbidden from viewing the audit log', function () {
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
    $hope = User::factory()->create();
    $hope->assignRole('hope');

    $this->actingAs($hope)
        ->get('/admin/audit-log')
        ->assertForbidden();
});

// ─── UserManagementController writes audit entries ───────────────────────────

it('creating a user writes a user.created audit log entry', function () {
    $managerMock = Mockery::mock(Manager::class);
    $managerMock->shouldReceive('getNewAddress')->andReturn('1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2');
    $managerMock->shouldReceive('validateAddress')->andReturn(['isvalid' => true]);
    $this->app->instance(Manager::class, $managerMock);

    $this->actingAs($this->admin)
        ->post('/admin/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => 'bac_secretariat',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $entry = AuditLog::where('action', 'user.created')->first();
    expect($entry)->not->toBeNull();
    expect($entry->user_id)->toBe($this->admin->id);
    expect($entry->subject_type)->toBe('user');
    expect($entry->new_values['email'])->toBe('jane@example.com');
});

it('updating a user writes a user.updated audit log entry', function () {
    $target = User::factory()->create();
    $target->assignRole('bac_secretariat');

    $this->actingAs($this->admin)
        ->put("/admin/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'role' => 'bac_secretariat',
        ]);

    expect(AuditLog::where('action', 'user.updated')
        ->where('subject_id', (string) $target->id)
        ->exists()
    )->toBeTrue();
});

it('deleting a user writes a user.deleted audit log entry', function () {
    $target = User::factory()->create();
    $target->assignRole('bac_secretariat');
    $targetId = $target->id;

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}");

    expect(AuditLog::where('action', 'user.deleted')
        ->where('subject_id', (string) $targetId)
        ->exists()
    )->toBeTrue();
});

it('bulk-deleting users writes a user.bulk_deleted audit log entry', function () {
    $targets = User::factory()->count(2)->create();
    $targets->each(fn ($u) => $u->assignRole('bac_secretariat'));

    $this->actingAs($this->admin)
        ->delete('/admin/users', [
            'user_ids' => $targets->pluck('id')->toArray(),
        ])
        ->assertRedirect();

    expect(AuditLog::where('action', 'user.bulk_deleted')->exists())->toBeTrue();
});

// ─── AccountLockoutController writes audit entries ────────────────────────────

it('locking an account writes an account.locked audit log entry', function () {
    $target = User::factory()->create();

    $lockoutMock = Mockery::mock(AccountLockoutService::class);
    $lockoutMock->shouldReceive('lockAccount')->once()->andReturn(true);
    $this->app->instance(AccountLockoutService::class, $lockoutMock);

    $this->actingAs($this->admin)
        ->postJson("/admin/accounts/{$target->id}/lock", [
            'reason' => 'Suspicious activity',
            'duration_hours' => 24,
        ])
        ->assertOk();

    expect(AuditLog::where('action', 'account.locked')
        ->where('subject_id', (string) $target->id)
        ->exists()
    )->toBeTrue();
});

it('unlocking an account writes an account.unlocked audit log entry', function () {
    $target = User::factory()->create(['account_locked' => true]);

    $lockoutMock = Mockery::mock(AccountLockoutService::class);
    $lockoutMock->shouldReceive('unlockAccount')->once()->andReturn(true);
    $this->app->instance(AccountLockoutService::class, $lockoutMock);

    $this->actingAs($this->admin)
        ->postJson("/admin/accounts/{$target->id}/unlock", ['reason' => 'Resolved'])
        ->assertSuccessful();

    expect(AuditLog::where('action', 'account.unlocked')
        ->where('subject_id', (string) $target->id)
        ->exists()
    )->toBeTrue();
});

it('resetting login attempts writes an account.attempts_reset audit log entry', function () {
    $target = User::factory()->create(['failed_login_attempts' => 5]);

    $lockoutMock = Mockery::mock(AccountLockoutService::class);
    $lockoutMock->shouldReceive('resetFailedAttempts')->once()->andReturn(true);
    $this->app->instance(AccountLockoutService::class, $lockoutMock);

    $this->actingAs($this->admin)
        ->post("/admin/accounts/{$target->id}/reset-attempts")
        ->assertSuccessful();

    expect(AuditLog::where('action', 'account.attempts_reset')
        ->where('subject_id', (string) $target->id)
        ->exists()
    )->toBeTrue();
});

// ─── AuditLogger safety ───────────────────────────────────────────────────────

it('AuditLogger does not throw when the database write fails', function () {
    $logger = $this->app->make(AuditLogger::class);

    \Illuminate\Support\Facades\Schema::drop('audit_logs');

    expect(fn () => $logger->log('user.created', 'user', '1'))->not->toThrow(\Exception::class);
});

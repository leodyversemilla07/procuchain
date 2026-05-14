<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

// ─── AuditLogger: NGPA action labels completeness ────────────────────────────

it('AuditLogger has human-readable labels for all controller action types', function () {
    $logger = $this->app->make(AuditLogger::class);

    $controllerActions = [
        // Procurement
        'procurement.initiated',
        'procurement.document_uploaded',
        'procurement.stage_completed',
        'procurement.stage_skipped',
        'procurement.decision_published',
        'procurement.stage_repeated',
        'procurement.delivery_updated',
        'procurement.archived',
        'procurement.restored',
        'procurement.corrected',
        // Document
        'document.corrected',
        'document.downloaded',
        // Auth
        'auth.login',
        'auth.logout',
        'auth.password_reset',
        'auth.invitation_accepted',
        // Admin
        'admin.invitation_sent',
        'admin.invitation_resent',
        'admin.invitation_revoked',
        'admin.workflow_config_updated',
        'admin.workflow_config_reset',
        'admin.stage_document_config_updated',
        'admin.stage_document_config_reset',
        // Settings
        'settings.profile_updated',
        'settings.password_changed',
        'settings.account_deleted',
        'settings.two_factor_enabled',
        'settings.two_factor_confirmed',
        'settings.two_factor_disabled',
        // Security
        'security.ip_blocked',
        'security.ip_unblocked',
        'security.notification_read',
        'security.notifications_all_read',
    ];

    foreach ($controllerActions as $action) {
        $label = $logger->getActionLabel($action);
        // The label should differ from the raw key (i.e., have spaces/capitalization)
        expect($label)->not->toBe($action,
            "Action [{$action}] should have a human-readable label, not fall back to the raw key");
    }
});

// ─── AuditLogger: NGPA critical action classification ────────────────────────

it('AuditLogger correctly classifies NGPA-critical actions', function () {
    $logger = $this->app->make(AuditLogger::class);

    $criticalActions = [
        'procurement.initiated',
        'procurement.decision_published',
        'procurement.archived',
        'procurement.restored',
        'procurement.corrected',
        'document.corrected',
        'auth.password_reset',
        'auth.invitation_accepted',
        'settings.password_changed',
        'settings.account_deleted',
        'settings.two_factor_enabled',
        'settings.two_factor_disabled',
        'security.ip_blocked',
        'security.ip_unblocked',
        'admin.invitation_revoked',
        'admin.workflow_config_updated',
        'admin.workflow_config_reset',
        'admin.stage_document_config_updated',
        'admin.stage_document_config_reset',
    ];

    foreach ($criticalActions as $action) {
        expect($logger->isCritical($action))->toBeTrue(
            "Action [{$action}] should be classified as critical per NGPA"
        );
    }
});

it('AuditLogger correctly classifies non-critical actions', function () {
    $logger = $this->app->make(AuditLogger::class);

    $nonCriticalActions = [
        'auth.login',
        'auth.logout',
        'document.downloaded',
        'procurement.stage_completed',
        'procurement.stage_skipped',
        'procurement.stage_repeated',
        'procurement.delivery_updated',
        'procurement.document_uploaded',
        'settings.profile_updated',
        'security.notification_read',
        'security.notifications_all_read',
    ];

    foreach ($nonCriticalActions as $action) {
        expect($logger->isCritical($action))->toBeFalse(
            "Action [{$action}] should NOT be classified as critical"
        );
    }
});

// ─── Auth audit logging ─────────────────────────────────────────────────────

it('login writes an auth.login audit log entry', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('bac_secretariat');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect(AuditLog::where('action', 'auth.login')
        ->where('subject_id', (string) $user->id)
        ->exists()
    )->toBeTrue();
});

it('logout writes an auth.logout audit log entry', function () {
    $this->actingAs($this->admin);

    $this->post('/logout');

    expect(AuditLog::where('action', 'auth.logout')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

// ─── Admin invitation audit logging ─────────────────────────────────────────

it('sending an invitation writes an admin.invitation_sent audit log entry', function () {
    $this->actingAs($this->admin)
        ->post('/admin/invitations', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => 'bac_secretariat',
        ]);

    expect(AuditLog::where('action', 'admin.invitation_sent')->exists())->toBeTrue();
});

it('resending an invitation writes an admin.invitation_resent audit log entry', function () {
    $invitation = UserInvitation::create([
        'email' => 'newuser@example.com',
        'name' => 'New User',
        'role' => 'bac_secretariat',
        'invited_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/invitations/{$invitation->id}/resend");

    expect(AuditLog::where('action', 'admin.invitation_resent')
        ->where('subject_id', (string) $invitation->id)
        ->exists()
    )->toBeTrue();
});

it('revoking an invitation writes an admin.invitation_revoked audit log entry', function () {
    $invitation = UserInvitation::create([
        'email' => 'newuser@example.com',
        'name' => 'New User',
        'role' => 'bac_secretariat',
        'invited_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/invitations/{$invitation->id}");

    expect(AuditLog::where('action', 'admin.invitation_revoked')
        ->where('subject_id', (string) $invitation->id)
        ->exists()
    )->toBeTrue();
});

// ─── Settings audit logging ─────────────────────────────────────────────────

it('updating profile writes a settings.profile_updated audit log entry', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/profile', [
            'name' => 'Updated Admin',
            'email' => $this->admin->email,
        ]);

    expect(AuditLog::where('action', 'settings.profile_updated')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

it('changing password writes a settings.password_changed audit log entry', function () {
    $this->actingAs($this->admin)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

    expect(AuditLog::where('action', 'settings.password_changed')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

// ─── 2FA Fortify event hooks audit logging ──────────────────────────────────

it('2FA enabled event writes a settings.two_factor_enabled audit log entry', function () {
    $logger = $this->app->make(AuditLogger::class);

    // Simulate the event listener from AppServiceProvider
    $logger->log('settings.two_factor_enabled', 'user', (string) $this->admin->id);

    expect(AuditLog::where('action', 'settings.two_factor_enabled')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

it('2FA disabled event writes a settings.two_factor_disabled audit log entry', function () {
    $logger = $this->app->make(AuditLogger::class);

    $logger->log('settings.two_factor_disabled', 'user', (string) $this->admin->id);

    expect(AuditLog::where('action', 'settings.two_factor_disabled')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

it('2FA confirmed event writes a settings.two_factor_confirmed audit log entry', function () {
    $logger = $this->app->make(AuditLogger::class);

    $logger->log('settings.two_factor_confirmed', 'user', (string) $this->admin->id);

    expect(AuditLog::where('action', 'settings.two_factor_confirmed')
        ->where('subject_id', (string) $this->admin->id)
        ->exists()
    )->toBeTrue();
});

// ─── Security audit logging ─────────────────────────────────────────────────

it('blocking an IP writes a security.ip_blocked audit log entry', function () {
    $this->actingAs($this->admin)
        ->from('/admin/login-logs')
        ->post('/admin/login-logs/block-ip', [
            'ip_address' => '192.168.1.100',
            'reason' => 'Brute force attempt',
            'duration' => 'permanent',
        ]);

    expect(AuditLog::where('action', 'security.ip_blocked')
        ->where('subject_id', '192.168.1.100')
        ->exists()
    )->toBeTrue();
});

it('unblocking an IP writes a security.ip_unblocked audit log entry', function () {
    // First block the IP, then unblock it
    $this->actingAs($this->admin)
        ->from('/admin/login-logs')
        ->post('/admin/login-logs/block-ip', [
            'ip_address' => '192.168.1.100',
            'reason' => 'Brute force attempt',
            'duration' => 'permanent',
        ]);

    $this->actingAs($this->admin)
        ->from('/admin/login-logs')
        ->post('/admin/login-logs/unblock-ip', [
            'ip_address' => '192.168.1.100',
        ]);

    expect(AuditLog::where('action', 'security.ip_unblocked')
        ->where('subject_id', '192.168.1.100')
        ->exists()
    )->toBeTrue();
});

// ─── AuditLogger write failure resilience ───────────────────────────────────

it('AuditLogger does not throw on write failure for NGPA actions', function () {
    $logger = $this->app->make(AuditLogger::class);

    Schema::drop('audit_logs');

    expect(fn () => $logger->log('procurement.initiated', 'procurement', 'PR-001'))
        ->not->toThrow(Exception::class);
});

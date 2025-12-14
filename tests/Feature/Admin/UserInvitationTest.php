<?php

namespace Tests\Feature\Admin;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Mockery;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can access invitations page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/invitations');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($assert) => $assert
            ->component('admin/user-invitations')
            ->has('invitations')
            ->has('roles')
    );
});

test('admin can send invitation', function () {
    Mail::fake();

    $response = $this->actingAs($this->admin)->post('/admin/invitations', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'bac_secretariat',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('user_invitations', [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'role' => 'bac_secretariat',
        'invited_by' => $this->admin->id,
    ]);

    // Since mail is queued, use assertQueued instead of assertSent
    Mail::assertQueued(UserInvitationMail::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('cannot send invitation to existing user email', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->actingAs($this->admin)->post('/admin/invitations', [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'role' => 'bac_secretariat',
    ]);

    $response->assertSessionHasErrors('email');
});

test('cannot send invitation to email with pending invitation', function () {
    UserInvitation::factory()->create([
        'email' => 'pending@example.com',
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/invitations', [
        'name' => 'Test User',
        'email' => 'pending@example.com',
        'role' => 'bac_secretariat',
    ]);

    $response->assertSessionHasErrors('email');
});

test('admin can resend pending invitation', function () {
    Mail::fake();

    $invitation = UserInvitation::factory()->create([
        'email' => 'test@example.com',
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(1),
    ]);

    $response = $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $invitation->refresh();
    expect($invitation->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();

    // Since mail is queued, use assertQueued instead of assertSent
    Mail::assertQueued(UserInvitationMail::class);
});

test('cannot resend expired invitation', function () {
    $invitation = UserInvitation::factory()->expired()->create([
        'invited_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend");

    $response->assertSessionHasErrors();
});

test('admin can revoke pending invitation', function () {
    $invitation = UserInvitation::factory()->create([
        'invited_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/invitations/{$invitation->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $invitation->refresh();
    expect($invitation->revoked)->toBeTrue();
    expect($invitation->revoked_by)->toBe($this->admin->id);
});

test('cannot revoke accepted invitation', function () {
    $user = User::factory()->create();
    $invitation = UserInvitation::factory()->accepted()->create([
        'invited_by' => $this->admin->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/invitations/{$invitation->id}");

    $response->assertSessionHasErrors();
});

test('non-admin cannot access invitations', function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');

    $response = $this->actingAs($user)->get('/admin/invitations');

    $response->assertStatus(403);
});

test('guest can view valid invitation', function () {
    $invitation = UserInvitation::factory()->create([
        'invited_by' => $this->admin->id,
    ]);

    $url = URL::signedRoute('invitation.show', ['token' => $invitation->token]);

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($assert) => $assert
            ->component('auth/accept-invitation')
            ->has('invitation')
            ->has('token')
    );
});

test('cannot view expired invitation', function () {
    $invitation = UserInvitation::factory()->expired()->create([
        'invited_by' => $this->admin->id,
    ]);

    $url = URL::signedRoute('invitation.show', ['token' => $invitation->token]);

    $response = $this->get($url);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors();
});

test('can accept valid invitation and create account', function () {
    Mail::fake();

    // Create the role first
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);

    // Mock the Manager service - MUST be done before creating invitation
    $managerMock = Mockery::mock(Manager::class);
    $managerMock->shouldReceive('getnewaddress')
        ->andReturn('1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2');

    $this->app->instance(Manager::class, $managerMock);

    $invitation = UserInvitation::factory()->create([
        'email' => 'newuser@example.com',
        'name' => 'New User',
        'role' => 'bac_secretariat',
        'invited_by' => $this->admin->id,
    ]);

    $url = URL::signedRoute('invitation.accept', ['token' => $invitation->token]);

    $response = $this->post($url, [
        'name' => 'New User Updated',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/bac-secretariat/dashboard'); // Role-specific dashboard redirect

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'name' => 'New User Updated',
    ]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New User Updated');
    expect($user->hasRole('bac_secretariat'))->toBeTrue();

    // Email should be auto-verified since invitation was sent to this email
    expect($user->email_verified_at)->not->toBeNull();

    // Blockchain address should be set from the mock
    expect($user->blockchain_address)->not->toBeNull();
    expect($user->blockchain_address)->toBe('1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2');

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();
    expect($invitation->user_id)->toBe($user->id);

    $this->assertAuthenticatedAs($user);
});

test('cannot accept expired invitation', function () {
    $invitation = UserInvitation::factory()->expired()->create([
        'invited_by' => $this->admin->id,
    ]);

    $url = URL::signedRoute('invitation.accept', ['token' => $invitation->token]);

    $response = $this->post($url, [
        'name' => 'Test User',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors();
});

test('invitation statistics are correct', function () {
    // Create various invitations
    UserInvitation::factory()->count(3)->create(['invited_by' => $this->admin->id]);
    UserInvitation::factory()->expired()->count(2)->create(['invited_by' => $this->admin->id]);
    UserInvitation::factory()->accepted()->count(1)->create(['invited_by' => $this->admin->id]);
    UserInvitation::factory()->revoked()->count(1)->create(['invited_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->get('/admin/invitations');

    $response->assertInertia(
        fn ($assert) => $assert
            ->component('admin/user-invitations')
            ->has('invitations', 7)
    );
});

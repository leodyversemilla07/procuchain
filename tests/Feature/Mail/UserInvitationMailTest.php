<?php

use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('user invitation email can be sent', function () {
    Mail::fake();

    $invitation = UserInvitation::factory()->create([
        'email' => 'invited@example.com',
        'token' => 'test-token-123',
    ]);

    $acceptUrl = url('/invitation/'.$invitation->token);

    Mail::to($invitation->email)->send(new UserInvitationMail($invitation, $acceptUrl));

    Mail::assertQueued(UserInvitationMail::class, function ($mail) use ($invitation, $acceptUrl) {
        return $mail->hasTo($invitation->email) &&
               $mail->invitation->id === $invitation->id &&
               $mail->acceptUrl === $acceptUrl;
    });
});

test('user invitation email has correct subject and content', function () {
    $invitation = UserInvitation::factory()->create([
        'email' => 'invited@example.com',
        'token' => 'test-token-456',
    ]);

    $acceptUrl = url('/invitation/'.$invitation->token);

    $mail = new UserInvitationMail($invitation, $acceptUrl);

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Invitation to Join Procuchain Procurement System');

    $content = $mail->content();
    expect($content->view)->toBe('emails.user-invitation');
    expect($content->with)->toHaveKey('invitation', $invitation)
        ->and($content->with)->toHaveKey('acceptUrl', $acceptUrl);
});

test('user invitation mail implements ShouldQueue', function () {
    $implements = class_implements(UserInvitationMail::class);

    expect($implements)->toContain(Illuminate\Contracts\Queue\ShouldQueue::class);
});

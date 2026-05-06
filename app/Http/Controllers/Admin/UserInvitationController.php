<?php

namespace App\Http\Controllers\Admin;

use App\Events\UserInvited;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SendInvitationRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class UserInvitationController extends Controller
{
    /**
     * Display invitation management page
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $invitations = UserInvitation::with(['invitedBy:id,name,email', 'user:id,name,email', 'revokedBy:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'role' => $invitation->role,
                    'role_display' => ucwords(str_replace('_', ' ', $invitation->role)),
                    'invited_by' => [
                        'id' => $invitation->invitedBy->id,
                        'name' => $invitation->invitedBy->name,
                        'email' => $invitation->invitedBy->email,
                    ],
                    'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
                    'expires_at_human' => $invitation->expires_at->diffForHumans(),
                    'accepted_at' => $invitation->accepted_at?->format('Y-m-d H:i:s'),
                    'revoked' => $invitation->revoked,
                    'revoked_at' => $invitation->revoked_at?->format('Y-m-d H:i:s'),
                    'revoked_by' => $invitation->revokedBy ? [
                        'id' => $invitation->revokedBy->id,
                        'name' => $invitation->revokedBy->name,
                        'email' => $invitation->revokedBy->email,
                    ] : null,
                    'user' => $invitation->user ? [
                        'id' => $invitation->user->id,
                        'name' => $invitation->user->name,
                        'email' => $invitation->user->email,
                    ] : null,
                    'status' => $invitation->isAccepted() ? 'accepted' : ($invitation->isRevoked() ? 'revoked' : ($invitation->isExpired() ? 'expired' : 'pending')),
                    'is_valid' => $invitation->isValid(),
                    'is_pending' => $invitation->isPending(),
                    'created_at' => $invitation->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('admin/user-invitations', [
            'invitations' => $invitations,
            'roles' => ['bac_secretariat', 'bac_chairman', 'hope'],
        ]);
    }

    /**
     * Send a new invitation
     */
    public function store(SendInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Create invitation
            $invitation = UserInvitation::create([
                'email' => $validated['email'],
                'name' => $validated['name'],
                'role' => $validated['role'],
                'invited_by' => Auth::id(),
            ]);

            // Generate acceptance URL
            $acceptUrl = route('invitation.show', ['token' => $invitation->token]);

            // Send invitation email
            UserInvited::dispatch($invitation, $acceptUrl);

            Log::info('User invitation sent', [
                'invitation_id' => $invitation->id,
                'invited_by' => Auth::id(),
                'invitee_email' => $invitation->email,
                'role' => $invitation->role,
            ]);

            return redirect()->back()->with('success', "Invitation sent successfully to {$invitation->email}.");
        } catch (\Exception $e) {
            Log::error('Failed to send user invitation', [
                'error' => $e->getMessage(),
                'invited_by' => Auth::id(),
                'email' => $validated['email'],
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to send invitation. Please try again.']);
        }
    }

    /**
     * Resend an invitation
     */
    public function resend(UserInvitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);

        if (! $invitation->isPending()) {
            return redirect()->back()->withErrors(['error' => 'This invitation cannot be resent. It may have expired, been accepted, or revoked.']);
        }

        try {
            // Extend expiration
            $invitation->update([
                'expires_at' => now()->addDays(7),
            ]);

            // Generate acceptance URL
            $acceptUrl = route('invitation.show', ['token' => $invitation->token]);

            // Resend email
            UserInvited::dispatch($invitation, $acceptUrl);

            Log::info('User invitation resent', [
                'invitation_id' => $invitation->id,
                'resent_by' => Auth::id(),
                'invitee_email' => $invitation->email,
            ]);

            return redirect()->back()->with('success', "Invitation resent to {$invitation->email}.");
        } catch (\Exception $e) {
            Log::error('Failed to resend user invitation', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitation->id,
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to resend invitation. Please try again.']);
        }
    }

    /**
     * Revoke an invitation
     */
    public function destroy(UserInvitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);

        if ($invitation->isAccepted()) {
            return redirect()->back()->withErrors(['error' => 'Cannot revoke an invitation that has already been accepted.']);
        }

        if ($invitation->isRevoked()) {
            return redirect()->back()->withErrors(['error' => 'This invitation has already been revoked.']);
        }

        try {
            $invitation->revoke(Auth::user());

            Log::info('User invitation revoked', [
                'invitation_id' => $invitation->id,
                'revoked_by' => Auth::id(),
                'invitee_email' => $invitation->email,
            ]);

            return redirect()->back()->with('success', 'Invitation revoked successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to revoke user invitation', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitation->id,
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to revoke invitation. Please try again.']);
        }
    }
}

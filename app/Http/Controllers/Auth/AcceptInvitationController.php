<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AcceptInvitationRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogService;
use App\Services\BlockchainRpcClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private AuditLogService $AuditLogService,
    ) {}

    /**
     * Show the invitation acceptance form
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();

        // Check if invitation is valid
        if (! $invitation->isValid()) {
            $reason = $invitation->isExpired()
                ? 'This invitation has expired.'
                : ($invitation->isAccepted()
                    ? 'This invitation has already been accepted.'
                    : 'This invitation is no longer valid.');

            return redirect()->route('login')->with('error', $reason);
        }

        return Inertia::render('auth/accept-invitation', [
            'invitation' => [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'role_display' => ucwords(str_replace('_', ' ', $invitation->role)),
                'invited_by' => $invitation->invitedBy->name,
                'expires_at' => $invitation->expires_at->format('F j, Y \a\t g:i A'),
                'expires_at_human' => $invitation->expires_at->diffForHumans(),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Accept the invitation and create user account
     */
    public function accept(AcceptInvitationRequest $request, BlockchainRpcClient $multichain, string $token): RedirectResponse
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();

        // Validate invitation
        if (! $invitation->isValid()) {
            $reason = $invitation->isExpired()
                ? 'This invitation has expired.'
                : ($invitation->isAccepted()
                    ? 'This invitation has already been accepted.'
                    : 'This invitation is no longer valid.');

            return redirect()->route('login')->with('error', $reason);
        }

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // Generate blockchain address
            $blockchainAddress = $multichain->getnewaddress();

            // Create user
            // Password is set explicitly (not via $fillable) to prevent mass assignment attacks
            // Use User::make() to avoid NOT NULL constraint violation on password column
            $user = User::make([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'blockchain_address' => $blockchainAddress,
                'email_verified_at' => now(), // Auto-verify email since invitation was sent to it
            ]);

            // Password is set explicitly (not via $fillable) to prevent mass assignment attacks
            $user->password = Hash::make($validated['password']);
            $user->save();

            // Assign role
            $user->assignRole($invitation->role);

            // Mark invitation as accepted
            $invitation->markAsAccepted($user);

            DB::commit();

            Log::info('User accepted invitation and account created', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
            ]);

            $this->AuditLogService->log(
                'auth.invitation_accepted',
                'user',
                (string) $user->id,
                [],
                ['role' => $invitation->role, 'invited_by' => $invitation->invited_by],
            );

            // Log the user in
            Auth::login($user);

            // Redirect to role-specific dashboard
            return redirect($this->redirectToDashboard($request, $user))
                ->with('success', 'Welcome to Procuchain! Your account has been created successfully.');
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            Log::error('Failed to accept invitation', [
                'error' => 'An error occurred accepting the invitation.',
                'invitation_id' => $invitation->id,
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return redirect()->back()->with('error', 'Failed to create account. Please try again or contact support.');
        }
    }
}

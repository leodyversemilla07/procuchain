<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MfaController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show the MFA settings page.
     */
    public function edit(Request $request): Response
    {
        $user = Auth::user();
        
        return Inertia::render('settings/mfa', [
            'mfaEnabled' => $user->hasMfaEnabled(),
            'backupCodesCount' => $user->getRemainingBackupCodesCount(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Generate QR code and secret for MFA setup
     */
    public function setup(Request $request)
    {
        $user = Auth::user();
        
        if ($user->hasMfaEnabled()) {
            return back()->withErrors(['mfa' => 'MFA is already enabled for your account.']);
        }

        // Generate a new secret
        $secret = $this->google2fa->generateSecretKey();
        
        // Store the secret temporarily (not yet enabled)
        $user->update(['google2fa_secret' => $secret]);
        
        // Generate QR code as data URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );
        
        // Generate QR code as SVG data URL
        $qrCodeDataUrl = 'data:image/svg+xml;base64,' . base64_encode(
            QrCode::format('svg')->size(256)->generate($qrCodeUrl)
        );
        
        return response()->json([
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeDataUrl,
        ]);
    }

    /**
     * Enable MFA after verifying the TOTP code
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();
        
        if ($user->hasMfaEnabled()) {
            return back()->withErrors(['mfa' => 'MFA is already enabled for your account.']);
        }

        if (!$user->google2fa_secret) {
            return back()->withErrors(['mfa' => 'Please setup MFA first by generating a QR code.']);
        }

        // Verify the TOTP code
        $valid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        
        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        // Enable MFA
        $user->update([
            'mfa_enabled' => true,
            'mfa_enabled_at' => now(),
        ]);

        // Generate backup codes
        $backupCodes = $user->generateBackupCodes();

        return back()->with([
            'status' => 'mfa-enabled',
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Disable MFA
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password',
            'code' => 'required|string',
        ]);

        $user = Auth::user();
        
        if (!$user->hasMfaEnabled()) {
            return back()->withErrors(['mfa' => 'MFA is not enabled for your account.']);
        }

        // Check if it's a TOTP code or backup code
        $validTotp = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        $validBackup = $user->verifyBackupCode($request->code);

        if (!$validTotp && !$validBackup) {
            return back()->withErrors(['code' => 'Invalid verification code or backup code.']);
        }

        // Disable MFA
        $user->update([
            'mfa_enabled' => false,
            'mfa_enabled_at' => null,
            'google2fa_secret' => null,
            'backup_codes' => null,
            'backup_codes_generated_at' => null,
        ]);

        return back()->with('status', 'mfa-disabled');
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();
        
        if (!$user->hasMfaEnabled()) {
            return back()->withErrors(['mfa' => 'MFA is not enabled for your account.']);
        }

        $backupCodes = $user->generateBackupCodes();

        return response()->json([
            'backupCodes' => $backupCodes,
            'message' => 'Backup codes regenerated successfully.',
        ]);
    }

    /**
     * Verify MFA code during login
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        if (!session('mfa_user_id')) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find(session('mfa_user_id'));
        
        if (!$user || !$user->hasMfaEnabled()) {
            session()->forget(['mfa_user_id', 'remember_user']);
            return redirect()->route('login');
        }

        // Check if it's a TOTP code or backup code
        $validTotp = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        $validBackup = $user->verifyBackupCode($request->code);

        if (!$validTotp && !$validBackup) {
            return back()->withErrors(['code' => 'Invalid verification code or backup code.']);
        }

        // Complete login
        $remember = session('remember_user', false);
        session()->forget(['mfa_user_id', 'remember_user']);
        
        Auth::login($user, $remember);
        session()->regenerate();
        
        // Mark MFA as verified for this session
        session(['mfa_verified_' . $user->id => true]);
        
        // Clear rate limiting
        \Illuminate\Support\Facades\RateLimiter::clear(
            \Illuminate\Support\Str::transliterate(\Illuminate\Support\Str::lower($user->email).'|'.$request->ip())
        );
        
        // Log successful login
        app(\App\Services\LoginTrackingService::class)->logLogin($user, $request);

        // Redirect to appropriate dashboard based on user role
        switch ($user->role) {
            case 'bac_secretariat':
                return redirect()->intended(route('bac-secretariat.dashboard'));
            case 'bac_chairman':
                return redirect()->intended(route('bac-chairman.dashboard'));
            case 'hope':
                return redirect()->intended(route('hope.dashboard'));
            case 'admin':
                return redirect()->intended(route('admin.dashboard'));
            default:
                return redirect('/');
        }
    }
}

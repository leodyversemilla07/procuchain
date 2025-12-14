@extends('emails.layouts.base')

@section('title', 'Account Security Alert - Account Locked')

@section('header-title', 'Account Locked')
@section('header-subtitle', 'Security Alert')

@section('content')
    <p class="greeting">Dear {{ $user->name }},</p>

    <div class="alert-box alert-danger">
        <div class="alert-title">🔒 Your Account Has Been Locked</div>
        <div class="alert-message">
            Your account has been temporarily locked for security reasons. This is a protective measure to keep your account
            safe.
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Lock Information</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Account</td>
                <td class="details-value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="details-label">Reason</td>
                <td class="details-value">{{ $lockReason }}</td>
            </tr>
            <tr>
                <td class="details-label">Locked At</td>
                <td class="details-value">{{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
            @if ($unlockTime !== 'Unknown')
                <tr>
                    <td class="details-label">Auto-Unlock</td>
                    <td class="details-value"><span class="badge badge-warning">{{ $unlockTime }}</span></td>
                </tr>
            @endif
        </table>
    </div>

    <div class="info-box">
        <div class="info-box-title">Security Best Practices</div>
        <ul>
            <li>Use a strong, unique password for your account</li>
            <li>Enable two-factor authentication if available</li>
            <li>Never share your login credentials with anyone</li>
            <li>Log out from shared or public computers</li>
            <li>Report any suspicious account activity immediately</li>
        </ul>
    </div>

    <p class="message-text">
        If you believe this lock was placed in error or need immediate access to your account, please contact our support
        team.
    </p>

    <div class="cta-container">
        <a href="mailto:{{ $supportEmail }}" class="cta-button">Contact Support</a>
    </div>

    <p class="message-text">Thank you for helping us keep your account secure.</p>
@endsection

@extends('emails.layouts.base')

@section('title', 'Account Security Update - Account Unlocked')

@section('header-title', 'Account Unlocked')
@section('header-subtitle', 'Security Update')

@section('content')
    <p class="greeting">Dear {{ $user->name }},</p>

    <div class="alert-box alert-success">
        <div class="alert-title">✅ Your Account Has Been Unlocked</div>
        <div class="alert-message">
            Your account has been unlocked and you can now access the ProcuChain system normally.
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Unlock Information</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Account</td>
                <td class="details-value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="details-label">Status</td>
                <td class="details-value"><span class="badge badge-success">Active</span></td>
            </tr>
            <tr>
                <td class="details-label">Unlocked At</td>
                <td class="details-value">{{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
            <tr>
                <td class="details-label">Method</td>
                <td class="details-value">{{ $wasAutoUnlocked ? 'Automatic expiration' : $unlockReason }}</td>
            </tr>
        </table>
    </div>

    <div class="alert-box alert-warning">
        <div class="alert-title">⚠️ Security Reminder</div>
        <div class="alert-message">
            Your account was previously locked due to security concerns. To protect your account:
        </div>
    </div>

    <div class="info-box">
        <div class="info-box-title">Recommended Actions</div>
        <ul>
            <li>Review your recent login activity</li>
            <li>Change your password if you suspect it may be compromised</li>
            <li>Use a strong, unique password</li>
            <li>Be cautious when accessing from public networks</li>
            <li>Report any suspicious activity immediately</li>
        </ul>
    </div>

    <div class="cta-container">
        <a href="{{ $loginUrl }}" class="cta-button">Access Your Account</a>
    </div>

    <p class="message-text">
        If you did not request this unlock or notice any suspicious activity, please contact our support team immediately.
    </p>
@endsection

@extends('emails.layouts.base')

@section('title', 'Password Reset - ProcuChain')

@section('header-title', 'Password Reset')
@section('header-subtitle', 'Account Security')

@section('content')
    <p class="greeting">Hello!</p>

    <div class="alert-box alert-info">
        <div class="alert-title">🔐 Password Reset Request</div>
        <div class="alert-message">
            We received a request to reset your password for your ProcuChain account. If you made this request, click the
            button below to reset your password.
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Reset Information</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Email</td>
                <td class="details-value">{{ $email }}</td>
            </tr>
            <tr>
                <td class="details-label">Expires</td>
                <td class="details-value"><span class="badge badge-warning">60 minutes</span></td>
            </tr>
            <tr>
                <td class="details-label">Requested</td>
                <td class="details-value">{{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $resetUrl }}" class="cta-button">Reset Your Password</a>
    </div>

    <div class="link-section">
        <span class="link-label">If the button doesn't work, copy and paste this link:</span>
        <a href="{{ $resetUrl }}" class="link-url">{{ $resetUrl }}</a>
    </div>

    <div class="info-box">
        <div class="info-box-title">Security Best Practices</div>
        <ul>
            <li>Choose a strong password with at least 8 characters</li>
            <li>Use a mix of uppercase, lowercase, numbers, and symbols</li>
            <li>Don't reuse passwords from other accounts</li>
            <li>Enable two-factor authentication when available</li>
            <li>Never share your password with anyone</li>
        </ul>
    </div>

    <p class="message-text">
        If you didn't request a password reset, please ignore this email. Your password will remain unchanged and your
        account will stay secure.
    </p>

    <p class="message-text">
        <strong>For security reasons, this link will expire in 60 minutes.</strong>
    </p>
@endsection

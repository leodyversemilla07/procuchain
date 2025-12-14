@extends('emails.layouts.base')

@section('title', 'Verify Your Email Address')

@section('header-title', 'Verify Your Email')
@section('header-subtitle', 'Confirm your email address to continue')

@section('content')
    <p class="greeting">Hello {{ $user->name }},</p>

    <p class="message-text">
        Thank you for registering with Procuchain! To complete your registration and access the system, 
        please verify your email address by clicking the button below.
    </p>

    <div class="alert-box alert-info">
        <div class="alert-title">🔐 Email Verification Required</div>
        <div class="alert-message">
            For security purposes, we need to confirm that this email address belongs to you. 
            This helps protect your account and ensures secure communication.
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Account Information</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Name</td>
                <td class="details-value">{{ $user->name }}</td>
            </tr>
            <tr>
                <td class="details-label">Email</td>
                <td class="details-value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="details-label">Registration Date</td>
                <td class="details-value">{{ $user->created_at->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>

    <p class="message-text">
        Click the button below to verify your email address and activate your account:
    </p>

    <div class="cta-container">
        <a href="{{ $verificationUrl }}" class="cta-button">Verify Email Address</a>
    </div>

    <div class="info-box">
        <div class="info-box-title">What happens after verification?</div>
        <ul>
            <li>Your account will be fully activated</li>
            <li>You'll gain access to all system features based on your role</li>
            <li>You'll be able to participate in procurement processes</li>
            <li>Your actions will be recorded on the blockchain for transparency</li>
        </ul>
    </div>

    <div class="alert-box alert-warning">
        <div class="alert-title">⏰ Verification Link Expires</div>
        <div class="alert-message">
            This verification link will expire in 60 minutes for security reasons. 
            If the link expires, you can request a new verification email from the login page.
        </div>
    </div>

    <p class="message-text">
        If you did not create an account with Procuchain, please ignore this email. 
        No account will be created if you don't verify your email address.
    </p>

    <p class="small-text">
        If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:
    </p>

    <p class="small-text" style="word-break: break-all; overflow-wrap: break-word; color: #6b7280;">
        {{ $verificationUrl }}
    </p>
@endsection

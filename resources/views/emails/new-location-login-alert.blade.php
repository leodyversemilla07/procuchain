@extends('emails.layouts.base')

@section('title', 'Security Alert - New Login Location')

@section('header-title', 'New Login Detected')
@section('header-subtitle', 'Security Alert')

@section('content')
    <p class="greeting">Hello {{ $user->name }},</p>

    <div class="alert-box alert-warning">
        <div class="alert-title">📍 New Location Sign-In Detected</div>
        <div class="alert-message">
            We detected a sign-in to your ProcuChain account from a new location: <strong>{{ $formattedLocation }}</strong>
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Login Details</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Time</td>
                <td class="details-value">{{ $loginTime->format('F j, Y \a\t g:i A') }}</td>
            </tr>
            <tr>
                <td class="details-label">Location</td>
                <td class="details-value">{{ $formattedLocation }}</td>
            </tr>
            @if(isset($location['city']))
            <tr>
                <td class="details-label">City</td>
                <td class="details-value">{{ $location['city'] }}</td>
            </tr>
            @endif
            @if(isset($location['region']))
            <tr>
                <td class="details-label">Region</td>
                <td class="details-value">{{ $location['region'] }}</td>
            </tr>
            @endif
            @if(isset($location['country']))
            <tr>
                <td class="details-label">Country</td>
                <td class="details-value">{{ $location['country'] }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">IP Address</td>
                <td class="details-value">{{ $ipAddress }}</td>
            </tr>
            @if($userAgent)
            <tr>
                <td class="details-label">Device</td>
                <td class="details-value">{{ Str::limit($userAgent, 80) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="alert-box alert-success">
        <div class="alert-title">✅ Was This You?</div>
        <div class="alert-message">
            If you recognize this login activity (e.g., you're traveling or using a VPN), you can safely ignore this email. This location will be remembered for future logins.
        </div>
    </div>

    <div class="alert-box alert-danger">
        <div class="alert-title">🚨 Not You? Take Action Immediately</div>
        <div class="alert-message">
            If you did not perform this login, your account may be compromised.
        </div>
    </div>

    <div class="info-box">
        <div class="info-box-title">Recommended Actions</div>
        <ul>
            <li>Change your password immediately</li>
            <li>Enable two-factor authentication (2FA) if not already active</li>
            <li>Review your recent account activity</li>
            <li>Contact support if you notice unauthorized changes</li>
        </ul>
    </div>

    <div class="cta-container">
        <a href="{{ url('/settings/password') }}" class="cta-button">Change Password</a>
    </div>

    <div class="info-box">
        <div class="info-box-title">💡 Security Tip</div>
        <ul>
            <li>Enable two-factor authentication (2FA) for an extra layer of security. Even if someone obtains your password, they won't be able to access your account without the second factor.</li>
        </ul>
    </div>
@endsection

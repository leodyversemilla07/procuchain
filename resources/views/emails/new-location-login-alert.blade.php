<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert - New Login Location</title>
    <style>
        /* Reset styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px auto;
            display: block;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 8px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 30px 20px;
        }

        .greeting {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
            color: #333;
        }

        .alert-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .alert-title {
            font-size: 16px;
            font-weight: 600;
            color: #b45309;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .alert-icon {
            margin-right: 8px;
            font-size: 20px;
        }

        .alert-message {
            font-size: 15px;
            line-height: 1.5;
            color: #92400e;
        }

        .info-section {
            margin-top: 25px;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            flex-wrap: wrap;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6b7280;
            width: 140px;
            flex-shrink: 0;
        }

        .info-value {
            color: #333;
            flex: 1;
            word-break: break-word;
        }

        .location-highlight {
            background-color: #fef3c7;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            color: #92400e;
        }

        .action-section {
            margin-top: 25px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 8px;
        }

        .action-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .action-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 15px;
        }

        .action-list {
            list-style: disc;
            padding-left: 20px;
            margin-bottom: 15px;
        }

        .action-list li {
            font-size: 14px;
            color: #374151;
            margin-bottom: 8px;
        }

        .button-container {
            text-align: center;
            margin-top: 20px;
        }

        .secure-button {
            display: inline-block;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin: 5px;
        }

        .secondary-button {
            display: inline-block;
            background: #6b7280;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin: 5px;
        }

        .was-you-section {
            margin-top: 25px;
            padding: 20px;
            background-color: #ecfdf5;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }

        .was-you-title {
            font-size: 15px;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 8px;
        }

        .was-you-text {
            font-size: 14px;
            color: #047857;
        }

        .footer {
            padding: 25px 20px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #f59e0b;
            text-decoration: none;
            margin: 0 8px;
            font-size: 13px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .security-tip {
            margin-top: 20px;
            padding: 15px;
            background-color: #eff6ff;
            border-radius: 6px;
            font-size: 13px;
            color: #1e40af;
        }

        .security-tip strong {
            display: block;
            margin-bottom: 5px;
        }

        @media only screen and (max-width: 480px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .content {
                padding: 20px 15px;
            }

            .info-list li {
                flex-direction: column;
            }

            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }

            .button-container .secure-button,
            .button-container .secondary-button {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <img src="{{ url('/images/logo.png') }}" alt="{{ $appName }}" class="header-logo">
            <h1>🔔 New Login Location Detected</h1>
            <p class="subtitle">Security Alert for Your Account</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello {{ $user->name }},</p>

            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-title">
                    <span class="alert-icon">📍</span>
                    New Location Sign-In
                </div>
                <p class="alert-message">
                    We detected a sign-in to your {{ $appName }} account from a new location:
                    <span class="location-highlight">{{ $formattedLocation }}</span>
                </p>
            </div>

            <!-- Login Details -->
            <div class="info-section">
                <h3 class="info-title">Login Details</h3>
                <ul class="info-list">
                    <li>
                        <span class="info-label">Time:</span>
                        <span class="info-value">{{ $loginTime }}</span>
                    </li>
                    <li>
                        <span class="info-label">Location:</span>
                        <span class="info-value">{{ $formattedLocation }}</span>
                    </li>
                    @if(isset($location['city']))
                    <li>
                        <span class="info-label">City:</span>
                        <span class="info-value">{{ $location['city'] }}</span>
                    </li>
                    @endif
                    @if(isset($location['region']))
                    <li>
                        <span class="info-label">Region:</span>
                        <span class="info-value">{{ $location['region'] }}</span>
                    </li>
                    @endif
                    @if(isset($location['country']))
                    <li>
                        <span class="info-label">Country:</span>
                        <span class="info-value">{{ $location['country'] }}</span>
                    </li>
                    @endif
                    <li>
                        <span class="info-label">IP Address:</span>
                        <span class="info-value">{{ $ipAddress }}</span>
                    </li>
                    @if($userAgent)
                    <li>
                        <span class="info-label">Device/Browser:</span>
                        <span class="info-value">{{ Str::limit($userAgent, 100) }}</span>
                    </li>
                    @endif
                    @if(isset($location['isp']))
                    <li>
                        <span class="info-label">ISP:</span>
                        <span class="info-value">{{ $location['isp'] }}</span>
                    </li>
                    @endif
                </ul>
            </div>

            <!-- Was This You Section -->
            <div class="was-you-section">
                <p class="was-you-title">✅ Was this you?</p>
                <p class="was-you-text">
                    If you recognize this login activity (e.g., you're traveling or using a VPN), you can safely ignore this email. This location will be remembered for future logins.
                </p>
            </div>

            <!-- Action Section (if not you) -->
            <div class="action-section">
                <p class="action-title">🚨 Not you? Take action immediately:</p>
                <p class="action-text">If you did not perform this login, your account may be compromised. We recommend:</p>
                <ul class="action-list">
                    <li>Change your password immediately</li>
                    <li>Enable two-factor authentication (2FA) if not already active</li>
                    <li>Review your recent account activity</li>
                    <li>Contact support if you notice any unauthorized changes</li>
                </ul>
                <div class="button-container">
                    <a href="{{ url('/settings/password') }}" class="secure-button">Change Password</a>
                    <a href="{{ url('/settings/two-factor') }}" class="secondary-button">Enable 2FA</a>
                </div>
            </div>

            <!-- Security Tip -->
            <div class="security-tip">
                <strong>💡 Security Tip:</strong>
                Enable two-factor authentication (2FA) for an extra layer of security. Even if someone obtains your password, they won't be able to access your account without the second factor.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                This is an automated security notification from {{ $appName }}.<br>
                You're receiving this because a login was detected from a new location.
            </p>
            <p class="footer-text">
                If you have questions, contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            </p>
            <div class="footer-links">
                <a href="{{ url('/') }}">Visit {{ $appName }}</a> |
                <a href="{{ url('/settings/email-notification') }}">Email Preferences</a>
            </div>
            <p class="footer-text" style="margin-top: 15px;">
                © {{ date('Y') }} {{ $appName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>

</html>

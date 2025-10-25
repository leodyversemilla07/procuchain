<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - ProcuChain</title>
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
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
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
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .alert-title {
            font-size: 16px;
            font-weight: 600;
            color: #d97706;
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

        .details-section {
            margin: 30px 0;
        }

        .details-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .details-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .detail-row {
            display: table-row;
        }

        .detail-label {
            display: table-cell;
            font-weight: 600;
            color: #666;
            padding: 8px 0;
            width: 30%;
            vertical-align: top;
        }

        .detail-value {
            display: table-cell;
            color: #333;
            padding: 8px 0 8px 15px;
            vertical-align: top;
        }

        .security-tips {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }

        .security-tips-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .security-tips ul {
            list-style-type: none;
            padding: 0;
        }

        .security-tips li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .security-tips li:before {
            content: "•";
            color: #2563eb;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%) !important;
            color: white !important;
            text-decoration: none !important;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
            transition: background 0.2s ease;
            border: none !important;
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%) !important;
            text-decoration: none !important;
            color: white !important;
        }

        .cta-button:visited {
            color: white !important;
            text-decoration: none !important;
        }

        .cta-button:active {
            color: white !important;
            text-decoration: none !important;
        }

        .cta-button:link {
            color: white !important;
            text-decoration: none !important;
        }

        /* Additional specificity for email clients */
        a.cta-button {
            color: white !important;
            text-decoration: none !important;
        }

        a.cta-button:hover {
            color: white !important;
            text-decoration: none !important;
        }

        a.cta-button:visited {
            color: white !important;
            text-decoration: none !important;
        }

        a.cta-button:active {
            color: white !important;
            text-decoration: none !important;
        }

        a.cta-button:link {
            color: white !important;
            text-decoration: none !important;
        }

        .cta-container {
            text-align: center;
            margin: 30px 0;
        }

        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #e9ecef;
        }

        .footer-logo {
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .time-badge {
            background-color: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reset-link-section {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
        }

        .reset-link-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .reset-link {
            color: #2563eb;
            text-decoration: none;
            word-break: break-all;
        }

        .reset-link:hover {
            text-decoration: underline;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .content {
                padding: 20px 15px;
            }

            .header {
                padding: 20px 15px;
            }

            .header-logo {
                width: 50px;
                height: 50px;
                margin-bottom: 12px;
            }

            .details-grid {
                display: block;
            }

            .detail-row {
                display: block;
                margin-bottom: 10px;
            }

            .detail-label,
            .detail-value {
                display: block;
                width: 100%;
                padding: 2px 0;
            }

            .detail-value {
                padding-left: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">

        <!-- Header -->
        <div class="header">
            <img src="{{ asset('logo.png') }}" alt="ProcuChain Logo" class="header-logo">
            <h1>ProcuChain</h1>
            <div class="subtitle">Password Reset Request</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">Hello!</div>

            <!-- Password Reset Alert -->
            <div class="alert-box">
                <div class="alert-title">
                    🔐 Password Reset Request
                </div>
                <div class="alert-message">
                    We received a request to reset your password for your ProcuChain account. If you made this request,
                    click the button below to reset your password.
                </div>
            </div>

            <!-- Reset Details -->
            <div class="details-section">
                <div class="details-title">Reset Information</div>
                <div class="details-grid">
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value">{{ $email }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Expires:</div>
                        <div class="detail-value">
                            <span class="time-badge">60 minutes</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Requested:</div>
                        <div class="detail-value">{{ now()->format('F j, Y \a\t g:i A') }}</div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="cta-container">
                <a href="{{ $resetUrl }}" class="cta-button">
                    Reset Your Password
                </a>
            </div>

            <!-- Alternative Link -->
            <div class="reset-link-section">
                <div class="reset-link-label">If the button doesn't work, copy and paste this link:</div>
                <a href="{{ $resetUrl }}" class="reset-link">{{ $resetUrl }}</a>
            </div>

            <!-- Security Tips -->
            <div class="security-tips">
                <div class="security-tips-title">Security Best Practices</div>
                <ul>
                    <li>Choose a strong password with at least 8 characters</li>
                    <li>Use a mix of uppercase, lowercase, numbers, and symbols</li>
                    <li>Don't reuse passwords from other accounts</li>
                    <li>Enable two-factor authentication when available</li>
                    <li>Never share your password with anyone</li>
                </ul>
            </div>

            <p style="margin-top: 20px; color: #666;">
                If you didn't request a password reset, please ignore this email. Your password will remain unchanged
                and your account will stay secure.
            </p>

            <p style="margin-top: 30px; color: #333;">
                For security reasons, this link will expire in 60 minutes.
            </p>

            <p style="margin-top: 20px; color: #333;">
                If you need help, please contact our support team.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">ProcuChain</div>
            <div>Blockchain-powered Document Management System for Bids and Awards Committee Office</div>
            <div style="margin-top: 10px; font-size: 12px; color: #999;">
                This is an automated password reset notification. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>

</html>

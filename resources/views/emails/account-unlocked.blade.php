<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Security Update - Account Unlocked</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

        .success-box {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .success-title {
            font-size: 16px;
            font-weight: 600;
            color: #10b981;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .success-icon {
            margin-right: 8px;
            font-size: 20px;
        }

        .success-message {
            font-size: 15px;
            line-height: 1.5;
            color: #15803d;
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

        .security-reminder {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }

        .security-reminder-title {
            font-size: 16px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .security-reminder-icon {
            margin-right: 8px;
            font-size: 20px;
        }

        .security-reminder p {
            color: #92400e;
            margin-bottom: 10px;
        }

        .security-reminder ul {
            list-style-type: none;
            padding: 0;
            margin-top: 10px;
        }

        .security-reminder li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
            color: #92400e;
        }

        .security-reminder li:before {
            content: "•";
            color: #f59e0b;
            font-weight: bold;
            position: absolute;
            left: 0;
        }        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
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
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
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
            color: #0d9488;
            margin-bottom: 10px;
        }

        .status-badge {
            background-color: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            <div class="subtitle">Account Security Update</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">Dear {{ $user->name }},</div>            
            <!-- Success Alert -->
            <div class="success-box">
                <div class="success-title">
                    Account Successfully Unlocked
                </div>
                <div class="success-message">
                    Your account has been unlocked and you can now access the ProcuChain system normally.
                </div>
            </div>

            <!-- Unlock Details -->
            <div class="details-section">
                <div class="details-title">Unlock Information</div>
                <div class="details-grid">
                    <div class="detail-row">
                        <div class="detail-label">Account:</div>
                        <div class="detail-value">{{ $user->email }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="status-badge">Active</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Unlocked At:</div>
                        <div class="detail-value">{{ now()->format('F j, Y \a\t g:i A') }}</div>
                    </div>
                    @if(!$wasAutoUnlocked)
                    <div class="detail-row">
                        <div class="detail-label">Unlock Method:</div>
                        <div class="detail-value">{{ $unlockReason }}</div>
                    </div>
                    @else
                    <div class="detail-row">
                        <div class="detail-label">Unlock Method:</div>
                        <div class="detail-value">Automatic expiration</div>
                    </div>
                    @endif
                </div>
            </div>            
            
            <!-- Security Reminder -->
            <div class="security-reminder">
                <div class="security-reminder-title">
                    Important Security Reminder
                </div>
                <p>
                    Your account was previously locked due to security concerns. To help protect your account in the future:
                </p>
                <ul>
                    <li>Review your recent login activity</li>
                    <li>Change your password if you suspect it may be compromised</li>
                    <li>Use a strong, unique password</li>
                    <li>Be cautious when accessing your account from public networks</li>
                    <li>Report any suspicious activity immediately</li>
                </ul>
            </div>

            <!-- Call to Action -->
            <div class="cta-container">
                <a href="{{ $loginUrl }}" class="cta-button">
                    Access Your Account
                </a>
            </div>

            <p style="margin-top: 20px; color: #666;">
                If you did not request this unlock or if you notice any suspicious activity on your account, please contact our support team immediately.
            </p>

            <p style="margin-top: 30px; color: #333;">
                Thank you for helping us keep your account secure.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">ProcuChain</div>
            <div>Blockchain-powered Document Management System for Bids and Awards Committee Office</div>
            <div style="margin-top: 10px; font-size: 12px; color: #999;">
                This is an automated security notification. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>

</html>

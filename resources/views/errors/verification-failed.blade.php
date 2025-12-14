<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Failed - Procuchain</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            color: #dc2626;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .alert {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }

        .alert-title {
            font-size: 14px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 8px;
        }

        .alert-message {
            font-size: 13px;
            color: #78350f;
            line-height: 1.5;
        }

        .reasons {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .reasons h3 {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        .reasons ul {
            list-style: none;
            padding: 0;
        }

        .reasons li {
            font-size: 13px;
            color: #4b5563;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: start;
        }

        .reasons li:last-child {
            border-bottom: none;
        }

        .reasons li:before {
            content: "•";
            color: #f59e0b;
            font-weight: bold;
            margin-right: 12px;
            font-size: 18px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-direction: column;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #0d9488;
            color: white;
        }

        .btn-primary:hover {
            background: #0f766e;
        }

        .btn-secondary {
            background: white;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }

        @media (min-width: 640px) {
            .buttons {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1>Email Verification Link Invalid</h1>
        <p class="subtitle">
            The verification link you clicked is no longer valid or has expired.
        </p>

        <div class="alert">
            <div class="alert-title">⏰ Link Expired or Invalid</div>
            <div class="alert-message">
                Email verification links expire after 60 minutes for security purposes. This link may have expired or was already used.
            </div>
        </div>

        <div class="reasons">
            <h3>Common Reasons</h3>
            <ul>
                <li>The link has expired (older than 60 minutes)</li>
                <li>The link was already used to verify your email</li>
                <li>You clicked an old verification email</li>
                <li>The link was modified or incomplete when copied</li>
            </ul>
        </div>

        <div class="buttons">
            <a href="{{ route('verification.notice') }}" class="btn btn-primary">
                Request New Link
            </a>
            <a href="{{ route('login') }}" class="btn btn-secondary">
                Back to Login
            </a>
        </div>

        <div class="footer">
            Need help? Contact support at support@procuchain.tech
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'ProcuChain')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            color: #111827;
            background-color: #f1f5f9;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 32px 16px;
        }

        .email-container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 0;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Header */
        .header {
            background: #0d9488;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }

        .header-logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px auto;
            display: block;
            border-radius: 0;
        }

        .header-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
            color: #ffffff;
        }

        .header-subtitle {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
            font-weight: 400;
            color: #ffffff;
        }

        /* Content */
        .content {
            padding: 28px 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .greeting {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: #111827;
        }

        .message-text {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 14px 0;
        }

        /* Alert boxes */
        .alert-box {
            padding: 14px 16px;
            margin: 20px 0;
            border-radius: 0;
            border-left: 3px solid;
        }

        .alert-box.alert-info {
            background-color: #f0fdfa;
            border-left-color: #0d9488;
        }

        .alert-box.alert-success {
            background-color: #f0fdf4;
            border-left-color: #10b981;
        }

        .alert-box.alert-warning {
            background-color: #fffbeb;
            border-left-color: #f59e0b;
        }

        .alert-box.alert-danger {
            background-color: #fef2f2;
            border-left-color: #dc2626;
        }

        .alert-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .alert-box.alert-info .alert-title { color: #0f766e; }
        .alert-box.alert-success .alert-title { color: #059669; }
        .alert-box.alert-warning .alert-title { color: #b45309; }
        .alert-box.alert-danger .alert-title { color: #b91c1c; }

        .alert-message {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .alert-box.alert-info .alert-message { color: #134e4a; }
        .alert-box.alert-success .alert-message { color: #166534; }
        .alert-box.alert-warning .alert-message { color: #78350f; }
        .alert-box.alert-danger .alert-message { color: #7f1d1d; }

        /* Details section */
        .details-section {
            margin: 24px 0;
        }

        .details-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            margin: 0 0 12px 0;
            padding: 0 0 8px 0;
            border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .details-table tr {
            border-bottom: 1px solid #f3f4f6;
        }

        .details-table tr:last-child {
            border-bottom: none;
        }

        .details-table td {
            padding: 8px 0;
            vertical-align: top;
            font-size: 13px;
        }

        .details-label {
            font-weight: 600;
            color: #6b7280;
            width: 35%;
        }

        .details-value {
            color: #111827;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 0;
            font-size: 11px;
            font-weight: 600;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .badge-primary {
            background-color: #ccfbf1;
            color: #0f766e;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* CTA Button */
        .cta-container {
            text-align: center;
            margin: 24px 0;
        }

        .cta-button {
            display: inline-block;
            background-color: #0d9488;
            color: #ffffff !important;
            text-decoration: none !important;
            padding: 12px 28px;
            border-radius: 0;
            font-weight: 600;
            font-size: 14px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .cta-button:hover {
            background-color: #0f766e;
        }

        /* Info box */
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0;
            padding: 14px 16px;
            margin: 16px 0;
        }

        .info-box-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: #374151;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-box li {
            padding: 4px 0 4px 16px;
            position: relative;
            font-size: 13px;
            color: #4b5563;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.4;
        }

        .info-box li:before {
            content: "•";
            color: #0d9488;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        /* Link section */
        .link-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0;
            padding: 12px 14px;
            margin: 16px 0;
            word-break: break-all;
        }

        .link-label {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            margin: 0 0 6px 0;
            display: block;
        }

        .link-url {
            color: #0d9488;
            text-decoration: none;
            font-size: 12px;
            font-family: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
            word-break: break-all;
        }

        /* Footer */
        .footer {
            background-color: #f8fafc;
            padding: 20px 24px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer-logo {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-weight: 700;
            color: #0d9488;
            font-size: 14px;
            margin: 0 0 6px 0;
        }

        .footer-text {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
            margin: 0;
        }

        .footer-disclaimer {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            color: #9ca3af;
            margin: 14px 0 0 0;
            padding: 14px 0 0 0;
            border-top: 1px solid #e2e8f0;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 16px 12px;
            }

            .email-container {
                border-radius: 0;
            }

            .header {
                padding: 24px 16px;
            }

            .header-title {
                font-size: 18px;
            }

            .content {
                padding: 20px 16px;
            }

            .details-label {
                width: 40%;
            }

            .cta-button {
                display: block;
                text-align: center;
            }
        }
    </style>
    @yield('additional-styles')
</head>

<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div class="email-wrapper" style="background-color: #f1f5f9; padding: 32px 16px;">
        <div class="email-container" style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 0; border: 1px solid #e2e8f0; overflow: hidden;">
            <!-- Header -->
            <div class="header" style="background-color: #0d9488; color: #ffffff; padding: 28px 24px; text-align: center;">
                <img src="{{ asset('logo.png') }}" alt="ProcuChain Logo" class="header-logo" style="width: 48px; height: 48px; margin: 0 auto 12px auto; display: block; border-radius: 0;">
                <h1 class="header-title" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 20px; font-weight: 600; margin: 0 0 4px 0; color: #ffffff;">@yield('header-title', 'ProcuChain')</h1>
                <p class="header-subtitle" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; opacity: 0.9; margin: 0; color: #ffffff;">@yield('header-subtitle', 'Blockchain-Powered Procurement')</p>
            </div>

            <!-- Content -->
            <div class="content" style="padding: 28px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="footer" style="background-color: #f8fafc; padding: 20px 24px; text-align: center; border-top: 1px solid #e2e8f0;">
                <div class="footer-logo" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-weight: 700; color: #0d9488; font-size: 14px; margin: 0 0 6px 0;">ProcuChain</div>
                <div class="footer-text" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; color: #6b7280; line-height: 1.4;">
                    Blockchain-Powered Document Management<br>
                    for Bids and Awards Committee Offices
                </div>
                <div class="footer-disclaimer" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #9ca3af; margin: 14px 0 0 0; padding: 14px 0 0 0; border-top: 1px solid #e2e8f0;">
                    This is an automated notification. Please do not reply to this email.<br>
                    &copy; {{ date('Y') }} ProcuChain. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>

</html>

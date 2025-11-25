<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
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

        .notification-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: #d97706;
            margin-bottom: 10px;
        }

        .notification-message {
            font-size: 15px;
            line-height: 1.5;
            color: #555;
        }

        .correction-reason {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .correction-reason-title {
            font-size: 14px;
            font-weight: 600;
            color: #d97706;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .changed-fields {
            background-color: #f3f4f6;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }

        .changed-fields-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
        }

        .field-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .field-item {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 5px;
            font-size: 13px;
            color: #4b5563;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
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
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
            text-decoration: none !important;
            color: white !important;
        }

        .cta-button:visited {
            color: white !important;
            text-decoration: none !important;
        }

        .cta-button:link {
            color: white !important;
            text-decoration: none !important;
        }

        .cta-button:active {
            color: white !important;
            text-decoration: none !important;
        }

        /* Additional specificity for email clients */
        a.cta-button {
            color: white !important;
            text-decoration: none !important;
        }

        a.cta-button:link,
        a.cta-button:visited,
        a.cta-button:hover,
        a.cta-button:active {
            color: white !important;
            text-decoration: none !important;
        }

        /* Force white text for all link states */
        .cta-button,
        .cta-button * {
            color: white !important;
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
            color: #f59e0b;
            margin-bottom: 10px;
        }

        .correction-badge {
            background-color: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .procurement-id {
            font-family: 'Courier New', monospace;
            background-color: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 14px;
        }

        .correction-txid {
            font-family: 'Courier New', monospace;
            background-color: #fef3c7;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            color: #92400e;
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

            .field-list {
                display: block;
            }

            .field-item {
                display: block;
                margin-bottom: 8px;
                margin-right: 0;
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
            <div class="subtitle">Procurement Correction Notification</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">Dear {{ $notifiable->name }},</div>

            <!-- Main Notification -->
            <div class="notification-box">
                <div class="notification-title">Procurement Correction Submitted</div>
                <div class="notification-message">
                    <strong>{{ $correctedBy }}</strong> has submitted a correction for procurement
                    <strong>{{ $procurementTitle }}</strong>.
                    <span class="correction-badge">{{ count($changedFields) }} field(s) corrected</span>
                </div>
            </div>

            <!-- Correction Reason -->
            <div class="correction-reason">
                <div class="correction-reason-title">Reason for Correction</div>
                <div>{{ $correctionReason }}</div>
            </div>

            <!-- Changed Fields -->
            <div class="changed-fields">
                <div class="changed-fields-title">Fields Modified:</div>
                <ul class="field-list">
                    @foreach($changedFields as $field)
                        <li class="field-item">{{ ucwords(str_replace('_', ' ', $field)) }}</li>
                    @endforeach
                </ul>
            </div>

            <!-- Procurement Details -->
            <div class="details-section">
                <div class="details-title">Procurement Details</div>
                <div class="details-grid">
                    <div class="detail-row">
                        <div class="detail-label">Title:</div>
                        <div class="detail-value">{{ $procurementTitle }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">ID:</div>
                        <div class="detail-value">
                            <span class="procurement-id">{{ $prNumber }}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Submitted By:</div>
                        <div class="detail-value">{{ $correctedBy }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Correction TXID:</div>
                        <div class="detail-value">
                            <span class="correction-txid">{{ $correctionTxId }}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Timestamp:</div>
                        <div class="detail-value">{{ date('F j, Y \a\t g:i A', strtotime($timestamp)) }}</div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="cta-container">
                <a href="{{ $actionUrl }}" class="cta-button">
                    Review Correction Details
                </a>
            </div>

            <p style="margin-top: 20px; color: #666;">
                Please review the procurement correction and take appropriate action if needed.
            </p>

            <p style="margin-top: 30px; color: #333;">
                Thank you for maintaining the integrity of our procurement records.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">ProcuChain</div>
            <div>Blockchain-powered Document Management System for Bids and Awards Committee Office</div>
            <div style="margin-top: 10px; font-size: 12px; color: #999;">
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>

</html>
@extends('emails.layouts.base')

@section('title', $subject)

@section('header-title', 'Security Alert')

@section('header-subtitle', 'Integrity Breach Detected')

@section('additional-styles')
<style>
    .violation-item {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0;
        padding: 14px 16px;
        margin: 12px 0;
    }
    .violation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .violation-type {
        font-weight: 600;
        font-size: 13px;
    }
    .severity-badge {
        padding: 2px 8px;
        border-radius: 0;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .severity-critical { background-color: #fee2e2; color: #b91c1c; }
    .severity-high { background-color: #fef3c7; color: #92400e; }
    .severity-medium { background-color: #ccfbf1; color: #0f766e; }
    .severity-low { background-color: #e5e7eb; color: #374151; }
    .violation-details {
        font-size: 12px;
        color: #4b5563;
        font-family: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
    }
    .violation-details .detail-row {
        margin: 4px 0;
    }
    .violation-details .detail-label {
        color: #6b7280;
        font-weight: 600;
    }
    .field-diff {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        padding: 10px 12px;
        margin: 8px 0;
        font-size: 11px;
        font-family: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
    }
    .field-diff .field-name { color: #b91c1c; font-weight: 600; }
    .field-diff .field-old { color: #dc2626; }
    .field-diff .field-new { color: #16a34a; }
    .digest-summary {
        background-color: #f0fdfa;
        border: 1px solid #99f6e4;
        padding: 16px;
        margin: 16px 0;
    }
    .digest-stat {
        display: inline-block;
        margin-right: 20px;
        text-align: center;
    }
    .digest-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #0d9488;
    }
    .digest-stat-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
    }
</style>
@endsection

@section('content')
    <p class="greeting">Dear {{ $notifiable->name }},</p>

    @if (isset($isDigest) && $isDigest)
        <!-- DAILY DIGEST VERSION -->
        <p class="message-text">
            This is your <strong>daily integrity breach digest</strong> for <strong>{{ $date }}</strong>.
            The system detected the following breaches during automated verification runs.
        </p>

        <div class="digest-summary">
            <div class="digest-stat">
                <div class="digest-stat-value">{{ $summary['total'] ?? 0 }}</div>
                <div class="digest-stat-label">Total Breaches</div>
            </div>
            <div class="digest-stat">
                <div class="digest-stat-value" style="color: #dc2626;">{{ $summary['critical'] ?? 0 }}</div>
                <div class="digest-stat-label">Critical</div>
            </div>
            <div class="digest-stat">
                <div class="digest-stat-value" style="color: #f59e0b;">{{ $summary['high'] ?? 0 }}</div>
                <div class="digest-stat-label">High</div>
            </div>
            <div class="digest-stat">
                <div class="digest-stat-value" style="color: #0d9488;">{{ $summary['medium'] ?? 0 }}</div>
                <div class="digest-stat-label">Medium</div>
            </div>
            <div class="digest-stat">
                <div class="digest-stat-value" style="color: #6b7280;">{{ $summary['low'] ?? 0 }}</div>
                <div class="digest-stat-label">Low</div>
            </div>
        </div>

        <div class="details-section">
            <div class="details-title">Breach Details</div>
            @foreach ($violations as $violation)
                <div class="violation-item">
                    <div class="violation-header">
                        <span class="violation-type">{{ $violation['display_name'] }}</span>
                        <span class="severity-badge severity-{{ $violation['severity'] }}">{{ $violation['severity'] }}</span>
                    </div>
                    <div class="violation-details">
                        <div class="detail-row">
                            <span class="detail-label">PR Number:</span> {{ $violation['stream_key'] }}
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Stream:</span> {{ $violation['stream'] }}
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Transaction:</span> {{ $violation['txid'] ?: 'N/A' }}
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Run ID:</span> {{ $violation['run_id'] }}
                        </div>
                        @if (!empty($violation['field_diffs']))
                            @foreach ($violation['field_diffs'] as $diff)
                                <div class="field-diff">
                                    <span class="field-name">{{ $diff['field'] }}:</span>
                                    <span class="field-old"> was "{{ $diff['old_value'] }}"</span>
                                    <span class="field-new"> now "{{ $diff['new_value'] }}"</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="cta-container">
            <a href="{{ $actionUrl }}" class="cta-button">View All Breaches in Dashboard</a>
        </div>

        <p class="message-text">
            These violations were recorded during automated integrity verification runs. 
            Breaches marked as <strong>critical</strong> or <strong>high</strong> severity may indicate data tampering 
            and should be investigated immediately.
        </p>

    @else
        <!-- SINGLE VIOLATION VERSION -->
        <p class="message-text">
            A <strong>data integrity breach</strong> has been detected in the procurement mirror system 
            during automated verification.
        </p>

        <div class="alert-box alert-{{ $severityClass }}">
            <div class="alert-title">{{ $severityIcon }} {{ $displayName }}</div>
            <div class="alert-message">
                <strong>Severity:</strong> {{ ucfirst($severity) }} |
                <strong>Stream:</strong> {{ $stream }} |
                <strong>PR:</strong> {{ $streamKey }}
            </div>
        </div>

        <div class="details-section">
            <div class="details-title">Breach Details</div>
            <table class="details-table">
                <tr>
                    <td class="details-label">Breach Type</td>
                    <td class="details-value">
                        <span class="badge {{ $severity === 'critical' ? 'badge-danger' : ($severity === 'high' ? 'badge-warning' : ($severity === 'medium' ? 'badge-primary' : '')) }}">
                            {{ $displayName }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="details-label">Severity</td>
                    <td class="details-value">{{ ucfirst($severity) }}</td>
                </tr>
                <tr>
                    <td class="details-label">Stream</td>
                    <td class="details-value"><code>{{ $stream }}</code></td>
                </tr>
                <tr>
                    <td class="details-label">PR Number</td>
                    <td class="details-value">{{ $streamKey }}</td>
                </tr>
                <tr>
                    <td class="details-label">Transaction ID</td>
                    <td class="details-value"><code>{{ $txid }}</code></td>
                </tr>
                <tr>
                    <td class="details-label">Verification Run</td>
                    <td class="details-value">{{ $runId }}</td>
                </tr>
                <tr>
                    <td class="details-label">Detected</td>
                    <td class="details-value">{{ $detectedAt }}</td>
                </tr>
            </table>
        </div>

        @if (!empty($fieldDiffs))
            <div class="details-section">
                <div class="details-title">Field-Level Differences</div>
                @foreach ($fieldDiffs as $diff)
                    <div class="field-diff">
                        <span class="field-name">{{ $diff['field'] }}:</span>
                        <span class="field-old"> was "{{ $diff['old_value'] }}"</span>
                        <span class="field-new"> now "{{ $diff['new_value'] }}"</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if (!empty($breachData))
            <div class="details-section">
                <div class="details-title">Additional Context</div>
                <table class="details-table">
                    @foreach ($breachData as $key => $value)
                        <tr>
                            <td class="details-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                            <td class="details-value">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if ($isCritical)
            <div class="info-box">
                <div class="info-box-title">⚠ Critical Security Event</div>
                <ul>
                    <li>The data in the procurement mirror does not match the blockchain — this may indicate database tampering.</li>
                    <li>Immediate review is required for critical and high severity breaches.</li>
                    <li>The blockchain is the authoritative source of truth. MySQL mirror can be restored from it.</li>
                </ul>
            </div>
        @endif

        <div class="cta-container">
            <a href="{{ $actionUrl }}" class="cta-button">View in Integrity Dashboard</a>
        </div>

        <div class="link-section">
            <span class="link-label">Auto-Repair Command</span>
            <a href="#" class="link-url" onclick="return false;">{{ $repairCommand }}</a>
        </div>

        <p class="message-text">
            To restore the mirror from the blockchain, run the command above or use the 
            <strong>Repair</strong> button in the Integrity Breaches dashboard.
        </p>
    @endif

    <p class="message-text">
        This is an automated security notification from the ProcuChain integrity verification system.
        If you did not expect this alert, please contact your system administrator immediately.
    </p>

    <p class="message-text">Thank you for your attention to this matter.</p>
@endsection
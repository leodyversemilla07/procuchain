@extends('emails.layouts.base')

@section('title', $subject)

@section('header-title', 'Security Alert')
@section('header-subtitle', 'Audit Event Notification')

@section('content')
    <p class="greeting">Dear {{ $notifiable->name }},</p>

    @php
        $severityClass = match($severity) {
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        $severityIcon = match($severity) {
            'error' => '🔴',
            'warning' => '⚠️',
            default => 'ℹ️',
        };
    @endphp

    <div class="alert-box {{ $severityClass }}">
        <div class="alert-title">{{ $severityIcon }} {{ ucwords(str_replace(['.', '_'], ' ', $action)) }}</div>
        <div class="alert-message">
            <strong>{{ $actorName }}</strong> performed this action on the system.
        </div>
    </div>

    <div class="details-section">
        <div class="details-title">Event Details</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Action</td>
                <td class="details-value">
                    <span class="badge {{ $severity === 'error' ? 'badge-danger' : ($severity === 'warning' ? 'badge-warning' : 'badge-primary') }}">
                        {{ str_replace(['.', '_'], ' ', $action) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="details-label">Performed By</td>
                <td class="details-value">{{ $actorName }}</td>
            </tr>
            @if (!empty($details))
                <tr>
                    <td class="details-label">Details</td>
                    <td class="details-value">{{ $details }}</td>
                </tr>
            @endif
            <tr>
                <td class="details-label">Timestamp</td>
                <td class="details-value">{{ date('F j, Y \a\t g:i A', strtotime($timestamp)) }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="cta-button">View Audit Logs</a>
    </div>

    <p class="message-text">
        This is an automated security notification. If you did not authorize this action, please contact your system administrator immediately.
    </p>

    <p class="message-text">Thank you for your attention to this matter.</p>
@endsection
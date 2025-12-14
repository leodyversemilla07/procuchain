@extends('emails.layouts.base')

@section('title', $subject)

@section('header-title', 'Procurement Update')
@section('header-subtitle', 'Status Notification')

@section('content')
    <p class="greeting">Dear {{ $notifiable->name }},</p>

    <div class="alert-box alert-info">
        <div class="alert-title">📋 Procurement Status Update</div>
        <div class="alert-message">
            @if (($documentCount ?? 0) > 0)
                @if (in_array($actionType ?? '', ['uploaded', 'submitted']))
                    <strong>{{ $documentCount }} document(s)</strong> have been uploaded for the
                    <strong>{{ $stageIdentifier }}</strong> stage.
                @else
                    The <strong>{{ $stageIdentifier }}</strong> stage {{ $formattedAction }} with
                    <strong>{{ $documentCount }} document(s)</strong>.
                @endif
            @else
                The <strong>{{ $stageIdentifier }}</strong> stage {{ $formattedAction }}.
            @endif
        </div>
    </div>

    @if (!empty($nextStage))
        <div class="alert-box alert-success">
            <div class="alert-title">➡️ Stage Transition</div>
            <div class="alert-message">
                The procurement process is now moving to the <strong>{{ $nextStage }}</strong> stage.
            </div>
        </div>
    @endif

    <div class="details-section">
        <div class="details-title">Procurement Details</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Title</td>
                <td class="details-value">{{ $procurementTitle }}</td>
            </tr>
            <tr>
                <td class="details-label">PR Number</td>
                <td class="details-value"><span class="badge badge-primary">{{ $pr_number }}</span></td>
            </tr>
            <tr>
                <td class="details-label">Current Status</td>
                <td class="details-value"><span class="badge badge-success">{{ $currentStatus }}</span></td>
            </tr>
            <tr>
                <td class="details-label">Last Updated</td>
                <td class="details-value">{{ date('F j, Y \a\t g:i A', strtotime($timestamp)) }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="cta-button">View Procurement Details</a>
    </div>

    <p class="message-text">
        Please review the updated procurement information at your earliest convenience.
    </p>

    <p class="message-text">Thank you for your attention to this matter.</p>
@endsection

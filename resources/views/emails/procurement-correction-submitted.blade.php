@extends('emails.layouts.base')

@section('title', $subject)

@section('header-title', 'Procurement Correction')
@section('header-subtitle', 'Correction Notification')

@section('content')
    <p class="greeting">Dear {{ $notifiable->name }},</p>

    <div class="alert-box alert-warning">
        <div class="alert-title">📝 Procurement Correction Submitted</div>
        <div class="alert-message">
            <strong>{{ $correctedBy }}</strong> has submitted a correction for procurement
            <strong>{{ $procurementTitle }}</strong>.
            <br><br>
            <span class="badge badge-warning">{{ count($changedFields) }} field(s) corrected</span>
        </div>
    </div>

    <div class="alert-box alert-info">
        <div class="alert-title">📄 Reason for Correction</div>
        <div class="alert-message">{{ $correctionReason }}</div>
    </div>

    <div class="info-box">
        <div class="info-box-title">Fields Modified</div>
        <ul>
            @foreach ($changedFields as $field)
                <li>{{ ucwords(str_replace('_', ' ', $field)) }}</li>
            @endforeach
        </ul>
    </div>

    <div class="details-section">
        <div class="details-title">Correction Details</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Title</td>
                <td class="details-value">{{ $procurementTitle }}</td>
            </tr>
            <tr>
                <td class="details-label">PR Number</td>
                <td class="details-value"><span class="badge badge-primary">{{ $prNumber }}</span></td>
            </tr>
            <tr>
                <td class="details-label">Submitted By</td>
                <td class="details-value">{{ $correctedBy }}</td>
            </tr>
            <tr>
                <td class="details-label">Transaction ID</td>
                <td class="details-value" style="font-family: monospace; font-size: 12px;">{{ $correctionTxId }}</td>
            </tr>
            <tr>
                <td class="details-label">Timestamp</td>
                <td class="details-value">{{ date('F j, Y \a\t g:i A', strtotime($timestamp)) }}</td>
            </tr>
        </table>
    </div>

    <div class="cta-container">
        <a href="{{ $actionUrl }}" class="cta-button">Review Correction Details</a>
    </div>

    <p class="message-text">
        Please review the procurement correction and take appropriate action if needed.
    </p>

    <p class="message-text">Thank you for maintaining the integrity of our procurement records.</p>
@endsection

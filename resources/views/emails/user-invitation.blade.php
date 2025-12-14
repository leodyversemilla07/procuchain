@extends('emails.layouts.base')

@section('title', 'Invitation to Join Procuchain')

@section('header-title', 'You're Invited!')
@section('header-subtitle', 'Join the Procuchain Procurement System')

@section('content')
    <p class="greeting">Hello {{ $invitation->name }},</p>

    <p class="message-text">
        You have been invited by {{ $invitation->invitedBy->name }} to join the Procuchain Procurement System as a <strong>{{ ucwords(str_replace('_', ' ', $invitation->role)) }}</strong>.
    </p>

    <div class="info-box">
        <div class="info-box-title">About Procuchain</div>
        <p>
            Procuchain is a blockchain-based procurement management system designed to ensure transparency, 
            accountability, and efficiency in government procurement processes following Philippine GPPB regulations.
        </p>
    </div>

    <div class="details-section">
        <div class="details-title">Invitation Details</div>
        <table class="details-table">
            <tr>
                <td class="details-label">Email</td>
                <td class="details-value">{{ $invitation->email }}</td>
            </tr>
            <tr>
                <td class="details-label">Role</td>
                <td class="details-value"><span class="badge badge-info">{{ ucwords(str_replace('_', ' ', $invitation->role)) }}</span></td>
            </tr>
            <tr>
                <td class="details-label">Invited By</td>
                <td class="details-value">{{ $invitation->invitedBy->name }}</td>
            </tr>
            <tr>
                <td class="details-label">Expires</td>
                <td class="details-value"><span class="badge badge-warning">{{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}</span></td>
            </tr>
        </table>
    </div>

    <p class="message-text">
        Click the button below to accept this invitation and create your account. You'll be able to set your own secure password.
    </p>

    <div class="cta-container">
        <a href="{{ $acceptUrl }}" class="cta-button">Accept Invitation</a>
    </div>

    <div class="alert-box alert-warning">
        <div class="alert-title">⏰ Important</div>
        <div class="alert-message">
            This invitation will expire in {{ $invitation->expires_at->diffForHumans() }}. 
            Please accept it before {{ $invitation->expires_at->format('F j, Y') }}.
        </div>
    </div>

    <p class="message-text">
        If you did not expect this invitation or have any questions, please contact the system administrator.
    </p>

    <p class="small-text">
        For security reasons, this invitation link can only be used once. If you have any issues accessing your account, 
        please contact support.
    </p>
@endsection

@extends('layouts.app')
@section('title', 'Review ambassador')
@section('page-title', 'Review ambassador')
@section('page-subtitle', 'Applications, contributions and rewards in one place.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ambassadors.css') }}">@endpush
@section('content')
<div class="amb-shell">
    <p><a href="{{ route('admin.ambassadors.index') }}">← All ambassadors</a></p>
    <section class="amb-card amb-section">
        <div class="amb-item-head"><div><h1>{{ $application->user?->full_name }}</h1><p class="amb-muted">{{ collect([$application->institution, $application->department, $application->study_level])->filter()->implode(' · ') }}</p></div><span class="amb-badge amb-badge-{{ $application->status }}">{{ $application->statusLabel() }}</span></div>
        <p class="amb-muted">{{ $application->user?->email }} · {{ $application->phone }}@if($application->social_profile_url) · <a href="{{ $application->social_profile_url }}" target="_blank" rel="noopener noreferrer nofollow">Profile link ↗</a>@endif</p>
        <p class="amb-muted">@if($application->university)Linked account institution: {{ $application->university->name }}@else No verified institution on the applicant’s account; the institution above is self-reported.@endif @if($application->faculty) · Faculty: {{ $application->faculty }}@endif</p>
        <p class="amb-muted">{{ ucfirst(str_replace('_', ' ', $application->leadership_role)) }} · Estimated community: {{ $application->community_size }} students · Applied {{ $application->created_at->format('j M Y') }}</p>
        <p class="amb-body">{{ $application->statement }}</p>
        @if(! $application->user?->is_active)<p class="amb-note">This account is inactive. New referrals and reward fulfilment are blocked.</p>@endif
        @if($application->review_note)<p class="amb-note">Latest review: {{ $application->review_note }}</p>@endif
        @if(! in_array($application->status, ['rejected', 'withdrawn'], true))
            <form method="POST" action="{{ route('admin.ambassadors.review', $application) }}">@csrf @method('PATCH')
                <div class="amb-fields"><label class="amb-field amb-wide">Review note visible to the applicant<textarea name="review_note" rows="3" minlength="5" maxlength="2000" required>{{ old('review_note') }}</textarea></label></div>
                <div class="amb-actions">
                    @if($application->status === 'pending')<button class="amb-button amb-button-primary" name="status" value="approved">Approve ambassador</button><button class="amb-button amb-button-danger" name="status" value="rejected" onclick="return confirm('Reject this application? Your note is shown to the applicant.')">Reject application</button>
                    @elseif($application->status === 'approved')<button class="amb-button amb-button-danger" name="status" value="suspended" onclick="return confirm('Suspend this ambassador? New referrals, reports and reward fulfilment stop until reinstated.')">Suspend ambassador</button>
                    @elseif($application->status === 'suspended')<button class="amb-button amb-button-primary" name="status" value="approved">Reinstate ambassador</button>@endif
                </div>
            </form>
        @else<p class="amb-muted">{{ $application->status === 'withdrawn' ? 'The applicant withdrew this application and can resubmit it.' : 'The applicant can update their details and resubmit.' }}</p>@endif
    </section>
    @include('ambassadors._metrics')
    @include('admin.ambassadors._referrals')
    @if($application->status === 'approved')
        <section class="amb-card amb-section"><h2>Award a contribution</h2><p class="amb-muted">Record an agreed reward with a clear reason. Use a repeatable reference such as october-2026-demo-1 to prevent duplicate awards for the same contribution. This creates a pending award; fulfil it below when ready.</p>
            <form method="POST" action="{{ route('admin.ambassadors.rewards.store', $application) }}">@csrf
                <div class="amb-fields">
                    <label class="amb-field">Reward type<select name="kind" required>@foreach(\App\Models\AmbassadorReward::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="amb-field">Award reference<input name="award_key" value="{{ old('award_key') }}" pattern="[a-zA-Z0-9_-]{3,80}" maxlength="80" required></label>
                    <label class="amb-field amb-wide">Qualified referral ID<input type="number" name="referral_id" value="{{ old('referral_id') }}" min="1"><small>Required for performance bonuses. Use an approved referral number from the queue. Each referral can support one reward; checks run again at fulfilment. Leave blank only for an independently verified contribution award.</small></label>
                    <label class="amb-field amb-wide">Title<input name="title" value="{{ old('title') }}" maxlength="160" required></label>
                    <label class="amb-field">Amount<input type="number" name="amount" value="{{ old('amount') }}" min="0" max="9999999.99" step="0.01" required><small>Enter 0 for recognition. Wallet credits must match the recipient’s wallet currency.</small></label>
                    <label class="amb-field">Currency<select name="currency" required>@foreach(['NGN', 'USD', 'GBP', 'EUR'] as $currency)<option @selected(old('currency', 'NGN') === $currency)>{{ $currency }}</option>@endforeach</select></label>
                    <label class="amb-field amb-wide">Reason and agreed delivery details<textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                </div><div class="amb-actions"><button class="amb-button amb-button-primary">Record award</button></div>
            </form>
        </section>
    @endif
    @include('ambassadors._reports', ['adminReview' => true])
    @include('ambassadors._rewards', ['adminReview' => true])
</div>
@endsection

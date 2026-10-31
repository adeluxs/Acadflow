@extends('layouts.app')
@section('title', 'Campus Ambassador')
@section('page-title', 'Campus Ambassador')
@section('page-subtitle', 'Help your classmates. Grow your campus community.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ambassadors.css') }}">@endpush
@section('content')
<div class="amb-shell">
    @include('ambassadors._own-referral')
    <div class="amb-hero"><p class="amb-eyebrow">AcadSyntra campus programme</p><h1>Your campus. Your contribution.</h1><p>Help students get started, share what you learn and turn their feedback into a better academic experience.</p></div>
    @if($application)
        <section class="amb-card"><div class="amb-item-head"><div><h2>{{ $application->institution }}</h2><p class="amb-muted">{{ collect([$application->faculty, $application->department, $application->study_level])->filter()->implode(' · ') }}</p></div><span class="amb-badge amb-badge-{{ $application->status }}">{{ $application->statusLabel() }}</span></div>
            @if($application->status === 'pending')
                <p class="amb-muted">Submitted {{ ($application->submitted_at ?? $application->created_at)->format('j M Y') }}. The platform team reviews applications and replies here. Referral and reporting tools become available after approval.</p>
                <form method="POST" action="{{ route('ambassadors.withdraw') }}" class="amb-actions" onsubmit="return confirm('Withdraw your application? You can update and resubmit it later.')">@csrf<button class="amb-button">Withdraw application</button></form>
            @endif
            @if($application->status === 'approved')<p class="amb-muted">You are an approved campus ambassador. Being an ambassador gives no access to student records or institution administration.</p>@endif
            @if($application->status === 'suspended')<p class="amb-muted">New referrals, activity submissions and reward fulfilment are paused. Contact the team if you need help with the review.</p>@endif
            @if($application->status === 'rejected')<p class="amb-muted">This application was not approved. You can update your details below and resubmit.</p>@endif
            @if($application->status === 'withdrawn')<p class="amb-muted">You withdrew this application. Update it below whenever you are ready.</p>@endif
            @if($application->review_note)<p class="amb-note">Team feedback: {{ $application->review_note }}</p>@endif
        </section>
    @endif
    @if(! $application || in_array($application->status, \App\Models\AmbassadorApplication::RESUBMITTABLE, true))
        @if(auth()->user()->isAdmin())<p class="amb-note">Applications are for student accounts. Platform administrators can manage the programme from Campus Ambassadors in the sidebar.</p>
        @elseif(! auth()->user()->isStudent())<p class="amb-note">The campus ambassador programme is for student accounts. If you are a lecturer, you can still share AcadSyntra with colleagues from your own dashboard.</p>
        @else @include('ambassadors._application-form')@endif
    @endif
    @if($application && in_array($application->status, ['approved', 'suspended']))
        @include('ambassadors._metrics')
    @endif
    @if($application?->status === 'approved')
        @php
            $referralUrl = route('ambassadors.referral', $application->referral_code);
            $shareMessage = "I’m an AcadSyntra campus ambassador. Join me to explore tools for study, research and sharing academic resources. Participation is optional, and I may earn programme rewards from referrals. Get started: ".$referralUrl;
        @endphp
        <div class="amb-grid amb-section">
            <section class="amb-card"><h2>Your invitation link</h2><p class="amb-muted">Share with interested classmates. New registrations through this link count towards your results.</p><div class="amb-fields"><label class="amb-field amb-wide">Select and copy your link<input readonly value="{{ $referralUrl }}" onclick="this.select()"></label><label class="amb-field amb-wide">Ready-to-share message<textarea rows="5" readonly onclick="this.select()">{{ $shareMessage }}</textarea></label></div><div class="amb-actions"><a class="amb-button amb-button-primary" href="https://wa.me/?text={{ rawurlencode($shareMessage) }}" target="_blank" rel="noopener noreferrer">Share on WhatsApp ↗</a></div><p class="amb-muted">Referral code: {{ $application->referral_code }}</p></section>
            <section class="amb-card"><h2>Your starter guide</h2><ol><li>Try the study or knowledge tools yourself.</li><li>Choose one useful task and invite a small group.</li><li>Run a 10-minute demo using materials you can share.</li><li>Help interested students sign up and complete onboarding.</li><li>Check in after a week and submit feedback.</li></ol><p class="amb-muted">Use aggregate attendance counts. Do not put classmates’ names, phone numbers, passwords or private academic records in your reports.</p><div class="amb-actions"><a href="{{ route('public.features') }}">Product overview</a><a href="{{ route('public.help') }}">Help centre</a><a href="{{ route('public.contact') }}">Contact the team</a></div></section>
        </div>
        <section class="amb-card amb-section" id="activities"><h2>Programme activities</h2>
            <p class="amb-muted">Activities published by the AcadSyntra team. Pick one when you report, or report your own useful activity.</p>
            @forelse($tasks as $task)
                <article class="amb-item">
                    <div class="amb-item-head"><div><h3>{{ $task->title }}</h3><p class="amb-muted">{{ \App\Models\AmbassadorReport::KINDS[$task->kind] ?? $task->kind }}@if($task->ends_on) · Open until {{ $task->ends_on->format('j M Y') }}@endif @if($task->requires_evidence) · Supporting link or file needed @endif</p></div></div>
                    <p class="amb-body">{{ $task->summary }}</p>
                    @if($task->instructions)<p class="amb-muted">{{ $task->instructions }}</p>@endif
                    @if($task->resource_url)<p class="amb-muted"><a href="{{ $task->resource_url }}" target="_blank" rel="noopener noreferrer">Open programme resource ↗</a></p>@endif
                </article>
            @empty<p class="amb-muted">No team activities are open right now. You can still report your own demo, onboarding session or student feedback below.</p>@endforelse
        </section>
        <section class="amb-card amb-section"><h2>Report an activity or share feedback</h2>
            <form method="POST" action="{{ route('ambassadors.reports.store') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="amb-fields">
                    @if($tasks->isNotEmpty())<label class="amb-field amb-wide">Programme activity (optional)<select name="task"><option value="">My own activity</option>@foreach($tasks as $task)<option value="{{ $task->uuid }}" @selected(old('task') === $task->uuid)>{{ $task->title }}</option>@endforeach</select>@error('task')<small class="amb-error">{{ $message }}</small>@enderror</label>@endif
                    <label class="amb-field">Activity type<select name="kind" required>@foreach(\App\Models\AmbassadorReport::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="amb-field">Title<input name="title" value="{{ old('title') }}" maxlength="160" required></label>
                    <label class="amb-field">Activity date<input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                    <label class="amb-field">Participants<input type="number" name="participants" value="{{ old('participants', 0) }}" min="0" max="10000" required></label>
                    <label class="amb-field amb-wide">What happened? What did you learn?<textarea name="summary" rows="4" minlength="20" maxlength="5000" required>{{ old('summary') }}</textarea></label>
                    <label class="amb-field amb-wide">Supporting link (optional)<input type="url" name="evidence_url" value="{{ old('evidence_url') }}" maxlength="1000" placeholder="https://"><small>Only share content you have permission to share. The team can review the link.</small>@error('evidence_url')<small class="amb-error">{{ $message }}</small>@enderror</label>
                    <label class="amb-field amb-wide">Supporting file (optional)<input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"><small>JPG, PNG, WebP or PDF, up to 5 MB. Only the team and you can open it. Do not include classmates’ names, phone numbers or private records.</small>@error('evidence_file')<small class="amb-error">{{ $message }}</small>@enderror</label>
                </div><div class="amb-actions"><button class="amb-button amb-button-primary">Submit report</button></div>
            </form>
        </section>
    @endif
    @if($application)@include('ambassadors._reports')@include('ambassadors._rewards')@endif
</div>
@endsection

@extends('layouts.app')
@section('title', 'Campus Ambassadors')
@section('page-title', 'Campus Ambassadors')
@section('page-subtitle', 'Review applications, support campus leaders and manage rewards.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ambassadors.css') }}">@endpush
@section('content')
<div class="amb-shell">
    <div class="amb-hero"><p class="amb-eyebrow">Programme management</p><h1>Build a campus community that lasts.</h1><p>Review real contributions alongside referral results. Reward useful work and sustained participation.</p><div class="amb-actions"><a class="amb-button" href="{{ route('public.ambassadors') }}">View recruitment page</a><a class="amb-button" href="{{ route('admin.ambassadors.tasks.index') }}">Programme activities ({{ $activeTasks }} open)</a></div></div>
    @include('ambassadors._metrics')
    <div class="amb-actions"><a class="amb-button" href="{{ route('admin.ambassadors.index', ['status' => 'all', 'work' => 'referrals']) }}">{{ $pendingReferrals }} referrals awaiting review</a><a class="amb-button" href="{{ route('admin.ambassadors.index', ['status' => 'all', 'work' => 'reports']) }}">{{ $pendingReports }} reports awaiting review</a><a class="amb-button" href="{{ route('admin.ambassadors.index', ['status' => 'all', 'work' => 'rewards']) }}">{{ $pendingRewards }} rewards awaiting fulfilment</a></div>
    <section class="amb-card amb-section"><h2>Ambassador applications</h2>
        <nav class="amb-actions" aria-label="Application status">@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn', 'all' => 'All'] as $value => $label)<a class="amb-button {{ $status === $value && ! $work ? 'amb-button-primary' : '' }}" href="{{ route('admin.ambassadors.index', array_filter(['status' => $value] + $filters)) }}" @if($status === $value && ! $work) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        <form method="GET" action="{{ route('admin.ambassadors.index') }}" class="amb-fields amb-filter" role="search" aria-label="Filter applications">
            <input type="hidden" name="status" value="{{ $status }}">@if($work)<input type="hidden" name="work" value="{{ $work }}">@endif
            <label class="amb-field">Institution or department<input name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="e.g. JABU"></label>
            <label class="amb-field">Applied from<input type="date" name="from" value="{{ $filters['from'] }}"></label>
            <label class="amb-field">Applied to<input type="date" name="to" value="{{ $filters['to'] }}"></label>
            <div class="amb-actions"><button class="amb-button amb-button-primary">Filter</button>@if($filters['q'] || $filters['from'] || $filters['to'])<a href="{{ route('admin.ambassadors.index', ['status' => $status]) }}">Clear</a>@endif</div>
        </form>
        @if($work)<p class="amb-note">Showing ambassadors with pending {{ $work }}. <a href="{{ route('admin.ambassadors.index', ['status' => 'all']) }}">Clear filter</a></p>@endif
        <div class="amb-table-wrap" tabindex="0" role="region" aria-label="Ambassador applications table"><table class="amb-table"><thead><tr><th scope="col">Applicant</th><th scope="col">Campus</th><th scope="col">Status</th><th scope="col">Signups</th><th scope="col">To review</th><th scope="col">Action</th></tr></thead><tbody>
            @forelse($applications as $application)<tr><td>{{ $application->user?->full_name }}<p class="amb-muted">{{ $application->created_at->format('j M Y') }}</p></td><td>{{ $application->institution }}<p class="amb-muted">{{ $application->department }}</p></td><td><span class="amb-badge amb-badge-{{ $application->status }}">{{ $application->statusLabel() }}</span></td><td>{{ $application->referrals_count }}</td><td>{{ $application->pending_referrals_count }} referrals<br>{{ $application->pending_reports_count }} reports<br>{{ $application->pending_rewards_count }} rewards</td><td><a href="{{ route('admin.ambassadors.show', $application) }}">Review<span class="sr-only"> {{ $application->user?->full_name }}</span></a></td></tr>
            @empty<tr><td colspan="6">No applications match this filter. Share the recruitment page to invite your first campus leaders.</td></tr>@endforelse
        </tbody></table></div><div class="amb-pagination">{{ $applications->links() }}</div>
    </section>
    <section class="amb-card amb-section" id="audit-trail"><h2>Recent programme audit trail</h2>
        <p class="amb-muted">The latest 15 recorded ambassador actions: application decisions, referral reviews, reports, rewards and activity changes. The full history is in <a href="{{ route('admin.settings.audit-logs') }}">System Settings → Audit logs</a>.</p>
        <div class="amb-table-wrap" tabindex="0" role="region" aria-label="Programme audit trail"><table class="amb-table"><thead><tr><th scope="col">When</th><th scope="col">Action</th><th scope="col">By</th><th scope="col">Record</th></tr></thead><tbody>
            @forelse($auditTrail as $entry)<tr><td>{{ $entry->created_at?->format('j M Y, H:i') }}</td><td>{{ str_replace(['ambassador.', '.'], ['', ' · '], $entry->action) }}</td><td>{{ $entry->user?->full_name ?? 'System' }}</td><td>{{ class_basename((string) $entry->model_type) }} #{{ $entry->model_id }}</td></tr>
            @empty<tr><td colspan="4">No programme actions recorded yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection

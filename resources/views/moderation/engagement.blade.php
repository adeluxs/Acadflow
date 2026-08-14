@extends('layouts.app')

@section('title', 'Engagement Moderation')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Engagement moderation</h1>
            <p class="text-muted mb-0">Human review of reported comments, discussions, and publications.</p>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select" aria-label="Report status">
                <option value="">All statuses</option>
                @foreach(['open', 'resolved', 'dismissed'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary">Filter</button>
        </form>
    </div>

    @forelse($reports as $report)
        <article class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <span class="badge text-bg-{{ $report->status === 'open' ? 'warning' : 'secondary' }}">{{ ucfirst($report->status) }}</span>
                        <strong class="ms-2">{{ str_replace('_', ' ', ucfirst($report->reason)) }}</strong>
                    </div>
                    <small class="text-muted">{{ $report->created_at?->diffForHumans() }}</small>
                </div>
                <p class="mt-3 mb-1"><strong>Reporter:</strong> {{ $report->reporter?->full_name ?? 'Deleted user' }}</p>
                <p class="mb-1"><strong>Target:</strong> {{ class_basename($report->reportable_type) }} #{{ $report->reportable_id }}</p>
                @if($report->details)<p class="mb-3">{{ $report->details }}</p>@endif

                @if($report->status === 'open')
                    <form method="POST" action="{{ route('moderation.engagement.review', $report) }}" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <select name="decision" class="form-select" required>
                                <option value="dismiss">Dismiss report</option>
                                <option value="hide">Hide comment</option>
                                <option value="restore">Restore comment</option>
                                <option value="lock_thread">Lock discussion</option>
                                <option value="unpublish">Unpublish publication</option>
                            </select>
                        </div>
                        <div class="col-md-7"><input name="resolution" class="form-control" maxlength="5000" required placeholder="Reason and evidence for the decision"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">Record</button></div>
                    </form>
                @else
                    <div class="alert alert-light border mt-3 mb-0">
                        {{ $report->resolution }}
                        <small class="d-block text-muted">Reviewed by {{ $report->reviewer?->full_name ?? 'administrator' }} {{ $report->reviewed_at?->diffForHumans() }}</small>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <div class="alert alert-info">No reports match this filter.</div>
    @endforelse

    {{ $reports->links() }}
</div>
@endsection

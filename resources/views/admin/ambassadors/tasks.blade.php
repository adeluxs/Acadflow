@extends('layouts.app')
@section('title', 'Ambassador activities')
@section('page-title', 'Programme activities')
@section('page-subtitle', 'Publish campaigns and tasks that approved ambassadors can report against.')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ambassadors.css') }}">@endpush
@section('content')
@php($form = $editing)
<div class="amb-shell">
    <p><a href="{{ route('admin.ambassadors.index') }}">← All ambassadors</a></p>
    <section class="amb-card amb-section" id="activity-form">
        <h2>{{ $form ? 'Edit activity' : 'Publish an activity' }}</h2>
        <p class="amb-muted">Approved ambassadors see open activities on their dashboard and can link an activity report to one. Reports are always reviewed; publishing an activity does not create or promise a reward.</p>
        <form method="POST" action="{{ $form ? route('admin.ambassadors.tasks.update', $form) : route('admin.ambassadors.tasks.store') }}">
            @csrf @if($form) @method('PATCH') @endif
            <div class="amb-fields">
                <label class="amb-field amb-wide">Title<input name="title" value="{{ old('title', $form?->title) }}" maxlength="160" required>@error('title')<small class="amb-error">{{ $message }}</small>@enderror</label>
                <label class="amb-field">Activity type<select name="kind" required>@foreach(\App\Models\AmbassadorReport::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('kind', $form?->kind) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="amb-field">Opens on (optional)<input type="date" name="starts_on" value="{{ old('starts_on', $form?->starts_on?->toDateString()) }}"></label>
                <label class="amb-field">Closes on (optional)<input type="date" name="ends_on" value="{{ old('ends_on', $form?->ends_on?->toDateString()) }}">@error('ends_on')<small class="amb-error">{{ $message }}</small>@enderror</label>
                <label class="amb-field amb-wide">Summary shown to ambassadors<textarea name="summary" rows="2" minlength="10" maxlength="1000" required>{{ old('summary', $form?->summary) }}</textarea>@error('summary')<small class="amb-error">{{ $message }}</small>@enderror</label>
                <label class="amb-field amb-wide">Instructions (optional)<textarea name="instructions" rows="3" maxlength="5000">{{ old('instructions', $form?->instructions) }}</textarea></label>
                <label class="amb-field amb-wide">Official resource link (optional)<input type="url" name="resource_url" value="{{ old('resource_url', $form?->resource_url) }}" maxlength="1000" placeholder="https://">@error('resource_url')<small class="amb-error">{{ $message }}</small>@enderror</label>
                <label class="amb-check"><input type="hidden" name="requires_evidence" value="0"><input type="checkbox" name="requires_evidence" value="1" @checked(old('requires_evidence', $form?->requires_evidence))> Reports must include a supporting link or file</label>
                <label class="amb-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $form?->is_active ?? true))> Open to ambassadors</label>
            </div>
            <div class="amb-actions"><button class="amb-button amb-button-primary">{{ $form ? 'Save changes' : 'Publish activity' }}</button>@if($form)<a href="{{ route('admin.ambassadors.tasks.index') }}">Cancel editing</a>@endif</div>
        </form>
    </section>

    <section class="amb-card amb-section"><h2>All activities</h2>
        <div class="amb-table-wrap" tabindex="0" role="region" aria-label="Programme activities"><table class="amb-table"><thead><tr><th scope="col">Activity</th><th scope="col">Window</th><th scope="col">Status</th><th scope="col">Reports</th><th scope="col">Actions</th></tr></thead><tbody>
            @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->title }}<p class="amb-muted">{{ \App\Models\AmbassadorReport::KINDS[$task->kind] ?? $task->kind }}@if($task->requires_evidence) · evidence required @endif</p></td>
                    <td>{{ $task->starts_on?->format('j M Y') ?? 'Now' }} – {{ $task->ends_on?->format('j M Y') ?? 'open-ended' }}</td>
                    <td><span class="amb-badge amb-badge-{{ $task->is_active ? 'approved' : 'suspended' }}">{{ $task->is_active ? 'Open' : 'Closed' }}</span></td>
                    <td>{{ $task->reports_count }}<p class="amb-muted">{{ $task->pending_reports_count }} awaiting review</p></td>
                    <td><div class="amb-actions"><a href="{{ route('admin.ambassadors.tasks.index', ['edit' => $task->uuid]) }}#activity-form">Edit<span class="sr-only"> {{ $task->title }}</span></a>
                        <form method="POST" action="{{ route('admin.ambassadors.tasks.update', $task) }}">@csrf @method('PATCH')<input type="hidden" name="toggle" value="1"><button class="amb-button">{{ $task->is_active ? 'Close' : 'Reopen' }}<span class="sr-only"> {{ $task->title }}</span></button></form></div></td>
                </tr>
            @empty<tr><td colspan="5">No activities yet. Publish a first campaign, for example “Host a 10-minute study tools demo”.</td></tr>@endforelse
        </tbody></table></div>
        <div class="amb-pagination">{{ $tasks->links() }}</div>
    </section>
</div>
@endsection

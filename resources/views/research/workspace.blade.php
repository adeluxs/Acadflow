@extends('layouts.app')
@section('title', 'Research control center')
@section('page-title', 'Research control center')
@section('page-subtitle', $research->title)

@section('content')
<div class="space-y-6">
    <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <a href="{{ route('research.show', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Writing workspace</a>
        <a href="{{ route('research.literature.search', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Literature discovery</a>
        <a href="{{ route('research.specialized.show', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">SIWES & seminar</a>
        <a href="{{ route('research.export.html', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export HTML</a>
    </nav>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-black text-slate-900">Milestones</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">{{ $research->milestones->count() }}</span>
            </div>
            <div class="mt-4 space-y-3">
                @forelse($research->milestones as $milestone)
                    <form method="POST" action="{{ route('research.milestones.update', [$research, $milestone]) }}" class="grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-[1fr_150px_auto] sm:items-center">
                        @csrf
                        @method('PATCH')
                        <div>
                            <p class="font-semibold text-slate-900">{{ $milestone->title }}</p>
                            <p class="text-xs text-slate-500">{{ $milestone->weight }}% · Due {{ $milestone->due_at?->format('M j, Y') ?? 'not set' }}</p>
                        </div>
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            <option value="pending" @selected($milestone->status === 'pending')>Pending</option>
                            <option value="in_progress" @selected($milestone->status === 'in_progress')>In progress</option>
                            <option value="completed" @selected($milestone->status === 'completed')>Completed</option>
                            <option value="blocked" @selected($milestone->status === 'blocked')>Blocked</option>
                        </select>
                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">Save</button>
                    </form>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">No configured milestones.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Assign task</h2>
            <form method="POST" action="{{ route('research.tasks.store', $research) }}" class="mt-4 grid gap-3">
                @csrf
                <input name="title" required class="rounded-xl border-slate-300" placeholder="Task title">
                <textarea name="description" class="rounded-xl border-slate-300" placeholder="Description"></textarea>
                <div class="grid gap-3 sm:grid-cols-3">
                    <select name="assigned_to" class="rounded-xl border-slate-300">
                        <option value="">Unassigned</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                    <select name="priority" class="rounded-xl border-slate-300">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="low">Low</option>
                    </select>
                    <input type="datetime-local" name="due_at" class="rounded-xl border-slate-300">
                </div>
                <button class="rounded-xl bg-indigo-600 px-4 py-2.5 font-bold text-white">Assign task</button>
            </form>

            <div class="mt-5 space-y-2">
                @forelse($research->tasks as $task)
                    <form method="POST" action="{{ route('research.tasks.update', [$research, $task]) }}" class="flex flex-col gap-3 rounded-xl bg-slate-50 p-3 sm:flex-row sm:items-center">
                        @csrf
                        @method('PATCH')
                        <div class="flex-1">
                            <p class="font-semibold text-slate-900">{{ $task->title }}</p>
                            <p class="text-xs text-slate-500">{{ str($task->priority)->headline() }} · {{ $task->due_at?->diffForHumans() ?? 'No deadline' }}</p>
                        </div>
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            @foreach(['open','in_progress','blocked','completed','cancelled'] as $status)
                                <option value="{{ $status }}" @selected($task->status === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700">Update</button>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">No project tasks yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-900">Meetings, attendance, notes and action items</h2>
        <form method="POST" action="{{ route('research.meetings.store', $research) }}" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <input type="datetime-local" name="scheduled_at" required class="rounded-xl border-slate-300">
            <input type="number" name="duration_minutes" value="60" min="15" class="rounded-xl border-slate-300">
            <input name="location" class="rounded-xl border-slate-300" placeholder="Physical location">
            <input type="url" name="online_url" class="rounded-xl border-slate-300" placeholder="Online meeting URL">
            <textarea name="agenda" class="rounded-xl border-slate-300 md:col-span-2" placeholder="Agenda"></textarea>
            <select name="attendee_ids[]" multiple class="rounded-xl border-slate-300 md:col-span-2">
                @foreach($members as $member)
                    <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-blue-600 px-4 py-2.5 font-bold text-white md:col-span-2">Schedule meeting</button>
        </form>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse($research->meetings->sortByDesc('scheduled_at') as $meeting)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $meeting->scheduled_at?->toDayDateTimeString() ?? 'Schedule pending' }}</p>
                            <p class="text-sm text-slate-500">{{ $meeting->location ?: $meeting->online_url ?: 'Location not set' }} · {{ str($meeting->status)->headline() }}</p>
                        </div>
                        <a href="{{ route('research.meetings.calendar', [$research, $meeting]) }}" class="shrink-0 text-sm font-bold text-blue-600">Add to calendar</a>
                    </div>

                    @if($meeting->agenda)
                        <p class="mt-3 text-sm leading-6 text-slate-700">{{ $meeting->agenda }}</p>
                    @endif
                    <div class="mt-3 text-xs text-slate-500">Attendees: {{ $meeting->attendees->pluck('user.full_name')->filter()->implode(', ') ?: 'None selected' }}</div>

                    @if($meeting->status !== 'completed')
                        <form method="POST" action="{{ route('research.meetings.complete', [$research, $meeting]) }}" class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                            @csrf
                            <textarea name="notes" class="w-full rounded-lg border-slate-300" placeholder="Meeting notes"></textarea>
                            <div class="flex flex-wrap gap-3">
                                @foreach($meeting->attendees as $attendee)
                                    <label class="text-sm text-slate-700"><input type="checkbox" name="attendance[{{ $attendee->user_id }}]" value="1"> {{ $attendee->user?->full_name }}</label>
                                @endforeach
                            </div>
                            <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white">Complete meeting</button>
                        </form>
                    @endif

                    @foreach($meeting->actionItemRecords as $item)
                        <div class="mt-2 flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-950">
                            <span class="flex-1">{{ $item->title }} · {{ $item->assignee?->full_name ?: 'Unassigned' }}</span>
                            @if($item->status !== 'completed')
                                <form method="POST" action="{{ route('research.action-items.complete', [$research, $item]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="font-bold text-emerald-700">Complete</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 lg:col-span-2">No meetings scheduled yet.</p>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Group authorship and contribution evidence</h2>
            <form method="POST" action="{{ route('research.members.store', $research) }}" class="mt-4 grid gap-3">
                @csrf
                <select name="user_id" class="rounded-xl border-slate-300">
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <div class="grid gap-3 sm:grid-cols-2">
                    <select name="role" class="rounded-xl border-slate-300">
                        @foreach(['lead_author','author','data_analyst','research_assistant','advisor'] as $role)
                            <option value="{{ $role }}">{{ str($role)->headline() }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" max="100" name="contribution_percent" class="rounded-xl border-slate-300" placeholder="Declared contribution %">
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                    @foreach(['write','comment','manage_references','manage_datasets','view_private','assign_tasks'] as $permission)
                        <label><input type="checkbox" name="permissions[]" value="{{ $permission }}"> {{ str($permission)->headline() }}</label>
                    @endforeach
                </div>
                <button class="rounded-xl bg-slate-900 px-4 py-2.5 font-bold text-white">Add or update collaborator</button>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th class="py-2 text-left">Member</th><th>Role</th><th>Measured</th><th>Declared</th></tr></thead>
                    <tbody>
                        @foreach($contributions as $row)
                            <tr class="border-t border-slate-100">
                                <td class="py-2 font-medium text-slate-800">{{ $row['name'] }}</td>
                                <td class="text-center">{{ str($row['role'])->headline() }}</td>
                                <td class="text-center">{{ $row['measured_percent'] }}%</td>
                                <td class="text-center">{{ $row['declared_percent'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Immutable archive and amendments</h2>

            @can('publish', $research)
                <form method="POST" action="{{ route('research.archives.seal', $research) }}" class="mt-4">
                    @csrf
                    <button class="rounded-xl bg-emerald-600 px-4 py-2.5 font-bold text-white">Seal approved version</button>
                </form>
            @endcan

            <div class="mt-4 space-y-2">
                @forelse($research->archives as $archive)
                    <a class="block rounded-xl bg-slate-50 p-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="{{ route('research.archives.download', [$research, $archive]) }}">Archive v{{ $archive->version }} · {{ $archive->status }} · {{ str($archive->checksum)->limit(18) }}</a>
                @empty
                    <p class="text-sm text-slate-500">No sealed archive versions yet.</p>
                @endforelse
            </div>

            @if($research->archives->isNotEmpty())
                <form method="POST" action="{{ route('research.amendments.store', $research) }}" class="mt-5 space-y-2">
                    @csrf
                    <textarea name="reason" required class="w-full rounded-xl border-slate-300" placeholder="Reason and controlled changes requested"></textarea>
                    <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Request amendment</button>
                </form>
            @endif

            @foreach($research->amendments as $amendment)
                <div class="mt-3 rounded-xl border border-slate-200 p-3">
                    <p class="font-semibold text-slate-900">{{ $amendment->reason }}</p>
                    <p class="text-xs text-slate-500">{{ str($amendment->status)->headline() }}</p>
                    @can('review', $research)
                        @if($amendment->status === 'pending')
                            <form method="POST" action="{{ route('research.amendments.review', [$research, $amendment]) }}" class="mt-2 flex flex-col gap-2 sm:flex-row">
                                @csrf
                                <select name="decision" class="rounded-lg border-slate-300"><option value="approve">Approve</option><option value="reject">Reject</option></select>
                                <input name="review_note" class="flex-1 rounded-lg border-slate-300" placeholder="Review note">
                                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">Record</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endforeach
        </section>
    </div>
</div>
@endsection

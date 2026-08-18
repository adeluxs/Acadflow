@extends('layouts.app')
@section('title', $task->title)
@section('content')
@php
    $mySubmission = $studentSubmissions->first();
    $isBeforeOpen = $task->open_at && now()->lt($task->open_at);
    $effectiveClose = $task->allow_late_submissions ? $task->late_deadline : ($task->due_date ?? $task->close_at);
    $isClosed = $effectiveClose && now()->gt($effectiveClose);
    $canResubmit = !$mySubmission || $task->max_resubmissions===null || $mySubmission->resubmission_count < $task->max_resubmissions;
@endphp
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <a href="{{ route('courses.assignments',$course) }}" class="text-sm font-semibold text-indigo-200 hover:text-white">← Back to assignments</a>
        <div class="mt-5 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-4xl"><div class="flex flex-wrap gap-2"><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">{{ str($task->type)->headline() }}</span>@if($mySubmission)<span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-200">{{ $mySubmission->is_late ? 'Late · ' : '' }}{{ str($mySubmission->status)->headline() }}</span>@else<span class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-bold text-amber-200">Not submitted</span>@endif</div><h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $task->title }}</h1><p class="mt-3 text-sm text-slate-300">{{ $course->code }} · {{ $course->name }}</p></div>
            <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 text-center backdrop-blur"><p class="text-xs text-slate-300">Maximum score</p><p class="mt-1 text-3xl font-black">{{ $task->max_score }}</p></div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_340px]">
        <main class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">Assignment brief</p><h2 class="mt-1 text-xl font-black text-slate-900">What you need to do</h2>
                @if($task->description)<p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $task->description }}</p>@endif
                @if($task->instructions)<div class="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5"><p class="text-xs font-black uppercase tracking-wide text-indigo-600">Instructions</p><div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $task->instructions }}</div></div>@endif
            </section>

            @include('ai._contextual-assistant', ['assistantFeature' => 'assignment_assistant', 'assistantEndpoint' => route('ai.context.assignment',[$course,$task])])

            @if($task->attachments && $task->attachments->count())
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-900">Supporting materials</h2><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($task->attachments as $attachment)<a href="{{ route('submission-tasks.attachment.download',[$course,$task,$attachment]) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/30"><div><p class="font-bold text-slate-900">{{ $attachment->file_name }}</p><p class="mt-1 text-xs text-slate-500">{{ str($attachment->type)->headline() }}</p></div><span class="text-sm font-bold text-indigo-600">Download</span></a>@endforeach</div></section>
            @endif

            @if($studentSubmissions->isNotEmpty())
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-xl font-black text-slate-900">Your submissions</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $studentSubmissions->count() }} version{{ $studentSubmissions->count()===1?'':'s' }}</span></div><div class="mt-5 space-y-4">
                    @foreach($studentSubmissions as $sub)
                        <article class="rounded-2xl border {{ $sub->grade ? 'border-violet-200 bg-violet-50/40' : 'border-slate-200' }} p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-black text-slate-900">Submission {{ $loop->iteration }}</p><p class="mt-1 text-xs text-slate-500">{{ $sub->submitted_at ? 'Submitted '.$sub->submitted_at->diffForHumans() : 'Draft' }} @if($sub->is_late)· <span class="font-bold text-rose-600">Late</span>@endif</p></div>@if($sub->grade)<p class="text-2xl font-black text-violet-700">{{ $sub->grade->score }}/{{ $task->max_score }}</p>@endif</div>
                        @if($sub->versions->isNotEmpty())<div class="mt-4 grid gap-2">@foreach($sub->versions as $file)<a href="{{ route('submission-versions.download',$file) }}" class="flex items-center justify-between rounded-xl bg-white px-3 py-2 text-sm"><span class="font-semibold text-slate-700">{{ $file->file_name }}</span><span class="font-bold text-indigo-600">Download</span></a>@endforeach</div>@endif
                        @if($sub->comments && $sub->comments->count())<div class="mt-4 space-y-2 border-t border-slate-200 pt-4">@foreach($sub->comments as $comment)<div class="rounded-xl bg-white p-3"><p class="text-xs font-bold text-slate-800">{{ $comment->user->name ?? $comment->user->full_name ?? 'Reviewer' }} · {{ $comment->created_at->format('M j, H:i') }}</p><p class="mt-1 text-sm text-slate-600">{{ $comment->comment }}</p></div>@endforeach</div>@endif
                        </article>
                    @endforeach
                </div></section>
            @endif
        </main>

        <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Timeline</h2><div class="mt-4 space-y-4 text-sm"><div><p class="text-xs font-bold uppercase text-slate-400">Opens</p><p class="mt-1 font-bold text-slate-800">{{ $task->open_at?->format('M j, Y · H:i') ?? 'Immediately' }}</p></div><div><p class="text-xs font-bold uppercase text-slate-400">Due</p><p class="mt-1 font-bold text-slate-800">{{ $task->due_date?->format('M j, Y · H:i') ?? 'Not set' }}</p></div><div><p class="text-xs font-bold uppercase text-slate-400">Hard deadline</p><p class="mt-1 font-bold text-slate-800">{{ $task->late_deadline?->format('M j, Y · H:i') ?? 'Not set' }}</p></div></div></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Submission requirements</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">Files</dt><dd class="font-bold">{{ $task->min_file_count }}–{{ $task->max_file_count }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Max size</dt><dd class="font-bold">{{ $task->max_file_size_mb }} MB</dd></div><div><dt class="text-slate-500">Formats</dt><dd class="mt-1 break-words font-bold">{{ implode(', ',array_map('strtoupper',$task->allowed_file_types ?? [])) ?: 'As instructed' }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Resubmissions</dt><dd class="font-bold">{{ $task->max_resubmissions ?? 'Unlimited' }}</dd></div></dl>@if($task->late_submission_penalty_percent>0)<div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">Late work may receive a {{ $task->late_submission_penalty_percent }}% penalty.</div>@endif</section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                @if(!$isBeforeOpen && !$isClosed && $canResubmit)<a href="{{ route('submissions.create') }}?task_id={{ $task->id }}" class="block w-full rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-black text-white hover:bg-indigo-700">{{ $mySubmission ? 'Resubmit assignment' : 'Submit assignment' }}</a>
                @elseif($isBeforeOpen)<div class="rounded-xl bg-slate-100 p-4 text-center text-sm font-bold text-slate-600">Opens {{ $task->open_at->diffForHumans() }}</div>
                @elseif($isClosed)<div class="rounded-xl bg-rose-50 p-4 text-center text-sm font-bold text-rose-700">Submission window closed</div>
                @else<div class="rounded-xl bg-amber-50 p-4 text-center text-sm font-bold text-amber-700">Maximum resubmissions reached</div>@endif
                @if($mySubmission && $mySubmission->status==='draft')<form action="{{ route('submissions.submit',$mySubmission) }}" method="POST" class="mt-3">@csrf<button onclick="return confirm('Submit this assignment? You cannot edit it after submission.')" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-black text-white">Submit current draft</button></form>@endif
            </section>
        </aside>
    </div>
</div>
@endsection

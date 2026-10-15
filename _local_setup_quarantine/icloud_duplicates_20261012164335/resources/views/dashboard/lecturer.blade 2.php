@extends('layouts.app')

@section('title', 'Lecturer Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Welcome back, '.(auth()->user()->first_name ?? 'Lecturer').' 👋')

@section('content')
@php
    $overviewTotal = max(1, (int)($submissionOverview['total'] ?? 0));
    $pendingDeg = (($submissionOverview['pending'] ?? 0) / $overviewTotal) * 360;
    $reviewDeg = $pendingDeg + (($submissionOverview['in_review'] ?? 0) / $overviewTotal) * 360;
    $returnedDeg = $reviewDeg + (($submissionOverview['returned'] ?? 0) / $overviewTotal) * 360;
    $dashboardUser = auth()->user();
    $featureVisible = fn (string $feature): bool => \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
@endphp
<div class="space-y-5">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label'=>'My Courses','value'=>$courses->count(),'sub'=>'Active courses','icon'=>'book','classes'=>'bg-indigo-50 text-indigo-600'],
            ['label'=>'My Students','value'=>$studentCount,'sub'=>'Unique students','icon'=>'users','classes'=>'bg-emerald-50 text-emerald-600'],
            ['label'=>'Pending Reviews','value'=>$pendingReviews,'sub'=>'Submissions','icon'=>'file','classes'=>'bg-orange-50 text-orange-600'],
            ['label'=>'Average Class','value'=>$averageClass !== null ? number_format($averageClass, 1).'%' : '—','sub'=>'Graded work','icon'=>'chart','classes'=>'bg-blue-50 text-blue-600'],
        ] as $stat)
            <article class="acad-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-medium text-slate-500">{{ $stat['label'] }}</p><p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $stat['value'] }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $stat['sub'] }}</p></div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $stat['classes'] }}">
                        @if($stat['icon']==='book')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 4h5a3 3 0 013 3v13a3 3 0 00-3-3H5zM19 4h-5a3 3 0 00-3 3v13a3 3 0 013-3h5z"/></svg>
                        @elseif($stat['icon']==='users')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75"/></svg>
                        @elseif($stat['icon']==='file')<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6"/></svg>
                        @else<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg>@endif
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    @if($featureVisible('submissions'))
    <section class="grid gap-5 xl:grid-cols-2">
        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold text-slate-950">Submission Overview</h2><a href="{{ route('submissions.lecturer-index') }}" class="text-xs font-semibold acad-link">View all</a></div>
            <div class="mt-5 grid gap-5 sm:grid-cols-[180px_1fr] sm:items-center">
                <div class="mx-auto flex h-36 w-36 items-center justify-center rounded-full" style="background:conic-gradient(#f59e0b 0 {{ $pendingDeg }}deg,#2563eb {{ $pendingDeg }}deg {{ $reviewDeg }}deg,#7c3aed {{ $reviewDeg }}deg {{ $returnedDeg }}deg,#10b981 {{ $returnedDeg }}deg 360deg)">
                    <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white shadow-inner"><span class="text-2xl font-black">{{ $submissionOverview['total'] ?? 0 }}</span><span class="text-[10px] text-slate-500">Total</span></div>
                </div>
                <div class="space-y-3 text-xs">
                    @foreach([
                        ['Pending Review',$submissionOverview['pending'] ?? 0,'bg-amber-500'],
                        ['In Review',$submissionOverview['in_review'] ?? 0,'bg-blue-600'],
                        ['Returned',$submissionOverview['returned'] ?? 0,'bg-violet-600'],
                        ['Approved',$submissionOverview['approved'] ?? 0,'bg-emerald-500'],
                    ] as $row)
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-2 last:border-0"><span class="h-2 w-2 rounded-full {{ $row[2] }}"></span><span class="flex-1 text-slate-600">{{ $row[0] }}</span><strong class="text-slate-900">{{ $row[1] }}</strong></div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold text-slate-950">Recent Submissions</h2><a href="{{ route('submissions.lecturer-index') }}" class="text-xs font-semibold acad-link">View all</a></div>
            <div class="mt-4 space-y-2">
                @forelse($recentSubmissions->take(5) as $submission)
                <a href="{{ route('submissions.review', $submission) }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/30">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M6 3h9l4 4v14H6zM9 13h6M9 17h4"/></svg></div>
                    <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-900">{{ $submission->title }}</p><p class="mt-0.5 truncate text-[10px] text-slate-500">{{ $submission->course?->code }} · {{ $submission->user?->full_name }}</p></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600">{{ str($submission->status)->headline() }}</span>
                </a>
                @empty <p class="rounded-xl bg-slate-50 p-5 text-center text-xs text-slate-500">No submissions yet.</p> @endforelse
            </div>
        </article>
    </section>
    @endif

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,.55fr)]">
        @if($featureVisible('courses'))
        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold">Course Performance</h2><a href="{{ route('lecturer.courses') }}" class="text-xs font-semibold acad-link">View courses</a></div>
            <div class="mt-5 space-y-4">
                @forelse($coursePerformance as $item)
                @php($score = $item['average'] ?? 0)
                <div><div class="mb-1.5 flex items-center justify-between text-xs"><span class="font-medium text-slate-700">{{ $item['course']->code }} · {{ $item['course']->name }}</span><span class="font-bold text-slate-900">{{ $item['average'] !== null ? number_format($item['average'],1).'%' : 'No grades' }}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" style="width:{{ min(100,max(0,$score)) }}%"></div></div></div>
                @empty <p class="text-xs text-slate-500">No assigned courses yet.</p> @endforelse
            </div>
        </article>
        @endif

        @if($featureVisible('ai_assistant'))
        <article class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white via-indigo-50/50 to-violet-100/70 p-5 shadow-sm">
            <div class="absolute -right-6 -top-8 h-28 w-28 rounded-full bg-indigo-200/40 blur-xl"></div>
            <div class="relative"><div class="flex items-center gap-2"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-950 text-white">AI</div><div><h2 class="text-sm font-bold">AI Assistant</h2><p class="text-[10px] text-slate-500">Your academic copilot</p></div></div>
            <div class="mt-4 space-y-2 text-xs"><a href="{{ route('submissions.lecturer-index') }}" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Analyze submissions</a><a href="{{ route('ai.lecturer.layout.preferences') }}" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Set document standards</a><a href="{{ route('research.index') }}" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Research assistance</a></div>
            <a href="{{ route('ai.assistant') }}" class="mt-4 flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white">Open AI tools →</a></div>
        </article>
        @endif
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        @if($featureVisible('attendance') || $featureVisible('assignments'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Upcoming Schedule</h2><a href="{{ route('attendance.lecturer') }}" class="text-xs font-semibold acad-link">Attendance</a></div><div class="mt-4 space-y-2">
            @forelse($upcomingTasks as $task)<a href="{{ route('submission-tasks.lecturer.show', [$task->course, $task]) }}" class="flex items-center gap-3 rounded-xl border-b border-slate-100 px-1 py-3 last:border-0"><div class="w-12 rounded-lg bg-indigo-50 p-2 text-center"><p class="text-[9px] font-bold uppercase text-indigo-500">{{ ($task->close_at ?? $task->due_date)?->format('M') }}</p><p class="text-lg font-black text-indigo-800">{{ ($task->close_at ?? $task->due_date)?->format('d') }}</p></div><div class="min-w-0"><p class="truncate text-xs font-semibold">{{ $task->title }}</p><p class="text-[10px] text-slate-500">{{ $task->course?->code }} · {{ ($task->close_at ?? $task->due_date)?->format('g:i A') }}</p></div></a>@empty<p class="text-xs text-slate-500">No upcoming deadlines.</p>@endforelse
        </div></article>
        @endif

        @if($featureVisible('submissions'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Recent Activity</h2><span class="text-[10px] text-slate-400">Latest course activity</span></div><div class="mt-4 space-y-3">
            @forelse($recentSubmissions->take(5) as $submission)<div class="flex gap-3"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><div class="min-w-0 flex-1"><p class="truncate text-xs font-medium">New submission: {{ $submission->title }}</p><p class="text-[10px] text-slate-500">{{ $submission->course?->code }} · {{ $submission->created_at?->diffForHumans() }}</p></div></div>@empty<p class="text-xs text-slate-500">No recent activity.</p>@endforelse
        </div></article>
        @endif
    </section>
</div>
@endsection

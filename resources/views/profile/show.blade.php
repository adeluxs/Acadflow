@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Your identity, institution membership and academic access')

@section('content')
@php
    $toneClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $membershipClass = $toneClasses[$membershipStatus['tone']] ?? $toneClasses['slate'];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    {{-- Identity header --}}
    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-blue-800 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[1.5rem] bg-white text-3xl font-black text-indigo-700 shadow-lg">
                @if($user->avatar_media_id && $user->avatarMedia)
                    <img src="{{ route('media.preview', $user->avatarMedia) }}" alt="{{ $user->full_name }} profile photo" class="h-full w-full object-cover">
                @elseif($user->avatar)
                    <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->full_name }} profile photo" class="h-full w-full object-cover">
                @else
                    {{ strtoupper(substr($user->first_name ?: 'U', 0, 1)) }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ $user->full_name }}</h1>
                <p class="mt-1 text-sm text-indigo-100">
                    @if($user->username)&#64;{{ $user->username }} · @endif{{ $user->email }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $user->role)) }}</span>
                    @if($user->account_type)
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $user->account_type)) }}</span>
                    @endif
                    <span class="rounded-full px-3 py-1 ring-1 {{ $membershipStatus['is_member'] ? 'bg-emerald-400/20 ring-emerald-300/30' : 'bg-white/10 ring-white/15' }}">{{ $membershipStatus['label'] }}</span>
                    <span class="rounded-full px-3 py-1 ring-1 {{ $user->is_active ? 'bg-emerald-400/20 ring-emerald-300/30' : 'bg-rose-400/20 ring-rose-300/30' }}">{{ $user->is_active ? 'Active account' : 'Inactive account' }}</span>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-6">
            {{-- Institution membership --}}
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-950">Institution & membership</h2>
                    <span class="rounded-full px-3 py-1 text-[11px] font-black ring-1 {{ $membershipClass }}">{{ $membershipStatus['label'] }}</span>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Institution</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">{{ $university?->name ?? 'Independent / not attached' }}</dd>
                        @if($university?->code)<dd class="text-xs text-slate-500">{{ $university->short_name ?: $university->name }} · {{ $university->code }}</dd>@endif
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Faculty</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">{{ $faculty?->name ?? '—' }}</dd>
                        @if($faculty?->code)<dd class="text-xs text-slate-500">{{ $faculty->code }}</dd>@endif
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Department</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">{{ $department?->name ?? '—' }}</dd>
                        @if($department?->code)<dd class="text-xs text-slate-500">{{ $department->code }}</dd>@endif
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Membership status</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">
                            @if($affiliation)
                                {{ \Illuminate\Support\Str::headline($affiliation->status) }}
                            @elseif($membershipStatus['is_member'])
                                Institution set (affiliation not recorded)
                            @else
                                Independent
                            @endif
                        </dd>
                        <dd class="mt-1 text-xs text-slate-500">{{ $membershipStatus['detail'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Academic session</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">{{ $academicSession?->name ?? 'No active session' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-[11px] font-black uppercase tracking-wide text-slate-400">Active semester</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-800">{{ $activeSemester?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Student enrollments --}}
            @if($user->isStudent())
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-slate-950">Course enrollment</h2>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-bold text-indigo-700">{{ $activeEnrollments->count() }} active</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse($activeEnrollments as $enrollment)
                            <a href="{{ route('courses.show', $enrollment->course) }}" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/30">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wide text-indigo-600">{{ $enrollment->course?->code }}</p>
                                    <p class="truncate font-bold text-slate-900">{{ $enrollment->course?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $enrollment->course?->access_mode === 'independent' ? 'Independent classroom' : ($enrollment->course?->department?->name ?? 'Department course') }} @if($enrollment->semester) · {{ $enrollment->semester->name }} @endif</p>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-black text-emerald-700">Enrolled</span>
                            </a>
                        @empty
                            <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500">You are not currently enrolled in any course. Use a course join link from your lecturer to enroll.</p>
                        @endforelse
                    </div>

                    @if($inactiveEnrollments->isNotEmpty())
                        <div class="mt-5">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Previous / dropped</h3>
                            <div class="mt-2 space-y-2">
                                @foreach($inactiveEnrollments as $enrollment)
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-3 text-sm">
                                        <span class="text-slate-700">{{ $enrollment->course?->code }} — {{ $enrollment->course?->name }}</span>
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">{{ \Illuminate\Support\Str::headline($enrollment->status) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($invitations->isNotEmpty())
                        <div class="mt-5">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Pending course invitations</h3>
                            <div class="mt-2 space-y-2">
                                @foreach($invitations as $invitation)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm">
                                        <div>
                                            <p class="font-bold text-amber-900">{{ $invitation->course?->code }} — {{ $invitation->course?->name }}</p>
                                            <p class="text-xs text-amber-700">Expires {{ $invitation->expires_at?->diffForHumans() }}</p>
                                        </div>
                                        @if($invitation->course)
                                            <a href="{{ route('courses.join.link', $invitation->course->uuid) }}" class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700">Join course</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Teaching (lecturer / admin) --}}
            @if($user->isLecturer() || $user->isAdmin())
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">Teaching & management</h2>
                    <div class="mt-4 space-y-2">
                        @forelse($teachingCourses as $assignment)
                            <a href="{{ $assignment->course ? route('courses.show', $assignment->course) : '#' }}" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 p-4 hover:border-indigo-200">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wide text-indigo-600">{{ $assignment->course?->code }}</p>
                                    <p class="truncate font-bold text-slate-900">{{ $assignment->course?->name }}</p>
                                    @if($assignment->semester)<p class="text-xs text-slate-500">{{ $assignment->semester->name }}</p>@endif
                                </div>
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-black text-indigo-700">{{ $assignment->is_coordinator ? 'Coordinator' : 'Teaching team' }}</span>
                            </a>
                        @empty
                            <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-500">No course assignments recorded for the active semester.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            {{-- Role & permissions --}}
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-950">Role & permissions</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">{{ $permissions->count() }} permissions</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Platform role: <strong>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $user->role)) }}</strong>@if($user->account_type) · Account type: <strong>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $user->account_type)) }}</strong>@endif</p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @forelse($permissions as $permission)
                        <span class="rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">{{ $permission }}</span>
                    @empty
                        <span class="text-sm text-slate-500">No permissions are granted to this role.</span>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="space-y-6">
            {{-- Access restrictions --}}
            <section class="rounded-3xl border p-6 shadow-sm {{ $restrictions === [] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                <h2 class="text-sm font-black text-slate-950">Access & restrictions</h2>
                @if($restrictions === [])
                    <p class="mt-2 text-sm font-semibold text-emerald-800">Your account has no access restrictions.</p>
                @else
                    <ul class="mt-2 space-y-2 text-xs leading-5 text-amber-900">
                        @foreach($restrictions as $restriction)
                            <li class="rounded-xl bg-white/70 p-3">{{ $restriction }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-[11px] text-slate-500">
                    Course enrollment scope: {{ $university ? 'institution' : 'independent only' }}@if($departmentRestricted && $university) · department-restricted @endif.
                </p>
            </section>

            {{-- Account details --}}
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Account details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-0.5 font-semibold text-slate-800 break-all">{{ $user->email }}</dd></div>
                    @if($user->phone)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Phone</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->phone }}</dd></div>@endif
                    @if($user->programme)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Programme</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->programme }}</dd></div>@endif
                    @if($user->academic_level)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Academic level</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->academic_level }}</dd></div>@endif
                    @if($user->location || $user->country_code)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Location</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ trim(($user->location ?? '').' '.($user->country_code ?? '')) }}</dd></div>@endif
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Profile visibility</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ \Illuminate\Support\Str::headline($user->profile_visibility ?? 'public') }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Email verified</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->email_verified_at ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Onboarding</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->onboarding_completed_at ? 'Complete' : 'Incomplete' }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Member since</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->created_at?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Last login</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
                </dl>
            </section>

            {{-- Quick links --}}
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Manage</h2>
                <div class="mt-3 grid gap-2 text-xs font-bold text-indigo-700">
                    @if(Route::has('knowledge.creator.edit'))
                        <a href="{{ route('knowledge.creator.edit') }}" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Public creator profile</a>
                    @endif
                    @if(Route::has('security.show'))
                        <a href="{{ route('security.show') }}" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Security & two-factor</a>
                    @endif
                    @if(Route::has('notifications.settings'))
                        <a href="{{ route('notifications.settings') }}" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">Notification preferences</a>
                    @endif
                    @if($user->isStudent() && Route::has('student.learning-profile'))
                        <a href="{{ route('student.learning-profile') }}" class="rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-indigo-50">AI learning preferences</a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

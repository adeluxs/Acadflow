@extends('layouts.onboarding')
@section('title', 'Manage onboarding and profile')
@section('content')
@php
    $labels = ['path' => 'Account path', 'profile' => 'Personal profile', 'affiliation' => 'Academic affiliation', 'interests' => 'Academic interests', 'preferences' => 'Privacy and notifications', 'review' => 'Review and finish'];
    $statusLabels = ['completed' => 'Completed', 'skipped' => 'Skipped', 'incomplete' => 'Incomplete'];
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-sm font-semibold text-blue-700">Your academic identity</p><h1 class="mt-2 text-3xl font-black">Manage onboarding</h1><p class="mt-3 text-slate-600">Complete your profile at your own pace. Revisit any section to update your saved information.</p></div>
            <a href="{{ route($user->onboarding_completed_at ? 'dashboard' : 'onboarding.show') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">{{ $user->onboarding_completed_at ? 'Back to dashboard' : 'Continue setup' }}</a>
        </div>
        <p class="mt-6 font-bold">Your academic profile is {{ $completion['percentage'] }}% complete</p>
        <div role="progressbar" aria-label="Profile completion" aria-valuenow="{{ $completion['percentage'] }}" aria-valuemin="0" aria-valuemax="100" class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-700 transition-all" style="width: {{ $completion['percentage'] }}%"></div></div>
        <p class="mt-3 text-sm text-slate-600">{{ $completion['fully_completed'] ? 'Fully Completed · All applicable profile information provided.' : ($completion['mandatory_complete'] ? 'Completed · Required profile information is ready. Optional details can be added anytime.' : 'Incomplete · Required information is still missing.') }}</p>
        <p class="mt-2 text-xs text-slate-500">{{ $completion['filled_fields'] }} of {{ $completion['total_fields'] }} applicable fields provided. Optional steps do not restrict your workspace after setup.</p>
    </header>
    <ol class="grid gap-4 sm:grid-cols-2">
        @foreach($completion['steps'] as $key => $details)
            <li class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3"><h2 class="font-bold">{{ $labels[$key] }}</h2><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $details['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($details['status'] === 'skipped' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-800') }}">{{ $statusLabels[$details['status']] }}</span></div>
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $details['required'] ? 'Required' : 'Optional' }}</p>
                @if($details['missing_fields'])<p class="mt-3 text-sm text-slate-600">Add {{ implode(', ', $details['missing_labels']) }} for a more personalised experience.</p>@else<p class="mt-3 text-sm text-slate-600">Your saved information is ready to review or update.</p>@endif
                <a href="{{ $details['url'] }}" class="mt-4 self-start rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">{{ $details['status'] === 'completed' ? 'Edit' : 'Complete' }}</a>
            </li>
        @endforeach
    </ol>
    @if($user->onboarding_completed_at)
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold">Connect your academic community</h2><p class="mt-2 text-sm text-slate-600">Find an adopted department or institution, request membership, or accept a secure invitation using this account. Approval is required for organisational access.</p>
        <div class="mt-4 flex flex-wrap gap-3">@if($membershipRequestsAvailable)<a href="{{ route('institution.access') }}" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Search and request membership</a>@endif<a href="{{ route('academic.organisations') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">Memberships and invitations</a></div>
        @unless($membershipRequestsAvailable)<p class="mt-3 text-sm text-slate-500">New membership requests are currently unavailable. You can still manage existing memberships and accept invitations.</p>@endunless
        <ul class="mt-4 space-y-2">@foreach($memberships as $membership)<li class="rounded-xl bg-slate-50 p-3 text-sm"><strong>{{ $membership->department?->name ?? $membership->university?->name }}</strong> · {{ $membership->university?->name }} · {{ $membership->isVerified() ? 'Approved' : ucwords(str_replace('_', ' ', $membership->status)) }}</li>@endforeach</ul>
    </section>
    @if($user->account_type === 'lecturer')
        <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Teaching and supervision</h2><p class="mt-2 text-sm text-slate-600">Manage your existing courses and supervision work in your academic workspace.</p><a href="{{ route('courses.index') }}" class="mt-3 inline-block font-bold text-blue-700">Manage courses →</a></section>
    @endif
    @if($user->account_type === 'department_representative' || $user->isDepartmentAdmin())
        <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Department setup</h2><p class="mt-2 text-sm text-slate-600">Update your department information, verification evidence, lecturer invitations, modules and academic calendar through the existing setup tools.</p><div class="mt-4 flex flex-wrap gap-4"><a href="{{ route('department.onboarding') }}" class="font-bold text-blue-700">Department information and verification →</a><a href="{{ route('academic.organisations') }}#invite-members" class="font-bold text-blue-700">Invitations and departmental configuration →</a></div>@foreach($departmentApplications as $application)<p class="mt-3 text-sm">Department application · {{ ucwords(str_replace('_', ' ', $application->status)) }}</p>@endforeach</section>
    @endif
    @if($user->account_type === 'university_representative' || $user->isUniversityAdmin())
        <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Institution setup</h2><p class="mt-2 text-sm text-slate-600">Review your institution profile and verification, then manage faculties, departments and administration in your authorised institution workspace.</p><div class="mt-4 flex flex-wrap gap-4"><a href="{{ route('institution.onboarding.request') }}" class="font-bold text-blue-700">Institution profile and verification →</a><a href="{{ route('academic.organisations') }}" class="font-bold text-blue-700">Institution administration and configuration →</a></div>@foreach($institutionApplications as $application)<p class="mt-3 text-sm">Institution application · {{ ucwords(str_replace('_', ' ', $application->status)) }}</p>@endforeach</section>
    @endif
    @endif
    @if($organisationSteps && $user->onboarding_completed_at)
    <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-bold">Organisation setup progress</h2><p class="mt-2 text-sm text-slate-600">Organisation approval and configuration are tracked separately from your personal profile.</p><ul class="mt-4 space-y-3">@foreach($organisationSteps as $item)<li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4"><div><strong class="text-sm">{{ $item['label'] }}</strong><p class="mt-1 text-xs font-semibold text-slate-500">{{ $item['status'] }}</p></div><a href="{{ $item['url'] }}" class="text-sm font-bold text-blue-700">{{ $item['status'] === 'Completed' ? 'Edit' : 'Continue' }} →</a></li>@endforeach</ul></section>
    @endif
    <p class="text-sm text-slate-500">Notification frequency and quiet hours are available in <a class="font-semibold text-blue-700" href="{{ route('notifications.settings') }}">Notification Preferences</a>.</p>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Onboarding Management')
@section('page-title', 'Onboarding Management')
@section('page-subtitle', 'Inspect profile completion without exposing private passwords or credentials')
@section('content')
<form class="mb-6 grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-3">
    <select name="status" class="rounded-xl border-slate-300"><option value="">All completion states</option><option value="complete" @selected(request('status') === 'complete')>Complete</option><option value="incomplete" @selected(request('status') === 'incomplete')>Incomplete</option></select>
    <select name="account_type" class="rounded-xl border-slate-300"><option value="">All account paths</option>@foreach($accountPaths as $value => $label)<option value="{{ $value }}" @selected(request('account_type') === $value)>{{ $label }}</option>@endforeach</select>
    <button class="rounded-xl bg-blue-700 px-4 py-2 font-semibold text-white">Filter</button>
</form>
<div class="overflow-hidden rounded-2xl border bg-white">
    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Path</th><th class="px-4 py-3">Institution</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Completed</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($users as $user)<tr><td class="px-4 py-4"><strong>{{ $user->full_name }}</strong><span class="block text-slate-500">{{ $user->email }}</span></td><td class="px-4 py-4">{{ $accountPaths[$user->account_type] ?? 'Not selected' }}</td><td class="px-4 py-4">{{ $user->university?->name ?? 'Independent' }}<span class="block text-slate-500">{{ $user->department?->name }}</span></td><td class="px-4 py-4">Step {{ $user->onboardingState?->current_step ?? 1 }} / 6</td><td class="px-4 py-4">{{ $user->onboarding_completed_at?->toDayDateTimeString() ?? 'Incomplete' }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No users match these filters.</td></tr>@endforelse</tbody></table></div>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection

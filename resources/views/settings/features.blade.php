@extends('layouts.app')

@section('title', 'Feature & Module Management')

@section('content')
@php
    $statusMeta = [
        'enabled' => ['label' => 'Enabled', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'dot' => 'bg-emerald-500'],
        'maintenance' => ['label' => 'Maintenance', 'badge' => 'bg-amber-50 text-amber-700 ring-amber-200', 'dot' => 'bg-amber-500'],
        'disabled' => ['label' => 'Disabled / Unreleased', 'badge' => 'bg-slate-100 text-slate-700 ring-slate-200', 'dot' => 'bg-slate-500'],
    ];
    $counts = collect($features)->countBy('configured_status');
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500">
                <a href="{{ route('admin.settings') }}" class="acad-link">Settings</a>
                <span>/</span>
                <span>Feature & Module Management</span>
            </div>
            <h1 class="text-3xl font-black tracking-tight text-slate-950">Feature & Module Management</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                One authoritative release-control centre for AcadFlow. Administrators retain preview access when a feature is in maintenance or disabled; normal users are restricted at the backend, not only in the menu.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.settings') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">← Back to Settings</a>
            <a href="{{ route('admin.settings.audit-logs') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Audit Logs</a>
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        @foreach(['enabled','maintenance','disabled'] as $status)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><span class="h-2.5 w-2.5 rounded-full {{ $statusMeta[$status]['dot'] }}"></span>{{ $statusMeta[$status]['label'] }}</span>
                    <span class="text-2xl font-black text-slate-950">{{ $counts[$status] ?? 0 }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-6 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
        <div class="flex gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 3.8L2.7 17a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 3.8a2 2 0 00-3.4 0z"/></svg>
            <div>
                <p class="font-bold">Core recovery controls are intentionally protected.</p>
                <p class="mt-1 text-indigo-800/80">Authentication, authorization, onboarding, account security, Admin Settings and Feature Management itself are not listed here, so you cannot accidentally lock yourself out of the system.</p>
            </div>
        </div>
    </div>

    <div class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_220px_220px]">
        <label class="block">
            <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</span>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" stroke-linecap="round" d="M21 21l-4.3-4.3m1.3-5.2a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                <input id="feature-search" type="search" placeholder="Search features, categories or identifiers…" class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-[var(--acad-primary)] focus:ring-2 focus:ring-[rgb(var(--acad-primary-rgb)/.15)]">
            </div>
        </label>
        <label class="block">
            <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</span>
            <select id="feature-status-filter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--acad-primary)]">
                <option value="">All statuses</option>
                <option value="enabled">Enabled</option>
                <option value="maintenance">Maintenance</option>
                <option value="disabled">Disabled / Unreleased</option>
            </select>
        </label>
        <label class="block">
            <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Category</span>
            <select id="feature-category-filter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--acad-primary)]">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div id="feature-list" class="space-y-4">
        @foreach($features as $feature)
            @php
                $configured = $feature['configured_status'];
                $effective = $feature['effective_status'];
                $meta = $statusMeta[$configured] ?? $statusMeta['enabled'];
                $effectiveMeta = $statusMeta[$effective] ?? $statusMeta['enabled'];
            @endphp
            <article
                class="feature-row overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                data-feature-search="{{ strtolower($feature['key'].' '.$feature['title'].' '.$feature['category'].' '.$feature['description']) }}"
                data-feature-status="{{ $configured }}"
                data-feature-category="{{ $feature['category'] }}"
            >
                <form method="POST" action="{{ route('admin.settings.features.update', $feature['key']) }}" class="feature-form" data-feature-title="{{ $feature['title'] }}" data-impact="{{ $feature['impact'] }}">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_230px] lg:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $meta['badge'] }}"><span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ $feature['category'] }}</span>
                                @if($effective !== $configured)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $effectiveMeta['badge'] }}">Effective: {{ $effectiveMeta['label'] }}</span>
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h2 class="text-lg font-bold text-slate-950">{{ $feature['title'] }}</h2>
                                <code class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">{{ $feature['key'] }}</code>
                            </div>
                            <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">{{ $feature['description'] }}</p>

                            @if($feature['depends_on'])
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="font-semibold text-slate-600">Depends on:</span>
                                    @foreach($feature['depends_on'] as $dependency)
                                        <span class="rounded-md bg-slate-50 px-2 py-1 ring-1 ring-slate-200">{{ \App\Services\FeatureAccessService::metadata($dependency)['title'] ?? $dependency }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($feature['blocked_by'])
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    <strong>Dependency restriction:</strong>
                                    @foreach($feature['blocked_by'] as $blocked)
                                        {{ $blocked['title'] }} is {{ $blocked['status'] }}{{ !$loop->last ? ';' : '.' }}
                                    @endforeach
                                    This child feature cannot become effectively enabled until its dependency is enabled.
                                </div>
                            @endif

                            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60">
                                <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-slate-700">Maintenance message & internal admin note</summary>
                                <div class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-1.5 block text-xs font-semibold text-slate-700">User-facing maintenance message</span>
                                        <textarea name="maintenance_message" rows="4" maxlength="1000" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-[var(--acad-primary)] focus:ring-2 focus:ring-[rgb(var(--acad-primary-rgb)/.15)]" placeholder="Optional. A professional default is used when empty.">{{ old('maintenance_message', $feature['maintenance_message']) }}</textarea>
                                        <span class="mt-1 block text-[11px] text-slate-500">Shown only when the effective state is Maintenance.</span>
                                    </label>
                                    <label class="block">
                                        <span class="mb-1.5 block text-xs font-semibold text-slate-700">Internal admin note</span>
                                        <textarea name="admin_note" rows="4" maxlength="2000" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-[var(--acad-primary)] focus:ring-2 focus:ring-[rgb(var(--acad-primary-rgb)/.15)]" placeholder="Why was this changed? This is never shown to users.">{{ old('admin_note', $feature['admin_note']) }}</textarea>
                                        <span class="mt-1 block text-[11px] text-slate-500">Visible only in this administrator area and audit history.</span>
                                    </label>
                                </div>
                            </details>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <label class="block">
                                <span class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Runtime status</span>
                                <select name="status" class="feature-status-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800 outline-none focus:border-[var(--acad-primary)]">
                                    <option value="enabled" @selected($configured === 'enabled')>Enabled</option>
                                    <option value="maintenance" @selected($configured === 'maintenance')>Maintenance</option>
                                    <option value="disabled" @selected($configured === 'disabled')>Disabled / Unreleased</option>
                                </select>
                            </label>
                            <p class="mt-3 text-xs leading-5 text-slate-500">Admins keep access for testing. Backend route/API enforcement prevents direct-URL bypass by normal users.</p>
                            <button type="submit" class="acad-primary-button mt-4 inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold">Save feature</button>
                        </div>
                    </div>
                </form>
            </article>
        @endforeach
    </div>

    <div id="feature-empty" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
        No features match the current filters.
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('feature-search');
    const statusFilter = document.getElementById('feature-status-filter');
    const categoryFilter = document.getElementById('feature-category-filter');
    const rows = [...document.querySelectorAll('.feature-row')];
    const empty = document.getElementById('feature-empty');

    const applyFilters = () => {
        const query = (search?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || '';
        const category = categoryFilter?.value || '';
        let visible = 0;

        rows.forEach((row) => {
            const matches = (!query || row.dataset.featureSearch.includes(query))
                && (!status || row.dataset.featureStatus === status)
                && (!category || row.dataset.featureCategory === category);
            row.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });

        empty?.classList.toggle('hidden', visible !== 0);
    };

    search?.addEventListener('input', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    categoryFilter?.addEventListener('change', applyFilters);

    document.querySelectorAll('.feature-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const select = form.querySelector('.feature-status-select');
            const nextStatus = select?.value || 'enabled';
            if (nextStatus === 'enabled') return;

            const title = form.dataset.featureTitle || 'this feature';
            const impact = form.dataset.impact || 'Normal users will lose access until the feature is enabled again.';
            const action = nextStatus === 'maintenance'
                ? `Place ${title} into maintenance mode?`
                : `Disable / unrelease ${title}?`;
            const message = `${action}\n\n${impact}\n\nAdministrators will retain preview access.`;

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
</script>
@endpush

@extends('layouts.app')

@section('title', 'AI Diagnostics')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold">AI runtime observability</div>
                <h1 class="mt-3 text-3xl font-bold tracking-tight">AI Diagnostics</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Verify the configuration AcadFlow will actually use at runtime. Credentials and secret values are never displayed here.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('ai.settings') }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20">← AI Settings</a>
                <a href="{{ route('ai.analytics') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-slate-100">Usage Analytics</a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([
            ['AI Mode', str_replace('_',' ',ucfirst($mode))],
            ['Default Provider', $defaultProvider ?: '—'],
            ['Default Model', $defaultModel ?: '—'],
            ['Fallback', $fallbackProvider ?: 'None'],
            ['Config Generation', '#'.$configGeneration],
            ['Queue', $queueConnection ?: 'default'],
        ] as [$label,$value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div>
                <div class="mt-2 break-words text-sm font-bold text-slate-950">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Provider Health & Capability Registry</h2>
            <p class="mt-1 text-sm text-slate-500">Health results are cached to avoid unnecessary provider API calls. Use Test Connection from AI Settings for an explicit live check.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Provider</th><th class="px-5 py-3">Enabled</th><th class="px-5 py-3">Configured</th><th class="px-5 py-3">Model</th><th class="px-5 py-3">Capabilities</th><th class="px-5 py-3">Health</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($providers as $name => $provider)
                        @php($health = $provider['health'] ?? ['status'=>'not_checked','message'=>'Not checked recently.'])
                        <tr class="align-top">
                            <td class="px-5 py-4 font-bold text-slate-950">{{ $provider['label'] ?? $name }}<div class="mt-1 font-mono text-[11px] font-normal text-slate-400">{{ $name }}</div></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ !empty($provider['enabled']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ !empty($provider['enabled']) ? 'Enabled' : 'Disabled' }}</span></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ !empty($provider['configured']) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ !empty($provider['configured']) ? 'Ready' : 'Incomplete' }}</span></td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-700">{{ $provider['model'] ?: '—' }}</td>
                            <td class="px-5 py-4"><div class="flex max-w-sm flex-wrap gap-1">@foreach(($provider['capabilities'] ?? []) as $cap)<span class="rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-700">{{ $cap }}</span>@endforeach</div></td>
                            <td class="px-5 py-4"><div class="font-semibold text-slate-800">{{ str_replace('_',' ',ucfirst($health['status'] ?? 'not_checked')) }}</div><div class="mt-1 max-w-xs text-xs leading-5 text-slate-500">{{ $health['message'] ?? '' }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Feature Routing Matrix</h2>
            <p class="mt-1 text-sm text-slate-500">This is the route resolver result that each AI feature sees now—not a copy of environment variables.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Feature</th><th class="px-5 py-3">Enabled</th><th class="px-5 py-3">Configuration</th><th class="px-5 py-3">Resolved Provider</th><th class="px-5 py-3">Model</th><th class="px-5 py-3">Fallback Chain</th><th class="px-5 py-3">Capabilities</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($routes as $feature => $route)
                        <tr class="align-top">
                            <td class="px-5 py-4 font-mono text-xs font-bold text-slate-900">{{ $feature }}</td>
                            <td class="px-5 py-4">{{ ($route['feature_enabled'] ?? false) ? 'Yes' : 'No' }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ ($route['requested_configuration'] ?? 'global') === 'global' ? 'Use Global Default' : 'Feature Override' }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $route['resolved_provider'] ?? '—' }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $route['resolved_model'] ?? '—' }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">@forelse(($route['provider_chain'] ?? []) as $entry)<div>{{ $entry['role'] ?? 'route' }} → <strong>{{ $entry['provider'] ?? '—' }}</strong> / {{ $entry['model'] ?? '—' }}</div>@empty<span class="text-slate-400">No external chain</span>@endforelse</td>
                            <td class="px-5 py-4 text-xs"><div class="mb-1">{{ !empty($route['provider_compatible']) ? '✓ Compatible' : '— / Check mode' }}</div><div class="text-slate-400">{{ implode(', ', $route['required_capabilities'] ?? []) ?: 'Local/no provider requirement' }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Recent AI Requests</h2>
            <p class="mt-1 text-sm text-slate-500">Use request IDs to trace provider switching, fallbacks and failures. User prompts, responses and credentials are not displayed here.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">When</th><th class="px-5 py-3">Request</th><th class="px-5 py-3">Feature</th><th class="px-5 py-3">Mode</th><th class="px-5 py-3">Provider / Model</th><th class="px-5 py-3">Fallback</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Latency</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recent as $log)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{{ optional($log->created_at)->diffForHumans() }}</td>
                            <td class="px-5 py-4 font-mono text-[11px] text-slate-500">{{ $log->request_id ?: '—' }}</td>
                            <td class="px-5 py-4 font-mono text-xs font-semibold text-slate-800">{{ $log->feature }}</td>
                            <td class="px-5 py-4 text-xs">{{ $log->mode ?: '—' }}</td>
                            <td class="px-5 py-4"><div class="font-semibold text-slate-900">{{ $log->provider ?: $log->source ?: '—' }}</div><div class="font-mono text-[11px] text-slate-400">{{ $log->model ?: '—' }}</div></td>
                            <td class="px-5 py-4 text-xs">{{ $log->fallback_used ? 'Yes → '.($log->fallback_provider ?: 'fallback') : 'No' }}</td>
                            <td class="px-5 py-4"><span class="font-semibold {{ $log->success ? 'text-emerald-700' : 'text-rose-700' }}">{{ $log->success ? 'Successful' : 'Failed' }}</span>@if($log->error_type)<div class="mt-1 font-mono text-[11px] text-rose-500">{{ $log->error_type }}</div>@endif</td>
                            <td class="px-5 py-4 text-xs text-slate-500">{{ $log->processing_time !== null ? number_format((float)$log->processing_time, 3).'s' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">No AI usage records are available yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
        <strong>Deliberate capability boundary:</strong> this AcadFlow source does not include a live open-web search adapter or an external embedding-provider adapter. Provider AI is used for supported reasoning/generation requests; indexed semantic retrieval continues to use AcadFlow's existing local index. No provider is presented as live web research unless a real search adapter is implemented later.
    </div>
</div>
@endsection

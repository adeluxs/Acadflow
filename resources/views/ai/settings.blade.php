@extends('layouts.app')

@section('title', 'AI Settings')

@section('content')
@php
    $externalProviders = collect($providers)->reject(fn($p) => $p->value === 'rule_based')->values();
    $providerModels = collect($providerDefinitions)->mapWithKeys(fn($p, $key) => [$key => $p['models'] ?? []]);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <header class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-700 px-6 py-7 text-white md:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-indigo-100">Central AI Control Plane</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">AI Settings</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-indigo-100">One runtime configuration for provider routing, models, grounding, guardrails, limits and every AI-powered AcadFlow feature. Changes are database-driven and take effect without rebuilding Laravel config cache.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('ai.diagnostics') }}" class="rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-sm font-bold text-white hover:bg-white/20">AI Diagnostics</a>
                    @if(auth()->user()?->isSuperAdmin() && Route::has('admin.settings.features'))
                        <a href="{{ route('admin.settings.features') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700">Feature & Module Management</a>
                    @endif
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><p class="text-xs text-indigo-100">AI Mode</p><p class="mt-1 font-bold">{{ str_replace('_',' ',ucwords($mode,'_')) }}</p></div>
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><p class="text-xs text-indigo-100">Default Provider</p><p class="mt-1 font-bold">{{ $providerDefinitions[$defaultProvider]['label'] ?? $defaultProvider }}</p></div>
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><p class="text-xs text-indigo-100">Default Model</p><p class="mt-1 truncate font-bold">{{ $defaultModel ?: 'Provider default' }}</p></div>
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><p class="text-xs text-indigo-100">Fallback</p><p class="mt-1 font-bold">{{ $fallbackProvider === 'none' ? 'None' : ($providerDefinitions[$fallbackProvider]['label'] ?? $fallbackProvider) }}</p></div>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>
    @endif
    @if(session('provider_test'))
        @php($test = session('provider_test'))
        <div class="rounded-2xl border px-5 py-4 text-sm {{ ($test['status'] ?? '') === 'healthy' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            <div class="flex flex-wrap items-center gap-2">
                <strong>{{ $providerDefinitions[$test['provider'] ?? '']['label'] ?? ($test['provider'] ?? 'Provider') }}:</strong>
                <span>{{ $test['message'] ?? 'Connection test completed.' }}</span>
                @if(isset($test['latency_ms'])) <span class="text-xs opacity-75">{{ $test['latency_ms'] }} ms</span> @endif
                @if(!empty($test['error_code'])) <code class="rounded bg-white/60 px-2 py-0.5 text-[11px]">{{ $test['error_code'] }}</code> @endif
            </div>
            @if(!empty($test['diagnostic']) && ($test['status'] ?? '') !== 'healthy')
                <div class="mt-3 rounded-xl border border-current/10 bg-white/60 p-3">
                    <p class="text-xs font-black uppercase tracking-wide">Provider diagnostic</p>
                    <p class="mt-1 break-words text-xs leading-5">{{ $test['diagnostic'] }}</p>
                </div>
            @endif
            <p class="mt-2 text-[11px] opacity-70">Request {{ $test['request_id'] ?? 'n/a' }} · Detailed provider logs: <code>storage/logs/ai-provider.log</code></p>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">
            <p class="font-bold">Please correct the AI configuration:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 text-sm font-bold shadow-sm">
        @foreach(['general'=>'General','providers'=>'Providers','routing'=>'Feature Routing','grounding'=>'Grounding','guardrails'=>'Guardrails','prompts'=>'Prompts'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="whitespace-nowrap rounded-xl px-4 py-2 text-slate-600 hover:bg-indigo-50 hover:text-indigo-700">{{ $label }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('ai.settings.update') }}" id="ai-settings-form" class="space-y-6">
        @csrf

        <section id="general" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div><h2 class="text-xl font-black text-slate-900">General Runtime Configuration</h2><p class="mt-1 text-sm text-slate-500">The authoritative global/provider-routing behavior for this {{ $isPlatformAdmin ? 'platform' : 'institution' }}.</p></div>
                <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">Runtime DB settings</span>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block"><span class="text-sm font-bold text-slate-700">AI Operating Mode</span>
                    <select name="ai_mode" id="ai-mode" class="mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        @foreach($modes as $m)<option value="{{ $m->value }}" @selected(old('ai_mode',$mode)===$m->value)>{{ $m->label() }}</option>@endforeach
                    </select>
                </label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Default Provider</span>
                    <select name="ai_default_provider" id="ai-default-provider" class="mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        @foreach($providers as $p)<option value="{{ $p->value }}" @selected(old('ai_default_provider',$defaultProvider)===$p->value)>{{ $p->label() }}</option>@endforeach
                    </select>
                </label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Default Model</span>
                    <select name="ai_default_model" id="ai-default-model" data-current="{{ old('ai_default_model',$defaultModel) }}" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"></select>
                </label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Global Temperature</span><input name="ai_temperature" type="number" min="0" max="2" step="0.05" value="{{ old('ai_temperature',$settings['ai_temperature']) }}" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"></label>

                <label class="block"><span class="text-sm font-bold text-slate-700">Fallback Provider</span>
                    <select name="ai_fallback_provider" id="ai-fallback-provider" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"><option value="none" @selected(old('ai_fallback_provider',$fallbackProvider)==='none')>None</option>@foreach($externalProviders as $p)<option value="{{ $p->value }}" @selected(old('ai_fallback_provider',$fallbackProvider)===$p->value)>{{ $p->label() }}</option>@endforeach</select>
                </label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Fallback Model</span><select name="ai_fallback_model" id="ai-fallback-model" data-current="{{ old('ai_fallback_model',$fallbackModel) }}" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"></select></label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Secondary Fallback</span>
                    <select name="ai_secondary_fallback_provider" id="ai-secondary-provider" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"><option value="none" @selected(old('ai_secondary_fallback_provider',$secondaryFallbackProvider)==='none')>None</option>@foreach($externalProviders as $p)<option value="{{ $p->value }}" @selected(old('ai_secondary_fallback_provider',$secondaryFallbackProvider)===$p->value)>{{ $p->label() }}</option>@endforeach</select>
                </label>
                <label class="block"><span class="text-sm font-bold text-slate-700">Secondary Model</span><select name="ai_secondary_fallback_model" id="ai-secondary-model" data-current="{{ old('ai_secondary_fallback_model',$secondaryFallbackModel) }}" class="mt-1.5 w-full rounded-xl border px-3 py-2.5"></select></label>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="block"><span class="text-xs font-bold text-slate-600">Request timeout (s)</span><input name="ai_request_timeout" type="number" min="1" max="300" value="{{ old('ai_request_timeout',$settings['ai_request_timeout']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Retry count</span><input name="ai_retry_count" type="number" min="0" max="5" value="{{ old('ai_retry_count',$settings['ai_retry_count']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Retry delay (ms)</span><input name="ai_retry_delay_ms" type="number" min="0" max="10000" value="{{ old('ai_retry_delay_ms',$settings['ai_retry_delay_ms']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Max tokens</span><input name="ai_max_tokens" type="number" min="1" value="{{ old('ai_max_tokens',$settings['ai_max_tokens']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Context limit</span><input name="ai_context_limit" type="number" min="1000" value="{{ old('ai_context_limit',$settings['ai_context_limit']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Daily requests</span><input name="ai_daily_request_limit" type="number" min="0" value="{{ old('ai_daily_request_limit',$settings['ai_daily_request_limit']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Monthly requests</span><input name="ai_monthly_request_limit" type="number" min="0" value="{{ old('ai_monthly_request_limit',$settings['ai_monthly_request_limit']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Monthly cost ceiling ($)</span><input name="ai_max_cost" type="number" min="0" step="0.01" value="{{ old('ai_max_cost',$settings['ai_max_cost']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Requests/minute</span><input name="ai_rate_limit_per_minute" type="number" min="1" value="{{ old('ai_rate_limit_per_minute',$settings['ai_rate_limit_per_minute']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block"><span class="text-xs font-bold text-slate-600">Cache TTL (s)</span><input name="ai_cache_ttl" type="number" min="60" value="{{ old('ai_cache_ttl',$settings['ai_cache_ttl']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach(['ai_automatic_failover'=>'Automatic provider failover','ai_provider_health_enabled'=>'Provider health caching','ai_enable_cache'=>'AI response caching','ai_enable_logging'=>'AI usage logging','ai_grounding_enabled'=>'Grounding enabled','ai_hybrid_escalate_when_clean'=>'Hybrid escalation when rule-clean'] as $key=>$label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-medium"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$settings[$key] ?? false))><span>{{ $label }}</span></label>
                @endforeach
            </div>

            <label class="mt-5 block"><span class="text-sm font-bold text-slate-700">Global System Instruction</span><textarea name="ai_global_system_prompt" rows="4" class="mt-1.5 w-full rounded-xl border px-3 py-2.5">{{ old('ai_global_system_prompt',$settings['ai_global_system_prompt']) }}</textarea><span class="mt-1 block text-xs text-slate-400">Composed before feature-specific prompt instructions. Retrieved documents remain untrusted data.</span></label>
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>External live-web research:</strong> unavailable in this build. An LLM provider is not treated as a web-search engine, so this setting remains disabled until AcadFlow has a dedicated controlled web research adapter.</div>
        </section>

        <section id="providers" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
            <div><h2 class="text-xl font-black text-slate-900">Provider Registry</h2><p class="mt-1 text-sm text-slate-500">All provider tests use the same HTTP transport as real AI requests. Secrets are never rendered back into the page; safe API/network diagnostics are written to the dedicated provider log.</p></div>
            <div class="mt-6 grid gap-5 xl:grid-cols-2">
                @foreach($externalProviders as $p)
                    @php($d = $providerDefinitions[$p->value])
                    <article class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div><h3 class="font-black text-slate-900">{{ $p->label() }}</h3><p class="mt-1 text-xs text-slate-500">Capabilities: {{ implode(', ', $d['capabilities'] ?? []) }}</p></div>
                            <div class="text-right"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ ($d['health']['status'] ?? '')==='healthy' ? 'bg-emerald-100 text-emerald-700' : (($d['enabled'] ?? false) ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ str_replace('_',' ',ucwords($d['health']['status'] ?? 'not_checked','_')) }}</span></div>
                        </div>
                        @if($isPlatformAdmin)
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm font-bold"><input type="checkbox" name="provider_{{ $p->value }}_enabled" value="1" @checked($d['enabled'])> Enabled</label>
                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs"><span class="font-bold">Credential:</span> {{ $d['credential_configured'] ? 'Configured' : 'Not configured' }}</div>
                                <label class="block"><span class="text-xs font-bold text-slate-600">Default model/deployment</span><input name="provider_{{ $p->value }}_model" value="{{ old('provider_'.$p->value.'_model',$d['model']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                                <label class="block"><span class="text-xs font-bold text-slate-600">Temperature</span><input name="provider_{{ $p->value }}_temperature" type="number" min="0" max="2" step="0.05" value="{{ old('provider_'.$p->value.'_temperature',$d['temperature']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                                <label class="block md:col-span-2"><span class="text-xs font-bold text-slate-600">Configured models (comma separated)</span><input name="provider_{{ $p->value }}_models" value="{{ old('provider_'.$p->value.'_models',implode(', ',$d['models'] ?? [])) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                                <label class="block md:col-span-2"><span class="text-xs font-bold text-slate-600">Base URL / Endpoint</span><input name="provider_{{ $p->value }}_base_url" value="{{ old('provider_'.$p->value.'_base_url',$d['base_url'] ?? '') }}" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Leave blank to use .env/bootstrap endpoint"></label>
                                <label class="block md:col-span-2"><span class="text-xs font-bold text-slate-600">{{ $p->value === 'ollama' ? 'Optional cloud API key' : 'Replace API key' }}</span><input name="provider_{{ $p->value }}_api_key" type="password" autocomplete="new-password" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="Leave blank to preserve current credential"><span class="mt-1 block text-[11px] text-slate-400">{{ $p->value === 'ollama' ? 'Local Ollama normally needs no key; enter a key only when your remote/cloud endpoint requires Bearer authentication.' : 'The stored value is encrypted and never displayed.' }}</span></label>
                            </div>
                        @else
                            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">Provider credentials and endpoints are platform-managed. Your institution inherits them and may select configured providers in Feature Routing.</div>
                        @endif
                        <button type="submit" formaction="{{ route('ai.providers.test',$p->value) }}" formmethod="POST" class="mt-4 rounded-xl border border-indigo-200 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50">Test Connection</button>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="routing" class="scroll-mt-24 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6 md:p-7"><h2 class="text-xl font-black text-slate-900">Feature Configuration Matrix</h2><p class="mt-1 text-sm text-slate-500">Use Global Default unless a specific AI feature intentionally needs another configured provider/model.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-[1050px] w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Feature</th><th class="px-4 py-3">Enabled</th><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Model</th><th class="px-4 py-3">Hybrid rule fallback</th><th class="px-4 py-3">Resolved now</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($featureRouting as $feature=>$route)
                        @php($featureProfile = (array) config('ai.assistant_profiles.'.$feature, []))
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-800">{{ $featureProfile['label'] ?? ucwords(str_replace('_',' ',$feature)) }}</p>
                                @if(!empty($featureProfile['module']))<p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-500">{{ $featureProfile['module'] }}</p>@endif
                                @if(!empty($featureProfile['description']))<p class="mt-1 max-w-sm text-xs leading-5 text-slate-500">{{ $featureProfile['description'] }}</p>@endif
                                <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $feature }}</p>
                            </td>
                            <td class="px-4 py-4"><input type="checkbox" name="feature_enabled[{{ $feature }}]" value="1" @checked($route['enabled'])></td>
                            <td class="px-4 py-4"><select name="feature_provider[{{ $feature }}]" data-feature-provider="{{ $feature }}" class="w-48 rounded-xl border px-2 py-2"><option value="global" @selected($route['provider']==='global')>Use Global Default</option>@foreach($externalProviders as $p)<option value="{{ $p->value }}" @selected($route['provider']===$p->value)>{{ $p->label() }}</option>@endforeach</select></td>
                            <td class="px-4 py-4"><select name="feature_model[{{ $feature }}]" data-feature-model="{{ $feature }}" data-current="{{ $route['model'] }}" class="w-52 rounded-xl border px-2 py-2"></select></td>
                            <td class="px-4 py-4"><input type="checkbox" name="feature_rule_fallback[{{ $feature }}]" value="1" @checked($route['rule_fallback'])><p class="mt-1 max-w-[170px] text-[11px] text-slate-400">Used only in Hybrid mode after provider failure.</p></td>
                            <td class="px-4 py-4"><p class="font-semibold text-slate-700">{{ $providerDefinitions[$route['resolved']['resolved_provider']]['label'] ?? $route['resolved']['resolved_provider'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $route['resolved']['resolved_model'] ?: 'Provider default' }}</p></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section id="grounding" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div><h2 class="text-xl font-black text-slate-900">Grounding & Knowledge</h2><p class="mt-1 text-sm text-slate-500">Strict publication retrieval, nonsense rejection, evidence gates and source validation for Grounded AI Companion.</p></div><label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold"><input type="checkbox" name="ai_grounded_pattern_learning_enabled" value="1" @checked($settings['ai_grounded_pattern_learning_enabled'])> Learn from successful grounded patterns</label></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label><span class="text-xs font-bold">Minimum question characters</span><input type="number" name="ai_grounded_min_question_chars" value="{{ $settings['ai_grounded_min_question_chars'] }}" min="2" max="50" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Gibberish threshold</span><input type="number" step="0.01" name="ai_grounded_gibberish_threshold" value="{{ $settings['ai_grounded_gibberish_threshold'] }}" min="0.2" max="1" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Relevance threshold</span><input type="number" step="0.01" name="ai_grounded_relevance_threshold" value="{{ $settings['ai_grounded_relevance_threshold'] }}" min="0.01" max="0.95" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Lexical evidence floor</span><input type="number" step="0.01" name="ai_grounded_lexical_floor" value="{{ $settings['ai_grounded_lexical_floor'] }}" min="0" max="0.95" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Citation coverage minimum</span><input type="number" step="0.01" name="ai_grounded_citation_coverage_min" value="{{ $settings['ai_grounded_citation_coverage_min'] }}" min="0.2" max="1" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Source support threshold</span><input type="number" step="0.01" name="ai_grounded_support_threshold" value="{{ $settings['ai_grounded_support_threshold'] }}" min="0.01" max="0.95" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Support coverage minimum</span><input type="number" step="0.01" name="ai_grounded_support_coverage_min" value="{{ $settings['ai_grounded_support_coverage_min'] }}" min="0.2" max="1" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Similarity threshold (%)</span><input type="number" name="ai_similarity_threshold" value="{{ $settings['ai_similarity_threshold'] }}" min="0" max="100" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
            </div>
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs leading-5 text-emerald-900">Grounded Companion never treats a configured LLM as the open web. In Provider AI mode, a response that fails citation/source validation is withheld instead of silently becoming a deterministic answer. Hybrid mode may use an explicitly configured deterministic fallback.</div>
        </section>

        <section id="guardrails" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
            <h2 class="text-xl font-black text-slate-900">Guardrails, Documents & Layout</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label><span class="text-xs font-bold">Min font size (pt)</span><input type="number" name="ai_layout_min_font_size" value="{{ $settings['ai_layout_min_font_size'] }}" min="6" max="72" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="md:col-span-2"><span class="text-xs font-bold">Required fonts</span><input type="text" name="ai_layout_required_fonts" value="{{ implode(', ', $settings['ai_layout_required_fonts'] ?? []) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Page size</span><select name="ai_layout_page_size" class="mt-1 w-full rounded-xl border px-3 py-2">@foreach(['A4','Letter','Legal','A3','A5'] as $v)<option @selected($settings['ai_layout_page_size']===$v)>{{ $v }}</option>@endforeach</select></label>
                <label><span class="text-xs font-bold">Min margin (inches)</span><input type="number" step="0.1" name="ai_layout_min_margin_inches" value="{{ $settings['ai_layout_min_margin_inches'] }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label><span class="text-xs font-bold">Line spacing</span><select name="ai_layout_line_spacing" class="mt-1 w-full rounded-xl border px-3 py-2">@foreach(['1.0','1.15','1.5','2.0'] as $v)<option @selected((string)$settings['ai_layout_line_spacing']===$v)>{{ $v }}</option>@endforeach</select></label>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input type="checkbox" name="ai_layout_require_page_numbering" value="1" @checked($settings['ai_layout_require_page_numbering'])> Require page numbering</label><label class="flex items-center gap-2"><input type="checkbox" name="ai_layout_require_branding" value="1" @checked($settings['ai_layout_require_branding'])> Require institution branding</label></div>

            <div class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div><h3 class="font-black text-slate-800">Editor AI & deterministic academic rules</h3><p class="mt-1 text-xs text-slate-500">These are AI-specific runtime settings and are managed here instead of being duplicated under System Settings.</p></div>
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="ai_editor_suggestions_enabled" value="1" @checked(old('ai_editor_suggestions_enabled',$settings['ai_editor_suggestions_enabled']))> Editor AI suggestions</label>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <label><span class="text-xs font-bold">Suggestion min chars</span><input type="number" name="ai_editor_suggestion_min_chars" min="10" max="5000" value="{{ old('ai_editor_suggestion_min_chars',$settings['ai_editor_suggestion_min_chars']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                    <label><span class="text-xs font-bold">Suggestion delay (ms)</span><input type="number" name="ai_editor_suggestion_delay_ms" min="200" max="10000" value="{{ old('ai_editor_suggestion_delay_ms',$settings['ai_editor_suggestion_delay_ms']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                    <label><span class="text-xs font-bold">Min word count</span><input type="number" name="ai_min_word_count" min="0" max="1000000" value="{{ old('ai_min_word_count',$settings['ai_min_word_count']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                    <label><span class="text-xs font-bold">Max word count</span><input type="number" name="ai_max_word_count" min="1" max="2000000" value="{{ old('ai_max_word_count',$settings['ai_max_word_count']) }}" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                    <label><span class="text-xs font-bold">Institution required sections</span><input type="text" name="ai_institution_required_sections" maxlength="2000" value="{{ old('ai_institution_required_sections',$settings['ai_institution_required_sections']) }}" placeholder="Abstract, Methodology, References" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                </div>
            </div>

            <h3 class="mt-7 font-black text-slate-800">Rule packs</h3><div class="mt-3 grid gap-2 md:grid-cols-3 xl:grid-cols-5">@foreach($rulePacks as $key=>$enabled)<label class="flex items-center gap-2 rounded-xl border p-3 text-sm"><input type="checkbox" name="ai_rulepack_{{ $key }}" value="1" @checked($enabled)><span>{{ ucwords(str_replace('_',' ',$key)) }}</span></label>@endforeach</div>
        </section>

        <div class="sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur">
            <p class="hidden text-xs text-slate-500 md:block">Saving invalidates AI runtime/cache generations so future requests resolve the new provider/model immediately.</p>
            <button class="ml-auto rounded-xl bg-indigo-600 px-6 py-3 text-sm font-black text-white shadow hover:bg-indigo-700">Save AI Settings</button>
        </div>
    </form>

    <section id="prompts" class="scroll-mt-24 grid gap-6 lg:grid-cols-2">
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Create prompt version</h2><p class="mt-1 text-sm text-slate-500">Prompt versions remain immutable. The global AI instruction is composed automatically before the feature prompt.</p>
            <form method="POST" action="{{ route('ai.prompts.store') }}" class="mt-5 space-y-3">@csrf
                <label class="block text-sm font-bold">Feature<input name="feature" required placeholder="knowledge_companion" class="mt-1 w-full rounded-xl border px-3 py-2"></label>
                <label class="block text-sm font-bold">Feature system prompt<textarea name="system_prompt" rows="6" required class="mt-1 w-full rounded-xl border px-3 py-2">Use only authorized academic context, disclose uncertainty, and return valid structured JSON.</textarea></label>
                <label class="block text-sm font-bold">User template<textarea name="user_template" rows="6" required class="mt-1 w-full rounded-xl border px-3 py-2 font-mono text-xs">Feature: @{{feature}}
Authorized context:
@{{context_json}}</textarea></label>
                <label class="block text-sm font-bold">Response schema JSON<textarea name="response_schema" rows="5" class="mt-1 w-full rounded-xl border px-3 py-2 font-mono text-xs">{"type":"object","required":["summary"],"properties":{"summary":{"type":"string"},"findings":{"type":"array"}}}</textarea></label>
                <label class="block text-sm font-bold">Settings JSON<textarea name="settings" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2 font-mono text-xs">{}</textarea></label>
                @if(auth()->user()->isSuperAdmin())<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="global_scope" value="1"> Global fallback prompt</label>@endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activate" value="1"> Activate immediately</label>
                <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Create immutable version</button>
            </form>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black">Prompt registry</h2><div class="mt-4 max-h-[760px] space-y-3 overflow-auto">@forelse($promptVersions as $prompt)<div class="rounded-2xl border p-4"><div class="flex justify-between gap-3"><div><p class="font-bold">{{ $prompt->feature }} v{{ $prompt->version }}</p><p class="text-xs text-slate-400">{{ $prompt->university_id ? 'Institution override' : 'Global fallback' }}</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ $prompt->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $prompt->is_active?'Active':'Inactive' }}</span></div><p class="mt-2 line-clamp-3 text-xs text-slate-600">{{ $prompt->system_prompt }}</p>@unless($prompt->is_active)<form method="POST" action="{{ route('ai.prompts.activate',$prompt) }}" class="mt-3">@csrf<button class="rounded-xl border border-indigo-200 px-3 py-2 text-xs font-bold text-indigo-700">Activate & invalidate cache</button></form>@endunless</div>@empty<p class="text-sm text-slate-500">No versioned prompts yet.</p>@endforelse</div></article>
    </section>
</div>

<script>
(() => {
    const models = @json($providerModels);
    const fill = (providerEl, modelEl, includeGlobal = false) => {
        if (!providerEl || !modelEl) return;
        const provider = providerEl.value;
        const current = modelEl.dataset.current || modelEl.value || '';
        const values = provider === 'global' ? [] : (models[provider] || []);
        modelEl.innerHTML = '';
        if (includeGlobal || provider === 'global') {
            const o = document.createElement('option'); o.value = 'global'; o.textContent = 'Use Global Default'; modelEl.appendChild(o);
        } else {
            const o = document.createElement('option'); o.value = ''; o.textContent = 'Use provider default'; modelEl.appendChild(o);
        }
        values.forEach(v => { const o=document.createElement('option'); o.value=v; o.textContent=v; modelEl.appendChild(o); });
        if ([...modelEl.options].some(o => o.value === current)) modelEl.value = current;
        modelEl.dataset.current = modelEl.value;
    };

    const defaultProvider = document.getElementById('ai-default-provider');
    const defaultModel = document.getElementById('ai-default-model');
    const fallbackProvider = document.getElementById('ai-fallback-provider');
    const fallbackModel = document.getElementById('ai-fallback-model');
    const secondaryProvider = document.getElementById('ai-secondary-provider');
    const secondaryModel = document.getElementById('ai-secondary-model');
    fill(defaultProvider, defaultModel); fill(fallbackProvider, fallbackModel); fill(secondaryProvider, secondaryModel);
    defaultProvider?.addEventListener('change', () => { defaultModel.dataset.current=''; fill(defaultProvider, defaultModel); });
    fallbackProvider?.addEventListener('change', () => { fallbackModel.dataset.current=''; fill(fallbackProvider, fallbackModel); });
    secondaryProvider?.addEventListener('change', () => { secondaryModel.dataset.current=''; fill(secondaryProvider, secondaryModel); });

    document.querySelectorAll('[data-feature-provider]').forEach(provider => {
        const feature = provider.dataset.featureProvider;
        const model = document.querySelector(`[data-feature-model="${feature}"]`);
        fill(provider, model, true);
        provider.addEventListener('change', () => { model.dataset.current='global'; fill(provider, model, true); });
    });
})();
</script>
@endsection

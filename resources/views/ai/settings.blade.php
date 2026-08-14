@extends('layouts.app')

@section('title', 'AI Assistant Settings')

@section('content')
<div class="max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold mb-2">AI Academic Assistant Settings</h2>
    <p class="text-gray-500 mb-4">Configuration for how the AI layer behaves. Changes apply across the platform.</p>

    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
        <strong>Runtime availability is separate from AI configuration.</strong>
        The AI Assistant's Enabled / Maintenance / Disabled state is controlled only in
        @if(auth()->user()?->isSuperAdmin() && Route::has('admin.settings.features'))
            <a href="{{ route('admin.settings.features') }}" class="font-bold underline">Feature & Module Management</a>.
        @else
            platform Feature & Module Management.
        @endif
        The controls below configure providers and individual AI capabilities only; they do not release the AI Assistant to users.
    </div>

    <form method="POST" action="{{ route('ai.settings.update') }}">
        @csrf

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="font-semibold mb-4">Mode & Provider</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">AI Mode</label>
                    <select name="ai_mode" class="w-full border rounded px-3 py-2">
                        @foreach($modes as $m)
                            <option value="{{ $m->value }}" @selected($mode === $m->value)>{{ $m->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Default Provider</label>
                    <select name="ai_default_provider" class="w-full border rounded px-3 py-2">
                        @foreach($providers as $p)
                            <option value="{{ $p->value }}" @selected($defaultProvider === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Fallback Provider</label>
                    <select name="ai_fallback_provider" class="w-full border rounded px-3 py-2">
                        @foreach($providers as $p)
                            <option value="{{ $p->value }}" @selected($fallbackProvider === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                <div><label class="block text-sm font-medium mb-1">Similarity Threshold (%)</label>
                    <input type="number" name="ai_similarity_threshold" value="{{ $settings['ai_similarity_threshold'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Request Timeout (s)</label>
                    <input type="number" name="ai_request_timeout" value="{{ $settings['ai_request_timeout'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Max Tokens</label>
                    <input type="number" name="ai_max_tokens" value="{{ $settings['ai_max_tokens'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Daily Request Limit</label>
                    <input type="number" name="ai_daily_request_limit" value="{{ $settings['ai_daily_request_limit'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Monthly Request Limit</label>
                    <input type="number" name="ai_monthly_request_limit" value="{{ $settings['ai_monthly_request_limit'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Max Cost ($)</label>
                    <input type="number" step="0.01" name="ai_max_cost" value="{{ $settings['ai_max_cost'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Cache TTL (seconds)</label>
                    <input type="number" name="ai_cache_ttl" value="{{ $settings['ai_cache_ttl'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Max document size (MB)</label>
                    <input type="number" name="ai_max_document_size_mb" value="{{ $settings['ai_max_document_size_mb'] }}" class="w-full border rounded px-3 py-2"></div>
                <div><label class="block text-sm font-medium mb-1">Document formats</label>
                    <input type="text" name="ai_document_formats" value="{{ implode(', ', $settings['ai_document_formats'] ?? []) }}" class="w-full border rounded px-3 py-2"></div>
                <div class="md:col-span-3"><label class="block text-sm font-medium mb-1">Provider priority (comma separated)</label>
                    <input type="text" name="ai_provider_priority" value="{{ implode(', ', $settings['ai_provider_priority'] ?? []) }}" class="w-full border rounded px-3 py-2"></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="font-semibold mb-4">Global Toggles</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach(['ai_enable_rule_engine' => 'Rule Engine', 'ai_enable_external_ai' => 'External AI', 'ai_enable_hybrid_mode' => 'Hybrid Mode', 'ai_enable_cache' => 'Cache', 'ai_enable_logging' => 'Logging', 'ai_hybrid_escalate_when_clean' => 'Hybrid: escalate when clean'] as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key])> {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="font-semibold mb-1">AI Capability Configuration</h3>
            <p class="mb-4 text-xs text-gray-500">These switches control individual AI tools after the AI Assistant module is available; they are not duplicate release switches.</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($features as $feature)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="ai_feature_{{ $feature }}" value="1" @checked(\App\Services\SettingService::get('ai_feature_'.$feature, true))>
                        {{ str_replace('_', ' ', $feature) }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="font-semibold mb-4">Layout Requirements</h3>
            <p class="text-sm text-gray-500 mb-4">Institution-wide defaults used by the AI layout validator. Individual lecturers can override these in their own preferences.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Required Fonts (comma-separated)</label>
                    <input type="text" name="ai_layout_required_fonts" value="{{ is_array($settings['ai_layout_required_fonts'] ?? []) ? implode(', ', $settings['ai_layout_required_fonts']) : '' }}" class="w-full border rounded px-3 py-2" placeholder="Times New Roman, Arial">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Page Size</label>
                    <select name="ai_layout_page_size" class="w-full border rounded px-3 py-2">
                        @foreach(['A4', 'Letter', 'Legal', 'A3', 'A5'] as $size)
                            <option value="{{ $size }}" @selected(($settings['ai_layout_page_size'] ?? 'A4') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Min Margin (inches)</label>
                    <input type="number" step="0.1" min="0" max="5" name="ai_layout_min_margin_inches" value="{{ $settings['ai_layout_min_margin_inches'] ?? 1.0 }}" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Line Spacing</label>
                    <select name="ai_layout_line_spacing" class="w-full border rounded px-3 py-2">
                        @foreach(['1.0', '1.15', '1.5', '2.0'] as $ls)
                            <option value="{{ $ls }}" @selected(($settings['ai_layout_line_spacing'] ?? '1.5') === $ls)>{{ $ls }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Min Font Size (pt)</label>
                    <input type="number" min="6" max="72" name="ai_layout_min_font_size" value="{{ $settings['ai_layout_min_font_size'] ?? 10 }}" class="w-full border rounded px-3 py-2">
                </div>
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="ai_layout_require_page_numbering" value="1" @checked($settings['ai_layout_require_page_numbering'] ?? false)> Require page numbering
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="ai_layout_require_branding" value="1" @checked($settings['ai_layout_require_branding'] ?? false)> Require institution branding
                    </label>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($rulePacks as $key => $enabled)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="ai_rulepack_{{ $key }}" value="1" @checked($enabled)> {{ $key }}
                    </label>
                @endforeach
            </div>
        </div>

        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
            Save AI Settings
        </button>
    </form>
</div>

    <div class="mt-10 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Create prompt version</h3>
            <p class="mt-1 text-sm text-gray-500">Prompt versions are immutable. Activate a version only after its JSON response schema is ready.</p>
            <form method="POST" action="{{ route('ai.prompts.store') }}" class="mt-4 space-y-3">@csrf
                <label class="block text-sm font-medium">Feature<input name="feature" required placeholder="knowledge_companion" class="mt-1 w-full rounded border px-3 py-2"></label>
                <label class="block text-sm font-medium">System prompt<textarea name="system_prompt" rows="6" required class="mt-1 w-full rounded border px-3 py-2">You are AcadFlow AI Academic Assistant. Use only authorized academic context, disclose uncertainty, and respond with valid JSON.</textarea></label>
                <label class="block text-sm font-medium">User template<textarea name="user_template" rows="6" required class="mt-1 w-full rounded border px-3 py-2 font-mono text-xs">Feature: @{{feature}}
Authorized context:
@{{context_json}}</textarea></label>
                <label class="block text-sm font-medium">Response schema JSON<textarea name="response_schema" rows="6" class="mt-1 w-full rounded border px-3 py-2 font-mono text-xs">{"type":"object","required":["summary"],"properties":{"summary":{"type":"string"},"findings":{"type":"array"}}}</textarea></label>
                <label class="block text-sm font-medium">Settings JSON<textarea name="settings" rows="3" class="mt-1 w-full rounded border px-3 py-2 font-mono text-xs">{}</textarea></label>
                @if(auth()->user()->isSuperAdmin())<label class="block text-sm"><input type="checkbox" name="global_scope" value="1"> Global fallback prompt</label>@endif
                <label class="block text-sm"><input type="checkbox" name="activate" value="1"> Activate immediately</label>
                <button class="rounded bg-indigo-600 px-5 py-2 text-white">Create immutable version</button>
            </form>
        </section>
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Prompt registry</h3>
            <div class="mt-4 max-h-[760px] space-y-3 overflow-auto">
            @forelse($promptVersions as $prompt)
                <article class="rounded-xl border p-4"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $prompt->feature }} v{{ $prompt->version }}</p><p class="text-xs text-gray-500">{{ $prompt->university_id ? 'Institution override' : 'Global fallback' }}</p></div><span class="rounded-full px-2 py-1 text-xs {{ $prompt->is_active?'bg-green-100 text-green-700':'bg-gray-100' }}">{{ $prompt->is_active?'Active':'Inactive' }}</span></div>
                <p class="mt-2 line-clamp-3 text-xs text-gray-600">{{ $prompt->system_prompt }}</p>
                @unless($prompt->is_active)<form method="POST" action="{{ route('ai.prompts.activate',$prompt) }}" class="mt-3">@csrf<button class="rounded border border-indigo-600 px-3 py-1.5 text-xs font-semibold text-indigo-700">Activate and invalidate cache</button></form>@endunless
                </article>
            @empty<p class="text-sm text-gray-500">No versioned prompts yet. Default provider contracts remain active.</p>@endforelse
            </div>
        </section>
    </div>

@endsection

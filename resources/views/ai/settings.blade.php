@extends('layouts.app')

@section('title', 'AI Assistant Settings')

@section('content')
<div class="max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold mb-2">AI Academic Assistant Settings</h2>
    <p class="text-gray-500 mb-6">Centralized configuration for the AI layer. Changes apply across the platform.</p>

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
            <h3 class="font-semibold mb-4">Feature Permissions</h3>
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
@endsection

@extends('layouts.app')

@section('title', 'My Layout Preferences')

@section('content')
<div class="max-w-3xl mx-auto">
    <h2 class="text-2xl font-bold mb-2">My Layout Preferences</h2>
    <p class="text-gray-500 mb-6">Override institution defaults for how I review submissions. These preferences are used when I trigger AI analysis.</p>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('ai.lecturer.layout.preferences.update') }}">
        @csrf

        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="font-semibold mb-2">Overrides</h3>
            <p class="text-sm text-gray-500 mb-4">Leave a field empty to inherit the institution default ({{ $institutionDefaults['required_fonts'][0] ?? 'no default' }}).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Required Fonts (comma-separated)</label>
                    <input type="text" name="required_fonts" value="{{ is_array($prefs->required_fonts ?? []) ? implode(', ', $prefs->required_fonts ?? []) : '' }}" class="w-full border rounded px-3 py-2" placeholder="Times New Roman, Arial">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Page Size</label>
                    <select name="page_size" class="w-full border rounded px-3 py-2">
                        <option value="">Institution default</option>
                        @foreach($pageSizes as $size)
                            <option value="{{ $size }}" @selected(($prefs->page_size ?? '') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Min Margin (inches)</label>
                    <input type="number" step="0.1" min="0" max="5" name="min_margin_inches" value="{{ $prefs->min_margin_inches ?? '' }}" class="w-full border rounded px-3 py-2" placeholder="Institution default">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Line Spacing</label>
                    <select name="line_spacing" class="w-full border rounded px-3 py-2">
                        <option value="">Institution default</option>
                        @foreach($lineSpacings as $ls)
                            <option value="{{ $ls }}" @selected(($prefs->line_spacing ?? '') === $ls)>{{ $ls }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Min Font Size (pt)</label>
                    <input type="number" min="6" max="72" name="min_font_size_pt" value="{{ $prefs->min_font_size_pt ?? '' }}" class="w-full border rounded px-3 py-2" placeholder="Institution default">
                </div>
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="require_page_numbering" value="1" @checked($prefs->require_page_numbering ?? false)> Require page numbering
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="require_institution_branding" value="1" @checked($prefs->require_institution_branding ?? false)> Require institution branding
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
            Save Preferences
        </button>
    </form>
</div>
@endsection

@props(['name', 'kind', 'label', 'selected' => null, 'placeholder' => 'Select an option', 'required' => false, 'depends' => null, 'filters' => [], 'options' => []])
@php($lookupId = 'lookup-'.\Illuminate\Support\Str::uuid())
<div {{ $attributes->class(['min-w-0 space-y-1']) }} data-admin-lookup data-url="{{ route('admin.lookups', ['kind' => $kind] + $filters) }}" @if($depends) data-depends="{{ $depends }}" @endif>
    <label for="{{ $lookupId }}" class="block text-sm font-medium">{{ $label }}</label>
    <input type="search" data-lookup-search maxlength="100" autocomplete="off" aria-label="Search {{ strtolower($label) }}" aria-controls="{{ $lookupId }}" placeholder="Search {{ strtolower($label) }}…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select id="{{ $lookupId }}" name="{{ $name }}" data-lookup-select @required($required) class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">{{ $placeholder }}</option>
        @if($selected)<option value="{{ $selected['id'] }}" selected>{{ $selected['label'] }}</option>@endif
        @foreach($options as $option)@if((string)($selected['id'] ?? '') !== (string)$option['id'])<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endif @endforeach
    </select>
    <div class="flex items-center gap-2 text-xs text-slate-500"><span data-lookup-status role="status" aria-live="polite">{{ $depends ? 'Choose the field above, then search.' : 'Focus or search to load options.' }}</span><button type="button" data-lookup-more hidden class="text-indigo-600 underline">More results</button></div>
    <noscript><p class="text-xs text-slate-600">Enable JavaScript to search available options.</p></noscript>
</div>

@php
    $value = $setting->value;
    $key = $setting->key;
    $type = $setting->type;
@endphp

@if($type === 'boolean')
    <select name="settings[{{ $key }}]" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="1" {{ $value == '1' || $value === true ? 'selected' : '' }}>Enabled</option>
        <option value="0" {{ $value == '0' || $value === false ? 'selected' : '' }}>Disabled</option>
    </select>
@elseif($type === 'integer')
    <input type="number" name="settings[{{ $key }}]" 
           value="{{ $value }}"
           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
@elseif($type === 'json')
    <textarea name="settings[{{ $key }}]" 
              rows="3"
              class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Enter valid JSON">{{ $value }}</textarea>
@elseif($key === 'current_semester')
    <select name="settings[{{ $key }}]" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="first" {{ $value === 'first' ? 'selected' : '' }}>First Semester</option>
        <option value="second" {{ $value === 'second' ? 'selected' : '' }}>Second Semester</option>
        <option value="summer" {{ $value === 'summer' ? 'selected' : '' }}>Summer</option>
    </select>
@elseif($key === 'digest_frequency')
    <select name="settings[{{ $key }}]" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="realtime" {{ $value === 'realtime' ? 'selected' : '' }}>Real-time</option>
        <option value="daily" {{ $value === 'daily' ? 'selected' : '' }}>Daily Digest</option>
        <option value="weekly" {{ $value === 'weekly' ? 'selected' : '' }}>Weekly Digest</option>
    </select>
@elseif($key === 'default_grading_scale')
    <select name="settings[{{ $key }}]" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="100" {{ $value === '100' ? 'selected' : '' }}>100-Point Scale</option>
        <option value="4.0" {{ $value === '4.0' ? 'selected' : '' }}>4.0 GPA Scale</option>
        <option value="10" {{ $value === '10' ? 'selected' : '' }}>10-Point Scale</option>
    </select>
@elseif(str_contains($key, 'date') || str_contains($key, 'time'))
    <input type="date" name="settings[{{ $key }}]" 
           value="{{ $value }}"
           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
@elseif(str_contains($key, 'email'))
    <input type="email" name="settings[{{ $key }}]" 
           value="{{ $value }}"
           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
@else
    <input type="text" name="settings[{{ $key }}]" 
           value="{{ $value }}"
           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
@endif

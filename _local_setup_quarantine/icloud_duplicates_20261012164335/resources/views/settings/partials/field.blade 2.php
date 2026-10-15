@php
    $value = $setting->value;
    $key = $setting->key;
    $type = $setting->type;
    $displayValue = is_array($value) || is_object($value)
        ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : (string) ($value ?? '');
    $integerBounds = [
        'password_min_length' => [6, 128],
        'max_login_attempts' => [1, 50],
        'lockout_duration_minutes' => [1, 1440],
        'login_requests_per_minute' => [1, 120],
        'registration_requests_per_hour' => [1, 100],
        'password_reset_requests_per_minute' => [1, 30],
        'verification_requests_per_minute' => [1, 30],
        'two_factor_attempts_per_minute' => [1, 30],
        'payment_requests_per_minute' => [1, 60],
    ];
    [$integerMin, $integerMax] = $integerBounds[$key] ?? [null, null];
@endphp

@if($type === 'boolean')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="1" {{ $value == '1' || $value === true ? 'selected' : '' }}>Enabled</option>
        <option value="0" {{ $value == '0' || $value === false ? 'selected' : '' }}>Disabled</option>
    </select>
@elseif($type === 'integer')
    <input type="number" name="settings[{{ $key }}]"
           value="{{ $displayValue }}"
           @if($integerMin !== null) min="{{ $integerMin }}" @endif
           @if($integerMax !== null) max="{{ $integerMax }}" @endif
           step="1"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
@elseif($type === 'json')
    <textarea name="settings[{{ $key }}]" 
              rows="3"
              class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              placeholder="Enter valid JSON">{{ $displayValue }}</textarea>
@elseif($key === 'current_semester')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="first" {{ $value === 'first' ? 'selected' : '' }}>First Semester</option>
        <option value="second" {{ $value === 'second' ? 'selected' : '' }}>Second Semester</option>
        <option value="summer" {{ $value === 'summer' ? 'selected' : '' }}>Summer</option>
    </select>
@elseif($key === 'digest_frequency')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="realtime" {{ $value === 'realtime' ? 'selected' : '' }}>Real-time</option>
        <option value="daily" {{ $value === 'daily' ? 'selected' : '' }}>Daily Digest</option>
        <option value="weekly" {{ $value === 'weekly' ? 'selected' : '' }}>Weekly Digest</option>
    </select>
@elseif($key === 'default_grading_scale')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="100" {{ $value === '100' ? 'selected' : '' }}>100-Point Scale</option>
        <option value="4.0" {{ $value === '4.0' ? 'selected' : '' }}>4.0 GPA Scale</option>
        <option value="10" {{ $value === '10' ? 'selected' : '' }}>10-Point Scale</option>
    </select>
@elseif($key === 'timezone')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        <option value="">Select Timezone</option>
        @foreach(['Africa/Lagos','Africa/Accra','Africa/Cairo','Africa/Johannesburg','Africa/Nairobi','America/New_York','America/Los_Angeles','America/Chicago','Europe/London','Europe/Paris','Europe/Berlin','Asia/Tokyo','Asia/Shanghai','Asia/Kolkata','Asia/Dubai','Australia/Sydney','Pacific/Auckland'] as $tz)
            <option value="{{ $tz }}" {{ $value === $tz ? 'selected' : '' }}>{{ $tz }}</option>
        @endforeach
    </select>
@elseif($key === 'default_language')
    <select name="settings[{{ $key }}]" class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        @foreach(['en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','pt'=>'Portuguese','ar'=>'Arabic','zh'=>'Chinese','ja'=>'Japanese','yo'=>'Yoruba','ha'=>'Hausa'] as $lang => $label)
            <option value="{{ $lang }}" {{ $value === $lang ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
@elseif($key === 'site_logo' || $key === 'site_favicon')
    <input type="text" name="settings[{{ $key }}]" 
           value="{{ $displayValue }}"
           placeholder="Enter URL or path to {{ $key === 'site_logo' ? 'logo' : 'favicon' }}"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
    <p class="text-xs text-gray-500 mt-1">Enter a URL or relative path (e.g., /images/logo.png)</p>
@elseif(str_contains($key, 'date') || str_contains($key, 'time'))
    <input type="date" name="settings[{{ $key }}]" 
           value="{{ $displayValue }}"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
@elseif(str_contains($key, 'email'))
    <input type="email" name="settings[{{ $key }}]" 
           value="{{ $displayValue }}"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
@else
    <input type="text" name="settings[{{ $key }}]" 
           value="{{ $displayValue }}"
           class="w-full rounded border border-slate-300 px-3 py-2 hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
@endif

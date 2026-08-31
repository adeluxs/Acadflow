@php
    $safeUiMessage = static fn ($value, $fallback) => \App\Support\Errors\UserFacingError::safeMessage(is_string($value) ? $value : null, $fallback);
@endphp

@if(session('status'))
    <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">
        {{ $safeUiMessage(session('status'), 'Your request has been updated.') }}
    </div>
@endif
@if(session('success'))
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
        {{ $safeUiMessage(session('success'), 'Completed successfully.') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
        <p class="font-semibold">{{ $safeUiMessage(session('error'), 'We could not complete your request right now. Please try again.') }}</p>
        @if(session('request_id'))
            <p class="mt-1 text-xs font-normal text-rose-700/80">Request ID: {{ session('request_id') }}</p>
        @endif
    </div>
@endif
@if(session('retry_after'))
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900" aria-live="polite">
        <span data-rate-limit-countdown data-retry-after="{{ (int) session('retry_after') }}">Please wait before trying again.</span>
    </div>
@endif
@if(isset($errors) && $errors->any())
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
        <p class="font-semibold">Please correct the highlighted information.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $safeUiMessage($error, 'Please review this field and try again.') }}</li>
            @endforeach
        </ul>
    </div>
@endif

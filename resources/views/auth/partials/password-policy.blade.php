@php
    $policy = $passwordPolicy ?? \App\Services\SettingService::getPasswordPolicy();
    $passwordInputId = $passwordInputId ?? 'password';
    $confirmationInputId = $confirmationInputId ?? 'password_confirmation';
@endphp
<div
    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
    data-password-policy
    data-password-input="{{ $passwordInputId }}"
    data-confirmation-input="{{ $confirmationInputId }}"
    data-min-length="{{ $policy['min_length'] }}"
    data-require-uppercase="{{ $policy['require_uppercase'] ? '1' : '0' }}"
    data-require-number="{{ $policy['require_numbers'] ? '1' : '0' }}"
    data-require-special="{{ $policy['require_special'] ? '1' : '0' }}"
    data-special-characters="{{ $policy['special_characters'] }}"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-slate-800">Password requirements</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">These match the password policy currently configured by AcadFlow.</p>
        </div>
        <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600" data-password-policy-status aria-live="polite">Not ready</span>
    </div>

    <ul class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
        <li class="flex items-center gap-2" data-password-requirement="min_length">
            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-[11px] font-black" data-password-requirement-icon>○</span>
            <span>At least {{ $policy['min_length'] }} characters</span>
        </li>
        @if($policy['require_uppercase'])
            <li class="flex items-center gap-2" data-password-requirement="uppercase">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-[11px] font-black" data-password-requirement-icon>○</span>
                <span>One uppercase letter</span>
            </li>
        @endif
        @if($policy['require_numbers'])
            <li class="flex items-center gap-2" data-password-requirement="number">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-[11px] font-black" data-password-requirement-icon>○</span>
                <span>One number</span>
            </li>
        @endif
        @if($policy['require_special'])
            <li class="flex items-center gap-2" data-password-requirement="special">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-[11px] font-black" data-password-requirement-icon>○</span>
                <span>One special character ({{ $policy['special_characters'] }})</span>
            </li>
        @endif
        <li class="flex items-center gap-2" data-password-requirement="confirmation">
            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-[11px] font-black" data-password-requirement-icon>○</span>
            <span>Confirmation matches</span>
        </li>
    </ul>
</div>

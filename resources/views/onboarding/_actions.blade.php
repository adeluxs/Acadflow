<div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6">
    <button type="submit" formaction="{{ route('onboarding.back') }}" class="rounded-2xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">Back</button>
    <div class="flex gap-3">
        @if($optional ?? false)
            <button type="submit" formaction="{{ route('onboarding.skip', $step) }}" class="rounded-2xl px-5 py-3 font-bold text-slate-500 hover:bg-slate-50">Skip for now</button>
        @endif
        <button type="submit" class="rounded-2xl bg-blue-700 px-6 py-3 font-bold text-white shadow-lg shadow-blue-700/20 hover:bg-blue-800">Save and continue</button>
    </div>
</div>

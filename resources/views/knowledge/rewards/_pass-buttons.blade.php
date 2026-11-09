{{-- Reader Pass purchase buttons. Pays from the wallet; a low balance goes to top-up and returns here. --}}
@php($passPrices = $prices ?? ($readEarn['prices'] ?? ['monthly_minor' => null, 'semester_minor' => null]))
@if($passPrices['monthly_minor'])
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('commerce.features.purchase') }}">
            @csrf
            <input type="hidden" name="feature" value="knowledge_reader_pass">
            <input type="hidden" name="billing_period" value="monthly">
            <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-indigo-800">Get Reader Pass · ₦{{ number_format($passPrices['monthly_minor'] / 100) }}/month</button>
        </form>
        @if($passPrices['semester_minor'])
            <form method="POST" action="{{ route('commerce.features.purchase') }}">
                @csrf
                <input type="hidden" name="feature" value="knowledge_reader_pass">
                <input type="hidden" name="billing_period" value="semester">
                <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button class="rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-black text-indigo-800 hover:bg-indigo-50">Semester · ₦{{ number_format($passPrices['semester_minor'] / 100) }}</button>
            </form>
        @endif
    </div>
    <p class="mt-2 text-xs text-slate-500">Paid from your AcadSyntra wallet. If your balance is low you'll be taken to top up, then brought back.</p>
@endif

@extends('layouts.app')
@section('title', 'Read & Earn administration')
@section('page-title', 'Read & Earn')
@section('page-subtitle', 'Reader Pass, quiz-verified rewards, monthly pools and challenge prizes')
@section('content')
@php
    $naira = fn ($minor) => '₦'.number_format(((int) $minor) / 100);
@endphp
<div class="space-y-7">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif

    <section class="rounded-3xl bg-gradient-to-br from-emerald-900 via-indigo-950 to-indigo-800 p-7 text-white">
        <p class="text-xs font-black uppercase tracking-[.2em] text-emerald-200">Learn · Share · Earn</p>
        <h1 class="mt-2 text-3xl font-black">Read &amp; Earn controls</h1>
        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-indigo-200">Feature status</p><p class="mt-1 text-lg font-black">{{ ucfirst($moduleStatus) }}</p><a href="{{ route('admin.settings.features') }}" class="mt-1 inline-block text-xs font-bold text-emerald-200 underline">Feature &amp; Module Management → "Reader Pass &amp; Read &amp; Earn"</a></div>
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-indigo-200">Reader Pass price</p><p class="mt-1 text-lg font-black">{{ $passRule ? $naira($passRule->unit_amount_minor).' / month' : 'No active price' }}</p><a href="{{ route('admin.monetization') }}" class="mt-1 inline-block text-xs font-bold text-emerald-200 underline">Edit in Monetization (feature.knowledge_reader_pass)</a></div>
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-indigo-200">This month ({{ $period }})</p><p class="mt-1 text-lg font-black">{{ (int) ($stats->counted ?? 0) }} passed quizzes · {{ (int) ($stats->readers ?? 0) }} readers</p><p class="text-xs text-indigo-200">{{ $naira($stats->credit_minor ?? 0) }} study credit given · {{ (int) ($stats->flagged ?? 0) }} held for review</p></div>
        </div>
        <a href="{{ route('admin.knowledge.index') }}" class="mt-5 inline-flex text-sm font-bold text-white underline">← Knowledge Hub administration</a>
    </section>

    <form method="POST" action="{{ route('admin.knowledge.read-earn.settings') }}" class="rounded-3xl border bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <h2 class="text-xl font-black">Rules and budgets</h2>
        <p class="mt-1 text-sm text-slate-500">Changes apply immediately. Money amounts are in naira.</p>
        @foreach($groups as $group => $groupLabel)
            <h3 class="mt-6 text-sm font-black uppercase tracking-wide text-indigo-700">{{ $groupLabel }}</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($definitions as $key => [$type, $default, $label, $help, $itemGroup])
                    @continue($itemGroup !== $group)
                    @if($type === 'boolean')
                        <label class="flex items-start gap-3 rounded-2xl border p-4"><input type="checkbox" name="{{ $key }}" value="1" @checked($values[$key]) class="mt-1 rounded"><span><strong class="block text-sm text-slate-900">{{ $label }}</strong>@if($help)<span class="mt-1 block text-xs text-slate-500">{{ $help }}</span>@endif</span></label>
                    @else
                        <label class="block rounded-2xl border p-4 text-sm font-bold text-slate-900">{{ $label }}
                            <input required name="{{ $key }}" value="{{ old($key, $values[$key]) }}" @if($type === 'integer') type="number" min="0" step="1" @else inputmode="decimal" @endif class="mt-2 w-full rounded-xl border-slate-300">
                            @if($help)<span class="mt-1 block text-xs font-normal text-slate-500">{{ $help }}</span>@endif
                        </label>
                    @endif
                @endforeach
            </div>
        @endforeach
        <button class="mt-6 rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white">Save Read &amp; Earn settings</button>
    </form>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Held for review</h2>
        <p class="mt-1 text-sm text-slate-500">Quizzes from a network already used by several accounts today. Approve to award the points and credit, or reject.</p>
        <div class="mt-4 divide-y">
            @forelse($flagged as $attempt)
                <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                    <div><p class="font-bold">{{ $attempt->user?->full_name }} <span class="font-normal text-slate-500">{{ $attempt->user?->email }} · joined {{ $attempt->user?->created_at?->diffForHumans() }}</span></p><p class="text-slate-500">{{ $attempt->publication?->title }} · {{ $attempt->score_percent }}% · {{ $attempt->points }} points · read {{ $attempt->read_seconds }}s · {{ $attempt->created_at->format('j M H:i') }}</p></div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.attempts.review', $attempt) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white">Approve</button></form>
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.attempts.review', $attempt) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700">Reject</button></form>
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-slate-500">Nothing waiting for review.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><h2 class="text-xl font-black">Monthly cash pools</h2><p class="mt-1 text-sm text-slate-500">After a month ends: calculate, check the list, exclude anyone suspicious, then pay. Money goes to withdrawable earnings.</p></div>
            <form method="GET" class="flex items-end gap-2"><label class="text-sm font-bold">Month<input type="month" name="period" value="{{ $selected }}" class="mt-1 block rounded-xl border-slate-300"></label><button class="rounded-xl border px-4 py-2.5 text-sm font-bold">Show</button></form>
        </div>
        <div class="mt-5 grid gap-6 xl:grid-cols-2">
            @foreach(['reader_pool' => ['Reader pool', 'knowledge_reader_pool_amount'], 'creator_pool' => ['Creator pool', 'knowledge_creator_pool_amount']] as $kind => [$poolLabel, $amountKey])
                @php($rows = $pools[$kind])
                @php($drafts = $rows->where('status', 'draft'))
                <div class="rounded-2xl border p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-black">{{ $poolLabel }} · {{ $selected }}</h3>
                        <span class="text-xs font-bold text-slate-500">Budget ₦{{ number_format((float) $values[$amountKey]) }} · paid {{ $naira($rows->where('status', 'paid')->sum('amount_minor')) }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.pools.calculate', $kind) }}">@csrf<input type="hidden" name="period" value="{{ $selected }}"><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">{{ $rows->isEmpty() ? 'Calculate' : 'Recalculate drafts' }}</button></form>
                        @if($drafts->isNotEmpty())
                            <form method="POST" action="{{ route('admin.knowledge.read-earn.pay') }}" onsubmit="return confirm('Pay {{ $drafts->count() }} rewards totalling {{ $naira($drafts->sum('amount_minor')) }}? This cannot be undone.')">@csrf<input type="hidden" name="scope_key" value="{{ $kind }}:{{ $selected }}"><button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Pay {{ $drafts->count() }} · {{ $naira($drafts->sum('amount_minor')) }}</button></form>
                        @endif
                    </div>
                    @include('admin.knowledge.partials.reward-rows', ['rows' => $rows, 'pointsLabel' => $kind === 'reader_pool' ? 'points' : 'verified reads'])
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Reading Challenges</h2>
        <p class="mt-1 text-sm text-slate-500">Leaderboards use the same passed quizzes as Read &amp; Earn, so readers earn their normal rewards and climb the board at the same time. Prizes come from the challenge's own budget.</p>
        <details class="mt-4 rounded-2xl border p-4" @if($readingChallenges->isEmpty()) open @endif>
            <summary class="cursor-pointer font-bold">New Reading Challenge</summary>
            <form method="POST" action="{{ route('admin.knowledge.read-earn.challenges.store') }}" class="mt-4">
                @csrf
                @include('admin.knowledge.partials.reading-challenge-fields', ['challenge' => null, 'defaultPool' => (float) $values['knowledge_challenge_default_prize_pool']])
                <button class="mt-4 rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-bold text-white">Save challenge</button>
            </form>
        </details>
        <div class="mt-5 space-y-4">
            @foreach($readingChallenges as $challenge)
                @php($rows = $challengePayouts->get($challenge->scopeKey(), collect()))
                @php($drafts = $rows->where('status', 'draft'))
                <div class="rounded-2xl border p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="font-black">{{ $challenge->title }} <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ ucfirst($challenge->status) }}</span></p><p class="text-sm text-slate-500">{{ $challenge->starts_at->format('j M Y H:i') }} to {{ $challenge->ends_at->format('j M Y H:i') }} · prizes {{ collect($challenge->prizes)->map(fn ($p) => $naira($p))->implode(', ') }}</p></div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('knowledge.rewards.challenges.show', $challenge) }}" class="rounded-xl border px-3 py-2 text-xs font-bold">Leaderboard</a>
                            @if($challenge->ends_at->isPast())
                                <form method="POST" action="{{ route('admin.knowledge.read-earn.challenges.calculate', $challenge) }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">{{ $rows->isEmpty() ? 'Calculate winners' : 'Recalculate winners' }}</button></form>
                            @endif
                            @if($drafts->isNotEmpty())
                                <form method="POST" action="{{ route('admin.knowledge.read-earn.pay') }}" onsubmit="return confirm('Pay {{ $drafts->count() }} prizes totalling {{ $naira($drafts->sum('amount_minor')) }}?')">@csrf<input type="hidden" name="scope_key" value="{{ $challenge->scopeKey() }}"><button class="rounded-xl bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Pay {{ $naira($drafts->sum('amount_minor')) }}</button></form>
                            @endif
                        </div>
                    </div>
                    <details class="mt-3"><summary class="cursor-pointer text-sm font-bold text-indigo-700">Edit challenge</summary>
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.challenges.update', $challenge) }}" class="mt-3">@csrf @method('PUT')
                            @include('admin.knowledge.partials.reading-challenge-fields', ['challenge' => $challenge, 'defaultPool' => 0])
                            <button class="mt-3 rounded-xl bg-indigo-700 px-4 py-2 text-sm font-bold text-white">Update</button>
                        </form>
                    </details>
                    @include('admin.knowledge.partials.reward-rows', ['rows' => $rows, 'pointsLabel' => 'points'])
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">✍️ AcadSyntra Writing Challenges (official, paid by AcadSyntra)</h2>
        <p class="mt-1 text-sm text-slate-500">Writers publish a guide or story on the Knowledge Hub and enter it. A live "Readers' favourite" board ranks entries by verified reads; you score each entry; the final score blends both. Prizes are paid from here after results.</p>
        <details class="mt-4 rounded-2xl border p-4" @if($writingChallenges->isEmpty()) open @endif>
            <summary class="cursor-pointer font-bold">New Writing Challenge</summary>
            <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.store') }}" class="mt-4">@csrf
                @include('admin.knowledge.partials.writing-challenge-fields', ['challenge' => null, 'defaultPool' => (float) $values['knowledge_challenge_default_prize_pool']])
                <button class="mt-4 rounded-xl bg-violet-700 px-4 py-2.5 text-sm font-bold text-white">Create Writing Challenge</button>
            </form>
        </details>
        <div class="mt-4 divide-y rounded-2xl border">
            @forelse($writingChallenges as $writing)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div><p class="font-black">{{ $writing->title }} <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ ucfirst($writing->phase()) }}</span></p><p class="text-sm text-slate-500">{{ $writing->starts_at->format('j M') }} to {{ $writing->submission_deadline->format('j M Y') }} · {{ $writing->entries_count }} entries · ₦{{ number_format(intdiv($writing->prizePoolMinor(), 100)) }}</p></div>
                    <div class="flex gap-2"><a href="{{ route('admin.knowledge.read-earn.writing.show', $writing) }}" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Judge &amp; manage</a><a href="{{ route('knowledge.writing.show', $writing) }}" class="rounded-xl border px-3 py-2 text-xs font-bold">Public page</a></div>
                </div>
            @empty
                <p class="p-4 text-sm text-slate-500">No official Writing Challenge yet. Create one above.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-black">🤖 AI quiz questions</h2>
                <p class="mt-1 text-sm text-slate-500">Questions AI wrote for published guides, and the ones readers reported. Settings are in "Read &amp; Earn questions" above.</p>
            </div>
            <div class="flex gap-2 text-xs font-bold">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800">{{ (int) ($quizStats->ready ?? 0) }} guides ready</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ (int) ($quizStats->waiting ?? 0) }} waiting</span>
                @if(($quizStats->failed ?? 0) > 0)<span class="rounded-full bg-rose-50 px-3 py-1 text-rose-800">{{ (int) $quizStats->failed }} failed (retried hourly)</span>@endif
            </div>
        </div>
        @if($awaitingApproval->isNotEmpty())
            <h3 class="mt-5 text-sm font-black text-slate-800">Waiting for approval</h3>
            <div class="mt-2 divide-y rounded-2xl border">
                @foreach($awaitingApproval as $pending)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                        <a href="{{ route('knowledge.rewards.questions.edit', $pending) }}" class="font-semibold text-indigo-700 underline">{{ $pending->title }}</a>
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.questions.approve', $pending) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">Approve AI questions</button></form>
                    </div>
                @endforeach
            </div>
        @endif
        <h3 class="mt-5 text-sm font-black text-slate-800">Reported by readers</h3>
        <div class="mt-2 divide-y rounded-2xl border">
            @forelse($reportedQuestions as $reported)
                <div class="grid gap-2 p-3 text-sm md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $reported->question }} @if($reported->source === 'ai')<span class="rounded bg-violet-100 px-1.5 py-0.5 text-xs font-black text-violet-800">AI</span>@endif</p>
                        <p class="mt-1 text-xs text-slate-600">Marked answer: <strong>{{ $reported->options[$reported->correct_option] ?? '' }}</strong> · {{ $reported->publication?->title }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $reported->report_count }} report{{ $reported->report_count === 1 ? '' : 's' }}: {{ $reported->reports->pluck('reason')->filter()->countBy()->map(fn ($n, $r) => str_replace('_', ' ', $r).' ×'.$n)->implode(', ') }}{{ $reported->retired_at ? ' · pulled from quizzes' : ' · still being asked' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.questions.review', $reported) }}">@csrf<input type="hidden" name="decision" value="restore"><button class="rounded-lg border px-3 py-1.5 text-xs font-bold">Question is fine</button></form>
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.questions.review', $reported) }}">@csrf<input type="hidden" name="decision" value="remove"><button class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white">Remove it</button></form>
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-slate-500">No reported questions.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">🔒 Community challenge prize escrow</h2>
        <p class="mt-1 text-sm text-slate-500">Prizes that creators of community challenges (Knowledge Hub › Challenges) paid in upfront. AcadSyntra holds them until the creator pays the winners; unused amounts go back to the creator. Fee: {{ $values['knowledge_challenge_prize_fee_percent'] }}%.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-2 pr-3">Challenge</th><th class="py-2 pr-3">Creator</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3 text-right">Prize</th><th class="py-2 pr-3 text-right">Fee</th><th class="py-2 pr-3 text-right">Held</th><th class="py-2 pr-3">Status</th><th class="py-2"></th></tr></thead><tbody class="divide-y">
            @forelse($prizeFunds as $fund)
                <tr><td class="py-2 pr-3"><a href="{{ $fund->challenge ? route('knowledge.challenges.show', $fund->challenge) : '#' }}" class="font-semibold underline">{{ $fund->challenge?->title }}</a></td><td class="py-2 pr-3">{{ $fund->funder?->full_name }}</td><td class="py-2 pr-3">{{ \App\Models\ChallengePrizeFund::TYPES[$fund->prize_type] ?? $fund->prize_type }}</td><td class="py-2 pr-3 text-right">₦{{ number_format($fund->total_minor / 100) }}</td><td class="py-2 pr-3 text-right">₦{{ number_format($fund->fee_minor / 100) }}</td><td class="py-2 pr-3 text-right font-bold">₦{{ number_format($fund->heldMinor() / 100) }}</td><td class="py-2 pr-3">{{ ucfirst($fund->status) }}</td>
                <td class="py-2 text-right">@if($fund->status === 'funded')<form method="POST" action="{{ route('admin.knowledge.read-earn.escrow.settle', $fund) }}" onsubmit="return confirm('Pay the ranked winners now (or refund if there are none)?')">@csrf<button class="rounded-lg border px-2 py-1 text-xs font-bold">Settle now</button></form>@endif</td></tr>
            @empty
                <tr><td colspan="8" class="py-4 text-slate-500">No funded community challenges yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection

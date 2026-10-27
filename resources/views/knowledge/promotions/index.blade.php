@extends('layouts.app')
@section('title', 'Promote your work')
@section('page-title', 'Knowledge Hub promotions')
@section('page-subtitle', 'Clearly labelled sponsored discovery for your published work')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-3xl bg-gradient-to-br from-slate-950 to-indigo-800 p-7 text-white">
        <p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Paid promotion</p>
        <h1 class="mt-2 text-3xl font-black">Reach more interested readers</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-100">Promotions appear as Sponsored placements. They increase discovery only and never add academic validation, peer review or credibility badges.</p>
    </section>
    @if(session('success'))
        <div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif
    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Start a campaign</h2>
        @if($publications->isEmpty() || $packages->isEmpty())
            <p class="mt-3 text-sm text-slate-500">You need a public, published item and an active promotion package before you can start.</p>
        @else
            <form method="POST" action="{{ route('knowledge.promotions.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                @csrf
                <label class="text-sm font-bold">Published work<select name="knowledge_publication_id" required class="mt-2 w-full rounded-xl border-slate-300">@foreach($publications as $publication)<option value="{{ $publication->id }}">{{ $publication->title }}</option>@endforeach</select></label>
                <label class="text-sm font-bold">Package<select name="knowledge_promotion_package_id" required class="mt-2 w-full rounded-xl border-slate-300">@foreach($packages as $package)<option value="{{ $package->id }}">{{ $package->name }} · {{ $package->duration_days }} days · {{ config('app.currency_symbol', '₦') }}{{ number_format((float)$package->price, 2) }}</option>@endforeach</select></label>
                <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white">Continue to payment</button>
            </form>
        @endif
    </section>
    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Campaign performance</h2>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-400"><tr><th class="p-3">Publication</th><th class="p-3">Status</th><th class="p-3">Schedule</th><th class="p-3">Impressions</th><th class="p-3">Clicks</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($campaigns as $campaign)
                        <tr><td class="p-3"><p class="font-bold">{{ $campaign->publication?->title }}</p><p class="text-xs text-slate-500">{{ $campaign->package?->name }}</p></td><td class="p-3">{{ str($campaign->status)->headline() }}</td><td class="p-3 text-xs text-slate-500">{{ $campaign->starts_at?->format('M j') ?: 'After payment' }} — {{ $campaign->ends_at?->format('M j, Y') ?: '—' }}</td><td class="p-3 font-bold">{{ number_format($campaign->impressions) }}</td><td class="p-3 font-bold">{{ number_format($campaign->clicks) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No promotion campaigns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    </section>
</div>
@endsection

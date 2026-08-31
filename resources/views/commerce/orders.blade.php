@extends('layouts.app')
@section('title','Commerce Orders')
@section('page-title','Commerce Orders')
@section('page-subtitle','Purchases, entitlements, payment states and refund history')
@section('content')
@php($fmt = fn (int $minor) => \App\Support\Money::fromMinor($minor))
<div class="space-y-4">
@forelse($orders as $order)
    @php
        $orderMinor = (int)($order->total_amount_minor ?? \App\Support\Money::toMinor((string)$order->total_amount));
        $reservedRefundMinor = (int)$order->refunds->whereIn('status',['requested','completed'])->sum(fn($r)=>(int)($r->amount_minor ?? \App\Support\Money::toMinor((string)$r->amount)));
        $remainingRefundMinor = max(0,$orderMinor-$reservedRefundMinor);
    @endphp
    <article class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-slate-950">{{ $order->order_number }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ str($order->payment_status)->replace('_',' ')->headline() }}</span></div>
                <p class="mt-1 text-sm text-slate-500">{{ $order->buyer?->full_name }} · {{ $order->created_at?->format('d M Y, H:i') }}</p>
                <div class="mt-3 space-y-1 text-sm">@foreach($order->items as $item)<p>{{ $item->title }}</p>@endforeach</div>
            </div>
            <div class="text-left lg:text-right"><p class="text-xs font-bold uppercase text-slate-400">Total</p><p class="text-2xl font-black">{{ $order->currency }} {{ $fmt($orderMinor) }}</p></div>
        </div>

        @if($order->refunds->isNotEmpty())
            <div class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-wide text-slate-500">Refund history</p><div class="mt-2 divide-y">@foreach($order->refunds as $refund)<div class="py-3 text-sm"><div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $order->currency }} {{ $fmt((int)($refund->amount_minor ?? \App\Support\Money::toMinor((string)$refund->amount))) }} · {{ str($refund->status)->headline() }}</span><span class="text-xs text-slate-500">{{ $refund->created_at?->format('d M Y, H:i') }}</span></div><p class="mt-1 text-slate-600">{{ $refund->reason }}</p>@if(auth()->user()->isAdmin() && $refund->status==='requested')<form method="POST" action="{{ route('commerce.refunds.process',$refund) }}" class="mt-3 flex flex-wrap gap-2">@csrf<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve refund</button><button name="decision" value="reject" class="rounded-lg border px-3 py-2 text-xs font-bold">Reject</button><input name="note" class="min-w-56 flex-1 rounded-lg border-slate-300 text-xs" placeholder="Decision note (optional)"></form>@endif</div>@endforeach</div></div>
        @endif

        @if($remainingRefundMinor>0 && in_array($order->payment_status,['paid','partially_refunded'],true) && ($order->buyer_id===auth()->id() || auth()->user()->isAdmin()))
            <details class="mt-4 rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-bold">Request a refund</summary><form method="POST" action="{{ route('commerce.refunds.store',$order) }}" class="mt-4 grid gap-3 md:grid-cols-[180px_1fr_auto]">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input required type="number" min="0.01" max="{{ $fmt($remainingRefundMinor) }}" step="0.01" name="amount" class="rounded-xl border-slate-300" placeholder="Amount"><input required name="reason" maxlength="5000" class="rounded-xl border-slate-300" placeholder="Reason for refund"><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Submit request</button></form><p class="mt-2 text-xs text-slate-500">Maximum currently refundable: {{ $order->currency }} {{ $fmt($remainingRefundMinor) }}.</p></details>
        @endif
    </article>
@empty
    <div class="rounded-2xl border border-dashed bg-white p-12 text-center text-sm text-slate-500">No commerce orders yet.</div>
@endforelse
</div>
<div class="mt-5">{{ $orders->links() }}</div>
@endsection

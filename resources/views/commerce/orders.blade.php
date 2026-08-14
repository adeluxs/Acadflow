@extends('layouts.app')
@section('title','Commerce Orders')
@section('page-title','Commerce Orders')
@section('page-subtitle','Purchases, entitlements and refund history')
@section('content')
<div class="overflow-hidden rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Order</th><th>Buyer</th><th>Total</th><th>Status</th><th>Items</th><th>Refunds</th></tr></thead><tbody>@foreach($orders as $order)<tr class="border-t"><td class="p-4 font-medium">{{ $order->order_number }}</td><td>{{ $order->buyer?->full_name }}</td><td>{{ $order->currency }} {{ number_format((float)$order->total_amount,2) }}</td><td>{{ $order->payment_status }}</td><td>@foreach($order->items as $item)<div>{{ $item->title }}</div>@endforeach</td><td>{{ $order->refunds->count() }}</td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $orders->links() }}</div>
@endsection

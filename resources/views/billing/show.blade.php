@extends('layouts.app')

@section('title', 'Invoice Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Invoice #{{ $invoice->uuid }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <div class="text-gray-500 text-sm">Amount</div>
                <div class="text-2xl font-bold">${{ number_format($invoice->amount, 2) }}</div>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Status</div>
                <span class="px-2 inline-flex text-sm font-semibold rounded-full 
                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ ucfirst($invoice->status) }}
                </span>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Due Date</div>
                <div>{{ $invoice->due_date->format('Y-m-d') }}</div>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Paid At</div>
                <div>{{ $invoice->paid_at?->format('Y-m-d H:i') ?? '-' }}</div>
            </div>
        </div>

        @if($invoice->status !== 'paid')
            <form method="POST" action="{{ route('billing.pay', $invoice) }}">
                @csrf
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                    Pay Now
                </button>
            </form>
        @endif

        <a href="{{ route('billing.my') }}" class="ml-4 text-gray-600 hover:text-gray-900">Back</a>
    </div>
</div>
@endsection
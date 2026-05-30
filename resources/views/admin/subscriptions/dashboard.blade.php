@extends('layouts.app')

@section('title', 'Subscription Analytics')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Subscription Analytics</h1>
        <p class="text-gray-600 mt-2">Overview of subscription metrics and revenue</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Active Subscriptions</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($activeSubscriptions) }}</p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Expired</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($expiredSubscriptions) }}</p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Cancelled</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($cancelledSubscriptions) }}</p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">MRR (Monthly Recurring Revenue)</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($mrr, 2) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Revenue (Last 30 Days)</h3>
            <p class="text-3xl font-bold text-green-600">${{ number_format($revenue30Days, 2) }}</p>
            <p class="text-sm text-gray-500 mt-2">Failed payments: {{ $failedPayments }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">All-Time Revenue</h3>
            <p class="text-3xl font-bold text-gray-900">${{ number_format($revenueAllTime, 2) }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Active Plans</h3>
            @foreach($planStats as $stat)
                <div class="flex justify-between items-center py-2 border-b last:border-b-0">
                    <span class="text-sm {{ $stat['type'] === 'b2b' ? 'text-purple-600' : ($stat['type'] === 'free' ? 'text-green-600' : 'text-blue-600') }}">
                        {{ $stat['name'] }}
                    </span>
                    <span class="text-sm font-medium">{{ $stat['active_subscribers'] }} (${{ number_format($stat['revenue'], 2) }}/mo)</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h3 class="text-lg font-bold mb-4">Recent Transactions</h3>
        @if($recentTransactions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm">User</th>
                            <th class="px-4 py-2 text-left text-sm">Amount</th>
                            <th class="px-4 py-2 text-left text-sm">Status</th>
                            <th class="px-4 py-2 text-left text-sm">Gateway</th>
                            <th class="px-4 py-2 text-left text-sm">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransactions as $transaction)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm">{{ $transaction['user'] }}</td>
                                <td class="px-4 py-2 text-sm">${{ number_format($transaction['amount'], 2) }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $transaction['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($transaction['status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($transaction['status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ $transaction['gateway'] }}</td>
                                <td class="px-4 py-2 text-sm">{{ $transaction['created_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No transactions found</p>
        @endif
    </div>

    <!-- Expiring Soon -->
    @if($expiringSoon->isNotEmpty())
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h3 class="text-lg font-bold mb-4">Subscriptions Expiring Soon (Next 7 Days)</h3>
        <div class="space-y-4">
            @foreach($expiringSoon as $sub)
                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900">{{ $sub->user->full_name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-600">{{ $sub->plan->display_name ?? 'N/A' }} - ${{ number_format($sub->amount, 2) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Expires</p>
                        <p class="font-medium text-yellow-800">{{ $sub->ends_at->format('M d, Y') }}</p>
                    </div>
                    <a href="{{ route('admin.subscriptions.edit', $sub) }}" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Renew
                    </a>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Top Plans -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h3 class="text-lg font-bold mb-4">Top Subscription Plans</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($topPlans as $plan)
                <div class="border rounded-lg p-4 text-center">
                    <h4 class="font-bold text-gray-900">{{ $plan->display_name }}</h4>
                    <p class="text-2xl font-bold text-indigo-600 my-2">{{ $plan->count }}</p>
                    <p class="text-sm text-gray-500">active subscriptions</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

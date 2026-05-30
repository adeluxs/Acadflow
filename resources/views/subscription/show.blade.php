@extends('layouts.app')

@section('title', 'My Subscription')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Subscription</h1>
        <p class="text-gray-600 mt-2">Manage your subscription plan</p>
    </div>

    @if($subscription && $plan)
        <!-- Current Plan -->
        <div class="bg-white rounded-lg shadow p-8 mb-8">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ $plan->display_name }}</h2>
                    <p class="text-gray-600 mt-1">{{ $plan->description }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $subscription->isActive() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $subscription->isActive() ? 'Active' : ucfirst($subscription->status) }}
                </span>
            </div>

            <!-- Subscription Details -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm text-gray-600">Amount</p>
                    <p class="font-semibold">${{ number_format($subscription->amount ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Billing Cycle</p>
                    <p class="font-semibold capitalize">{{ $subscription->billing_cycle ?? 'monthly' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Payment Status</p>
                    <p class="font-semibold {{ $subscription->isPaid() ? 'text-green-600' : 'text-yellow-600' }}">
                        {{ ucfirst($subscription->payment_status ?? 'pending') }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Auto-Renew</p>
                    <p class="font-semibold">{{ $subscription->auto_renew ? 'Yes' : 'No' }}</p>
                </div>
            </div>

            <!-- Validity Period -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-800">
                    Valid from {{ $subscription->started_at?->format('M d, Y') ?? 'N/A' }}
                    @if($subscription->ends_at)
                        to {{ $subscription->ends_at->format('M d, Y') }}
                        ({{ $subscription->ends_at->diffForHumans() }})
                    @endif
                </p>
                @if($subscription->trial_ends_at)
                    <p class="text-sm text-blue-800 mt-1">
                        Trial ends: {{ $subscription->trial_ends_at->format('M d, Y') }}
                    </p>
                @endif
            </div>

            <!-- Pricing -->
            <div class="grid grid-cols-3 gap-6 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Monthly</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($plan->price_per_month ?? 0, 2) }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Semester</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($plan->price_per_semester ?? $plan->price_per_month * 4, 2) }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Yearly</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($plan->price_per_year ?? $plan->price_per_month * 10, 2) }}</p>
                </div>
            </div>

            <!-- Usage -->
            <div class="mb-6">
                <h3 class="font-bold text-gray-900 mb-4">Usage</h3>
                <div class="grid grid-cols-2 gap-4">
                    @if($summary['limits']['max_courses'])
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Courses</span>
                                <span class="text-gray-900">{{ $summary['usage']['courses'] }} / {{ $summary['limits']['max_courses'] }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, ($summary['usage']['courses'] / max(1, $summary['limits']['max_courses'])) * 100) }}%"></div>
                            </div>
                        </div>
                    @endif
                    @if($summary['limits']['max_storage_gb'])
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Storage</span>
                                <span class="text-gray-900">{{ $summary['usage']['storage_gb'] }}GB / {{ $summary['limits']['max_storage_gb'] }}GB</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, ($summary['usage']['storage_gb'] / max(1, $summary['limits']['max_storage_gb'])) * 100) }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Features -->
            <div class="mb-6">
                <h3 class="font-bold text-gray-900 mb-4">Features</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($summary['features'] as $feature)
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">{{ ucwords(str_replace('_', ' ', $feature)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('subscription.upgrade') }}" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                Change Plan
            </a>
        </div>
    @else
        <!-- No Active Subscription -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 mb-8">
            <h2 class="text-xl font-bold text-yellow-900 mb-2">No Active Subscription</h2>
            <p class="text-yellow-800 mb-4">You don't have an active subscription plan. Some features may be limited.</p>
            <a href="{{ route('subscription.upgrade') }}" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                View Plans
            </a>
        </div>
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', 'Subscriptions')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Subscriptions</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($subscriptions as $subscription)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold">{{ $subscription->plan_name }}</h3>
                <p class="text-gray-600">${{ number_format($subscription->price_per_student, 2) }} / student</p>
                <div class="mt-4 text-sm text-gray-500">
                    <p>Billing: {{ $subscription->billing_model }}</p>
                    <p>Valid: {{ $subscription->start_date->format('Y-m-d') }} to {{ $subscription->end_date->format('Y-m-d') }}</p>
                </div>
                <div class="mt-4">
                    <span class="px-2 inline-flex text-xs font-semibold rounded-full {{ $subscription->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $subscription->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No subscriptions found.</p>
        @endforelse
    </div>
</div>
@endsection
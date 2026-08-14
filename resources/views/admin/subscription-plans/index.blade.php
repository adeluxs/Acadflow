@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Subscription Plans</h1>
        <a href="{{ route('admin.subscription-plans.create') }}" 
           class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            + Create Plan
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <!-- Plan Categories -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
            <h3 class="font-bold text-gray-900 mb-2">B2C Plans</h3>
            <p class="text-sm text-gray-600 mb-4">Individual user subscriptions</p>
            <p class="text-2xl font-bold text-blue-600">
                {{ \App\Models\SubscriptionPlan::where('plan_type', 'b2c')->count() }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
            <h3 class="font-bold text-gray-900 mb-2">B2B Plans</h3>
            <p class="text-sm text-gray-600 mb-4">Business/Team subscriptions</p>
            <p class="text-2xl font-bold text-purple-600">
                {{ \App\Models\SubscriptionPlan::where('plan_type', 'b2b')->count() }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
            <h3 class="font-bold text-gray-900 mb-2">Free Plans</h3>
            <p class="text-sm text-gray-600 mb-4">No-cost subscriptions</p>
            <p class="text-2xl font-bold text-green-600">
                {{ \App\Models\SubscriptionPlan::where('plan_type', 'free')->count() }}
            </p>
        </div>
    </div>

    <!-- Active Plans -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-bold">Active Plans</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse(\App\Models\SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get() as $plan)
                    <div class="border rounded-lg p-6 hover:shadow-md transition {{ $plan->is_recommended ? 'border-indigo-500 ring-2 ring-indigo-500' : 'border-gray-200' }}">
                        @if($plan->is_recommended)
                            <span class="inline-block bg-indigo-100 text-indigo-800 text-xs font-bold px-2 py-1 rounded-full mb-3">
                                Most Popular
                            </span>
                        @endif
                        
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ $plan->display_name }}</h3>
                                <p class="text-sm text-gray-500 capitalize">{{ $plan->plan_type }} - {{ $plan->billing_cycle }}</p>
                            </div>
                            @if($plan->trial_days > 0)
                                <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded">
                                    {{ $plan->trial_days }}d trial
                                </span>
                            @endif
                        </div>

                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-900">
                                ${{ number_format($plan->price_per_month, 2) }}
                            </span>
                            <span class="text-gray-500">/month</span>
                        </div>

                        @if($plan->description)
                            <p class="text-sm text-gray-600 mb-4">{{ \Str::limit($plan->description, 100) }}</p>
                        @endif

                        <div class="space-y-2 mb-4">
                            @if($plan->max_courses)
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $plan->max_courses }} courses
                                </div>
                            @endif
                            @if($plan->max_storage_gb)
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $plan->max_storage_gb }}GB storage
                                </div>
                            @endif
                            @if($plan->allow_group_submissions)
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Group submissions
                                </div>
                            @endif
                            @if($plan->priority_support)
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Priority support
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('admin.subscription-plans.edit', $plan) }}" 
                               class="flex-1 text-center px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                                Edit
                            </a>
                            @if($plan->can_upgrade || $plan->can_downgrade)
                                <button type="button" onclick="document.getElementById('plan-{{ $plan->id }}-form').submit();" 
                                   class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm" 
                                   title="{{ $plan->can_upgrade ? 'Allow upgrades' : 'Upgrades disabled' }}">
                                    @if($plan->can_upgrade)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" opacity="0.3"/>
                                        </svg>
                                    @endif
                                </a>
                                <form id="plan-{{ $plan->id }}-form" method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" style="display: none;">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="can_upgrade" value="{{ $plan->can_upgrade ? 0 : 1 }}">
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 text-gray-500">
                        No active subscription plans. 
                        <a href="{{ route('admin.subscription-plans.create') }}" class="text-indigo-600 hover:underline">Create one</a> to get started.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Inactive Plans -->
    @php
        $inactivePlans = \App\Models\SubscriptionPlan::where('is_active', false)->get();
    @endphp
    @if($inactivePlans->isNotEmpty())
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Inactive Plans</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($inactivePlans as $plan)
                            <tr>
                                <td class="px-6 py-4">{{ $plan->display_name }}</td>
                                <td class="px-6 py-4 capitalize">{{ $plan->plan_type }}</td>
                                <td class="px-6 py-4">${{ number_format($plan->price_per_month, 2) }}/mo</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('admin.subscription-plans.edit', $plan) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <form method="POST" action="{{ route('admin.subscription-plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Delete this plan?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 ml-2">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

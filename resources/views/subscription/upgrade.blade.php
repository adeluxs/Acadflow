@extends('layouts.app')

@section('title', 'Upgrade Subscription')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Upgrade Your Plan</h1>
        <p class="text-gray-600 mt-2">Choose the plan that fits your needs</p>
    </div>

    @if($currentPlan)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-blue-900">Current Plan: <strong>{{ $currentPlan->display_name }}</strong></p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($plans as $plan)
            <div class="bg-white rounded-lg shadow {{ $currentPlan && $currentPlan->id === $plan->id ? 'ring-2 ring-blue-600' : '' }}">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-900">{{ $plan->display_name }}</h3>
                    <p class="text-gray-600 text-sm mt-1">{{ $plan->description }}</p>
                    <div class="mt-4">
                        <span class="text-3xl font-bold text-gray-900">${{ number_format($plan->price_per_month, 2) }}</span>
                        <span class="text-gray-600">/month</span>
                    </div>
                </div>

                <div class="p-6">
                    <ul class="space-y-3 mb-6">
                        @if($plan->max_courses)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">{{ $plan->max_courses }} Courses</span>
                            </li>
                        @else
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">Unlimited Courses</span>
                            </li>
                        @endif
                        @if($plan->max_storage_gb)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">{{ $plan->max_storage_gb }}GB Storage</span>
                            </li>
                        @endif
                        @if($plan->allow_group_submissions)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">Group Submissions</span>
                            </li>
                        @endif
                        @if($plan->allow_rubrics)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">Rubrics</span>
                            </li>
                        @endif
                        @if($plan->allow_document_generation)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">Document Generation</span>
                            </li>
                        @endif
                        @if($plan->allow_api_access)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-gray-700">API Access</span>
                            </li>
                        @endif
                    </ul>

                    @if($currentPlan && $currentPlan->id === $plan->id)
                        <button disabled class="w-full px-6 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed font-semibold">
                            Current Plan
                        </button>
                    @else
                        <form action="{{ route('subscription.process-upgrade') }}" method="POST">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                                Select Plan
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

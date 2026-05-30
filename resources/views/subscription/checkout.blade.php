@extends('layouts.app')

@section('title', 'Checkout - ' . $plan->display_name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <a href="{{ route('subscription.upgrade') }}" class="text-indigo-600 hover:underline flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Plans
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Plan Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h2 class="text-xl font-bold text-gray-900 mb-4">{{ $plan->display_name }}</h2>
                
                @if($plan->trial_days > 0)
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-green-800">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            {{ $plan->trial_days }} day free trial
                        </p>
                    </div>
                @endif

                <div class="mb-6">
                    <span class="text-4xl font-bold text-gray-900">${{ number_format($amount, 2) }}</span>
                    <span class="text-gray-600">/{{ $billingCycle }}</span>
                </div>

                <ul class="space-y-3 mb-6">
                    @if($plan->max_courses)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">{{ $plan->max_courses }} Courses</span>
                        </li>
                    @endif
                    @if($plan->max_storage_gb)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">{{ $plan->max_storage_gb }}GB Storage</span>
                        </li>
                    @endif
                    @if($plan->allow_group_submissions)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Group Submissions</span>
                        </li>
                    @endif
                    @if($plan->allow_rubrics)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Rubrics</span>
                        </li>
                    @endif
                    @if($plan->allow_document_generation)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Document Generation</span>
                        </li>
                    @endif
                    @if($plan->priority_support)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Priority Support</span>
                        </li>
                    @endif
                </ul>

                @if($plan->is_recommended)
                    <div class="mb-4">
                        <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-3 py-1 rounded-full">
                            Most Popular
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Complete Your Purchase</h2>

                @if(session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('subscription.initiate-payment', $plan->id) }}">
                    @csrf

                    <input type="hidden" name="billing_cycle" value="{{ $billingCycle }}">

                    <!-- Billing Cycle -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Billing Cycle</label>
                        <div class="flex gap-4">
                            @foreach(['monthly' => 'Monthly', 'semester' => 'Semester (4 months)', 'yearly' => 'Yearly'] as $cycle => $label)
                                <label class="flex items-center">
                                    <input type="radio" name="billing_cycle" value="{{ $cycle }}" 
                                           {{ $billingCycle === $cycle ? 'checked' : '' }}
                                           class="mr-2 text-indigo-600" {{ $plan->available_billing_cycles && !in_array($cycle, $plan->available_billing_cycles ?? []) ? 'disabled' : '' }}>
                                    <span class="text-sm {{ $plan->available_billing_cycles && !in_array($cycle, $plan->available_billing_cycles ?? []) ? 'text-gray-400' : '' }}">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Price Summary -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Plan Price</span>
                            <span class="font-semibold">${{ number_format($amount, 2) }}</span>
                        </div>
                        @if($plan->trial_days > 0)
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-600">Trial Period</span>
                                <span class="text-green-600">Free ({{ $plan->trial_days }} days)</span>
                            </div>
                        @endif
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between items-center">
                                <span class="font-bold">Total</span>
                                <span class="font-bold text-xl text-indigo-600">${{ number_format($amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Gateway -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <select name="gateway" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($gateways as $gateway)
                                <option value="{{ $gateway['code'] }}" {{ !$gateway['is_configured'] ? 'disabled' : '' }}>
                                    {{ $gateway['name'] }} {{ !$gateway['is_configured'] ? '(Not Configured)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Coupon -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Coupon Code (Optional)</label>
                        <input type="text" name="coupon_code" placeholder="Enter coupon code" 
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <!-- Terms -->
                    <div class="mb-6">
                        <label class="flex items-start">
                            <input type="checkbox" name="terms" required class="mt-1 text-indigo-600">
                            <span class="ml-2 text-sm text-gray-600">
                                I agree to the 
                                @if($plan->cancellation_minimum_days > 0)
                                    {{ $plan->cancellation_minimum_days }} day cancellation minimum and
                                @endif
                                 terms of service
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                        Proceed to Payment
                    </button>
                </form>

                <!-- Trust Indicators -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-center gap-4 text-sm text-gray-500">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="256" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Secure Payment
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="256" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            256-bit Encryption
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="256" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                            Money Back Guarantee
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Frequently Asked Questions</h3>
                <div class="space-y-4">
                    <div>
                        <p class="font-medium text-gray-900">Can I cancel anytime?</p>
                        <p class="text-sm text-gray-600">{{ $plan->can_downgrade ? 'Yes, you can downgrade or cancel anytime. You will continue to have access until the end of your billing period.' : 'Cancellations require ' . $plan->cancellation_minimum_days . ' days notice.' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">What happens after my trial ends?</p>
                        <p class="text-sm text-gray-600">You will be automatically charged the subscription amount unless you cancel before the trial ends.</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Is my payment secure?</p>
                        <p class="text-sm text-gray-600">Yes, all payments are processed through secure, PCI-compliant payment gateways.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

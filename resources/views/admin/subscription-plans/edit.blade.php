@extends('layouts.app')

@section('title', 'Edit Subscription Plan')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="mb-8">
        <a href="{{ route('admin.subscription-plans') }}" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Plans
        </a>
        <h1 class="text-3xl font-bold">Edit {{ $subscriptionPlan->display_name }}</h1>
        <p class="text-gray-600 mt-2">Update plan details, pricing, and features.</p>
    </div>

    <form method="POST" action="{{ route('admin.subscription-plans.update', $subscriptionPlan) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan Type *</label>
                    <select name="plan_type" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach($planTypes as $value => $label)
                            <option value="{{ $value }}" {{ $subscriptionPlan->plan_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                    <input type="text" name="display_name" value="{{ old('display_name', $subscriptionPlan->display_name) }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $subscriptionPlan->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle *</label>
                    <select name="billing_cycle" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach($billingCycles as $value => $label)
                            <option value="{{ $value }}" {{ $subscriptionPlan->billing_cycle === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Available Billing Cycles</label>
                    <select name="available_billing_cycles[]" multiple class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($billingCycles as $value => $label)
                            <option value="{{ $value }}" {{ in_array($value, old('available_billing_cycles', $subscriptionPlan->available_billing_cycles ?? [$subscriptionPlan->billing_cycle])) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $subscriptionPlan->sort_order) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="is_recommended" value="1" {{ $subscriptionPlan->is_recommended ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded" id="is_recommended">
                <label for="is_recommended" class="ml-2 text-sm text-gray-700">Mark as Recommended Plan</label>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="is_active" value="1" {{ $subscriptionPlan->is_active ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded" id="is_active">
                <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Pricing</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Price ($)</label>
                    <input type="number" name="price_per_month" step="0.01" min="0" value="{{ old('price_per_month', $subscriptionPlan->price_per_month) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester Price ($)</label>
                    <input type="number" name="price_per_semester" step="0.01" min="0" value="{{ old('price_per_semester', $subscriptionPlan->price_per_semester) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Optional (defaults to 4x monthly)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Yearly Price ($)</label>
                    <input type="number" name="price_per_year" step="0.01" min="0" value="{{ old('price_per_year', $subscriptionPlan->price_per_year) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Optional (defaults to 10x monthly)</p>
                </div>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="custom_pricing" value="1" {{ $subscriptionPlan->custom_pricing ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded" id="custom_pricing">
                <label for="custom_pricing" class="ml-2 text-sm text-gray-700">Allow custom pricing (contact sales)</label>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pricing Note</label>
                <textarea name="pricing_note" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('pricing_note', $subscriptionPlan->pricing_note) }}</textarea>
            </div>
        </div>

        <!-- Trial Settings -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Trial Settings</h2>
            
            <div class="flex items-center mb-4">
                <input type="checkbox" name="has_trial" value="1" {{ $subscriptionPlan->has_trial ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded" id="has_trial">
                <label for="has_trial" class="ml-2 text-sm text-gray-700">Offer free trial</label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trial Days</label>
                    <input type="number" name="trial_days" value="{{ old('trial_days', $subscriptionPlan->trial_days) }}" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Limits -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Limits & Restrictions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Courses</label>
                    <input type="number" name="max_courses" min="0" value="{{ old('max_courses', $subscriptionPlan->max_courses) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Students Per Course</label>
                    <input type="number" name="max_students_per_course" min="0" value="{{ old('max_students_per_course', $subscriptionPlan->max_students_per_course) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Storage (GB)</label>
                    <input type="number" name="max_storage_gb" min="0" value="{{ old('max_storage_gb', $subscriptionPlan->max_storage_gb) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max File Upload Size (MB)</label>
                    <input type="number" name="max_file_upload_size_mb" value="{{ old('max_file_upload_size_mb', $subscriptionPlan->max_file_upload_size_mb) }}" min="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Seats (B2B)</label>
                    <input type="number" name="min_seats" min="1" value="{{ old('min_seats', $subscriptionPlan->min_seats) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Seats (B2B)</label>
                    <input type="number" name="max_seats" min="1" value="{{ old('max_seats', $subscriptionPlan->max_seats) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Administrators</label>
                    <input type="number" name="max_administrators" min="0" value="{{ old('max_administrators', $subscriptionPlan->max_administrators) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cancellation Minimum Days</label>
                    <input type="number" name="cancellation_minimum_days" value="{{ old('cancellation_minimum_days', $subscriptionPlan->cancellation_minimum_days) }}" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Period (days)</label>
                    <input type="number" name="refund_period_days" value="{{ old('refund_period_days', $subscriptionPlan->refund_period_days) }}" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-6">
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_group_submissions" value="1" {{ $subscriptionPlan->allow_group_submissions ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Group Submissions</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_rubrics" value="1" {{ $subscriptionPlan->allow_rubrics ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Rubrics</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_attendance_tracking" value="1" {{ $subscriptionPlan->allow_attendance_tracking ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Attendance Tracking</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_document_generation" value="1" {{ $subscriptionPlan->allow_document_generation ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Document Generation</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_api_access" value="1" {{ $subscriptionPlan->allow_api_access ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">API Access</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_white_label" value="1" {{ $subscriptionPlan->allow_white_label ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">White Label</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="priority_support" value="1" {{ $subscriptionPlan->priority_support ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Priority Support</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="dedicated_account_manager" value="1" {{ $subscriptionPlan->dedicated_account_manager ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Dedicated Account Manager</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="sso_enabled" value="1" {{ $subscriptionPlan->sso_enabled ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">SSO Enabled</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="custom_branding" value="1" {{ $subscriptionPlan->custom_branding ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Custom Branding</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="refundable" value="1" {{ $subscriptionPlan->refundable ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Refundable</span>
                </label>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="can_upgrade" value="1" {{ $subscriptionPlan->can_upgrade ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Allow Upgrades</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="can_downgrade" value="1" {{ $subscriptionPlan->can_downgrade ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Allow Downgrades</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="prorated_upgrades" value="1" {{ $subscriptionPlan->prorated_upgrades ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Prorated Upgrades</span>
                </label>
            </div>
        </div>

        <!-- Features -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Features (JSON)</label>
                    <textarea name="features" rows="6" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">{{ old('features', $subscriptionPlan->features ? json_encode($subscriptionPlan->features) : '[]') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Array of feature identifiers</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Limits (JSON)</label>
                    <textarea name="limits" rows="6" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">{{ old('limits', $subscriptionPlan->limits ? json_encode($subscriptionPlan->limits) : '{}') }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Custom key-value limits</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.subscription-plans') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Update Plan
            </button>
        </div>
    </form>
</div>
@endsection

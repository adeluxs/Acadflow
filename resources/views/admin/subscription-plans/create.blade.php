@extends('layouts.app')

@section('title', 'Create Subscription Plan')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="mb-8">
        <a href="{{ route('admin.subscription-plans') }}" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Plans
        </a>
        <h1 class="text-3xl font-bold">Create New Subscription Plan</h1>
        <p class="text-gray-600 mt-2">Define a new subscription tier with pricing, limits, and features.</p>
    </div>

    <form method="POST" action="{{ route('admin.subscription-plans.store') }}" class="space-y-6">
        @csrf

        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan Type *</label>
                    <select name="plan_type" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach($planTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan Name *</label>
                    <input type="text" name="name" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., basic, pro, enterprise" required>
                    <p class="text-xs text-gray-500 mt-1">Internal identifier (lowercase, no spaces)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                    <input type="text" name="display_name" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., Basic Plan, Pro Plan" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Brief description of what this plan offers..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle *</label>
                    <select name="billing_cycle" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach($billingCycles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Available Billing Cycles</label>
                    <select name="available_billing_cycles[]" multiple class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($billingCycles as $value => $label)
                            <option value="{{ $value }}" {{ $value === 'monthly' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                </div>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="is_recommended" value="1" class="h-4 w-4 text-indigo-600 rounded" id="is_recommended">
                <label for="is_recommended" class="ml-2 text-sm text-gray-700">Mark as Recommended Plan</label>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 text-indigo-600 rounded" id="is_active">
                <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Pricing</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Price ($)</label>
                    <input type="number" name="price_per_month" step="0.01" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester Price ($)</label>
                    <input type="number" name="price_per_semester" step="0.01" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Optional (defaults to 4x monthly)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Yearly Price ($)</label>
                    <input type="number" name="price_per_year" step="0.01" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Optional (defaults to 10x monthly)</p>
                </div>
            </div>

            <div class="mt-4 flex items-center">
                <input type="checkbox" name="custom_pricing" value="1" class="h-4 w-4 text-indigo-600 rounded" id="custom_pricing">
                <label for="custom_pricing" class="ml-2 text-sm text-gray-700">Allow custom pricing (contact sales)</label>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pricing Note</label>
                <textarea name="pricing_note" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                          placeholder="Special pricing information..."></textarea>
            </div>
        </div>

        <!-- Trial Settings -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Trial Settings</h2>
            
            <div class="flex items-center mb-4">
                <input type="checkbox" name="has_trial" value="1" class="h-4 w-4 text-indigo-600 rounded" id="has_trial">
                <label for="has_trial" class="ml-2 text-sm text-gray-700">Offer free trial</label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trial Days</label>
                    <input type="number" name="trial_days" value="0" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Limits -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Limits & Restrictions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Courses</label>
                    <input type="number" name="max_courses" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Students Per Course</label>
                    <input type="number" name="max_students_per_course" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Storage (GB)</label>
                    <input type="number" name="max_storage_gb" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max File Upload Size (MB)</label>
                    <input type="number" name="max_file_upload_size_mb" value="50" min="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Seats (B2B)</label>
                    <input type="number" name="min_seats" min="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Seats (B2B)</label>
                    <input type="number" name="max_seats" min="1" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Administrators</label>
                    <input type="number" name="max_administrators" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cancellation Minimum Days</label>
                    <input type="number" name="cancellation_minimum_days" value="0" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Period (days)</label>
                    <input type="number" name="refund_period_days" value="0" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-6">
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_group_submissions" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Group Submissions</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_rubrics" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Rubrics</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_attendance_tracking" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Attendance Tracking</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_document_generation" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Document Generation</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_api_access" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">API Access</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="allow_white_label" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">White Label</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="priority_support" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Priority Support</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="dedicated_account_manager" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Dedicated Account Manager</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="sso_enabled" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">SSO Enabled</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="custom_branding" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Custom Branding</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="refundable" value="1" class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Refundable</span>
                </label>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="can_upgrade" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Allow Upgrades</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="can_downgrade" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2 text-sm">Allow Downgrades</span>
                </label>

                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="prorated_upgrades" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
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
                    <textarea name="features" rows="6" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                              placeholder='["feature1", "feature2", "feature3"]'>[]</textarea>
                    <p class="text-xs text-gray-500 mt-1">Array of feature identifiers</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Limits (JSON)</label>
                    <textarea name="limits" rows="6" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                              placeholder='{"key": "value"}'>{}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Custom key-value limits</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.subscription-plans') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Create Plan
            </button>
        </div>
    </form>
</div>
@endsection

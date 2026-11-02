@extends('layouts.app')

@section('title', 'Create Payment Gateway')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <a href="{{ route('admin.payment-gateways.index') }}" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Gateways
        </a>
        <h1 class="text-3xl font-bold">Add Payment Gateway</h1>
        <p class="text-gray-600 mt-2">Configure a payment gateway for wallet funding, marketplace payments and institutional billing.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.payment-gateways.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Code *</label>
                    <input type="text" name="code" value="{{ old('code') }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., paystack" required>
                    <p class="text-xs text-gray-500 mt-1">Unique identifier (lowercase, no spaces)</p>
                    @error('code')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., Paystack" required>
                    @error('name')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="Brief description of the gateway">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                    <select name="is_test_mode" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1">Test Mode (Sandbox)</option>
                        <option value="0">Live Mode (Production)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Test with dummy transactions before going live</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                </div>

                <p class="md:col-span-2 text-sm text-gray-600">The gateway starts inactive. Configure credentials and activate it on the next page.</p>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <a href="{{ route('admin.payment-gateways.index') }}" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Create Gateway
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

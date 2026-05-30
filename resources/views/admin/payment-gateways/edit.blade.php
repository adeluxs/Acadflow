@extends('layouts.app')

@section('title', 'Configure ' . $paymentGateway->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <a href="{{ route('admin.payment-gateways.index') }}" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Gateways
        </a>
        <h1 class="text-3xl font-bold">Configure {{ $paymentGateway->name }}</h1>
        <p class="text-gray-600 mt-2">Set up credentials and configuration for {{ $paymentGateway->name }}.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.payment-gateways.update', $paymentGateway) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Code</label>
                    <input type="text" value="{{ $paymentGateway->code }}" disabled 
                           class="w-full border-gray-200 bg-gray-50 rounded-lg shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name</label>
                    <input type="text" value="{{ $paymentGateway->name }}" disabled 
                           class="w-full border-gray-200 bg-gray-50 rounded-lg shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                    <select name="is_test_mode" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1" {{ $paymentGateway->is_test_mode ? 'selected' : '' }}>Test Mode (Sandbox)</option>
                        <option value="0" {{ !$paymentGateway->is_test_mode ? 'selected' : '' }}>Live Mode (Production)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $paymentGateway->sort_order) }}" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ $paymentGateway->is_active ? 'checked' : '' }} 
                               class="h-4 w-4 text-indigo-600 rounded">
                        <span class="ml-2 text-sm text-gray-700">Activate gateway</span>
                        <p class="text-xs text-gray-500 ml-2">Enable this gateway for use in subscription billing</p>
                    </label>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t">
                <h3 class="text-lg font-bold mb-4">API Credentials</h3>
                <p class="text-sm text-gray-600 mb-4">These credentials are encrypted and stored securely.</p>
                
                @php
                    $gatewayManager = app(\App\Services\PaymentGateway\PaymentGatewayManager::class);
                    $gateway = $gatewayManager->gateway($paymentGateway->code);
                    $configFields = $gateway->getConfigFields();
                    $credentials = $paymentGateway->credentials ?? [];
                @endphp

                @foreach($configFields as $fieldKey => $field)
                    @if($field['type'] === 'password')
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field['label'] }}</label>
                            <input type="password" name="{{ $fieldKey }}" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="{{ $field['placeholder'] ?? '' }}"
                                   value="">
                            @if(isset($field['hint']))
                                <p class="text-xs text-gray-500 mt-1">{{ $field['hint'] }}</p>
                            @endif
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current value</p>
                        </div>
                    @elseif($field['type'] === 'select')
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field['label'] }}</label>
                            <select name="{{ $fieldKey }}" 
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($field['options'] as $value => $label)
                                    <option value="{{ $value }}" {{ (old($fieldKey, $paymentGateway->settings[$fieldKey] ?? $field['default'] ?? null) == $value) ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($field['type'] === 'multiselect')
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field['label'] }}</label>
                            <select name="{{ $fieldKey }}[]" multiple 
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-32">
                                @foreach($field['options'] as $value => $label)
                                    <option value="{{ $value }}" 
                                            {{ in_array($value, old($fieldKey, $paymentGateway->settings[$fieldKey] ?? $field['default'] ?? [])) ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-6 pt-6 border-t">
                <h3 class="text-lg font-bold mb-4">Test Connection</h3>
                <p class="text-sm text-gray-600 mb-4">Verify that the gateway is configured correctly.</p>
                <button type="button" onclick="testGatewayConnection('{{ $paymentGateway->id }}')" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Test Connection
                </button>
                <div id="connection-test-result" class="mt-3"></div>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <a href="{{ route('admin.payment-gateways.index') }}" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function testGatewayConnection(gatewayId) {
    const resultDiv = document.getElementById('connection-test-result');
    resultDiv.innerHTML = '<div class="text-blue-600">Testing connection...</div>';
    
    fetch(`/admin/payment-gateways/${gatewayId}/test`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                resultDiv.innerHTML = '<div class="text-green-600 mt-2">✓ Connection successful! Gateway is configured correctly.</div>';
            } else {
                resultDiv.innerHTML = '<div class="text-red-600 mt-2">✗ Connection failed: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="text-red-600 mt-2">✗ Error testing connection: ' + error + '</div>';
        });
}
</script>
@endsection

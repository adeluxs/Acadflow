@extends('layouts.app')

@section('title', auth()->user()->isSuperAdmin() ? 'System Settings' : 'Institution Settings')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ auth()->user()->isSuperAdmin() ? 'System Settings' : 'Institution Settings' }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ auth()->user()->isSuperAdmin() ? 'Manage platform-wide defaults and infrastructure controls.' : 'Manage settings for your institution. Platform-only controls remain protected.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
            @if(auth()->user()->hasPermission(\App\Enums\Permission::MANAGE_AI_SETTINGS))
                <a href="{{ route('ai.settings') }}" class="acad-primary-button inline-flex items-center rounded-lg px-3 py-2 text-xs font-semibold">AI Settings</a>
            @endif
            @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.settings.features') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Feature & Module Management</a>
                <a href="{{ route('admin.settings.permissions') }}" class="acad-link">Permission Management</a>
                <a href="{{ route('admin.settings.audit-logs') }}" class="acad-link">Audit Logs</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Settings Navigation Tabs -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2">
            @foreach($settingGroups as $groupKey => $groupInfo)
                <a href="#{{ $groupKey }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ $loop->first ? 'text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                   @if($loop->first) style="background-color: var(--acad-primary)" @endif">
                    {{ $groupInfo['name'] }}
                </a>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <!-- General Settings -->
        @if(isset($settings['general']))
        <div id="general" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">General Settings</h2>
                <p class="text-sm text-gray-600">Platform name, branding, timezone, and basic configuration</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['general'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Academic Settings -->
        @if(isset($settings['academic']))
        <div id="academic" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Academic Settings</h2>
                <p class="text-sm text-gray-600">Semesters, submission rules, grading policies</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['academic'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Notification Settings -->
        @if(isset($settings['notification']))
        <div id="notification" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Notification Settings</h2>
                <p class="text-sm text-gray-600">Channels, templates, reminders, announcements</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['notification'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Subscription Settings -->
        @if(isset($settings['subscription']))
        <div id="subscription" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Subscription Settings</h2>
                <p class="text-sm text-gray-600">Billing, trials, plan rules</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['subscription'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Security Settings -->
        @if(isset($settings['security']))
        <div id="security" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Security Settings</h2>
                <p class="text-sm text-gray-600">Passwords, sessions, 2FA, audit logs</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['security'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- PWA Settings -->
        @if(isset($settings['pwa']))
        <div id="pwa" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">PWA Settings</h2>
                <p class="text-sm text-gray-600">Offline mode, caching, sync behavior</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['pwa'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Storage Settings -->
        @if(isset($settings['storage']))
        <div id="storage" class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Storage Settings</h2>
                <p class="text-sm text-gray-600">File uploads, retention, archives</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($settings['storage'] as $setting)
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                {{ \Str::title(str_replace('_', ' ', $setting->key)) }}
                            </label>
                            @include('settings.partials.field', ['setting' => $setting])
                            @if($setting->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $setting->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="acad-primary-button px-6 py-3 rounded-lg font-medium">
                Save All Settings
            </button>
        </div>
    </form>

    @if(auth()->user()->isSuperAdmin())
    <div class="mb-6 overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-violet-50 shadow-sm">
        <div class="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Release control</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Feature & Module Management</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Runtime availability is controlled from one centralized page. Configuration such as AI provider keys or notification channel preferences remains in its specialist settings area.</p>
            </div>
            <a href="{{ route('admin.settings.features') }}" class="acad-primary-button inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold">Manage feature states →</a>
        </div>
    </div>

    <!-- Payment Gateway Settings -->
    <div id="payment-gateways" class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">Payment Gateways</h2>
                <p class="text-sm text-gray-600">Configure payment providers for subscription billing</p>
            </div>
            <a href="{{ route('admin.payment-gateways.create') }}" 
               class="acad-primary-button px-4 py-2 rounded text-sm">
               + Add Gateway
            </a>
        </div>
        <div class="p-6">
            @if($paymentGateways->isEmpty())
                <p class="text-gray-500 text-center py-8">No payment gateways configured. Add one to start accepting payments.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($paymentGateways as $gateway)
                        <div class="border rounded-lg p-4 {{ $gateway->is_active ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' }}">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ $gateway->name }}</h3>
                                    <p class="text-xs text-gray-500">{{ $gateway->description ?? $gateway->code }}</p>
                                </div>
                                @if($gateway->is_test_mode)
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">Test</span>
                                @endif
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center text-sm">
                                    <span class="w-20 text-gray-500">Status:</span>
                                    <span class="px-2 py-0.5 rounded text-xs {{ $gateway->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $gateway->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-20 text-gray-500">Configured:</span>
                                    <span class="text-sm {{ $gateway->credentials ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $gateway->credentials ? '✓' : '✗' }}
                                    </span>
                                </div>
                                @if($gateway->transactions_count > 0)
                                    <div class="flex items-center text-sm">
                                        <span class="w-20 text-gray-500">Transactions:</span>
                                        <span class="text-sm text-gray-700">{{ $gateway->transactions_count }}</span>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="{{ route('admin.payment-gateways.edit', $gateway) }}" 
                                   class="flex-1 text-center px-3 py-1.5 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50">
                                    Edit
                                </a>
                                @if(!$gateway->is_active)
                                    <form method="POST" action="{{ route('admin.payment-gateways.destroy', $gateway) }}" 
                                          onsubmit="return confirm('Delete this gateway?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Subscription Plans -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">Subscription Plans</h2>
                <p class="text-sm text-gray-600">Manage subscription tiers and pricing</p>
            </div>
            <a href="{{ route('admin.subscription-plans.create') }}" 
               class="acad-primary-button px-4 py-2 rounded text-sm">
               + Add Plan
            </a>
        </div>
        <div class="p-6">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm">Plan Name</th>
                        <th class="px-4 py-2 text-left text-sm">Type</th>
                        <th class="px-4 py-2 text-left text-sm">Price</th>
                        <th class="px-4 py-2 text-left text-sm">Max Courses</th>
                        <th class="px-4 py-2 text-left text-sm">Max Storage</th>
                        <th class="px-4 py-2 text-left text-sm">Status</th>
                        <th class="px-4 py-2 text-left text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscriptionPlans as $plan)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">
                                {{ $plan->display_name }}
                                @if($plan->is_recommended)
                                    <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded">Recommended</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs {{ $plan->plan_type === 'b2b' ? 'bg-purple-100 text-purple-800' : ($plan->plan_type === 'free' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ strtoupper($plan->plan_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">${{ number_format($plan->price_per_month, 2) }}/mo</td>
                            <td class="px-4 py-3">{{ $plan->max_courses ?? 'Unlimited' }}</td>
                            <td class="px-4 py-3">{{ $plan->max_storage_gb ?? 'Unlimited' }} GB</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.subscription-plans.edit', $plan) }}" class="acad-link text-sm">Edit</a>
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

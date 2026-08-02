@extends('layouts.app')

@section('title', 'Maintenance Mode')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
            <svg class="w-16 h-16 mx-auto text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Under Maintenance</h1>
        
        <p class="text-gray-600 mb-6">
            We're currently performing scheduled maintenance. Please check back soon.
        </p>
        
        <p class="text-sm text-gray-500">
            Contact <a href="mailto:{{ \App\Services\SettingService::get('support_email', 'support@example.com') }}" class="text-blue-600 hover:underline">
                {{ \App\Services\SettingService::get('support_email', 'support') }}
            </a> if you need immediate assistance.
        </p>
    </div>
</div>
@endsection

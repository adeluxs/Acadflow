@extends('layouts.app')

@section('title', 'Notification Settings')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Notification Settings</h1>
        <p class="text-gray-600 mt-2">Control how you receive notifications</p>
    </div>

    <section data-browser-push class="mb-6 rounded-xl border bg-white p-6">
        <h2 class="text-xl font-bold">Notifications on this browser</h2>
        <p data-push-status role="status" aria-live="polite" class="mt-2 text-sm text-gray-600">Checking browser notification availability…</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" data-push-enable disabled class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Enable this browser</button>
            <button type="button" data-push-disable disabled class="rounded-lg border px-4 py-2 disabled:opacity-50">Disable this browser</button>
            <button type="button" data-push-test disabled class="rounded-lg border px-4 py-2 disabled:opacity-50">Send test notification</button>
        </div>
        <p class="mt-3 text-xs text-gray-500">Keep Push Notifications enabled in your preferences below. Browser permission and system notification settings also control what appears.</p>
    </section>

    <form action="{{ route('notifications.settings.update') }}" method="POST" class="bg-white rounded-lg shadow p-8">
        @csrf
        @method('PUT')

        <!-- Global Channel Toggles -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Notification Channels</h3>
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Email Notifications</span>
                        <p class="text-sm text-gray-600">Receive notifications via email</p>
                    </div>
                    <input type="hidden" name="email_enabled" value="0">
                    <input type="checkbox" name="email_enabled" value="1" 
                           {{ $settings->email_enabled ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Push Notifications</span>
                        <p class="text-sm text-gray-600">Receive browser push notifications</p>
                    </div>
                    <input type="hidden" name="push_enabled" value="0">
                    <input type="checkbox" name="push_enabled" value="1" 
                           {{ $settings->push_enabled ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
        </div>

        <!-- Per-Type Toggles -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Notification Types</h3>
            <p class="text-sm text-gray-600 mb-4">Choose which types of notifications you want to receive.</p>
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Submission Notifications</span>
                        <p class="text-sm text-gray-600">New submissions, corrections, approvals</p>
                    </div>
                    <input type="hidden" name="submission_notifications" value="0">
                    <input type="checkbox" name="submission_notifications" value="1" 
                           {{ $settings->submission_notifications ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Grade Notifications</span>
                        <p class="text-sm text-gray-600">When grades are posted</p>
                    </div>
                    <input type="hidden" name="grade_notifications" value="0">
                    <input type="checkbox" name="grade_notifications" value="1" 
                           {{ $settings->grade_notifications ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Attendance Notifications</span>
                        <p class="text-sm text-gray-600">Attendance session alerts</p>
                    </div>
                    <input type="hidden" name="attendance_notifications" value="0">
                    <input type="checkbox" name="attendance_notifications" value="1" 
                           {{ $settings->attendance_notifications ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Billing Notifications</span>
                        <p class="text-sm text-gray-600">Payment confirmations, overdue alerts</p>
                    </div>
                    <input type="hidden" name="billing_notifications" value="0">
                    <input type="checkbox" name="billing_notifications" value="1" 
                           {{ $settings->billing_notifications ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
        </div>

        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection

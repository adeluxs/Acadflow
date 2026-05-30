@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">System Settings</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="#">
            @csrf
            
            <div class="mb-6">
                <h2 class="text-lg font-bold mb-4">General Settings</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Application Name</label>
                        <input type="text" class="w-full px-3 py-2 border rounded" value="UniAcademic">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Timezone</label>
                        <select class="w-full px-3 py-2 border rounded">
                            <option>Africa/Lagos</option>
                            <option>Africa/Johannesburg</option>
                            <option>Africa/Nairobi</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-bold mb-4">Session Settings</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Session Lifetime (minutes)</label>
                        <input type="number" class="w-full px-3 py-2 border rounded" value="120">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Remember Me Duration (minutes)</label>
                        <input type="number" class="w-full px-3 py-2 border rounded" value="20160">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-bold mb-4">Attendance Settings</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Default QR Expiry (minutes)</label>
                        <input type="number" class="w-full px-3 py-2 border rounded" value="30">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Geofence Radius (meters)</label>
                        <input type="number" class="w-full px-3 py-2 border rounded" value="100">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Late Threshold (minutes)</label>
                        <input type="number" class="w-full px-3 py-2 border rounded" value="15">
                    </div>
                </div>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                Save Settings
            </button>
        </form>
    </div>
</div>
@endsection
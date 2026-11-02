@extends('layouts.app')

@section('title', 'Permission Management')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Permission Management</h1>
        <a href="{{ route('admin.settings') }}" class="text-blue-600 hover:underline text-sm">Back to Settings</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-bold">Role-Based Permissions</h2>
            <p class="text-sm text-gray-600">View and manage permissions for each role</p>
        </div>
        <div class="p-6">
            <!-- Role Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    @foreach($roles as $roleKey => $roleData)
                        <a href="#role-{{ $roleKey }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                                  {{ $loop->first ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ ucfirst(str_replace('_', ' ', $roleKey)) }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Permissions by Role -->
            @foreach($roles as $roleKey => $roleData)
                <div id="role-{{ $roleKey }}" class="mb-8">
                    <h3 class="text-md font-bold mb-4 pb-2 border-b">{{ ucfirst(str_replace('_', ' ', $roleKey)) }} Permissions</h3>
                    
                    @foreach($roleData['groups'] as $groupName => $permissions)
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ $groupName }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($permissions as $permission)
                                    <div class="flex items-center p-3 border rounded hover:bg-gray-50">
                                        <input type="checkbox" 
                                               id="perm_{{ $roleKey }}_{{ $permission->value }}"
                                               name="permissions[{{ $roleKey }}][]"
                                               value="{{ $permission->value }}"
                                               {{ in_array($permission, $roleData['assigned']) ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="perm_{{ $roleKey }}_{{ $permission->value }}" class="ml-2 block text-sm text-gray-700">
                                            {{ ucfirst(str_replace('_', ' ', $permission->value)) }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-bold">Permission Descriptions</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($allPermissions as $permission)
                    <div class="p-3 border rounded">
                        <p class="font-medium text-sm">{{ str_replace('_', ' ', $permission->value) }}</p>
                        <p class="text-xs text-gray-500">{{ $permission->value }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    document.querySelectorAll('a[href^="#role-"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
@endsection
@endsection

{{-- resources/views/partials/sidebar.blade.php --}}
@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $role = $user?->role;

    $menu = match ($role) {
        UserRole::SUPER_ADMIN->value => [
            ['label' => 'Universities', 'route' => 'admin.universities', 'icon' => 'building'],
            ['label' => 'System Settings', 'route' => 'admin.settings', 'icon' => 'settings'],
            ['label' => 'AI Assistant', 'route' => 'ai.settings', 'icon' => 'settings'],
            ['label' => 'AI Analytics', 'route' => 'ai.analytics', 'icon' => 'chart'],
            ['label' => 'Subscriptions', 'route' => 'admin.subscriptions', 'icon' => 'wallet'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart'],
        ],

        UserRole::UNIVERSITY_ADMIN->value => [
            ['label' => 'Faculties', 'route' => 'admin.faculties', 'icon' => 'building'],
            ['label' => 'Courses', 'route' => 'admin.courses', 'icon' => 'book'],
            ['label' => 'AI Assistant', 'route' => 'ai.settings', 'icon' => 'settings'],
            ['label' => 'AI Analytics', 'route' => 'ai.analytics', 'icon' => 'chart'],
            ['label' => 'Subscriptions', 'route' => 'admin.subscriptions', 'icon' => 'wallet'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart'],
        ],

        UserRole::DEPARTMENT_ADMIN->value => [
            ['label' => 'Department', 'route' => 'admin.department', 'icon' => 'building'],
            ['label' => 'Courses', 'route' => 'admin.courses', 'icon' => 'book'],
            ['label' => 'AI Assistant', 'route' => 'ai.settings', 'icon' => 'settings'],
            ['label' => 'AI Analytics', 'route' => 'ai.analytics', 'icon' => 'chart'],
            ['label' => 'Billing', 'route' => 'admin.billing', 'icon' => 'wallet'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart'],
        ],

        UserRole::LECTURER->value => [
            ['label' => 'My Courses', 'route' => 'lecturer.courses', 'icon' => 'book'],
            ['label' => 'Assignments', 'route' => 'submissions.lecturer-index', 'icon' => 'task'],
            ['label' => 'AI Analytics', 'route' => 'ai.analytics', 'icon' => 'chart'],
            ['label' => 'Attendance', 'route' => 'attendance.lecturer', 'icon' => 'calendar'],
            
        ],

        UserRole::STUDENT->value => [
            ['label' => 'My Courses', 'route' => 'courses.index', 'icon' => 'book'],
            ['label' => 'My Groups', 'route' => 'groups.index', 'icon' => 'users'],
            ['label' => 'Assignments', 'route' => 'submissions.dashboard', 'icon' => 'task'],
            ['label' => 'Attendance', 'route' => 'attendance.my', 'icon' => 'calendar'],
        ],

        default => [],
    };

    $isActive = fn ($route) => request()->routeIs($route)
        ? 'bg-blue-50 text-blue-700 border-blue-600'
        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900';

    if (!function_exists('sidebarIcon')) {
    function sidebarIcon($type) {
        return match ($type) {
            'building' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
            'users' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>',
            'book' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 5 7.5 5c1.747 0 3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253"></path></svg>',
            'task' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>',
            'upload' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>',
            'calendar' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
            'chat' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 3.866-4.03 7-9 7a9.93 9.93 0 01-3.14-.512L3 20l1.53-3.855A6.96 6.96 0 013 12c0-3.866 4.03-7 9-7s9 3.134 9 7z"></path></svg>',
            'wallet' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h-3a2 2 0 000 4h3m0-4a2 2 0 110 4"></path></svg>',
            'chart' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6m6 13V4m6 15V10"></path></svg>',
            'settings' => '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924-1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>',
            default => '',
        };
    }
    }
@endphp

<nav class="space-y-2">
    <a href="{{ route('dashboard') }}"
       class="flex items-center gap-3 px-4 py-3 rounded-2xl border-l-4 transition {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700 border-blue-600' : 'text-slate-700 hover:bg-slate-100 border-transparent' }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="font-medium">Dashboard</span>
    </a>

    <div class="pt-4 pb-2">
        <p class="px-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Navigation</p>
    </div>

    @foreach($menu as $item)
        @php
            $params = $item['params'] ?? [];
            $routeName = $item['route'];
        @endphp

        @if(Route::has($routeName))
            <a href="{{ route($routeName, $params) }}"
               class="flex items-center gap-3 px-4 py-3 rounded-2xl border-l-4 transition {{ $isActive($routeName . '*') }}">
                {!! sidebarIcon($item['icon']) !!}
                <span class="font-medium">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach

    <div class="pt-4 pb-2">
        <p class="px-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Account</p>
    </div>

    <a href="{{ route('settings.index') }}"
       class="flex items-center gap-3 px-4 py-3 rounded-2xl border-l-4 transition {{ request()->routeIs('settings.*') ? 'bg-blue-50 text-blue-700 border-blue-600' : 'text-slate-700 hover:bg-slate-100 border-transparent' }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
        </svg>
        <span class="font-medium">Settings</span>
    </a>
</nav>
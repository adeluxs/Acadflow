@php
    $user = auth()->user();
    $isInstitutionalStudent = $user?->isStudent() && $user?->university_id;

    $icons = [
        'home'=>'M3 11.5L12 3l9 8.5 M5 10v11h14V10 M9 21v-7h6v7',
        'book'=>'M5 4h5a3 3 0 013 3v13a3 3 0 00-3-3H5z M19 4h-5a3 3 0 00-3 3v13a3 3 0 013-3h5z',
        'task'=>'M7 4h10v16H7z M9 8h6 M9 12h6 M9 16h4',
        'submit'=>'M12 16V4m0 0L8 8m4-4 4 4 M5 14v6h14v-6',
        'calendar'=>'M8 2v4m8-4v4 M3 10h18 M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z',
        'chat'=>'M4 5h16v12H8l-4 4z M8 9h8 M8 13h5',
        'grade'=>'M4 19V9m5 10V5m5 14v-7m5 7V3',
        'users'=>'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z M22 21v-2a4 4 0 00-3-3.87',
        'ai'=>'M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3z M19 15l.8 1.8L22 18l-2.2 1.2L19 21l-.8-1.8L16 18l2.2-1.2L19 15z',
        'research'=>'M9 3h6m-5 0v6l-5 9a2 2 0 001.7 3h10.6a2 2 0 001.7-3l-5-9V3 M8 14h8',
        'community'=>'M17 20h5v-2a4 4 0 00-4-4h-1 M7 20H2v-2a4 4 0 014-4h1 M12 12a4 4 0 100-8 4 4 0 000 8z',
        'trophy'=>'M8 21h8 M12 17v4 M7 4h10v4a5 5 0 01-10 0V4z M7 6H3v1a4 4 0 004 4 M17 6h4v1a4 4 0 01-4 4',
        'bell'=>'M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M13.7 21a2 2 0 01-3.4 0',
        'profile'=>'M20 21a8 8 0 10-16 0 M12 11a4 4 0 100-8 4 4 0 000 8z',
        'settings'=>'M12 15.5A3.5 3.5 0 1012 8a3.5 3.5 0 000 7.5z M19 12a7 7 0 01-.2 1.7l2 1.5-2 3.5-2.5-1a7 7 0 01-2.9 1.7L13 22H9l-.4-2.6a7 7 0 01-2.9-1.7l-2.5 1-2-3.5 2-1.5A7 7 0 013 12c0-.6.1-1.2.2-1.7l-2-1.5 2-3.5 2.5 1a7 7 0 012.9-1.7L9 2h4l.4 2.6a7 7 0 012.9 1.7l2.5-1 2 3.5-2 1.5c.1.5.2 1.1.2 1.7z',
        'building'=>'M3 21h18 M6 21V3h12v18 M9 7h2m2 0h2M9 11h2m2 0h2M9 15h2m2 0h2',
        'wallet'=>'M3 6h16a2 2 0 012 2v10H5a2 2 0 01-2-2V6z M16 11h5v4h-5a2 2 0 010-4z',
    ];

    $primary = match($user?->role) {
        'lecturer' => [
            ['Courses','lecturer.courses','book'],
            ['Assignments','submissions.lecturer-index','task'],
            ['Attendance','attendance.lecturer','calendar'],
            ['AI Assistant','ai.assistant','ai'],
            ['Research Studio','research.index','research'],
            ['Knowledge Hub','knowledge.index','book'],
            ['Communities','knowledge.communities.index','community'],
            ['Calendar','knowledge.events.index','calendar'],
            ['Notifications','notifications.index','bell'],
        ],
        'student' => [
            ['My Courses','courses.index','book'],
            ['Assignments','submissions.dashboard','task'],
            ['My Submissions','submissions.dashboard','submit'],
            ['Attendance','attendance.my','calendar'],
            ['Groups','groups.index','users'],
            ['AI Assistant','ai.assistant','ai'],
            ['Research Studio','research.index','research'],
            ['Knowledge Hub','knowledge.index','book'],
            ['Calendar','knowledge.events.index','calendar'],
            ['Notifications','notifications.index','bell'],
        ],
        'member' => [
            ['AI Assistant','ai.assistant','ai'],
            ['Knowledge Hub','knowledge.index','book'],
            ['Research Studio','research.index','research'],
            ['Communities','knowledge.communities.index','community'],
            ['Groups','groups.index','users'],
            ['Events','knowledge.events.index','calendar'],
            ['Challenges','knowledge.challenges.index','trophy'],
            ['Notifications','notifications.index','bell'],
        ],
        'university_admin' => [
            ['Faculties','admin.faculties','building'],['Courses','admin.courses','book'],['Users','admin.users','users'],
            ['AI Settings','ai.settings','ai'],['AI Analytics','ai.analytics','grade'],['Monetization','admin.monetization','wallet'],
            ['Reports','admin.reports','grade'],['Notifications','admin.notifications.index','bell'],['Settings','admin.settings','settings'],
        ],
        'department_admin' => [
            ['Department','admin.department','building'],['Courses','admin.courses','book'],['Users','admin.users','users'],
            ['AI Settings','ai.settings','ai'],['AI Analytics','ai.analytics','grade'],['Billing','admin.billing','wallet'],
            ['Reports','admin.reports','grade'],['Notifications','admin.notifications.index','bell'],
        ],
        'super_admin' => [
            ['Institutions','admin.universities','building'],['Users','admin.users','users'],['System Settings','admin.settings','settings'],
            ['AI Settings','ai.settings','ai'],['AI Analytics','ai.analytics','grade'],['Monetization','admin.monetization','wallet'],
            ['Reports','admin.reports','grade'],['Notifications','admin.notifications.index','bell'],['Onboarding','admin.onboarding.index','users'],
        ],
        default => [],
    };

    $secondary = $user?->isAdmin() ? [
        ['Knowledge Hub','knowledge.index','book'],['Research Studio','research.index','research'],['Communities','knowledge.communities.index','community']
    ] : [
        ['Profile','knowledge.creator.edit','profile'],['Preferences','notifications.settings','settings'],['Security','security.show','settings']
    ];

    $render = function(array $items) use ($icons, $user) {
        foreach($items as [$label,$routeName,$icon]) {
            if(!\Illuminate\Support\Facades\Route::has($routeName)) continue;

            $feature = \App\Services\FeatureAccessService::featureForRoute($routeName);
            if ($feature && ! \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $user)) {
                continue;
            }

            $featureStatus = $feature
                ? \App\Services\FeatureAccessService::effectiveStatus($feature, $user?->university_id)
                : null;
            $segments = explode('.', $routeName);
            $activePattern = count($segments) >= 3
                ? implode('.', array_slice($segments, 0, 2)).'.*'
                : $routeName;
            $active = request()->routeIs($routeName) || ($activePattern !== $routeName && request()->routeIs($activePattern));
            echo '<a href="'.e(route($routeName)).'" class="sidebar-link '.($active?'sidebar-link-active':'').'">';
            echo '<svg class="h-[17px] w-[17px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="'.e($icons[$icon] ?? $icons['book']).'"></path></svg>';
            echo '<span class="min-w-0 flex-1">'.e($label).'</span>';
            if ($featureStatus === \App\Services\FeatureAccessService::STATUS_MAINTENANCE) {
                echo '<span class="ml-auto rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-amber-700">Maintenance</span>';
            } elseif ($featureStatus === \App\Services\FeatureAccessService::STATUS_DISABLED && $user?->isAdmin()) {
                echo '<span class="ml-auto rounded-full bg-slate-200 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-slate-600">Disabled</span>';
            }
            echo '</a>';
        }
    };

    $dashboardFeature = \App\Services\FeatureAccessService::featureForRoute('dashboard');
    $showDashboard = ! $dashboardFeature || \App\Services\FeatureAccessService::shouldShowInNavigation($dashboardFeature, $user);
    $dashboardStatus = $dashboardFeature ? \App\Services\FeatureAccessService::effectiveStatus($dashboardFeature, $user?->university_id) : null;
@endphp
<nav class="space-y-1" aria-label="Primary navigation">
    @if($showDashboard)
    <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link-active' : '' }}">
        <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $icons['home'] }}"></path></svg><span class="min-w-0 flex-1">Dashboard</span>
        @if($dashboardStatus === \App\Services\FeatureAccessService::STATUS_MAINTENANCE)
            <span class="ml-auto rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-amber-700">Maintenance</span>
        @elseif($dashboardStatus === \App\Services\FeatureAccessService::STATUS_DISABLED && $user?->isAdmin())
            <span class="ml-auto rounded-full bg-slate-200 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-slate-600">Disabled</span>
        @endif
    </a>
    @endif
    @php($render($primary))
    @if($secondary)
        <div class="my-3 border-t border-slate-100"></div>
        @php($render($secondary))
    @endif
</nav>

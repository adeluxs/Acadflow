@php
    $knowledgeNavigation = [
        ['route' => 'knowledge.index', 'pattern' => 'knowledge.index', 'label' => 'Discover'],
        ['route' => 'knowledge.search', 'pattern' => 'knowledge.search', 'label' => 'Search'],
        ['route' => 'knowledge.communities.index', 'pattern' => 'knowledge.communities.*', 'label' => 'Communities'],
        ['route' => 'knowledge.learning.index', 'pattern' => 'knowledge.learning.*', 'label' => 'Learning paths'],
        ['route' => 'knowledge.reading.index', 'pattern' => 'knowledge.reading.*', 'label' => 'Reading lists'],
        ['route' => 'knowledge.events.index', 'pattern' => 'knowledge.events.*', 'label' => 'Events'],
        ['route' => 'knowledge.challenges.index', 'pattern' => 'knowledge.challenges.*', 'label' => 'Challenges'],
        ['route' => 'knowledge.leaderboard', 'pattern' => 'knowledge.leaderboard', 'label' => 'Leaderboard'],
    ];
@endphp

<nav class="mb-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur">
    <div class="flex min-w-max items-center gap-1">
        @foreach($knowledgeNavigation as $item)
            <a
                href="{{ route($item['route']) }}"
                class="inline-flex items-center rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs($item['pattern']) ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}"
            >
                {{ $item['label'] }}
            </a>
        @endforeach

        @auth
            <div class="mx-1 h-6 w-px bg-slate-200"></div>
            <a href="{{ route('knowledge.creator.edit') }}" class="inline-flex items-center rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-950">
                Creator profile
            </a>
        @endauth
    </div>
</nav>

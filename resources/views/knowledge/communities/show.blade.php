@extends('layouts.app')
@section('title', $community->name)
@section('page-title', $community->name)
@section('page-subtitle', str($community->community_type)->headline())

@section('content')
@include('knowledge._nav')

@php
    $membership = auth()->check() ? $community->members->firstWhere('user_id', auth()->id()) : null;
    $activePostType = old('post_type', 'discussion');
    $postTypes = [
        'discussion' => ['label' => 'Discussion', 'icon' => '💬', 'hint' => 'Ask, share or start a conversation'],
        'announcement' => ['label' => 'Announcement', 'icon' => '📣', 'hint' => 'Share an important community update'],
        'resource' => ['label' => 'Resource', 'icon' => '📚', 'hint' => 'Recommend a useful academic resource'],
        'event' => ['label' => 'Event', 'icon' => '🗓️', 'hint' => 'Share an upcoming activity or opportunity'],
        'poll' => ['label' => 'Poll', 'icon' => '📊', 'hint' => 'Collect opinions with two or more choices'],
    ];
    $postTypeHints = collect($postTypes)->mapWithKeys(fn ($item, $key) => [$key => $item['hint']])->all();
@endphp

<div class="space-y-7">
    <section class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-cyan-950 text-white shadow-xl shadow-slate-950/10">
        @if($community->coverMedia)
            <img src="{{ route('media.preview', $community->coverMedia) }}" alt="{{ $community->name }} cover" class="absolute inset-0 h-full w-full object-cover opacity-30">
        @endif
        <div class="absolute inset-0 bg-gradient-to-br from-slate-950/95 via-indigo-950/90 to-blue-900/85"></div>
        <div class="relative p-6 sm:p-8 lg:p-10">
            <div class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-4xl">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold backdrop-blur">{{ ucfirst($community->visibility) }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold backdrop-blur">{{ number_format($community->member_count) }} members</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold backdrop-blur">{{ str($community->community_type)->headline() }}</span>
                    </div>
                    <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">{{ $community->name }}</h1>
                    <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-200 sm:text-base">{{ $community->description }}</p>
                    @if($community->tags->isNotEmpty())
                        <div class="mt-5 flex flex-wrap gap-2">
                            @foreach($community->tags as $tag)
                                <span class="rounded-full bg-indigo-400/15 px-3 py-1 text-xs font-bold text-indigo-100 ring-1 ring-inset ring-indigo-300/20">#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @auth
                        @can('update', $community)
                            <a href="{{ route('knowledge.communities.edit', $community) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-slate-900 shadow-sm transition hover:-translate-y-0.5">Manage community</a>
                        @endcan

                        @if(! $membership)
                            <form method="POST" action="{{ route('knowledge.communities.join', $community) }}">
                                @csrf
                                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-indigo-400">Join community</button>
                            </form>
                        @elseif($community->owner_id !== auth()->id())
                            <form method="POST" action="{{ route('knowledge.communities.leave', $community) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-xl border border-white/25 bg-white/5 px-4 py-2.5 text-sm font-bold text-white backdrop-blur hover:bg-white/10">Leave</button>
                            </form>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-5">
            @auth
                @can('post', $community)
                    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 via-white to-blue-50/80 px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 font-black text-white">{{ strtoupper(substr(auth()->user()->first_name ?: auth()->user()->full_name ?: 'U', 0, 1)) }}</span>
                                <div>
                                    <h2 class="font-black text-slate-950">Create a community post</h2>
                                    <p class="text-xs text-slate-500">Share something useful with {{ $community->name }}.</p>
                                </div>
                            </div>
                        </div>

                        <form id="community-post-form" method="POST" action="{{ route('knowledge.communities.posts.store', $community) }}" class="space-y-5 p-5 sm:p-6">
                            @csrf

                            @if($errors->has('body') || $errors->has('title') || $errors->has('post_type') || $errors->has('poll_options') || $errors->has('poll_options.*'))
                                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                                    <p class="font-black">Please check your post.</p>
                                    <ul class="mt-1 list-disc pl-5">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div>
                                <p class="mb-2 text-xs font-black uppercase tracking-[0.16em] text-slate-400">Post type</p>
                                <input type="hidden" name="post_type" id="community-post-type" value="{{ $activePostType }}">
                                <div class="flex flex-wrap gap-2" role="group" aria-label="Post type">
                                    @foreach($postTypes as $type => $config)
                                        <button
                                            type="button"
                                            data-post-type="{{ $type }}"
                                            class="community-post-type inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold transition {{ $activePostType === $type ? 'border-indigo-200 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:bg-indigo-50/60 hover:text-indigo-700' }}"
                                            aria-pressed="{{ $activePostType === $type ? 'true' : 'false' }}"
                                        >
                                            <span>{{ $config['icon'] }}</span>
                                            <span>{{ $config['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                <p id="community-post-type-hint" class="mt-2 text-xs text-slate-400">{{ $postTypes[$activePostType]['hint'] ?? $postTypes['discussion']['hint'] }}</p>
                            </div>

                            <div class="grid gap-3">
                                <input name="title" value="{{ old('title') }}" class="w-full rounded-xl border-slate-300 px-4 py-3 font-semibold text-slate-900" placeholder="Add a title (optional)">
                                <div class="relative">
                                    <textarea id="community-post-body" required name="body" rows="5" maxlength="100000" class="w-full resize-y rounded-2xl border-slate-300 px-4 py-3 pb-9 text-[15px] leading-7" placeholder="Share knowledge, ask a question, start a discussion…">{{ old('body') }}</textarea>
                                    <div class="pointer-events-none absolute bottom-3 right-4 text-xs font-medium text-slate-400"><span id="community-post-count">0</span> characters</div>
                                </div>
                            </div>

                            <div id="community-poll-panel" class="{{ $activePostType === 'poll' ? '' : 'hidden' }} rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">Poll choices</h3>
                                        <p class="mt-1 text-xs text-slate-500">Add at least two distinct options. Poll fields are ignored for every other post type.</p>
                                    </div>
                                    <button type="button" id="add-poll-option" class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-black text-indigo-700 hover:bg-indigo-50">+ Add option</button>
                                </div>
                                <div id="community-poll-options" class="mt-4 grid gap-2">
                                    @php
                                        $oldPollOptions = old('poll_options', ['', '']);
                                        if (count($oldPollOptions) < 2) {
                                            $oldPollOptions = ['', ''];
                                        }
                                    @endphp
                                    @foreach($oldPollOptions as $index => $option)
                                        <div class="poll-option-row flex items-center gap-2">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-xs font-black text-indigo-600 shadow-sm">{{ $index + 1 }}</span>
                                            <input name="poll_options[]" value="{{ $option }}" maxlength="255" class="poll-option-input flex-1 rounded-xl border-indigo-200 bg-white" placeholder="Poll option {{ $index + 1 }}" {{ $activePostType === 'poll' ? '' : 'disabled' }}>
                                            <button type="button" class="remove-poll-option rounded-lg px-2 py-1 text-xs font-black text-slate-400 hover:bg-white hover:text-rose-600" aria-label="Remove poll option">×</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs leading-5 text-slate-400">Be clear, constructive and respectful. Moderated communities may review posts before publishing.</p>
                                <button class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-indigo-700 hover:shadow-md">
                                    Publish post <span aria-hidden="true">↗</span>
                                </button>
                            </div>
                        </form>
                    </section>
                @endcan
            @endauth

            @forelse($posts as $post)
                @php
                    $postBody = app(\App\Services\RichTextSanitizer::class)->sanitize((string) ($post->document?->body ?? ''));
                    $typeConfig = $postTypes[$post->post_type] ?? $postTypes['discussion'];
                    $totalPollVotes = $post->post_type === 'poll' ? $post->pollOptions->sum(fn ($option) => $option->votes->count()) : 0;
                @endphp
                <article class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                    @if($post->is_pinned)
                        <div class="flex items-center gap-2 border-b border-amber-100 bg-amber-50 px-5 py-2 text-xs font-bold text-amber-700 sm:px-6">📌 Pinned by a community moderator</div>
                    @endif
                    <div class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-900 font-black text-white">{{ strtoupper(substr($post->author?->first_name ?: $post->author?->full_name ?: 'U', 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-black text-slate-900">{{ $post->author?->full_name ?: 'Community member' }}</p>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-500">{{ $typeConfig['icon'] }} {{ $typeConfig['label'] }}</span>
                                        @if($post->status !== 'published')
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-700">{{ $post->status }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $post->created_at->diffForHumans() }}</p>
                                </div>
                            </div>

                            @auth
                                @can('moderate', $community)
                                    <form method="POST" action="{{ route('knowledge.communities.posts.moderate', $post) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $post->status === 'published' ? 'hidden' : 'published' }}">
                                        <button class="rounded-lg px-2 py-1 text-xs font-bold text-slate-400 hover:bg-slate-50 hover:text-indigo-700">{{ $post->status === 'published' ? 'Hide' : 'Publish' }}</button>
                                    </form>
                                @endcan
                            @endauth
                        </div>

                        @if($post->title)
                            <h2 class="mt-5 text-xl font-black tracking-tight text-slate-950 sm:text-2xl">{{ $post->title }}</h2>
                        @endif
                        <div class="prose prose-slate mt-4 max-w-none prose-p:leading-7 prose-a:text-indigo-700">{!! $postBody !!}</div>

                        @if($post->post_type === 'poll')
                            <div class="mt-5 rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
                                <div class="mb-3 flex items-center justify-between text-xs font-bold text-slate-500"><span>Community poll</span><span>{{ number_format($totalPollVotes) }} vote{{ $totalPollVotes === 1 ? '' : 's' }}</span></div>
                                <div class="space-y-2">
                                    @foreach($post->pollOptions as $option)
                                        @php
                                            $voteCount = $option->votes->count();
                                            $percentage = $totalPollVotes > 0 ? round(($voteCount / $totalPollVotes) * 100) : 0;
                                            $voted = auth()->check() && $option->votes->contains('user_id', auth()->id());
                                        @endphp
                                        <form method="POST" action="{{ route('knowledge.polls.vote', $option) }}" class="group relative overflow-hidden rounded-xl border {{ $voted ? 'border-indigo-300 bg-white' : 'border-slate-200 bg-white' }}">
                                            @csrf
                                            <div class="absolute inset-y-0 left-0 bg-indigo-50 transition-all" style="width: {{ $percentage }}%"></div>
                                            <button class="relative flex w-full items-center justify-between gap-4 px-4 py-3 text-left" {{ auth()->check() ? '' : 'disabled' }}>
                                                <span class="text-sm font-bold {{ $voted ? 'text-indigo-700' : 'text-slate-700' }}">{{ $option->label }}</span>
                                                <span class="shrink-0 text-xs font-black text-slate-500">{{ $percentage }}% · {{ $voteCount }}</span>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @auth
                            <form class="mt-5 flex gap-2 border-t border-slate-100 pt-4" method="POST" action="{{ route('knowledge.community-posts.comments', $post) }}">
                                @csrf
                                <input name="body" required class="flex-1 rounded-xl border-slate-300" placeholder="Write a thoughtful reply…">
                                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white hover:bg-slate-800">Comment</button>
                            </form>
                        @endauth
                    </div>
                </article>
            @empty
                <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-12 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">💬</div>
                    <h2 class="mt-4 text-lg font-black text-slate-900">No community posts yet</h2>
                    <p class="mt-2 text-sm text-slate-500">Start the first useful conversation in this community.</p>
                </div>
            @endforelse

            <div>{{ $posts->links() }}</div>
        </main>

        <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div><p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">People</p><h2 class="mt-1 font-black text-slate-950">Community members</h2></div>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700">{{ number_format($community->member_count) }}</span>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach($community->members->where('status', 'active')->take(20) as $member)
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-black text-slate-600">{{ strtoupper(substr($member->user?->first_name ?: $member->user?->full_name ?: 'U', 0, 1)) }}</span>
                                <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800">{{ $member->user?->full_name ?: 'Member' }}</p><p class="text-xs text-slate-400">{{ ucfirst($member->role) }}</p></div>
                            </div>
                            @auth
                                @can('moderate', $community)
                                    @if($member->user_id !== $community->owner_id)
                                        <form method="POST" action="{{ route('knowledge.communities.members.update', [$community, $member->user]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="removed">
                                            <input type="hidden" name="role" value="member">
                                            <button class="text-xs font-bold text-slate-400 hover:text-rose-600">Remove</button>
                                        </form>
                                    @endif
                                @endcan
                            @endauth
                        </div>
                    @endforeach
                </div>
            </section>

            @auth
                @can('moderate', $community)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-600">Moderation</p>
                        <h2 class="mt-1 font-black text-slate-950">Invite or approve</h2>
                        <form method="POST" action="{{ route('knowledge.communities.invitations.store', $community) }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="number" name="user_id" required placeholder="User ID" class="w-full rounded-xl border-slate-300">
                            <select name="role" class="w-full rounded-xl border-slate-300"><option value="member">Member</option><option value="moderator">Moderator</option><option value="administrator">Administrator</option></select>
                            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white">Send invitation</button>
                        </form>

                        @foreach($community->members->where('status', 'pending') as $member)
                            <form method="POST" action="{{ route('knowledge.communities.members.update', [$community, $member->user]) }}" class="mt-3 rounded-xl bg-slate-50 p-3">
                                @csrf
                                @method('PATCH')
                                <p class="text-sm font-bold text-slate-800">{{ $member->user?->full_name }}</p>
                                <input type="hidden" name="role" value="member">
                                <div class="mt-2 flex gap-3"><button name="status" value="active" class="text-sm font-bold text-emerald-700">Approve</button><button name="status" value="removed" class="text-sm font-bold text-rose-700">Decline</button></div>
                            </form>
                        @endforeach
                    </section>
                @endcan
            @endauth

            @if($community->rules)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Guidelines</p>
                    <h2 class="mt-1 font-black text-slate-950">Community rules</h2>
                    <ol class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        @foreach($community->rules as $rule)
                            <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-black text-slate-500">{{ $loop->iteration }}</span><span>{{ $rule }}</span></li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @auth
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <details>
                        <summary class="cursor-pointer text-sm font-black text-rose-700">Report community</summary>
                        <form method="POST" action="{{ route('knowledge.communities.report', $community) }}" class="mt-3 space-y-3">
                            @csrf
                            <select name="reason" required class="w-full rounded-xl border-slate-300">
                                <option value="spam">Spam</option>
                                <option value="harassment">Harassment</option>
                                <option value="misinformation">Misinformation</option>
                                <option value="privacy">Privacy concern</option>
                                <option value="policy">Policy violation</option>
                                <option value="other">Other</option>
                            </select>
                            <textarea name="details" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Optional details"></textarea>
                            <button class="w-full rounded-xl border border-rose-200 px-4 py-2 text-sm font-black text-rose-700">Submit report</button>
                        </form>
                    </details>
                </section>
            @endauth
        </aside>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const form = document.getElementById('community-post-form');
    if (!form) return;

    const typeInput = document.getElementById('community-post-type');
    const typeButtons = Array.from(form.querySelectorAll('.community-post-type'));
    const hint = document.getElementById('community-post-type-hint');
    const pollPanel = document.getElementById('community-poll-panel');
    const pollOptions = document.getElementById('community-poll-options');
    const addOption = document.getElementById('add-poll-option');
    const body = document.getElementById('community-post-body');
    const count = document.getElementById('community-post-count');
    const hints = @json($postTypeHints);

    const updateCounter = () => { count.textContent = body.value.length.toLocaleString(); };
    body.addEventListener('input', updateCounter);
    updateCounter();

    const refreshPollRows = () => {
        const rows = Array.from(pollOptions.querySelectorAll('.poll-option-row'));
        rows.forEach((row, index) => {
            row.querySelector('span').textContent = index + 1;
            const input = row.querySelector('.poll-option-input');
            input.placeholder = `Poll option ${index + 1}`;
            input.disabled = typeInput.value !== 'poll';
            const remove = row.querySelector('.remove-poll-option');
            remove.disabled = rows.length <= 2;
            remove.classList.toggle('opacity-30', rows.length <= 2);
        });
    };

    const setType = (type) => {
        typeInput.value = type;
        typeButtons.forEach((button) => {
            const active = button.dataset.postType === type;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.classList.toggle('border-indigo-200', active);
            button.classList.toggle('bg-indigo-50', active);
            button.classList.toggle('text-indigo-700', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('border-slate-200', !active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-slate-600', !active);
        });
        pollPanel.classList.toggle('hidden', type !== 'poll');
        hint.textContent = hints[type] || hints.discussion;
        refreshPollRows();
    };

    typeButtons.forEach((button) => button.addEventListener('click', () => setType(button.dataset.postType)));

    addOption.addEventListener('click', () => {
        const currentRows = pollOptions.querySelectorAll('.poll-option-row');
        if (currentRows.length >= 10) return;
        const row = document.createElement('div');
        row.className = 'poll-option-row flex items-center gap-2';
        row.innerHTML = '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-xs font-black text-indigo-600 shadow-sm"></span><input name="poll_options[]" maxlength="255" class="poll-option-input flex-1 rounded-xl border-indigo-200 bg-white"><button type="button" class="remove-poll-option rounded-lg px-2 py-1 text-xs font-black text-slate-400 hover:bg-white hover:text-rose-600" aria-label="Remove poll option">×</button>';
        pollOptions.appendChild(row);
        refreshPollRows();
        row.querySelector('input').focus();
    });

    pollOptions.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-poll-option');
        if (!button || button.disabled) return;
        button.closest('.poll-option-row').remove();
        refreshPollRows();
    });

    setType(typeInput.value || 'discussion');
})();
</script>
@endpush
@endsection

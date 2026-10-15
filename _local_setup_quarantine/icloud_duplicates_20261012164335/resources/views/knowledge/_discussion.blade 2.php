<section class="rounded-2xl border bg-white p-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Discussion</h2>
            <p class="mt-1 text-sm text-slate-500">Ask questions, share evidence, and mention collaborators.</p>
        </div>
    </div>
    @auth
        <form method="POST" action="{{ $discussionAction }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
            @csrf
            <input required name="body" maxlength="10000" class="min-w-0 flex-1 rounded-xl border-slate-300" placeholder="Add a constructive message or @mention">
            <button class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Post</button>
        </form>
    @else
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Sign in to participate in this discussion.</p>
    @endauth
    <div class="mt-5 space-y-4">
        @forelse($comments as $comment)
            <article class="rounded-xl border p-4">
                <div class="flex justify-between gap-3 text-xs text-slate-500">
                    <strong class="text-slate-800">{{ $comment->user?->full_name ?? 'Former user' }}</strong>
                    <span>{{ $comment->created_at?->diffForHumans() }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $comment->body }}</p>
                @foreach($comment->replies as $reply)
                    <div class="ml-4 mt-3 border-l-2 border-slate-200 pl-3 text-sm">
                        <strong>{{ $reply->user?->full_name ?? 'Former user' }}</strong>
                        <p class="mt-1 whitespace-pre-line text-slate-700">{{ $reply->body }}</p>
                    </div>
                @endforeach
                @auth
                    <form method="POST" action="{{ $discussionAction }}" class="mt-3 flex gap-2">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <input required name="body" maxlength="10000" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" placeholder="Reply">
                        <button class="text-sm font-semibold text-blue-700">Reply</button>
                    </form>
                @endauth
            </article>
        @empty
            <div class="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500">No discussion messages yet. Start the conversation with a useful question or resource.</div>
        @endforelse
    </div>
    @if(method_exists($comments,'links'))<div class="mt-4">{{ $comments->links() }}</div>@endif
</section>

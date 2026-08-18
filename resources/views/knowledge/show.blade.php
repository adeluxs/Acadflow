@extends('layouts.app')
@section('title', $publication->title)
@section('page-title', $publication->title)
@section('page-subtitle', ucwords(str_replace('_', ' ', $publication->content_type)).' · '.($publication->creator?->full_name ?: 'Academic contributor'))

@section('content')
@include('knowledge._nav')

<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400"></div>
        <div class="p-6 sm:p-8 lg:p-10">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-indigo-50 px-3 py-1.5 font-bold text-indigo-700">{{ $publication->category?->name ?? ucwords(str_replace('_', ' ', $publication->content_type)) }}</span>
                @foreach($publication->tags as $tag)
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 font-medium text-slate-600">#{{ $tag->name }}</span>
                @endforeach
                @if($publication->access_type === 'premium')
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 font-bold text-amber-800">Premium · NGN {{ number_format((float) $publication->price, 2) }}</span>
                @elseif($publication->access_type === 'institution')
                    <span class="rounded-full bg-emerald-50 px-3 py-1.5 font-bold text-emerald-700">Institution access</span>
                @endif
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <h1 class="max-w-4xl text-3xl font-black tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">{{ $publication->title }}</h1>
                    @if($publication->excerpt)
                        <p class="mt-4 max-w-4xl text-base leading-8 text-slate-600 sm:text-lg">{{ $publication->excerpt }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 p-3 text-center">
                    <div class="rounded-xl bg-white px-4 py-3">
                        <p class="text-lg font-black text-slate-900">{{ number_format($publication->view_count) }}</p>
                        <p class="text-[11px] font-medium text-slate-400">Reads</p>
                    </div>
                    <div class="rounded-xl bg-white px-4 py-3">
                        <p class="text-lg font-black text-slate-900">{{ number_format($publication->bookmark_count) }}</p>
                        <p class="text-[11px] font-medium text-slate-400">Saves</p>
                    </div>
                    <div class="rounded-xl bg-white px-4 py-3">
                        <p class="text-lg font-black text-slate-900">{{ $publication->reading_time_minutes ?: 5 }}</p>
                        <p class="text-[11px] font-medium text-slate-400">Min read</p>
                    </div>
                </div>
            </div>

            <div class="mt-7 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-5 text-sm">
                @if($publication->creator)
                    <a href="{{ route('knowledge.creator', $publication->creator) }}" class="group flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 font-black text-white">{{ strtoupper(substr($publication->creator->first_name ?: $publication->creator->full_name ?: 'A', 0, 1)) }}</span>
                        <span>
                            <span class="block font-black text-slate-900 group-hover:text-indigo-700">{{ $publication->creator->full_name }}</span>
                            <span class="block text-xs text-slate-400">Published {{ $publication->published_at?->format('M j, Y') ?: 'recently' }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 font-black text-slate-600">A</span>
                        <span><span class="block font-black text-slate-900">Academic contributor</span><span class="block text-xs text-slate-400">Published {{ $publication->published_at?->format('M j, Y') ?: 'recently' }}</span></span>
                    </div>
                @endif

                @auth
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('knowledge.manage.bookmark', $publication) }}">
                            @csrf
                            <button class="rounded-xl px-4 py-2 text-sm font-bold transition {{ $bookmarked ? 'bg-indigo-600 text-white' : 'border border-slate-200 text-slate-700 hover:bg-slate-50' }}">{{ $bookmarked ? 'Saved' : 'Save' }}</button>
                        </form>
                        <form method="POST" action="{{ route('knowledge.follow', $publication) }}">
                            @csrf
                            <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">{{ $following ? 'Following' : 'Follow' }}</button>
                        </form>
                        @can('update', $publication)
                            <a href="{{ route('knowledge.manage.edit', $publication) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Edit</a>
                        @endcan
                    </div>
                @endauth
            </div>
        </div>
    </section>

    @if($publication->sourceResearchProject)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100">✓</span>
            <div><strong>Research provenance verified.</strong> This publication was derived from the approved Research Studio project <strong>{{ $publication->sourceResearchProject->title }}</strong>.</div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-6">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:p-10">
                @if($hasAccess)
                    <div class="prose prose-slate max-w-none prose-headings:font-black prose-a:text-indigo-700 prose-img:rounded-2xl">
                        {!! app(\App\Services\RichTextSanitizer::class)->sanitize((string) ($publication->document?->body ?? '')) !!}
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-6">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-xl shadow-sm">🔒</div>
                        <h2 class="mt-4 text-xl font-black text-amber-950">Protected academic resource</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-amber-900">Purchase entitlement is required to read the full content and download protected files.</p>
                        @auth
                            @if($publication->access_type === 'premium' && $gateways->isNotEmpty())
                                <form method="POST" action="{{ route('commerce.purchase', $publication) }}" class="mt-5 flex flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <select name="gateway" class="rounded-xl border-amber-300 bg-white">
                                        @foreach($gateways as $gateway)
                                            <option value="{{ $gateway->code }}">{{ $gateway->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-xl bg-amber-700 px-4 py-2.5 font-bold text-white">Purchase securely</button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="mt-4 inline-flex rounded-xl bg-amber-800 px-4 py-2.5 text-sm font-bold text-white">Sign in to continue</a>
                        @endauth
                    </div>
                @endif

                @if($publication->digitalFiles->isNotEmpty())
                    <section class="mt-10 border-t border-slate-100 pt-7">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Attachments</p>
                                <h2 class="mt-1 text-xl font-black text-slate-950">Files and resources</h2>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">{{ $publication->digitalFiles->count() }} file{{ $publication->digitalFiles->count() === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="mt-4 grid gap-3">
                            @foreach($publication->digitalFiles as $file)
                                @php
                                    $asset = $file->mediaAsset;
                                    $sizeKb = $asset?->size_bytes ? round($asset->size_bytes / 1024) : null;
                                @endphp
                                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-lg shadow-sm">↧</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black text-slate-900">{{ $file->label ?: $asset?->original_name ?: 'Academic file' }}</p>
                                            <p class="mt-1 text-xs text-slate-400">{{ $file->is_preview ? 'Preview available' : 'Protected download' }}{{ $sizeKb ? ' · '.number_format($sizeKb).' KB' : '' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        @if($file->is_preview && $asset)
                                            <a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:border-indigo-200 hover:text-indigo-700" href="{{ route('media.preview', $asset) }}" target="_blank" rel="noopener">Preview</a>
                                        @endif
                                        @auth
                                            @if($hasAccess && $asset)
                                                <button type="button" class="secure-download rounded-xl bg-indigo-600 px-3 py-2 text-sm font-bold text-white hover:bg-indigo-700" data-url="{{ route('media.token', $asset) }}">Download</button>
                                            @endif
                                        @endauth
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    @auth
                        @foreach(['helpful','insightful','celebrate'] as $reaction)
                            <form method="POST" action="{{ route('knowledge.reactions', $publication) }}">
                                @csrf
                                <input type="hidden" name="reaction" value="{{ $reaction }}">
                                <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">{{ ucfirst($reaction) }} · {{ $reactionCounts[$reaction] ?? 0 }}</button>
                            </form>
                        @endforeach
                        <form method="POST" action="{{ route('knowledge.shares', $publication) }}">
                            @csrf
                            <input type="hidden" name="channel" value="copy_link">
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Share</button>
                        </form>
                    @endauth
                </div>
            </article>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Discussion</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Academic conversation</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">Evidence-first</span>
                </div>

                @auth
                    <form method="POST" action="{{ route('knowledge.comments.store', $publication) }}" class="mt-5 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <input required name="body" class="flex-1 rounded-xl border-slate-300" placeholder="Add an evidence-based comment or @@mention">
                        <button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Comment</button>
                    </form>
                @endauth

                <div class="mt-6 space-y-4">
                    @forelse($comments as $comment)
                        <article class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex justify-between gap-3 text-xs text-slate-400">
                                <strong class="text-sm text-slate-800">{{ $comment->user?->full_name }}</strong>
                                <span>{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $comment->body }}</p>
                            @foreach($comment->replies as $reply)
                                <div class="ml-5 mt-3 rounded-r-xl border-l-2 border-indigo-200 bg-indigo-50/40 px-3 py-2 text-sm">
                                    <strong class="text-slate-800">{{ $reply->user?->full_name }}</strong>
                                    <p class="mt-1 text-slate-600">{{ $reply->body }}</p>
                                </div>
                            @endforeach
                            @auth
                                <form method="POST" action="{{ route('knowledge.comments.store', $publication) }}" class="mt-3 flex gap-2">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <input required name="body" class="flex-1 rounded-lg border-slate-300 text-sm" placeholder="Reply">
                                    <button class="rounded-lg px-3 text-sm font-bold text-indigo-700">Reply</button>
                                </form>
                            @endauth
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No comments yet. Start the academic conversation.</div>
                    @endforelse
                </div>
                <div class="mt-4">{{ $comments->links() }}</div>
            </section>
        </main>

        <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
            <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-lg text-white shadow-sm">✦</span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-slate-950">Grounded AI companion</h2><span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-700">Publication only</span></div>
                        <p class="mt-0.5 text-xs text-slate-500">No open web. No guessing. Source-backed answers only.</p>
                    </div>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600">AcadFlow checks whether your question is meaningful, searches only this publication, validates relevance, and rejects unsupported answers instead of inventing a response.</p>

                @auth
                    @if($hasAccess)
                        <div class="mt-4 grid gap-2">
                            @foreach([
                                'Summarize the main argument and key points.',
                                'What evidence supports the main conclusion?',
                                'What limitations or recommendations are identified?'
                            ] as $smartPrompt)
                                <button type="button" data-grounded-prompt="{{ $smartPrompt }}" class="grounded-prompt rounded-xl border border-indigo-100 bg-white px-3 py-2 text-left text-xs font-bold leading-5 text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-800">{{ $smartPrompt }}</button>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('knowledge.companion.ask', $publication) }}" class="mt-4 space-y-2" id="grounded-companion-form">
                            @csrf
                            <label for="grounded-question" class="sr-only">Ask a question about this publication</label>
                            <textarea required minlength="2" maxlength="2000" id="grounded-question" name="question" rows="4" class="w-full rounded-xl border-slate-300 bg-white" placeholder="Ask a clear question about this publication…">{{ old('question') }}</textarea>
                            <div class="flex items-center justify-between gap-3 text-[11px] text-slate-400"><span>Meaningless or unrelated input is rejected before external AI is called.</span><span id="grounded-question-count">0/2000</span></div>
                            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700">Ask this publication</button>
                        </form>
                    @else
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs leading-5 text-amber-800">You can view this publication preview, but Grounded AI is available only after you have access to the protected publication content.</div>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="mt-4 inline-flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white">Sign in to ask this publication</a>
                @endguest
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Impact</p>
                <div class="mt-3 flex items-end justify-between">
                    <div><p class="text-3xl font-black text-slate-950">{{ $publication->citationsReceived->count() }}</p><p class="text-sm text-slate-500">Internal citations</p></div>
                    <a class="text-sm font-black text-indigo-700" href="{{ route('knowledge.citations.graph', $publication) }}">View network →</a>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><h2 class="font-black text-slate-950">Related resources</h2><span class="text-xs text-slate-400">{{ $related->count() }}</span></div>
                <div class="mt-2 divide-y divide-slate-100">
                    @forelse($related as $item)
                        <a href="{{ route('knowledge.show', $item) }}" class="group block py-3">
                            <p class="line-clamp-2 text-sm font-bold leading-5 text-slate-800 group-hover:text-indigo-700">{{ $item->title }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $item->creator?->full_name }}</p>
                        </a>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No related resources yet.</p>
                    @endforelse
                </div>
            </section>

            @auth
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <details>
                        <summary class="cursor-pointer text-sm font-bold text-rose-700">Report content</summary>
                        <form method="POST" action="{{ route('knowledge.reports', $publication) }}" class="mt-3 space-y-2">
                            @csrf
                            <select name="reason" class="w-full rounded-xl border-slate-300">
                                @foreach(['spam','academic_integrity','harassment','misinformation','copyright','privacy','other'] as $reason)
                                    <option value="{{ $reason }}">{{ ucwords(str_replace('_', ' ', $reason)) }}</option>
                                @endforeach
                            </select>
                            <textarea name="details" class="w-full rounded-xl border-slate-300" placeholder="Optional details"></textarea>
                            <button class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-bold text-rose-700">Send to moderation</button>
                        </form>
                    </details>
                </section>
            @endauth
        </aside>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.secure-download').forEach((button) => {
    button.addEventListener('click', async () => {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Preparing…';
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (response.ok && data.url) {
                window.location.href = data.url;
                return;
            }
            alert(data.message || 'Download could not be authorized.');
        } catch (error) {
            alert('The download could not be prepared. Please try again.');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
});

const groundedQuestion = document.getElementById('grounded-question');
const groundedCount = document.getElementById('grounded-question-count');
if (groundedQuestion) {
    const updateGroundedCount = () => {
        if (groundedCount) groundedCount.textContent = `${groundedQuestion.value.length}/2000`;
    };
    updateGroundedCount();
    groundedQuestion.addEventListener('input', updateGroundedCount);
    document.querySelectorAll('.grounded-prompt').forEach((button) => {
        button.addEventListener('click', () => {
            groundedQuestion.value = button.dataset.groundedPrompt || '';
            groundedQuestion.focus();
            updateGroundedCount();
        });
    });
}

</script>
@endpush
@endsection

@extends('layouts.app')
@section('title','Learning Paths')
@section('page-title','Learning Paths')
@section('page-subtitle','Structured academic journeys with progress and completion goals')
@section('content')
@include('knowledge._nav')
<div class="space-y-7">
    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-950 via-slate-950 to-violet-950 p-7 text-white shadow-xl sm:p-9"><div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div><div class="relative max-w-3xl"><p class="text-xs font-black uppercase tracking-[.25em] text-violet-300">Guided learning</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">Learning paths</h1><p class="mt-3 text-sm leading-7 text-slate-300">Turn publications, course resources, assignments and external references into purposeful step-by-step learning journeys.</p></div></section>

    @auth
        <details class="group rounded-3xl border border-slate-200 bg-white shadow-sm" @if($errors->any()) open @endif><summary class="flex cursor-pointer list-none items-center justify-between p-5 sm:p-6"><div><p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">Creator tools</p><h2 class="mt-1 text-xl font-black text-slate-900">Build a new learning path</h2></div><span class="rounded-xl bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700 group-open:bg-indigo-600 group-open:text-white">Create</span></summary><form method="POST" action="{{ route('knowledge.learning.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:p-6 md:grid-cols-2">@csrf<input required name="title" class="rounded-xl border-slate-300" placeholder="Path title"><input name="outcomes" class="rounded-xl border-slate-300" placeholder="Learning outcomes, comma separated"><textarea name="description" rows="4" class="rounded-xl border-slate-300 md:col-span-2" placeholder="What will learners achieve?"></textarea><select name="visibility" class="rounded-xl border-slate-300"><option>public</option><option>institution</option><option>private</option></select><select name="access_type" class="rounded-xl border-slate-300"><option>free</option><option>institution</option><option>premium</option></select><input name="price" type="number" step="0.01" class="rounded-xl border-slate-300" placeholder="Premium price"><select name="status" class="rounded-xl border-slate-300"><option>draft</option><option>published</option></select><label class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700"><input type="checkbox" name="certificate_enabled" value="1"> Certificate on completion</label><button class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-black text-white">Create path</button></form></details>
    @endauth

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($paths as $path)
            <a href="{{ route('knowledge.learning.show',$path) }}" class="group flex min-h-64 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl"><div class="flex items-center justify-between gap-3"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ str($path->access_type)->headline() }}</span><span class="text-xs font-bold text-slate-400">{{ $path->items_count }} steps</span></div><h2 class="mt-5 text-xl font-black text-slate-900 group-hover:text-indigo-700">{{ $path->title }}</h2><p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ Illuminate\Support\Str::limit($path->description,160) }}</p><div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-5"><span class="text-xs font-semibold text-slate-500">{{ str($path->visibility)->headline() }}</span><span class="text-sm font-black text-indigo-600">Explore path →</span></div></a>
        @empty
            <div class="md:col-span-2 xl:col-span-3 rounded-[2rem] border border-dashed border-slate-300 bg-white p-14 text-center"><div class="text-4xl">🧭</div><h2 class="mt-4 text-xl font-black text-slate-900">No learning paths yet</h2><p class="mt-2 text-sm text-slate-500">Published learning journeys will appear here.</p></div>
        @endforelse
    </div>
    {{ $paths->links() }}
</div>
@endsection

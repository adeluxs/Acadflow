@extends('layouts.app')
@section('title','Knowledge Search')
@section('page-title','Knowledge Search')
@section('page-subtitle','Lexical and privacy-aware semantic discovery')
@section('content')
@include('knowledge._nav')
<form class="rounded-2xl border bg-white p-4" method="GET"><div class="grid gap-3 md:grid-cols-[1fr_180px_auto]"><input class="rounded-xl border-slate-300" name="q" value="{{ request('q') }}" placeholder="Search academic knowledge"><input class="rounded-xl border-slate-300" name="type" value="{{ request('type') }}" placeholder="Content type"><button class="rounded-xl bg-blue-600 px-5 py-2 text-white">Search</button></div></form>
<div class="mt-6 space-y-4">
@forelse($results as $result)
@php($document=$result['document'])
<article class="rounded-2xl border bg-white p-5"><div class="flex justify-between gap-4"><div><p class="text-xs uppercase text-blue-700">{{ str_replace('_',' ',$document->content_type) }}</p><h2 class="mt-1 text-lg font-semibold">{{ $document->title }}</h2></div><span class="text-xs text-slate-500">{{ number_format($result['score']*100,1) }}% match</span></div><p class="mt-2 text-sm text-slate-600">{{ Illuminate\Support\Str::limit($document->summary ?: strip_tags((string)$document->body),220) }}</p>@if($document->searchable_type===App\Models\KnowledgePublication::class && $document->searchable)<a class="mt-3 inline-block text-sm font-semibold text-blue-700" href="{{ route('knowledge.show',$document->searchable) }}">Open publication</a>@endif</article>
@empty<div class="rounded-2xl border border-dashed bg-white p-10 text-center text-slate-500">No authorized results found.</div>@endforelse
</div>
@if($recommended->isNotEmpty())<h2 class="mt-8 text-xl font-semibold">Recommended for you</h2><div class="mt-3 grid gap-4 md:grid-cols-3">@foreach($recommended as $item)<a class="rounded-2xl border bg-white p-4" href="{{ route('knowledge.show',$item['publication']) }}"><strong>{{ $item['publication']->title }}</strong><p class="mt-2 text-xs text-slate-500">{{ $item['recommendation']->reason }}</p></a>@endforeach</div>@endif
@endsection

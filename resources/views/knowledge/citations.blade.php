@extends('layouts.app')
@section('title','Citation Network')
@section('page-title','Citation Network')
@section('page-subtitle',$publication->title)
@section('content')
@include('knowledge._nav')
<div class="rounded-2xl border bg-white p-6"><pre class="overflow-auto text-xs">{{ json_encode($graph, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></div>
@auth<form method="POST" action="{{ route('knowledge.citations.rebuild',$publication) }}" class="mt-4">@csrf<button class="rounded-xl border px-4 py-2">Rebuild internal citations</button></form>@endauth
@endsection

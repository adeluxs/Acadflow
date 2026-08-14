@extends('layouts.app')
@section('title','Grounded AI Companion')
@section('page-title','Grounded AI Companion')
@section('page-subtitle','Answers constrained to authorized indexed sources')
@section('content')
<div class="rounded-2xl border bg-white p-6"><p class="text-sm text-slate-500">Question</p><h2 class="mt-1 text-lg font-semibold">{{ $session->question }}</h2><p class="mt-6 text-sm text-slate-500">Answer</p><div class="prose mt-2 max-w-none">{{ $session->answer }}</div><p class="mt-4 text-xs text-slate-500">Confidence {{ number_format((float)$session->confidence*100,1) }}% @if($session->human_review_required) · Human review advised @endif</p></div><div class="mt-5 space-y-3"><h2 class="font-semibold">Grounding sources</h2>@foreach($session->sources as $source)<div class="rounded-2xl border bg-white p-4"><strong>{{ data_get($source->metadata,'title','Source') }}</strong><p class="mt-2 text-sm text-slate-600">{{ $source->excerpt }}</p></div>@endforeach</div>
@endsection

@extends('layouts.app')
@section('title','Research Impact Rankings')
@section('page-title','Research Impact Rankings')
@section('page-subtitle','Internal and provenance-separated external citation impact')
@section('content')
@include('knowledge._nav')
<div class="space-y-3">@foreach($rankings as $rank)<a class="block rounded-2xl border bg-white p-5" href="{{ route('knowledge.show',$rank) }}"><div class="flex justify-between"><strong>{{ $rank->title }}</strong><span>{{ $rank->internal_citations_count ?? 0 }} internal · {{ $rank->external_citations_count ?? 0 }} external</span></div></a>@endforeach</div>
@endsection

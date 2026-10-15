@extends('layouts.app')
@section('title','Preview: '.$publication->title)
@section('page-title','Publication Preview')
@section('page-subtitle','Private preview; this does not publish the content')
@section('content')
<article class="mx-auto max-w-4xl rounded-3xl border bg-white p-8"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800">{{ $publication->status }}</span><h1 class="mt-4 text-4xl font-semibold">{{ $publication->title }}</h1><p class="mt-3 text-lg text-slate-500">{{ $publication->excerpt }}</p><div class="prose mt-8 max-w-none">{!! app(\App\Services\RichTextSanitizer::class)->sanitize((string) ($publication->document?->body ?? '')) !!}</div></article>
@endsection

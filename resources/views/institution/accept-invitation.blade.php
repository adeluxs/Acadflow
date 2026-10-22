@extends('layouts.app')
@section('title','Academic invitation')
@section('content')
<div class="mx-auto max-w-2xl acad-card p-6 sm:p-9">
    <p class="text-sm font-semibold text-indigo-600">Your next academic connection</p>
    <h1 class="mt-2 text-3xl font-bold">Join {{ $invitation->department?->name ?? $invitation->university->name }}</h1>
    <p class="mt-4 text-slate-600">You were invited as {{ $invitation->role }} at {{ $invitation->university->name }}. Accept with your existing account. Your personal research, documents and other affiliations remain yours.</p>
    <p class="mt-3 text-sm text-slate-500">This invitation is for {{ $invitation->email }} and expires {{ $invitation->expires_at->format('j M Y, H:i') }}.</p>
    <form method="POST" action="{{ route('academic.invitations.consume',$token) }}" class="mt-6">@csrf<button class="acad-primary-button">Accept invitation</button></form>
</div>
@endsection

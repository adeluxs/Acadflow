@extends('layouts.app')
@section('title','Certificate Verification')
@section('page-title','Certificate Verification')
@section('page-subtitle','Public authenticity check')
@section('content')
<div class="mx-auto max-w-2xl rounded-3xl border bg-white p-10 text-center"><p class="text-sm uppercase tracking-widest text-emerald-700">Verified academic certificate</p><h1 class="mt-4 text-3xl font-semibold">{{ $certificate->title }}</h1><p class="mt-4">Issued to <strong>{{ $certificate->user?->full_name }}</strong></p><p class="mt-2 text-sm text-slate-500">{{ $certificate->issuer }} · {{ $certificate->issued_on?->format('F j, Y') }}</p><code class="mt-6 block break-all rounded-xl bg-slate-100 p-3 text-xs">{{ $certificate->verification_code }}</code></div>
@endsection

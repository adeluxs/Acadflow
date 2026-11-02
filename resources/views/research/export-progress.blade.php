@extends('layouts.app')
@section('title', 'Research export')
@section('page-title', 'Prepare research export')
@section('content')
<section class="studio-panel mx-auto max-w-2xl" data-export-progress="{{ route('research.exports.show', $export) }}" data-export-state="{{ $status }}">
    <h1 class="studio-heading">{{ $research->title }}</h1>
    <p class="studio-muted mt-4" data-export-message role="status">{{ match($status) { 'completed' => 'Your export is ready.', 'failed' => 'The export could not be completed. Return to your workspace to retry.', 'processing' => 'Preparing your document in the background. You can continue working.', default => 'Your export is queued. You can continue working while it is prepared.' } }}</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a class="studio-button" data-export-download href="{{ $download ?? '#' }}" @if(!$download) hidden @endif>Download document</a>
        <button class="studio-button-secondary" type="button" data-export-retry hidden>Check status again</button>
        <a class="studio-button-secondary" href="{{ route('research.show', $research) }}">Return to writing</a>
    </div>
    <noscript><p class="mt-4">Reload this page to check the export status.</p></noscript>
</section>
@endsection

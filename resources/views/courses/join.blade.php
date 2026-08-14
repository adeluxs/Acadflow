@extends('layouts.dashboard')

@section('title', 'Join '.$course->code)
@section('page-title', 'Join Course')
@section('page-subtitle', 'Confirm your enrollment using the course invitation link')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
    <p class="text-sm font-semibold text-blue-600">{{ $course->code }}</p>
    <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $course->name }}</h1>
    <p class="text-slate-600 mt-3">{{ $course->description ?: 'No course description has been provided.' }}</p>
    <dl class="grid grid-cols-2 gap-4 mt-6 text-sm">
        <div><dt class="text-slate-500">Department</dt><dd class="font-medium">{{ $course->department->name }}</dd></div>
        <div><dt class="text-slate-500">Credit hours</dt><dd class="font-medium">{{ $course->credit_hours }}</dd></div>
    </dl>

    @if($alreadyEnrolled)
        <a href="{{ route('courses.show', $course) }}" class="inline-flex mt-8 px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold">Open course</a>
    @else
        <form method="POST" action="{{ route('courses.join.link.process', $course->uuid) }}" class="mt-8">
            @csrf
            <button class="px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700">Join this course</button>
        </form>
    @endif
</div>
@endsection

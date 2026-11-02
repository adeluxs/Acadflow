@extends('layouts.app')

@section('title', 'My Enrollments')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Enrollments</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($enrollments as $enrollment)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold">{{ $enrollment->course->code }}</h3>
                <p class="text-gray-600">{{ $enrollment->course->name }}</p>
                <div class="mt-4">
                    <span class="px-2 inline-flex text-xs font-semibold rounded-full 
                        {{ $enrollment->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ ucfirst($enrollment->status) }}
                    </span>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No enrollments found.</p>
        @endforelse
    </div>
</div>
@endsection
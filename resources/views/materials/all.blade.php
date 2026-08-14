@extends('layouts.dashboard')

@section('title', 'Course Materials')
@section('page-title', 'Course Materials')
@section('page-subtitle', 'Materials available across your accessible courses')

@section('content')
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
    @forelse($materials as $material)
        <a href="{{ route('materials.show', [$material->course, $material]) }}" class="block bg-white border border-slate-200 rounded-2xl p-5 hover:border-blue-300 hover:shadow-sm transition">
            <p class="text-xs font-semibold text-blue-600">{{ $material->course->code }} · {{ str_replace('_', ' ', ucfirst($material->type)) }}</p>
            <h2 class="font-bold text-slate-900 mt-2">{{ $material->title }}</h2>
            <p class="text-sm text-slate-500 mt-2">{{ Str::limit($material->description, 120) }}</p>
            <p class="text-xs text-slate-400 mt-4">Uploaded by {{ $material->uploader->full_name }}</p>
        </a>
    @empty
        <div class="md:col-span-2 xl:col-span-3 bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center text-slate-500">No accessible materials found.</div>
    @endforelse
</div>
<div class="mt-6">{{ $materials->links() }}</div>
@endsection

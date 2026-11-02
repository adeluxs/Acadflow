@extends('layouts.app')
@section('title', 'My Study Space')
@section('page-title', 'My Study Space')
@section('page-subtitle', 'Understand your material. Test yourself. Return with a clear next step.')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    @include('student.study._today')
    @if(session('success'))<p class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-indigo-600">Start with your course</p><h2 class="mt-1 text-xl font-black">What are you studying?</h2></div><a href="{{ route('courses.index') }}" class="text-sm font-bold text-indigo-700">Manage my courses →</a></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse($courses as $course)<a href="{{ route('study.index', ['course_id' => $course->id]) }}#materials" class="rounded-2xl border bg-white p-4 hover:border-indigo-300"><span class="text-xs font-bold text-indigo-600">{{ $course->code }}</span><h3 class="mt-1 font-bold text-slate-900">{{ $course->name }}</h3><span class="mt-2 block text-xs text-slate-500">Open materials and study →</span></a>@empty<div class="rounded-2xl border border-dashed p-6 text-sm text-slate-500 sm:col-span-2">Join a course to connect your notes, coursework and revision. You can use an independent classroom without adopting an institution.</div>@endforelse</div>
        {{ $courses->fragment('materials')->links() }}
    </section>

    @if($materials)
    <section id="materials" class="rounded-3xl border bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-lg font-black">Course materials</h2><p class="mt-1 text-sm text-slate-500">Read the source, ask about a passage, and practise what you learned.</p></div><form method="GET" class="flex gap-2">@if(request('course_id'))<input type="hidden" name="course_id" value="{{ request('course_id') }}">@endif<label class="sr-only" for="study-search">Find a material</label><input id="study-search" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Find a material" class="min-w-0 w-44 rounded-xl border-slate-300 text-sm"><button class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-bold text-white">Search</button></form></div>
        @if(request()->filled('course_id') || request()->filled('q'))<a href="{{ route('study.index') }}#materials" class="mt-3 inline-block text-xs font-bold text-indigo-700">Show all my materials</a>@endif
        <div class="mt-4 grid gap-3 md:grid-cols-2">@forelse($materials as $material)<article class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 p-4"><div class="min-w-0"><p class="text-xs text-indigo-600">{{ $material->course?->code }} · {{ $material->topic ?: str($material->type)->headline() }}</p><h3 class="mt-1 break-words font-semibold">{{ $material->title }}</h3></div><form method="POST" action="{{ route('study.materials.start', $material) }}">@csrf<button class="whitespace-nowrap rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Study →</button></form></article>@empty<p class="col-span-full rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No matching published materials. Add your own notes below or open your course workspace.</p>@endforelse</div>
        <div class="mt-4">{{ $materials->fragment('materials')->links() }}</div>
    </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-3xl border bg-white p-5 sm:p-6"><h2 class="text-lg font-black">Add your own notes</h2><p class="mt-1 text-sm text-slate-500">Paste a passage or import a readable document. PDF, Word and TXT uploads follow your administrator’s allowed file types. These notes stay private to you.</p>
            <form method="POST" action="{{ route('study.notes.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">@csrf
                <label class="block text-sm font-semibold">Course<select name="course_id" required class="mt-1 w-full rounded-xl border-slate-300"><option value="">Choose a course</option>@if($selectedCourse && !$courses->contains('id', $selectedCourse->id))<option value="{{ $selectedCourse->id }}" selected>{{ $selectedCourse->code }} · {{ $selectedCourse->name }}</option>@endif @foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id', request('course_id'))==$course->id)>{{ $course->code }} · {{ $course->name }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold">Title<input name="title" value="{{ old('title') }}" maxlength="200" required class="mt-1 w-full rounded-xl border-slate-300" placeholder="e.g. Week 3: Database normalization"></label>
                <label class="block text-sm font-semibold">Your notes<textarea name="content" maxlength="20000" rows="5" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Paste the source passage you want to understand">{{ old('content') }}</textarea></label>
                <label class="block text-sm font-semibold">Or import a document<input name="file" type="file" accept=".pdf,.docx,.txt" class="mt-2 block w-full text-sm"></label><p class="text-xs text-slate-500">Up to 5 MB. The first 20,000 readable characters become a private note; the original upload is removed after extraction. Scanned image PDFs need readable text first.</p>
                <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Save and start studying →</button>
            </form>
        </section>
        <section class="rounded-3xl border bg-white p-5 sm:p-6"><div class="flex items-center justify-between gap-3"><h2 class="text-lg font-black">My private notes</h2><a href="{{ route('study-notes.index') }}" class="text-xs font-bold text-indigo-700">Manage notes →</a></div><div class="mt-4 space-y-3">@forelse($notes as $note)<form method="POST" action="{{ route('study.notes.start', $note) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 p-4">@csrf<div class="min-w-0"><h3 class="break-words text-sm font-semibold">{{ $note->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $note->course?->code }} · Only you</p></div><button class="text-sm font-bold text-indigo-700">Study →</button></form>@empty<p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">Your saved notes will appear here.</p>@endforelse</div><div class="mt-4">{{ $notes->links() }}</div></section>
    </div>
</div>
@endsection

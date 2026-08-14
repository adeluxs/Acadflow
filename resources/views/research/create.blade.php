@extends('layouts.app')
@section('title', 'Create Research Project')
@section('page-title', 'Create research project')
@section('page-subtitle', 'The selected type generates the correct workspace and workflow')
@section('content')
<form method="POST" action="{{ route('research.store') }}" class="mx-auto max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    <div class="grid gap-5 md:grid-cols-2">
        <label class="block"><span class="text-sm font-medium">Research type</span><select name="research_type_id" required class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose type</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('research_type_id')==$type->id)>{{ $type->name }}</option>@endforeach</select></label>
        <label class="block"><span class="text-sm font-medium">Department</span><select name="department_id" required class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', auth()->user()->department_id)==$department->id)>{{ $department->name }}</option>@endforeach</select></label>
    </div>
    <label class="block"><span class="text-sm font-medium">Research title</span><input name="title" value="{{ old('title') }}" required maxlength="255" class="mt-2 w-full rounded-xl border-slate-300" placeholder="A precise academic title"></label>
    <div class="grid gap-5 md:grid-cols-2">
        <label class="block"><span class="text-sm font-medium">Research area</span><input name="research_area" value="{{ old('research_area') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
        <label class="block"><span class="text-sm font-medium">Keywords</span><input name="keywords" value="{{ old('keywords') }}" class="mt-2 w-full rounded-xl border-slate-300" placeholder="education, analytics, assessment"></label>
        <label class="block"><span class="text-sm font-medium">Supervisor</span><select name="supervisor_id" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Assign later</option>@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}" @selected(old('supervisor_id')==$supervisor->id)>{{ $supervisor->full_name }}</option>@endforeach</select></label>
        <label class="block"><span class="text-sm font-medium">Expected completion</span><input type="date" name="expected_completion_date" value="{{ old('expected_completion_date') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    </div>
    <label class="block"><span class="text-sm font-medium">Proposal summary / abstract</span><textarea name="abstract" rows="6" class="mt-2 w-full rounded-xl border-slate-300">{{ old('abstract') }}</textarea></label>
    <div class="flex justify-end gap-3"><a href="{{ route('research.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm">Cancel</a><button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Create workspace</button></div>
</form>
@endsection

@extends('layouts.app')
@section('title', 'Edit Institution')
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.universities') }}" class="acad-link text-sm">← Back to institutions</a>
    <div class="mt-2"><p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-600">Institution settings</p><h1 class="mt-1 text-2xl font-bold">{{ $university->name }}</h1></div>
    <form method="POST" action="{{ route('admin.universities.update',$university) }}" class="acad-card mt-6 grid gap-5 p-6 md:grid-cols-2">@csrf @method('PUT')
        <label class="md:col-span-2 text-sm font-medium">Institution name<input name="name" required value="{{ old('name',$university->name) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Short name<input name="short_name" required value="{{ old('short_name',$university->short_name) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Code<input name="code" maxlength="10" required value="{{ old('code',$university->code) }}" class="mt-1 w-full rounded-xl uppercase"></label>
        <label class="text-sm font-medium">Institution type<select name="institution_type" required class="mt-1 w-full rounded-xl"><option value="university" @selected(old('institution_type',$university->institution_type)==='university')>University</option><option value="polytechnic" @selected(old('institution_type',$university->institution_type)==='polytechnic')>Polytechnic</option></select></label>
        <label class="text-sm font-medium">Ownership<select name="ownership" class="mt-1 w-full rounded-xl"><option value="">Select</option>@foreach(['Federal','State','Private'] as $option)<option value="{{ $option }}" @selected(old('ownership',$university->ownership)===$option)>{{ $option }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">State<input name="state" value="{{ old('state',$university->state) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Regulator<input name="regulator" value="{{ old('regulator',$university->regulator) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Email<input type="email" name="email" value="{{ old('email',$university->email) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Phone<input name="phone" value="{{ old('phone',$university->phone) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Website<input type="url" name="website" value="{{ old('website',$university->website) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="text-sm font-medium">Timezone<input name="timezone" required value="{{ old('timezone',$university->timezone ?: config('app.timezone')) }}" class="mt-1 w-full rounded-xl"></label>
        <label class="md:col-span-2 text-sm font-medium">Address<textarea name="address" rows="3" class="mt-1 w-full rounded-xl">{{ old('address',$university->address) }}</textarea></label>
        @if($university->catalog_source)<div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600"><p><strong>Catalogue source:</strong> {{ $university->catalog_source }}</p><p class="mt-1"><strong>Last regulator verification:</strong> {{ $university->catalog_verified_at?->format('M j, Y H:i') ?? 'Fallback/manual data — verify before relying on it.' }}</p></div>@endif
        <label class="flex items-center gap-2 md:col-span-2 text-sm font-medium"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$university->is_active))> Active institution</label>
        <div class="flex justify-end gap-3 md:col-span-2"><a href="{{ route('admin.universities') }}" class="rounded-xl border px-5 py-2.5">Cancel</a><button class="acad-primary-button">Save institution</button></div>
    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Edit User')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6"><a href="{{ route('admin.users') }}" class="text-sm text-indigo-600">← Back to users</a><h1 class="mt-2 text-2xl font-bold">Manage {{ $user->full_name }}</h1><p class="text-sm text-slate-600">Update identity, institutional assignment, role and account status.</p></div>
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5 rounded-2xl border bg-white p-6">@csrf @method('PUT')
        <div class="grid gap-4 md:grid-cols-2"><label>First name<input name="first_name" required value="{{ old('first_name', $user->first_name) }}" class="mt-1 w-full rounded-xl border-slate-300"></label><label>Last name<input name="last_name" required value="{{ old('last_name', $user->last_name) }}" class="mt-1 w-full rounded-xl border-slate-300"></label></div>
        <div class="grid gap-4 md:grid-cols-2"><label>Role<select name="role" required class="mt-1 w-full rounded-xl border-slate-300">@foreach(['member'=>'Platform member','student'=>'Student','lecturer'=>'Lecturer','department_admin'=>'Department administrator','university_admin'=>'University administrator'] as $value=>$label)<option value="{{ $value }}" @selected(old('role',$user->role)===$value)>{{ $label }}</option>@endforeach</select></label><label>Department<select name="department_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id',$user->department_id)==$department->id)>{{ $department->faculty?->short_name }} · {{ $department->name }}</option>@endforeach</select></label></div>
        <label class="flex items-center gap-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user->is_active))> Account is active</label>
        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Changing an institutional role requires a valid department. Platform members remain independent and have no forced university affiliation.</div>
        <div class="flex justify-end gap-3"><a href="{{ route('admin.users') }}" class="rounded-xl border px-5 py-2">Cancel</a><button class="rounded-xl bg-indigo-600 px-5 py-2 font-semibold text-white">Save account</button></div>
    </form>
</div>
@endsection

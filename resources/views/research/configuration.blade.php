@extends('layouts.app')
@section('title','Research Configuration')
@section('page-title','Research Types & Workflows')
@section('page-subtitle','Version-safe institution configuration for formal academic research')
@section('content')
<div class="mb-6 flex flex-wrap gap-3">
    <a href="{{ route('research.index') }}" class="rounded-xl border px-4 py-2 text-sm">Research Studio</a>
    <a href="{{ route('research.templates.index') }}" class="rounded-xl border px-4 py-2 text-sm">Template versions</a>
</div>
@if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="space-y-8">
<section class="rounded-2xl border bg-white p-6">
<h2 class="text-xl font-semibold">Create workflow</h2><p class="mt-1 text-sm text-slate-600">Stages are validated for one initial stage, one final stage and valid transition targets. Editing a workflow already used by a project creates a safe replacement version.</p>
<form method="POST" action="{{ route('research.configuration.workflows.store') }}" class="mt-5 grid gap-4 lg:grid-cols-2">@csrf
<label class="text-sm font-medium">Key<input name="key" required placeholder="postgraduate_thesis" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="text-sm font-medium">Name<input name="name" required placeholder="Postgraduate Thesis Workflow" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="lg:col-span-2 text-sm font-medium">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-slate-300"></textarea></label>
<label class="text-sm font-medium">Settings JSON<textarea name="settings" rows="8" class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs">{}</textarea></label>
<label class="text-sm font-medium">Stages JSON<textarea name="stages" rows="8" required class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs">[
  {"key":"creation","name":"Creation","actor_roles":["student"],"is_initial":true,"settings":{"allowed_transitions":["review"]}},
  {"key":"review","name":"Human Review","actor_roles":["lecturer"],"requirements":{"required_sections_approved":true},"is_final":true}
]</textarea></label>
<label class="text-sm"><input type="checkbox" name="is_active" value="1" checked> Active for new projects</label>
<div><button class="rounded-xl bg-blue-600 px-5 py-2.5 text-white">Create workflow</button></div>
</form></section>

<section><h2 class="mb-4 text-xl font-semibold">Existing workflows</h2><div class="grid gap-5 xl:grid-cols-2">
@forelse($workflows as $workflow)
<form method="POST" action="{{ route('research.configuration.workflows.update',$workflow) }}" class="rounded-2xl border bg-white p-5">@csrf @method('PUT')
<div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $workflow->name }}</h3><p class="text-xs text-slate-500">{{ $workflow->key }} · {{ $workflow->instances->count() }} recorded instance(s)</p></div><span class="rounded-full px-2 py-1 text-xs {{ $workflow->is_active?'bg-green-100 text-green-700':'bg-slate-100' }}">{{ $workflow->is_active?'Active':'Retired' }}</span></div>
<div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-xs font-medium">Key<input name="key" value="{{ $workflow->key }}" required class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-xs font-medium">Name<input name="name" value="{{ $workflow->name }}" required class="mt-1 w-full rounded-lg border-slate-300"></label></div>
<label class="mt-3 block text-xs font-medium">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-slate-300">{{ $workflow->description }}</textarea></label>
<label class="mt-3 block text-xs font-medium">Settings JSON<textarea name="settings" rows="5" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs">{{ json_encode($workflow->settings ?? new stdClass, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea></label>
<label class="mt-3 block text-xs font-medium">Stages JSON<textarea name="stages" rows="12" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs">{{ json_encode($workflow->stages->map(fn($stage)=>['key'=>$stage->key,'name'=>$stage->name,'deadline_days'=>$stage->deadline_days,'actor_roles'=>$stage->actor_roles ?? [],'settings'=>$stage->settings ?? new stdClass,'requirements'=>$stage->requirements ?? new stdClass,'is_initial'=>$stage->is_initial,'is_final'=>$stage->is_final])->values(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea></label>
<div class="mt-3 flex items-center justify-between"><label class="text-sm"><input type="checkbox" name="is_active" value="1" @checked($workflow->is_active)> Active</label><button class="rounded-xl border border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">Save safely</button></div>
</form>
@empty <p class="text-slate-500">No workflows configured.</p>@endforelse
</div></section>

<section class="rounded-2xl border bg-white p-6"><h2 class="text-xl font-semibold">Create research type</h2>
<form method="POST" action="{{ route('research.configuration.types.store') }}" class="mt-5 grid gap-4 lg:grid-cols-2">@csrf
<label class="text-sm font-medium">Name<input name="name" required placeholder="Dissertation" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="text-sm font-medium">Slug<input name="slug" placeholder="dissertation" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="text-sm font-medium">Workflow<select name="workflow_definition_id" required class="mt-1 w-full rounded-xl border-slate-300">@foreach($workflows->where('is_active',true) as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }}</option>@endforeach</select></label>
<label class="text-sm font-medium">Maximum similarity (%)<input type="number" name="similarity_threshold" value="20" min="0" max="100" step="0.01" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="lg:col-span-2 text-sm font-medium">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-slate-300"></textarea></label>
<label class="text-sm font-medium">Template schema JSON<textarea name="template_schema" rows="10" class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs">{"sections":[{"key":"abstract","title":"Abstract","required":true},{"key":"chapter_1","title":"Chapter 1","required":true},{"key":"references","title":"References","required":true}]}</textarea></label>
<label class="text-sm font-medium">Validation rules JSON<textarea name="validation_rules" rows="10" class="mt-1 w-full rounded-xl border-slate-300 font-mono text-xs">{"required_sections":true,"citations":true,"similarity":true,"minimum_readiness":70}</textarea></label>
<label class="text-sm"><input type="checkbox" name="publication_eligible" value="1" checked> Eligible for controlled Knowledge Hub publication</label><label class="text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
<div><button class="rounded-xl bg-blue-600 px-5 py-2.5 text-white">Create research type</button></div>
</form></section>

<section><h2 class="mb-4 text-xl font-semibold">Existing research types</h2><div class="grid gap-5 xl:grid-cols-2">
@foreach($types as $type)<form method="POST" action="{{ route('research.configuration.types.update',$type) }}" class="rounded-2xl border bg-white p-5">@csrf @method('PUT')
<h3 class="font-semibold">{{ $type->name }}</h3><p class="text-xs text-slate-500">{{ $type->projects()->count() }} project(s) · {{ $type->templateVersions->count() }} immutable template version(s)</p>
<div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-xs font-medium">Name<input name="name" value="{{ $type->name }}" required class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-xs font-medium">Slug<input name="slug" value="{{ $type->slug }}" required class="mt-1 w-full rounded-lg border-slate-300"></label></div>
<label class="mt-3 block text-xs font-medium">Workflow<select name="workflow_definition_id" class="mt-1 w-full rounded-lg border-slate-300">@foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected($type->workflow_definition_id===$workflow->id)>{{ $workflow->name }}</option>@endforeach</select></label>
<label class="mt-3 block text-xs font-medium">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-slate-300">{{ $type->description }}</textarea></label>
<label class="mt-3 block text-xs font-medium">Template schema JSON<textarea name="template_schema" rows="8" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs">{{ json_encode($type->template_schema ?? new stdClass, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea></label>
<label class="mt-3 block text-xs font-medium">Validation rules JSON<textarea name="validation_rules" rows="6" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs">{{ json_encode($type->validation_rules ?? new stdClass, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea></label>
<div class="mt-3 grid gap-3 sm:grid-cols-3"><label class="text-xs font-medium">Similarity %<input type="number" name="similarity_threshold" value="{{ $type->similarity_threshold }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="pt-6 text-sm"><input type="checkbox" name="publication_eligible" value="1" @checked($type->publication_eligible)> Publishable</label><label class="pt-6 text-sm"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> Active</label></div>
<div class="mt-4 text-right"><button class="rounded-xl border border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700">Save type</button></div></form>@endforeach
</div></section>
</div>
@endsection

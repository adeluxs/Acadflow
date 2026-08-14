@extends('layouts.app')
@section('title','Reading Lists')
@section('page-title','Reading Lists')
@section('page-subtitle','Private, public, course, research and collaborative collections')
@section('content')
@include('knowledge._nav')
@auth<form method="POST" action="{{ route('knowledge.reading.store') }}" class="mb-6 grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-2">@csrf<input required name="title" class="rounded-xl border-slate-300" placeholder="List title"><input name="description" class="rounded-xl border-slate-300" placeholder="Description"><select name="list_type" class="rounded-xl border-slate-300">@foreach(['private','public','course','research','department','collaborative'] as $type)<option>{{ $type }}</option>@endforeach</select><select name="visibility" class="rounded-xl border-slate-300"><option>private</option><option>public</option><option>institution</option></select><label><input type="checkbox" name="is_collaborative" value="1"> Collaborative</label><button class="rounded-xl bg-blue-600 px-4 py-2 text-white">Create list</button></form>@endauth
<div class="grid gap-4 md:grid-cols-3">@foreach($lists as $list)<a href="{{ route('knowledge.reading.show',$list) }}" class="rounded-2xl border bg-white p-5"><span class="text-xs text-blue-700">{{ $list->list_type }} · {{ $list->items_count }} items</span><h2 class="mt-2 font-semibold">{{ $list->title }}</h2><p class="mt-2 text-sm text-slate-500">{{ $list->description }}</p></a>@endforeach</div><div class="mt-4">{{ $lists->links() }}</div>
@endsection

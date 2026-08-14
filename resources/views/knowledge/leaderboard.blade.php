@extends('layouts.app')
@section('title','Academic Reputation')
@section('page-title','Academic Reputation')
@section('page-subtitle','Quality, research impact and contribution—not raw popularity')
@section('content')
@include('knowledge._nav')
<div class="overflow-hidden rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Rank</th><th>Creator</th><th>Level</th><th>Score</th><th>Publications</th><th>Citations</th></tr></thead><tbody>@foreach($profiles as $profile)<tr class="border-t"><td class="p-4">{{ $profiles->firstItem()+$loop->index }}</td><td><a class="font-semibold text-blue-700" href="{{ route('knowledge.creator',$profile->user) }}">{{ $profile->user?->full_name }}</a></td><td>{{ ucwords(str_replace('_',' ',$profile->level_key)) }}</td><td>{{ number_format((float)$profile->overall_score,1) }}</td><td>{{ $profile->publication_count }}</td><td>{{ $profile->citation_count }}</td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $profiles->links() }}</div>
@endsection

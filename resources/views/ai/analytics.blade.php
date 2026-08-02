@extends('layouts.app')

@section('title', 'AI Analytics')

@section('content')
<div class="max-w-5xl mx-auto">
    <h2 class="text-2xl font-bold mb-2">AI Analytics</h2>
    <p class="text-gray-500 mb-6">Usage and performance metrics for the AI Academic Assistant.</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Total Requests</p>
            <p class="text-2xl font-bold">{{ $summary['total_requests'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Cache Hits</p>
            <p class="text-2xl font-bold text-green-600">{{ $summary['cache_hits'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Rule Engine</p>
            <p class="text-2xl font-bold">{{ $summary['rule_engine_requests'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Provider</p>
            <p class="text-2xl font-bold">{{ $summary['provider_requests'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Est. Savings ($)</p>
            <p class="text-2xl font-bold text-emerald-600">{{ $summary['estimated_savings'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Avg Time (s)</p>
            <p class="text-2xl font-bold">{{ $summary['average_processing_time'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Failure Rate</p>
            <p class="text-2xl font-bold">{{ number_format($summary['failure_rate'] * 100, 2) }}%</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Hybrid Requests</p>
            <p class="text-2xl font-bold">{{ $summary['hybrid_requests'] }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="font-semibold mb-3">Most Used Features</h3>
        @if(empty($summary['top_features']))
            <p class="text-gray-500">No usage data yet.</p>
        @else
            <ul class="space-y-2">
                @foreach($summary['top_features'] as $feature => $count)
                    <li class="flex items-center justify-between text-sm border-b pb-2">
                        <span class="capitalize">{{ str_replace('_', ' ', $feature) }}</span>
                        <span class="font-medium">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

@extends('layouts.auth')
@section('title', 'Notification preferences')
@section('content')
<div class="space-y-5"><h1 class="text-2xl font-bold">{{ $submitted ? 'Preference saved' : 'Manage optional reminders' }}</h1><p class="text-sm text-slate-600">{{ $submitted ? 'You will no longer receive these '.$category.' reminders. Your academic alerts and account security messages keep their own settings.' : 'Stop optional '.$category.' reminders from AcadSyntra. Your account and academic records remain available.' }}</p>@if(!$submitted)<form method="POST" action="{{ request()->fullUrl() }}">@csrf<button class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white">Unsubscribe from these reminders</button></form>@endif<a href="{{ route('notifications.settings') }}" class="block font-semibold text-indigo-600">Open all notification preferences →</a></div>
@endsection

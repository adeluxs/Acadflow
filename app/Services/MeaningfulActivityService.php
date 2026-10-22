<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeaningfulActivityService
{
    public function record(User $user): void
    {
        $now = now();
        $previous = $user->getRawOriginal('last_meaningful_activity_at');
        $lastActivity = $previous ? \Illuminate\Support\Carbon::parse($previous) : null;
        // Authentication already loaded this value for the request. Avoid a
        // no-op write on every navigation; retain the conditional update below
        // to serialize concurrent requests when the five-minute window expires.
        if ($lastActivity && $lastActivity->gt($now->copy()->subMinutes(5)) && $lastActivity->isSameDay($now)) return;
        $changed = User::withoutGlobalScopes()->whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('last_meaningful_activity_at')
                ->orWhere('last_meaningful_activity_at', '<=', $now->copy()->subMinutes(5))
                ->orWhere('last_meaningful_activity_at', '<', $now->copy()->startOfDay()))
            ->update(['last_meaningful_activity_at' => $now]);
        if (! $changed) return;
        $user->setAttribute('last_meaningful_activity_at', $now)->syncOriginalAttribute('last_meaningful_activity_at');
        DB::table('user_activity_days')->upsert([['user_id' => $user->id, 'activity_date' => $now->toDateString(), 'first_activity_at' => $now, 'last_activity_at' => $now]], ['user_id', 'activity_date'], ['last_activity_at']);
        $notices = Notification::where('user_id', $user->id)->where('category', 'engagement')
            ->when($previous, fn ($q) => $q->where('created_at', '>', $previous))->where('created_at', '<=', now())->latest('id')->limit(5)->get();
        foreach ($notices as $notice) app(NotificationTrackingService::class)->record('returned', $user, $notice, key: 'returned:'.$notice->uuid);
        \App\Models\NotificationReminderState::where('user_id', $user->id)->where('kind', 'inactivity')->whereNull('completed_at')->update(['completed_at' => now()]);
    }

    public function onboardingCompleted(User $user): void
    {
        $notice = Notification::where('user_id', $user->id)->where('category', 'onboarding')->latest('id')->first();
        app(NotificationTrackingService::class)->record('onboarding_completed', $user, $notice, key: 'onboarding-completed:'.$user->id.':'.$user->onboarding_completed_at);
        \App\Models\NotificationReminderState::where('user_id', $user->id)->where('kind', 'onboarding')->whereNull('completed_at')->update(['completed_at' => now()]);
    }
}

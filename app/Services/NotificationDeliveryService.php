<?php

namespace App\Services;

use App\Mail\AcademicNotificationMail;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class NotificationDeliveryService
{
    public function deliver(int $id): ?int
    {
        $preferences = app(NotificationPreferences::class);
        $delivery = DB::transaction(function () use ($id, $preferences) {
            $row = NotificationLog::lockForUpdate()->find($id);
            if (! $row || ! in_array($row->status, ['queued', 'retrying', 'deferred', 'processing'], true)) return null;
            if ($row->status === 'processing') {
                if ($row->processing_started_at?->lte(now()->subMinutes(3))) $row->update(['status' => 'unknown', 'error_message' => 'provider_outcome_requires_reconciliation']);
                return null;
            }
            if ($row->next_attempt_at?->isFuture()) return max(1, now()->diffInSeconds($row->next_attempt_at, true));
            $user = User::withoutGlobalScopes()->lockForUpdate()->find($row->user_id);
            $notification = $row->notification;
            if (! $user || ! $notification) { $row->update(['status' => 'cancelled']); return null; }
            $settings = $preferences->settings($user);
            $stillRelevant = app(NotificationReminderService::class)->stillRelevant($notification, $user);
            if (! $user->is_active || ! $stillRelevant || ! $preferences->allows($settings, $notification->type, $notification->category)
                || ! ($settings?->{$row->channel.'_enabled'} ?? true)
                || ($row->channel === 'email' && (! $user->email_verified_at || $settings?->email_suppressed_at))
                || ! SettingService::get($row->channel.'_notifications_enabled', true, $notification->university_id)
                || FeatureAccessService::effectiveStatus('notifications', $notification->university_id) !== FeatureAccessService::STATUS_ENABLED) {
                $row->update(['status' => 'cancelled', 'processing_token' => null]);
                return null;
            }
            if ($quietUntil = $preferences->quietUntil($settings)) {
                $row->update(['status' => 'deferred', 'next_attempt_at' => $quietUntil]);
                return max(1, now()->diffInSeconds($quietUntil, true));
            }
            $limit = max(1, (int) $preferences->option('daily_'.$row->channel.'_limit'));
            $count = NotificationLog::where('user_id', $user->id)->where('channel', $row->channel)->where('id', '!=', $row->id)
                ->where(fn ($q) => $q->where('accepted_at', '>=', now()->subDay())->orWhere(fn ($p) => $p->where('status', 'processing')->where('processing_started_at', '>=', now()->subMinutes(3))))->count();
            if ($count >= $limit) {
                $row->update(['status' => 'deferred', 'next_attempt_at' => now()->addHour()]);
                return 3600;
            }
            if ($row->attempt_count >= 5) { $this->permanentlyFail($row, 'attempt_limit'); return null; }
            $row->update(['status' => 'processing', 'processing_started_at' => now(), 'processing_token' => (string) Str::uuid(), 'attempted_at' => now(), 'attempt_count' => $row->attempt_count + 1]);

            return $row;
        }, 3);
        if (! $delivery || is_numeric($delivery)) return $delivery ? (int) $delivery : null;

        $user = User::withoutGlobalScopes()->findOrFail($delivery->user_id);
        $notification = $delivery->notification;
        $started = microtime(true);
        $providerAccepted = false;
        try {
            if ($delivery->channel === 'email') {
                $sent = Mail::to($user->email)->send(new AcademicNotificationMail($notification, $delivery, $preferences->settings($user)));
                $simulated = in_array(config('mail.default'), ['log', 'array'], true) || ! $sent;
                $result = ['success' => true, 'simulated' => $simulated, 'provider_message_id' => $sent?->getMessageId()];
            } else {
                $subscription = PushSubscription::where('user_id', $user->id)->find($delivery->push_subscription_id);
                $result = $subscription ? app(PushNotificationService::class)->deliverToSubscription($subscription, 'AcadSyntra update', 'Open AcadSyntra to view your academic update securely.', [
                    'notification_id' => $notification->uuid,
                    'url' => URL::temporarySignedRoute('notification.delivery.open', now()->addDays(30), ['delivery' => $delivery->uuid]),
                ]) : ['success' => false, 'permanent' => true, 'reason' => 'subscription_missing'];
            }
            if (! $result['success']) {
                if ($result['permanent'] ?? false) { $this->permanentlyFail($delivery, $result['reason'] ?? 'provider_rejected'); return null; }
                throw new \RuntimeException('temporary_provider_rejection');
            }
            $providerAccepted = empty($result['simulated']);
            $status = ! empty($result['simulated']) ? 'simulated' : 'accepted';
            $updated = NotificationLog::whereKey($delivery->id)->where('processing_token', $delivery->processing_token)->update([
                'status' => $status, 'accepted_at' => $status === 'accepted' ? now() : null, 'provider_message_id' => $result['provider_message_id'] ?? null,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000), 'error_message' => null, 'processing_token' => null, 'next_attempt_at' => null,
            ]);
            if ($updated) app(NotificationTrackingService::class)->record($status, $user, $notification, $delivery, key: $status.':'.$delivery->uuid);
        } catch (\Throwable $exception) {
            // An acknowledged send followed by a persistence failure must never
            // become an automatic resend. SMTP errors after DATA are similarly
            // uncertain and need provider reconciliation rather than a retry.
            $smtpUncertain = $exception instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface && preg_match('/\b(?:DATA|BDAT)\b/i', $exception->getDebug());
            if ($providerAccepted || $smtpUncertain) {
                $delivery->update(['status' => 'unknown', 'error_message' => 'provider_outcome_requires_reconciliation', 'processing_token' => null, 'next_attempt_at' => null]);
                app(NotificationTrackingService::class)->record('unknown', $user, $notification, $delivery, key: 'unknown:'.$delivery->uuid);
                return null;
            }
            $delivery->update(['status' => 'retrying', 'next_attempt_at' => now()->addSeconds([60, 300, 1800, 3600, 3600][min(4, $delivery->attempt_count - 1)]), 'processing_token' => null, 'error_message' => 'transport_exception:'.class_basename($exception)]);
            app(NotificationTrackingService::class)->record('retrying', $user, $notification, $delivery, ['attempt' => $delivery->attempt_count], key: 'retry:'.$delivery->uuid.':'.$delivery->attempt_count);
            throw new \RuntimeException('Notification transport temporarily unavailable. See the delivery log for the classified error.');
        }

        return null;
    }

    public function permanentlyFail(NotificationLog $delivery, string $reason): void
    {
        if ($delivery->fresh()?->accepted_at) return;
        $delivery->update(['status' => 'failed', 'error_message' => mb_substr($reason, 0, 100), 'processing_token' => null, 'next_attempt_at' => null]);
        $user = User::withoutGlobalScopes()->find($delivery->user_id);
        app(NotificationTrackingService::class)->record('failed', $user, $delivery->notification, $delivery, ['reason' => $reason], key: 'failed:'.$delivery->uuid.':'.$delivery->attempt_count);
    }
}

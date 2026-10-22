<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\NotificationPreferences;
use App\Services\NotificationTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationInteractionController extends Controller
{
    public function open(Request $request, Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $this->clicked($request, $notification);

        return redirect()->to(app(NotificationPreferences::class)->safeUrl($notification->data['url'] ?? null));
    }

    public function delivery(Request $request, string $delivery)
    {
        $log = NotificationLog::where('uuid', $delivery)->firstOrFail();
        abort_unless((int) $log->user_id === (int) $request->user()->id, 403);
        abort_unless($log->notification, 404);
        $this->clicked($request, $log->notification, $log);

        return redirect()->to(app(NotificationPreferences::class)->safeUrl($log->notification->data['url'] ?? null));
    }

    private function clicked(Request $request, Notification $notice, ?NotificationLog $delivery = null): void
    {
        if ($request->isMethod('HEAD') || str_contains(strtolower($request->header('Sec-Purpose', '').' '.$request->header('Purpose', '')), 'prefetch')) return;
        $notice->update(['read_at' => $notice->read_at ?? now(), 'clicked_at' => $notice->clicked_at ?? now()]);
        if ($affiliationId = ($notice->data['affiliation_id'] ?? null)) {
            $membership = $request->user()->institutionAffiliations()->approved()->find($affiliationId);
            if ($membership && app(\App\Services\AcademicOrganisationService::class)->contextAvailable($membership) && $request->hasSession()) $request->session()->put('academic_affiliation_id', $membership->id);
        }
        if ($delivery) $delivery->update(['clicked_at' => $delivery->clicked_at ?? now()]);
        app(NotificationTrackingService::class)->record('clicked', $request->user(), $notice, $delivery, key: 'click:'.($delivery?->uuid ?? $notice->uuid));
    }

    public function unsubscribe(Request $request, string $delivery)
    {
        $log = NotificationLog::where('uuid', $delivery)->with('notification')->firstOrFail();
        abort_unless(in_array($log->notification?->category, ['engagement', 'onboarding'], true), 422, 'This message is not an optional reminder.');
        if ($request->isMethod('GET')) return view('notifications.unsubscribe', ['submitted' => false, 'category' => $log->notification->category]);
        $field = $log->notification->category === 'onboarding' ? 'onboarding_reminders' : 'engagement_reminders';
        NotificationSetting::updateOrCreate(['user_id' => $log->user_id], [$field => false]);
        $user = User::withoutGlobalScopes()->find($log->user_id);
        app(NotificationTrackingService::class)->record('unsubscribed', $user, $log->notification, $log, key: 'unsubscribe:'.$log->user_id.':'.$field);

        return view('notifications.unsubscribe', ['submitted' => true, 'category' => $log->notification->category]);
    }

    public function pixel(Request $request, string $delivery)
    {
        $log = NotificationLog::where('uuid', $delivery)->firstOrFail();
        $settings = NotificationSetting::where('user_id', $log->user_id)->first();
        if ($log->channel === 'email' && $settings?->email_open_tracking && ! $request->isMethod('HEAD')) {
            $log->update(['opened_at' => $log->opened_at ?? now()]);
            app(NotificationTrackingService::class)->record('opened', User::withoutGlobalScopes()->find($log->user_id), $log->notification, $log, key: 'open:'.$log->uuid);
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'private, no-store']);
    }

    public function provider(Request $request)
    {
        $secret = (string) config('notification_delivery.provider_webhook_secret');
        $timestamp = (string) $request->header('X-AcadSyntra-Timestamp', '');
        abort_unless($secret !== '' && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 403);
        abort_unless(hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), (string) $request->header('X-AcadSyntra-Signature')), 403);
        $data = $request->validate(['delivery_id' => ['required', 'uuid'], 'event_id' => ['required', 'string', 'max:160'], 'event' => ['required', Rule::in(['delivered', 'bounced', 'complained', 'opened', 'clicked'])]]);
        $log = NotificationLog::where('uuid', $data['delivery_id'])->where('channel', 'email')->firstOrFail();
        abort_unless($log->accepted_at, 422, 'Only accepted deliveries may receive provider confirmations.');
        DB::transaction(function () use ($log, $data): void {
            $log = NotificationLog::lockForUpdate()->findOrFail($log->id);
            $user = User::withoutGlobalScopes()->find($log->user_id);
            $settings = NotificationSetting::where('user_id', $log->user_id)->first();
            if ($data['event'] === 'opened' && ! $settings?->email_open_tracking) return;
            app(NotificationTrackingService::class)->record($data['event'], $user, $log->notification, $log, key: 'provider:'.$data['event_id']);
            if (in_array($data['event'], ['bounced', 'complained'], true)) {
                $log->update(['status' => $data['event'], 'error_message' => 'provider_'.$data['event']]);
                NotificationSetting::updateOrCreate(['user_id' => $log->user_id], ['email_suppressed_at' => now(), 'email_suppression_reason' => $data['event']]);
            } elseif ($data['event'] === 'delivered' && ! in_array($log->status, ['bounced', 'complained'], true)) $log->update(['status' => 'delivered', 'delivered_at' => $log->delivered_at ?? now()]);
            elseif ($data['event'] === 'opened') $log->update(['opened_at' => $log->opened_at ?? now()]);
            elseif ($data['event'] === 'clicked') $log->update(['clicked_at' => $log->clicked_at ?? now()]);
        });

        return response()->json(['accepted' => true]);
    }
}

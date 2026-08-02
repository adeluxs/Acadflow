<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private ?PushNotificationService $pushService = null
    ) {}

    /**
     * Send notification to a user via all enabled channels
     */
    public function send(User $user, NotificationType $type, string $title, string $message, array $data = []): void
    {
        // Check global channel toggles
        $globalInAppEnabled = $this->isGlobalChannelEnabled('in_app');
        $globalPushEnabled = $this->isGlobalChannelEnabled('push');
        $globalEmailEnabled = $this->isGlobalChannelEnabled('email');

        $notification = null;

        // Create in-app notification (unless globally disabled)
        if ($globalInAppEnabled) {
            $notification = $user->notifications()->create([
                'uuid' => Str::uuid(),
                'type' => $type->value,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            // Log database delivery
            $this->logDeliveryAttempt($notification, $user, 'database', 'success');
        }

        $settings = $this->getSettings($user);

        // Try push notification first
        $pushSent = false;
        if ($globalPushEnabled && ($settings?->push_enabled ?? true) && $this->pushService) {
            $pushSent = $this->sendPushNotification($user, $title, $message, $data, $notification);
        }

        // Email: send if enabled, OR as fallback if push failed
        if ($globalEmailEnabled && ($settings?->email_enabled ?? true)) {
            if ($this->shouldSendEmail($user, $type) || !$pushSent) {
                $this->sendEmailNotification($user, $type, $title, $message, $data, $notification);
            }
        }
    }

    /**
     * Send push notification
     */
    protected function sendPushNotification(User $user, string $title, string $message, array $data, $notification): bool
    {
        try {
            $result = $this->pushService->send($user, $title, $message, $data);
            $count = $result ? 1 : 0;

            if ($notification) {
                $this->logDeliveryAttempt($notification, $user, 'push', $count > 0 ? 'success' : 'failed');
            }

            return $count > 0;
        } catch (\Exception $e) {
            if ($notification) {
                $this->logDeliveryAttempt($notification, $user, 'push', 'failed', $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Send email notification with template
     */
    protected function sendEmailNotification(User $user, NotificationType $type, string $title, string $message, array $data, $notification): void
    {
        try {
            $template = $this->getEmailTemplate($type, $data);

            Mail::raw($template['body'], function ($mail) use ($user, $template, $title) {
                $mail->to($user->email)
                    ->subject($template['subject'] ?? $title);
            });

            if ($notification) {
                $this->logDeliveryAttempt($notification, $user, 'email', 'success');
            }
        } catch (\Exception $e) {
            if ($notification) {
                $this->logDeliveryAttempt($notification, $user, 'email', 'failed', $e->getMessage());
            }
        }
    }

    /**
     * Check if a channel is globally enabled (admin-controlled)
     */
    protected function isGlobalChannelEnabled(string $channel): bool
    {
        $key = "notifications_{$channel}_enabled";
        try {
            $setting = Setting::where('key', $key)->first();

            return $setting ? (string) $setting->value === '1' : true;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Log a delivery attempt to notification_logs
     */
    protected function logDeliveryAttempt($notification, User $user, string $channel, string $status, ?string $error = null): void
    {
        if (! $notification) {
            return;
        }

        try {
            NotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'status' => $status,
                'error_message' => $error,
                'attempted_at' => now(),
                'attempt_count' => 1,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log notification delivery', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user's notification settings
     */
    protected function getSettings(User $user): ?NotificationSetting
    {
        return NotificationSetting::where('user_id', $user->id)->first();
    }

    /**
     * Check if should send email for this notification type
     */
    protected function shouldSendEmail(User $user, NotificationType $type): bool
    {
        $settings = $this->getSettings($user);

        return match($type) {
            NotificationType::MATERIAL_UPLOADED => $settings?->notify_on_material_upload ?? true,
            NotificationType::ASSIGNMENT_CREATED => $settings?->notify_on_assignment ?? true,
            NotificationType::NEW_DISCUSSION => $settings?->notify_on_discussion ?? true,
            NotificationType::SUBMISSION_CONFIRMATION => $settings?->notify_on_submission ?? true,
            NotificationType::SYSTEM_ANNOUNCEMENT => $settings?->notify_on_announcement ?? true,
            default => true,
        };
    }

    /**
     * Get email template for notification type
     */
    protected function getEmailTemplate(NotificationType $type, array $data): array
    {
        return match($type) {
            NotificationType::MATERIAL_UPLOADED => [
                'subject' => 'New Material Uploaded',
                'body' => "New materials have been uploaded to your course.\n\n" .
                    ($data['course_name'] ?? 'Course'),
            ],
            NotificationType::ASSIGNMENT_CREATED => [
                'subject' => 'New Assignment',
                'body' => "A new assignment has been created.\n\n" .
                    ($data['task_title'] ?? 'Assignment'),
            ],
            default => [
                'subject' => 'Notification',
                'body' => $data['message'] ?? 'You have a new notification.',
            ],
        };
    }
}

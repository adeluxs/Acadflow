<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification as BaseNotification;

class SmsChannel
{
    public function __construct(protected SmsService $sms) {}

    public function send(object $notifiable, BaseNotification $notification): void
    {
        $message = $notification->toSms($notifiable);

        if (! $message) {
            return;
        }

        $phone = $notifiable->phone;

        if (! $phone) {
            return;
        }

        try {
            $this->sms->send($phone, $message);
        } catch (\Throwable $e) {
            \Log::warning('SMS notification failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

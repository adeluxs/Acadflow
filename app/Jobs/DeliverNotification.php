<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;
    public array $backoff = [60, 300, 1800, 3600];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(config('notification_delivery.queue', 'notifications'));
        $this->onConnection(config('notification_delivery.connection', 'database'));
    }

    public function handle(NotificationDeliveryService $deliveries): void
    {
        $delay = $deliveries->deliver($this->deliveryId);
        if ($delay && $this->job) $this->release($delay);
    }

    public function failed(?\Throwable $exception): void
    {
        $delivery = NotificationLog::find($this->deliveryId);
        if ($delivery?->status === 'processing') {
            $delivery->update(['status' => 'unknown', 'error_message' => 'provider_outcome_requires_reconciliation', 'processing_token' => null, 'next_attempt_at' => null]);
            return;
        }
        if ($delivery && in_array($delivery->status, ['queued', 'retrying', 'processing'], true)) {
            app(NotificationDeliveryService::class)->permanentlyFail($delivery, 'queue_attempts_exhausted');
        }
    }
}

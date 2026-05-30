<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserSubscription $subscription, public string $reason = '')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;

        $message = (new MailMessage)
            ->subject('Subscription Payment Failed - ' . $plan->display_name)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('We were unable to process your subscription payment.')
            ->line('Plan: ' . $plan->display_name)
            ->line('Amount: $' . number_format($this->subscription->amount, 2));

        if ($this->reason) {
            $message->line('Reason: ' . $this->reason);
        }

        $message->line('Please update your payment method to avoid service interruption.')
            ->action('Update Payment Method', url('/subscription'))
            ->line('If you need help, please contact our support team.');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_failed',
            'message' => 'Payment failed for ' . $plan->display_name . ' subscription',
            'plan' => $plan->display_name,
            'amount' => $this->subscription->amount,
            'reason' => $this->reason,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserSubscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;

        return (new MailMessage)
            ->subject('Subscription Activated - ' . $plan->display_name)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been successfully activated!')
            ->line('Plan: ' . $plan->display_name)
            ->line('Amount: $' . number_format($this->subscription->amount, 2) . ' per ' . $this->subscription->billing_cycle)
            ->line('Billing Cycle: ' . ucfirst($this->subscription->billing_cycle))
            ->line('Start Date: ' . $this->subscription->started_at->format('M d, Y'))
            ->line('Next Billing Date: ' . $this->subscription->ends_at->format('M d, Y'))
            ->action('View Subscription', url('/subscription'))
            ->line('Thank you for subscribing to our platform!');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_activated',
            'message' => 'Your ' . $plan->display_name . ' subscription has been activated',
            'plan' => $plan->display_name,
            'amount' => $this->subscription->amount,
            'billing_cycle' => $this->subscription->billing_cycle,
            'started_at' => $this->subscription->started_at->toDateTimeString(),
            'ends_at' => $this->subscription->ends_at->toDateTimeString(),
        ];
    }
}

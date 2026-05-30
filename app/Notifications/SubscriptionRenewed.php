<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification implements ShouldQueue
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
            ->subject('Subscription Renewed - ' . $plan->display_name)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been successfully renewed!')
            ->line('Plan: ' . $plan->display_name)
            ->line('Amount: $' . number_format($this->subscription->amount, 2) . ' per ' . $this->subscription->billing_cycle)
            ->line('Next Billing Date: ' . $this->subscription->ends_at->format('M d, Y'))
            ->action('View Subscription', url('/subscription'))
            ->line('Thank you for continuing with our service!');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_renewed',
            'message' => 'Your ' . $plan->display_name . ' subscription has been renewed',
            'plan' => $plan->display_name,
            'amount' => $this->subscription->amount,
            'ends_at' => $this->subscription->ends_at->toDateTimeString(),
        ];
    }
}

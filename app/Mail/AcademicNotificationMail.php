<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class AcademicNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(public Notification $record, public NotificationLog $delivery, public ?NotificationSetting $preferences) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'AcadSyntra · '.ucfirst($this->record->category).' update');
    }

    public function headers(): Headers
    {
        $headers = ['X-AcadSyntra-Delivery-ID' => $this->delivery->uuid];
        if (in_array($this->record->category, ['onboarding', 'engagement'], true)) {
            $headers['List-Unsubscribe'] = '<'.$this->unsubscribeUrl().'>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(messageId: 'delivery-'.$this->delivery->uuid.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'acadsyntra.invalid'), text: $headers);
    }

    public function unsubscribeUrl(): string
    {
        return URL::temporarySignedRoute('notification.unsubscribe', now()->addDays(180), ['delivery' => $this->delivery->uuid]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: [
            'title' => 'Your AcadSyntra '.($this->record->category === 'onboarding' ? 'setup is waiting' : 'workspace has an update'),
            'body' => $this->record->category === 'onboarding' ? "You're almost there. Continue your setup to unlock your personalised academic workspace." : 'Sign in to view your update securely. Academic records and confidential feedback are available inside your account.',
            'data' => ['url' => URL::temporarySignedRoute('notification.delivery.open', now()->addDays(30), ['delivery' => $this->delivery->uuid])],
            'unsubscribeUrl' => in_array($this->record->category, ['onboarding', 'engagement'], true) ? $this->unsubscribeUrl() : null,
            'trackingUrl' => $this->preferences?->email_open_tracking ? URL::temporarySignedRoute('notification.email.pixel', now()->addDays(30), ['delivery' => $this->delivery->uuid]) : null,
        ]);
    }
}

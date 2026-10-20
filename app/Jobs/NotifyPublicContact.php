<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContactMessage;
use App\Services\PublicWebsite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class NotifyPublicContact implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(public int $contactId)
    {
        $this->onQueue('default');
    }

    public function backoff(): array { return [60, 300]; }

    public function handle(): void
    {
        $recipient = PublicWebsite::supportEmail();
        $contact = ContactMessage::find($this->contactId);
        if (! $recipient || ! $contact) return;

        // No visitor text in email headers and no sensitive message body in
        // outbound mail or queue payloads: staff review it after signing in.
        Mail::raw('A new website enquiry is available in the AcadSyntra support inbox. Reference: '.$contact->reference."\n\n".route('admin.contact.index'), function ($mail) use ($recipient): void {
            $mail->to($recipient)->subject('New AcadSyntra website enquiry');
        });
    }
}

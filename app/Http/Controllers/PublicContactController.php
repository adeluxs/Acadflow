<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\NotifyPublicContact;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'privacy_acknowledged' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
            'form_token' => ['required', 'string', 'size:48'],
        ], [
            'message.min' => 'Please give us at least 20 characters of context.',
            'privacy_acknowledged.accepted' => 'Please acknowledge how your enquiry will be handled.',
            'website.max' => 'Please refresh the form and try again.',
        ]);

        $tokens = (array) $request->session()->get('public_contact_tokens', []);
        $issued = $tokens[$data['form_token']] ?? null;
        if (! is_int($issued) || time() - $issued < 2 || time() - $issued > 86400) {
            throw ValidationException::withMessages(['form_token' => 'Please refresh the contact page, take a moment to complete the form and try again.']);
        }

        $message = ContactMessage::create([
            'reference' => (string) Str::uuid(), 'name' => trim($data['name']),
            'email' => trim($data['email']), 'topic' => $data['topic'], 'message' => trim($data['message']),
        ]);
        unset($tokens[$data['form_token']]);
        $request->session()->put('public_contact_tokens', $tokens);

        try {
            NotifyPublicContact::dispatch($message->id);
        } catch (\Throwable) {
            // The inbox is the source of truth even if the queue/mail is down.
            Log::warning('Contact alert dispatch failed; enquiry retained in the admin inbox.', ['contact_id' => $message->id]);
        }

        return redirect()->route('public.contact')->with('contact_reference', $message->reference);
    }
}

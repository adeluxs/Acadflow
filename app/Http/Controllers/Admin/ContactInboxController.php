<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactInboxController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $status = $request->query('status') === 'closed' ? 'closed' : 'open';
        $messages = ContactMessage::where('status', $status)->latest('id')->paginate(20)->withQueryString();

        return response()->view('admin.contact.index', compact('messages', 'status'))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, ContactMessage $contactMessage)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);
        $contactMessage->update($data);

        return back()->with('success', 'Enquiry status updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CreatorMonetizationApplication;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KnowledgeCreatorMonetizationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_publisher_monetization_enabled', true), 404);
        $data = $request->validate(['statement' => ['required', 'string', 'min:30', 'max:3000'], 'payout_account_id' => ['nullable', 'integer', 'exists:payout_accounts,id'], 'ownership_declaration' => ['accepted'], 'terms_accepted' => ['accepted']]);
        if (! empty($data['payout_account_id'])) {
            abort_unless($request->user()->payoutAccounts()->whereKey($data['payout_account_id'])->exists(), 403);
        }
        $active = CreatorMonetizationApplication::query()->where('user_id', $request->user()->id)->whereIn('status', ['pending', 'approved'])->exists();
        if ($active) {
            throw ValidationException::withMessages(['application' => 'You already have a pending or approved monetization application.']);
        }
        CreatorMonetizationApplication::create($data + ['user_id' => $request->user()->id, 'status' => 'pending', 'submitted_at' => now()]);

        return back()->with('success', 'Creator monetization application submitted for review. Free publishing remains available.');
    }
}

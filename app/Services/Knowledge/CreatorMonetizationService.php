<?php

namespace App\Services\Knowledge;

use App\Models\CreatorMonetizationApplication;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Validation\ValidationException;

class CreatorMonetizationService
{
    public function latest(User $user): ?CreatorMonetizationApplication
    {
        return CreatorMonetizationApplication::query()->where('user_id', $user->id)->latest('id')->first();
    }

    public function isApproved(User $user): bool
    {
        return (bool) SettingService::getGlobal('knowledge_publisher_monetization_enabled', true)
            && $this->latest($user)?->isApproved();
    }

    public function assertCanSell(User $user): void
    {
        if (! SettingService::getGlobal('knowledge_paid_content_enabled', true)) {
            throw ValidationException::withMessages(['access_type' => 'Paid Knowledge Hub content is currently disabled. You can continue publishing for free.']);
        }
        if (! $this->isApproved($user)) {
            throw ValidationException::withMessages(['access_type' => 'Creator monetization approval is required before you can sell a publication or premium chapter.']);
        }
    }
}

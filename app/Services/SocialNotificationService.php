<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialNotificationService
{
    public function send(User $user, string $type, string $title, string $message, array $data = []): void
    {
        $user->notifications()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function sendMany(iterable $users, string $type, string $title, string $message, array $data = []): void
    {
        Collection::make($users)->unique('id')->each(fn (User $user) => $this->send($user, $type, $title, $message, $data));
    }
}

<?php

namespace App\Services\Media;

use App\Models\CommerceEntitlement;
use App\Models\MediaAccessLog;
use App\Models\MediaAsset;
use App\Models\SecureDownloadToken;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureMediaDeliveryService
{
    public function __construct(private readonly SafeFileDeliveryService $files) {}
    public function createToken(MediaAsset $asset, ?User $user, ?CommerceEntitlement $entitlement = null, int $minutes = 15, int $maxDownloads = 1): string
    {
        $plain = Str::random(64);
        SecureDownloadToken::create([
            'token_hash' => hash('sha256', $plain),
            'user_id' => $user?->id,
            'media_asset_id' => $asset->id,
            'commerce_entitlement_id' => $entitlement?->id,
            'max_downloads' => max(1, $maxDownloads),
            'expires_at' => now()->addMinutes(max(1, $minutes)),
        ]);

        return $plain;
    }

    public function consume(string $plainToken, ?User $user, string $ipAddress = '', string $userAgent = ''): StreamedResponse
    {
        $token = SecureDownloadToken::query()
            ->with('mediaAsset')
            ->where('token_hash', hash('sha256', $plainToken))
            ->firstOrFail();

        abort_if($token->revoked_at || $token->expires_at->isPast(), 410, 'This download link has expired.');
        abort_if($token->download_count >= $token->max_downloads, 410, 'This download limit has been reached.');
        abort_if($token->user_id && (! $user || $token->user_id !== $user->id), 403);
        abort_unless(in_array($token->mediaAsset->scan_status, ['clean', 'skipped'], true), 423, 'This file is unavailable until security checks pass.');
        if ($token->commerce_entitlement_id) {
            abort_unless($token->entitlement && $token->entitlement->status === 'active' && (! $token->entitlement->expires_at || $token->entitlement->expires_at->isFuture()), 403, 'The purchase entitlement is no longer active.');
        }

        // Open the file before consuming the one-time token. If storage is
        // unavailable or the object is missing, the user keeps the token and
        // receives a controlled 404/503 instead of a metadata exception.
        $response = $this->files->stream(
            $token->mediaAsset->disk,
            $token->mediaAsset->path,
            $token->mediaAsset->original_name,
            $token->mediaAsset->mime_type,
            'attachment'
        );

        $token->increment('download_count');
        $token->update(['last_used_at' => now()]);
        MediaAccessLog::create([
            'media_asset_id' => $token->media_asset_id,
            'user_id' => $user?->id,
            'action' => 'download',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => ['secure_download_token_id' => $token->id],
            'created_at' => now(),
        ]);

        return $response;
    }
}

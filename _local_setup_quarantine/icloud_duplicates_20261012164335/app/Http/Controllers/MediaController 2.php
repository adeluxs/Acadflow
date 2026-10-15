<?php

namespace App\Http\Controllers;

use App\Models\CommerceEntitlement;
use App\Models\DigitalResourceFile;
use App\Models\AcademicChallenge;
use App\Models\AcademicEvent;
use App\Models\Group;
use App\Models\GroupResource;
use App\Models\KnowledgeCommunity;
use App\Models\KnowledgePublication;
use App\Models\MediaAccessLog;
use App\Models\MediaAsset;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\Media\SecureMediaDeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function store(Request $request, MediaSecurityService $media): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'visibility' => ['nullable', 'in:private,institution,public,entitled'],
            'attachable_type' => ['nullable', 'in:knowledge_publication,research_project,research_dataset,verification,event,challenge,community,group'],
            'attachable_id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_preview' => ['nullable', 'boolean'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $attachable = $this->resolveAttachable($request, $data['attachable_type'] ?? null, $data['attachable_id'] ?? null, true);
        $asset = $media->store($data['file'], $request->user(), $attachable, $data['visibility'] ?? 'private', ['label' => $data['label'] ?? null]);

        if ($attachable instanceof KnowledgePublication) {
            DigitalResourceFile::updateOrCreate(
                ['knowledge_publication_id' => $attachable->id, 'media_asset_id' => $asset->id],
                ['label' => $data['label'] ?? $asset->original_name, 'is_preview' => (bool) ($data['is_preview'] ?? false), 'download_limit' => $data['download_limit'] ?? null]
            );
        }

        return back()->with('success', 'File uploaded and security checked.');
    }

    public function token(Request $request, MediaAsset $asset, SecureMediaDeliveryService $delivery)
    {
        $this->authorizeAccess($request, $asset, false);
        $entitlement = $this->entitlement($request, $asset);
        $plain = $delivery->createToken($asset, $request->user(), $entitlement, (int) config('media.download_token_minutes', 15), 1);
        $url = route('media.download', ['token' => $plain]);

        if ($request->expectsJson()) {
            return response()->json([
                'url' => $url,
                'expires_in_minutes' => (int) config('media.download_token_minutes', 15),
            ]);
        }

        return redirect()->to($url);
    }

    public function download(Request $request, string $token, SecureMediaDeliveryService $delivery): StreamedResponse
    {
        return $delivery->consume($token, $request->user(), $request->ip() ?? '', $request->userAgent() ?? '');
    }

    public function preview(Request $request, MediaAsset $asset, MediaSecurityService $media, SafeFileDeliveryService $files): StreamedResponse
    {
        $this->authorizeAccess($request, $asset, true);
        abort_unless($media->canPreview($asset), 415, 'A secure preview is unavailable for this file.');
        MediaAccessLog::create(['media_asset_id' => $asset->id, 'user_id' => $request->user()?->id, 'action' => 'preview', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now()]);
        return $files->stream(
            $asset->disk,
            $asset->path,
            $asset->original_name,
            $asset->mime_type,
            'inline',
            ['Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'"]
        );
    }

    private function resolveAttachable(Request $request, ?string $type, ?int $id, bool $write): ?Model
    {
        if (! $type || ! $id) return null;
        $class = match ($type) {
            'knowledge_publication' => KnowledgePublication::class,
            'research_project' => \App\Models\ResearchProject::class,
            'research_dataset' => \App\Models\ResearchDataset::class,
            'verification' => \App\Models\VerificationRequest::class,
            'event' => AcademicEvent::class,
            'challenge' => AcademicChallenge::class,
            'community' => KnowledgeCommunity::class,
            'group' => Group::class,
            default => abort(422, 'Unsupported media attachment type.'),
        };
        $model = $class::findOrFail($id);
        $user = $request->user();
        $allowed = match (true) {
            $model instanceof KnowledgePublication => $write ? $user->can('update', $model) : $user->can('view', $model),
            $model instanceof \App\Models\ResearchProject => $write ? $user->can('update', $model) : $user->can('view', $model),
            $model instanceof \App\Models\ResearchDataset => $user->can('update', $model->researchProject),
            $model instanceof \App\Models\VerificationRequest => $model->user_id === $user->id || $user->isAdmin(),
            $model instanceof AcademicEvent => $write ? $user->can('update', $model) : $user->can('view', $model),
            $model instanceof AcademicChallenge => $write ? $user->can('update', $model) : $user->can('view', $model),
            $model instanceof KnowledgeCommunity => $write ? $user->can('update', $model) : $user->can('view', $model),
            $model instanceof Group => $write ? $user->can('update', $model) : $user->can('view', $model),
            default => false,
        };
        abort_unless($allowed, 403);
        return $model;
    }

    private function authorizeAccess(Request $request, MediaAsset $asset, bool $preview): void
    {
        abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 423, 'File security processing is incomplete or failed.');
        $user = $request->user();
        if ($asset->visibility === 'public' && $preview) return;
        if ($user && ($asset->owner_id === $user->id || $user->isSuperAdmin())) return;
        if ($user && $asset->university_id && $asset->university_id === $user->university_id && $asset->visibility === 'institution') return;
        $publicationFile = $asset->digitalResources()->with('publication')->first();
        if ($publicationFile && $publicationFile->is_preview && $preview) return;
        if ($publicationFile && $user) {
            $publication = $publicationFile->publication;
            if ($publication->creator_id === $user->id || $publication->access_type === 'free' || $user->hasEntitlement($publication)) return;
        }

        if ($user && $asset->attachable) {
            $attachable = $asset->attachable;

            if ($attachable instanceof Group) {
                $resource = GroupResource::query()->where('media_asset_id', $asset->id)->first();
                if ($resource && $resource->visibility !== 'public') {
                    $isMember = $attachable->leader_id === $user->id
                        || $attachable->members()->where('user_id', $user->id)->where('status', 'active')->exists();
                    if ($isMember || $user->isSuperAdmin()) return;
                } elseif ($user->can('view', $attachable)) {
                    return;
                }
            } elseif ($attachable instanceof KnowledgeCommunity
                || $attachable instanceof AcademicEvent
                || $attachable instanceof AcademicChallenge) {
                if ($user->can('view', $attachable)) return;
            }
        }

        abort(403);
    }

    private function entitlement(Request $request, MediaAsset $asset): ?CommerceEntitlement
    {
        $file = $asset->digitalResources()->with('publication')->first();
        if (! $file || ! $request->user()) return null;
        return $request->user()->commerceEntitlements()->where('entitled_type', $file->publication->getMorphClass())->where('entitled_id', $file->publication->id)->where('status', 'active')->first();
    }
}

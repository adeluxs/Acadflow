<?php

namespace App\Services\Knowledge;

use App\Models\CreatorProfile;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\WorkflowDefinition;
use App\Services\WorkflowService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreatorService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function updateProfile(User $user, array $data): CreatorProfile
    {
        return DB::transaction(function () use ($user, $data): CreatorProfile {
            // Serialize changes to this personal profile, including partial form submissions.
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $profile = CreatorProfile::firstOrNew(['user_id' => $user->id]);
            $changes = Arr::only($data, ['headline', 'biography', 'position', 'orcid', 'website', 'social_links', 'is_public']);
            if (array_key_exists('expertise', $data)) {
                $changes['expertise'] = array_slice($this->list($data['expertise'] ?? []), 0, 30);
            }
            if (array_key_exists('privacy_settings', $data)) {
                $changes['privacy_settings'] = array_replace(
                    ['show_institution' => false, 'show_department' => false, 'show_impact' => $profile->exists],
                    $profile->privacy_settings ?? [], $data['privacy_settings']
                );
            }
            if (array_key_exists('orcid', $changes) && $profile->orcid !== $changes['orcid']) {
                $external = $profile->external_profiles ?? [];
                unset($external['orcid']);
                $changes['external_profiles'] = $external;
                $changes['orcid_synced_at'] = null;
            }
            if (! $profile->exists) {
                $profile->fill(['is_public' => false, 'privacy_settings' => ['show_institution' => false, 'show_department' => false, 'show_impact' => false]]);
            }
            $profile->fill($changes)->save();
            $user->unsetRelation('creatorProfile');

            return $profile;
        }, 3);
    }

    public function requestVerification(User $user, array $data): VerificationRequest
    {
        return DB::transaction(function () use ($user, $data) {
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $pending = VerificationRequest::where('user_id', $user->id)->where('verification_type', $data['verification_type'])->where('status', 'pending')->first();
            if ($pending) {
                return $pending;
            }
            $request = VerificationRequest::create([
                'university_id' => $user->university_id,
                'user_id' => $user->id,
                'verification_type' => $data['verification_type'],
                'statement' => $data['statement'] ?? null,
                'evidence' => $data['evidence'] ?? [],
                'status' => 'pending',
            ]);
            $definition = WorkflowDefinition::query()->where('is_active', true)->where(fn ($q) => $q->where('subject_type', VerificationRequest::class)->orWhere('key', 'verification'))->where(fn ($q) => $q->whereNull('university_id')->orWhere('university_id', $user->university_id))->first();
            if ($definition) {
                $instance = $this->workflows->start($definition, $request, $user);
                $request->update(['workflow_instance_id' => $instance->id]);
            }

            return $request->fresh('workflowInstance.currentStage');
        });
    }

    public function review(VerificationRequest $request, User $reviewer, string $decision, ?string $note = null): VerificationRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $decision, $note) {
            $status = match ($decision) {
                'approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'revoke' => 'revoked', default => throw new \InvalidArgumentException('Invalid verification decision.')
            };
            $request->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'review_note' => $note, 'reviewed_at' => now(), 'suspended_at' => $status === 'suspended' ? now() : null, 'revoked_at' => $status === 'revoked' ? now() : null]);
            $request->user->creatorProfile()->updateOrCreate([], ['verification_status' => $status]);

            return $request->fresh('user.creatorProfile');
        });
    }

    private function list(array|string $value): array
    {
        return collect(is_string($value) ? explode(',', $value) : $value)->map(fn ($item) => trim((string) $item))->filter()->unique()->values()->all();
    }
}

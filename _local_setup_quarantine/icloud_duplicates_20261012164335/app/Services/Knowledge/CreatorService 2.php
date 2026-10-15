<?php

namespace App\Services\Knowledge;

use App\Models\CreatorProfile;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\WorkflowDefinition;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

class CreatorService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function updateProfile(User $user, array $data): CreatorProfile
    {
        return CreatorProfile::updateOrCreate(['user_id' => $user->id], [
            'headline' => $data['headline'] ?? null,
            'biography' => $data['biography'] ?? null,
            'expertise' => $this->list($data['expertise'] ?? []),
            'position' => $data['position'] ?? null,
            'orcid' => $data['orcid'] ?? null,
            'website' => $data['website'] ?? null,
            'social_links' => $data['social_links'] ?? [],
            'privacy_settings' => $data['privacy_settings'] ?? ['show_institution' => true, 'show_department' => true, 'show_impact' => true],
            'is_public' => (bool) ($data['is_public'] ?? true),
        ]);
    }

    public function requestVerification(User $user, array $data): VerificationRequest
    {
        return DB::transaction(function () use ($user, $data) {
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
            $status = match ($decision) { 'approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'revoke' => 'revoked', default => throw new \InvalidArgumentException('Invalid verification decision.') };
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

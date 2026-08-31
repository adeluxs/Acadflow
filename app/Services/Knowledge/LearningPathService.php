<?php

namespace App\Services\Knowledge;

use App\Models\AcademicCertificate;
use App\Models\KnowledgePublication;
use App\Models\LearningEnrollment;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearningPathService
{
    public function create(User $creator, array $data): LearningPath
    {
        return LearningPath::create(['university_id' => $creator->university_id, 'creator_id' => $creator->id, 'title' => $data['title'], 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)), 'description' => $data['description'] ?? null, 'visibility' => $data['visibility'] ?? 'public', 'access_type' => $data['access_type'] ?? 'free', 'price' => ($data['access_type'] ?? 'free') === 'premium' ? Money::fromMinor(Money::toMinor((string)($data['price'] ?? '0'))) : '0.00', 'status' => $data['status'] ?? 'draft', 'certificate_enabled' => (bool) ($data['certificate_enabled'] ?? false), 'outcomes' => $this->list($data['outcomes'] ?? []), 'settings' => $data['settings'] ?? [], 'published_at' => ($data['status'] ?? null) === 'published' ? now() : null]);
    }

    public function addItem(LearningPath $path, User $actor, array $data): LearningPathItem
    {
        abort_unless($path->creator_id === $actor->id || $actor->isAdmin(), 403);
        $position = isset($data['position']) ? (int) $data['position'] : ((int) $path->items()->max('position') + 1);
        if ($path->items()->where('position', $position)->exists()) $path->items()->where('position', '>=', $position)->increment('position');
        if (($data['item_type'] ?? '') === KnowledgePublication::class && isset($data['item_id'])) {
            $publication = KnowledgePublication::findOrFail($data['item_id']);
            abort_unless($publication->status === 'published' && ($publication->visibility === 'public' || $publication->university_id === $path->university_id), 422);
        }
        return $path->items()->create($data + ['position' => $position]);
    }

    public function enroll(LearningPath $path, User $user): LearningEnrollment
    {
        if ($path->status !== 'published') throw ValidationException::withMessages(['path' => 'This learning path is not published.']);
        if ($path->visibility !== 'public' && ! $user->isSuperAdmin() && $path->university_id !== $user->university_id) abort(403);
        if ($path->access_type === 'premium' && ! $user->hasEntitlement($path)) throw ValidationException::withMessages(['path' => 'Purchase entitlement is required for this premium learning path.']);
        return DB::transaction(function () use ($path, $user) {
            $enrollment = $path->enrollments()->firstOrCreate(['user_id' => $user->id], ['status' => 'active', 'progress' => 0, 'current_item_id' => $path->items()->orderBy('position')->value('id'), 'started_at' => now()]);
            foreach ($path->items as $item) $enrollment->progressRecords()->firstOrCreate(['learning_path_item_id' => $item->id], ['status' => 'not_started']);
            return $enrollment->fresh(['path.items','progressRecords']);
        });
    }

    public function markProgress(LearningEnrollment $enrollment, LearningPathItem $item, User $user, array $data): LearningEnrollment
    {
        abort_unless($enrollment->user_id === $user->id && $item->learning_path_id === $enrollment->learning_path_id, 403);
        return DB::transaction(function () use ($enrollment, $item, $data) {
            $status = $data['status'] ?? 'completed';
            $enrollment->progressRecords()->updateOrCreate(['learning_path_item_id' => $item->id], ['status' => $status, 'score' => $data['score'] ?? null, 'time_spent_seconds' => $data['time_spent_seconds'] ?? 0, 'completed_at' => $status === 'completed' ? now() : null, 'state' => $data['state'] ?? null]);
            $required = $enrollment->path->items->where('is_required', true);
            $completedIds = $enrollment->progressRecords()->where('status','completed')->pluck('learning_path_item_id');
            $progress = $required->isEmpty() ? 100 : round($required->whereIn('id',$completedIds)->count() / $required->count() * 100, 2);
            $next = $enrollment->path->items->first(fn ($candidate) => ! $completedIds->contains($candidate->id));
            $enrollment->update(['progress' => $progress, 'current_item_id' => $next?->id, 'status' => $progress >= 100 ? 'completed' : 'active', 'completed_at' => $progress >= 100 ? ($enrollment->completed_at ?? now()) : null]);
            if ($progress >= 100 && $enrollment->path->certificate_enabled) {
                $this->issueCertificate($enrollment);
            }
            return $enrollment->fresh(['path.items','progressRecords']);
        });
    }

    private function issueCertificate(LearningEnrollment $enrollment): AcademicCertificate
    {
        $certificate = AcademicCertificate::firstOrCreate(
            [
                'user_id' => $enrollment->user_id,
                'certifiable_type' => $enrollment->path->getMorphClass(),
                'certifiable_id' => $enrollment->path->getKey(),
            ],
            [
                'title' => 'Learning Path Completion: '.$enrollment->path->title,
                'issuer' => config('app.name'),
                'issued_on' => today(),
                'metadata' => ['learning_enrollment_id' => $enrollment->id, 'progress' => 100],
            ]
        );

        if (! $certificate->file_path) {
            $path = 'certificates/'.$certificate->uuid.'.html';
            $html = '<!doctype html><html><body style="font-family:serif;text-align:center;padding:80px">'
                .'<h1>Certificate of Completion</h1><p>This certifies that</p><h2>'.e($enrollment->user->full_name).'</h2>'
                .'<p>completed the learning path</p><h2>'.e($enrollment->path->title).'</h2>'
                .'<p>Verification: '.e($certificate->verification_code).'</p></body></html>';
            Storage::disk('local')->put($path, $html);
            $certificate->update(['file_path' => $path]);
        }

        return $certificate;
    }

    private function list(array|string $value): array { return collect(is_string($value) ? explode(',', $value) : $value)->map(fn($v)=>trim((string)$v))->filter()->values()->all(); }
}

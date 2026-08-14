<?php

namespace App\Services\Discovery;

use App\Models\SearchDocument;
use App\Models\User;
use Illuminate\Support\Collection;

class DiscoverySearchService
{
    public function __construct(private readonly LocalEmbeddingService $embeddings) {}

    /** @return Collection<int,array{document:SearchDocument,score:float,lexical_score:float,semantic_score:float}> */
    public function search(string $query, ?User $user = null, array $filters = [], int $limit = 30): Collection
    {
        $term = trim($query);
        $builder = SearchDocument::query()->with('chunks');
        $this->applyPrivacy($builder, $user);

        if ($type = $filters['content_type'] ?? null) {
            $builder->where('content_type', $type);
        }
        if ($university = $filters['university_id'] ?? null) {
            $builder->where('university_id', $university);
        }
        if ($courseId = $filters['course_id'] ?? null) {
            $builder->where('metadata->course_id', (int) $courseId);
        }

        // Keep a broad, privacy-filtered candidate set so semantic matches are not
        // accidentally discarded by a mandatory SQL LIKE filter. Lexical scoring
        // is applied below alongside the local embedding score.
        $candidates = $builder->latest('indexed_at')->limit(max($limit * 10, 250))->get();
        $queryEmbedding = $term !== '' ? $this->embeddings->embed($term) : null;
        $tokens = preg_split('/\s+/', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $candidates->map(function (SearchDocument $document) use ($queryEmbedding, $tokens): array {
            $haystack = mb_strtolower($document->title.' '.$document->summary.' '.$document->keywords.' '.$document->body);
            $lexical = $tokens === [] ? 0.5 : collect($tokens)->avg(fn (string $token): float => str_contains($haystack, $token) ? 1.0 : 0.0);
            $semantic = $queryEmbedding ? max(0.0, $this->embeddings->cosine($queryEmbedding, $document->embedding)) : 0.5;
            $titleBoost = $tokens !== [] && collect($tokens)->contains(fn (string $token): bool => str_contains(mb_strtolower($document->title), $token)) ? 0.2 : 0.0;
            return ['document' => $document, 'score' => round(($lexical * 0.55) + ($semantic * 0.45) + $titleBoost, 6), 'lexical_score' => $lexical, 'semantic_score' => $semantic];
        })->sortByDesc('score')->take($limit)->values();
    }

    public function relevantChunks(string $query, ?User $user = null, array $filters = [], int $limit = 8): Collection
    {
        $embedding = $this->embeddings->embed($query);
        return $this->search($query, $user, $filters, 25)
            ->flatMap(fn (array $result) => $result['document']->chunks)
            ->map(fn ($chunk) => ['chunk' => $chunk, 'score' => $this->embeddings->cosine($embedding, $chunk->embedding)])
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function applyPrivacy($query, ?User $user): void
    {
        $authorizedCourseIds = $user ? $this->authorizedCourseIds($user) : [];

        $query->where(function ($visibility) use ($user, $authorizedCourseIds): void {
            $visibility->where('visibility', 'public');

            if (! $user) {
                return;
            }

            // Institution-visible content is tenant scoped.
            if ($user->university_id) {
                $visibility->orWhere(function ($institution) use ($user): void {
                    $institution->where('university_id', $user->university_id)
                        ->where('visibility', 'institution');
                });
            }

            // Course-visible content must be tied to a course the user can actually access.
            // This prevents a same-university student from searching another course's material.
            if ($user->isSuperAdmin()) {
                $visibility->orWhere('visibility', 'course');
            } elseif ($authorizedCourseIds !== []) {
                $visibility->orWhere(function ($course) use ($user, $authorizedCourseIds): void {
                    $course->where('visibility', 'course')
                        ->whereIn('metadata->course_id', $authorizedCourseIds);

                    if ($user->university_id) {
                        $course->where('university_id', $user->university_id);
                    }
                });
            }

            // Private indexed documents are visible only to their owner.
            $visibility->orWhere(function ($owner) use ($user): void {
                $owner->where('visibility', 'private')
                    ->where('metadata->owner_id', $user->id);
            });
        });

        if (! $user) {
            $query->where('access_type', 'free');
        }
    }

    /** @return list<int> */
    private function authorizedCourseIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [];
        }

        if ($user->isUniversityAdmin()) {
            return \App\Models\Course::query()
                ->whereHas('department.faculty', fn ($query) => $query->where('university_id', $user->university_id))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isDepartmentAdmin()) {
            return \App\Models\Course::query()
                ->where('department_id', $user->department_id)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isLecturer()) {
            return $user->lecturerAssignments()->pluck('course_id')
                ->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        if ($user->isStudent()) {
            return $user->enrollments()->where('status', 'enrolled')->pluck('course_id')
                ->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        return [];
    }
}

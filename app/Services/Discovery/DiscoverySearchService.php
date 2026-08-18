<?php

namespace App\Services\Discovery;

use App\Models\SearchDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Retrieve chunks only from the exact authorized subject before ranking.
     * This is the strict retrieval path used by the Grounded AI Companion so a
     * question can never rank chunks from other publications and filter later.
     *
     * @return Collection<int,array{chunk:mixed,score:float,lexical_score:float,semantic_score:float}>
     */
    public function relevantChunksForSubject(Model $subject, string $query, ?User $user = null, int $limit = 8): Collection
    {
        $builder = SearchDocument::query()
            ->with('chunks')
            ->where('searchable_type', $subject->getMorphClass())
            ->where('searchable_id', $subject->getKey());

        $this->applyPrivacy($builder, $user);
        $document = $builder->first();
        if (! $document) {
            return collect();
        }

        $embedding = $this->embeddings->embed($query);
        $terms = $this->meaningfulQueryTerms($query);
        $normalizedQuery = mb_strtolower(trim($query));

        return $document->chunks->map(function ($chunk) use ($embedding, $terms, $normalizedQuery): array {
            $content = mb_strtolower((string) $chunk->content);
            $heading = mb_strtolower((string) $chunk->heading);
            $matched = 0;
            $candidateTokens = $this->searchableTokens($heading.' '.$content);
            foreach ($terms as $term) {
                if (str_contains($content, $term) || str_contains($heading, $term) || $this->hasCloseTokenMatch($term, $candidateTokens)) {
                    $matched++;
                }
            }
            $lexical = $terms === [] ? 0.0 : ($matched / count($terms));
            $semantic = max(0.0, $this->embeddings->cosine($embedding, $chunk->embedding));
            $phraseBoost = mb_strlen($normalizedQuery) >= 6 && mb_strlen($normalizedQuery) <= 160 && str_contains($content, $normalizedQuery) ? 0.20 : 0.0;
            $headingBoost = $terms !== [] && collect($terms)->contains(fn (string $term): bool => str_contains($heading, $term)) ? 0.08 : 0.0;
            $score = min(1.0, ($lexical * 0.57) + ($semantic * 0.35) + $phraseBoost + $headingBoost);

            return [
                'chunk' => $chunk,
                'score' => round($score, 6),
                'lexical_score' => round($lexical, 6),
                'semantic_score' => round($semantic, 6),
            ];
        })->sortByDesc('score')->take(max(1, $limit))->values();
    }

    /**
     * Return broad document coverage for summary-style grounded questions.
     * Chunks are selected across the document rather than relying on a narrow
     * semantic match, so summaries do not accidentally ignore the beginning or
     * conclusion of a long publication.
     *
     * @return Collection<int,array{chunk:mixed,score:float,lexical_score:float,semantic_score:float}>
     */
    public function representativeChunksForSubject(Model $subject, ?User $user = null, int $limit = 8): Collection
    {
        $builder = SearchDocument::query()
            ->with('chunks')
            ->where('searchable_type', $subject->getMorphClass())
            ->where('searchable_id', $subject->getKey());

        $this->applyPrivacy($builder, $user);
        $document = $builder->first();
        if (! $document || $document->chunks->isEmpty()) {
            return collect();
        }

        $chunks = $document->chunks->values();
        $count = $chunks->count();
        $limit = max(1, min($limit, $count));
        if ($count <= $limit) {
            return $chunks->map(fn ($chunk) => [
                'chunk' => $chunk,
                'score' => 0.50,
                'lexical_score' => 0.50,
                'semantic_score' => 0.50,
            ])->values();
        }

        $indexes = [];
        for ($i = 0; $i < $limit; $i++) {
            $index = (int) round($i * ($count - 1) / max(1, $limit - 1));
            $indexes[$index] = true;
        }

        return collect(array_keys($indexes))->sort()->map(function (int $index) use ($chunks): array {
            return [
                'chunk' => $chunks[$index],
                'score' => 0.50,
                'lexical_score' => 0.50,
                'semantic_score' => 0.50,
            ];
        })->values();
    }

    /** @return list<string> */
    private function meaningfulQueryTerms(string $query): array
    {
        $stop = [
            'a','am','an','and','are','as','at','be','been','but','by','can','could','did','do','does','for','from','had','has','have','how','i','if','in','is','it','may','me','my','of','on','or','our','please','should','so','than','that','the','their','them','there','these','they','this','those','to','us','was','we','were','what','when','where','which','who','why','will','with','would','you','your',
            'summarize','summary','overview','explain','clarify','simplify','define','definition','meaning','compare','comparison','contrast','method','methods','methodology','finding','findings','result','results','outcome','outcomes','limitation','limitations','conclusion','conclusions','recommendation','recommendations','evidence','proof','citation','citations','reference','references','source','sources','article','document','paper','publication','resource','study','author','authors','say','says','tell','show','shows','about','main','key','major','overall','used','use','using','reach','reaches','identify','identifies','identified','provide','provides','provided','give','gives','given','no','ok','up','go',
        ];
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $term): bool => mb_strlen($term) >= 2 && ! in_array($term, $stop, true))));
    }


    /** @return list<string> */
    private function searchableTokens(string $text): array
    {
        return array_values(array_unique(preg_split('/[^\pL\pN]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    /**
     * Conservative typo matching used only after exact matching fails. Short
     * tokens are deliberately excluded so fuzzy matching cannot make random
     * noise appear relevant to a publication.
     *
     * @param list<string> $candidates
     */
    private function hasCloseTokenMatch(string $term, array $candidates): bool
    {
        $term = mb_strtolower($term);
        $length = mb_strlen($term);
        if ($length < 5 || preg_match('/^[a-z0-9]+$/i', $term) !== 1) return false;

        $maxDistance = $length >= 8 ? 2 : 1;
        $first = mb_substr($term, 0, 1);
        foreach ($candidates as $candidate) {
            if (mb_substr($candidate, 0, 1) !== $first) continue;
            if (abs(mb_strlen($candidate) - $length) > $maxDistance) continue;
            if (preg_match('/^[a-z0-9]+$/i', $candidate) !== 1) continue;
            if (levenshtein($term, $candidate) <= $maxDistance) return true;
        }

        return false;
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

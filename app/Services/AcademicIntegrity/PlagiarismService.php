<?php

namespace App\Services\AcademicIntegrity;

use App\Contracts\AcademicIntegrity\SimilarityProviderInterface;
use App\Models\PlagiarismCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PlagiarismService
{
    /** @param array<int,SimilarityProviderInterface> $providers */
    public function __construct(private readonly InternalSimilarityProvider $internal, private readonly array $providers = []) {}

    public function check(Model $subject, string $text, User $actor, array $context = []): PlagiarismCheck
    {
        $check = PlagiarismCheck::create([
            'university_id' => $actor->university_id,
            'requested_by' => $actor->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'provider' => $context['provider'] ?? 'hybrid',
            'status' => 'processing',
            'metadata' => ['context' => array_diff_key($context, ['text' => true])],
        ]);

        try {
            $results = [$this->internal->compare($text, $context + [
                'university_id' => $actor->university_id,
                'exclude_type' => $subject->getMorphClass(),
                'exclude_id' => $subject->getKey(),
            ])];
            foreach ($this->providers as $provider) {
                if ($provider->available()) {
                    $results[] = $provider->compare($text, $context);
                }
            }

            return DB::transaction(function () use ($check, $results) {
                $matches = collect($results)->flatMap(fn ($result) => $result['matches'] ?? [])->sortByDesc('similarity_score')->take(100);
                foreach ($matches as $match) {
                    $check->matches()->create($match + ['provider' => $match['provider'] ?? 'internal']);
                }
                $score = (float) $matches->max('similarity_score');
                $risk = $score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low');
                $check->update([
                    'status' => 'completed',
                    'similarity_score' => round($score, 2),
                    'risk_level' => $risk,
                    'summary' => $matches->isEmpty() ? 'No meaningful similarity matches were detected.' : $matches->count().' potential source match(es) require human review.',
                    'metadata' => array_merge($check->metadata ?? [], ['provider_results' => collect($results)->map(fn ($r) => collect($r)->except('matches'))->all(), 'not_a_misconduct_decision' => true]),
                    'completed_at' => now(),
                ]);
                return $check->fresh('matches');
            });
        } catch (\Throwable $exception) {
            $check->update(['status' => 'failed', 'summary' => 'Similarity processing failed safely.', 'metadata' => array_merge($check->metadata ?? [], ['error_type' => class_basename($exception)])]);
            report($exception);
            return $check->fresh();
        }
    }
}

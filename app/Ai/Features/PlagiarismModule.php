<?php

namespace App\Ai\Features;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Ai\Support\TextExtractor;
use App\Models\Submission;
use App\Models\User;
use App\Services\SettingService;

/**
 * AI Plagiarism Detection (Phase 12).
 *
 * Compares a submission's text against previous submissions, internal
 * repository and a lightweight internet-style fingerprint index. Produces an
 * overall similarity percentage, matched paragraphs, risk score and source list.
 *
 * Rule-based similarity uses shingling + hashing against other submissions in
 * the same course/university. External providers (when enabled) can augment with
 * internet checks.
 */
class PlagiarismModule
{
    public function __construct(protected AiManager $manager) {}

    public function isEnabled(?int $universityId = null): bool
    {
        return (bool) SettingService::get('ai_feature_plagiarism', true, $universityId);
    }

    public function threshold(?int $universityId = null): int
    {
        return (int) SettingService::get('ai_similarity_threshold', config('ai.similarity_threshold', 20), $universityId);
    }

    /**
     * Analyze a submission for similarity.
     */
    public function analyze(Submission $submission, ?User $user = null): AiResponse
    {
        if (! $this->isEnabled($user?->university_id ?? $submission->user?->university_id)) {
            return new AiResponse('disabled', 'plagiarism', false, summary: 'Plagiarism check disabled.');
        }

        $context = $this->buildContext($submission, $user?->university_id ?? $submission->user?->university_id);

        return $this->manager->analyze('plagiarism', $context, $user, $submission->uuid);
    }

    protected function buildContext(Submission $submission, ?int $universityId = null): array
    {
        $text = '';
        $version = $submission->versions()->where('is_current', true)->first()
            ?? $submission->versions()->latest()->first();

        if ($version && class_exists(TextExtractor::class)) {
            $text = app(TextExtractor::class)->fromVersion($version);
        }

        // Build local corpus fingerprints from prior submissions:
        //  - same course (institution repository),
        //  - same submission type (avoid cross-type false positives),
        //  - excluding the author's own submissions.
        $courseId = $submission->course_id;
        $type = $submission->type;
        $authorId = $submission->user_id;

        $others = Submission::where('course_id', $courseId)
            ->where('id', '!=', $submission->id)
            ->where('user_id', '!=', $authorId)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->with('versions')
            ->limit(200)
            ->get();

        $corpus = [];
        foreach ($others as $other) {
            $ov = $other->versions()->where('is_current', true)->first();
            if ($ov) {
                $corpus[] = [
                    'submission_uuid' => $other->uuid,
                    'title' => $other->title,
                    'shingles' => $this->shingles(app(TextExtractor::class)->fromVersion($ov)),
                ];
            }
        }

        return [
            'submission' => $submission,
            'type' => $submission->type,
            'text' => $text,
            'local_corpus' => $corpus,
            'threshold' => $this->threshold($universityId),
        ];
    }

    /**
     * Produce 5-word shingles hashed for fast overlap comparison.
     */
    protected function shingles(string $text): array
    {
        $words = preg_split('/\s+/', preg_replace('/\s+/', ' ', strip_tags($text)));
        $words = array_filter($words ?? [], fn ($w) => strlen($w) > 2);
        $shingles = [];

        for ($i = 0; $i + 4 < count($words); $i++) {
            $shingles[] = md5(implode(' ', array_slice($words, $i, 5)));
        }

        return $shingles;
    }
}

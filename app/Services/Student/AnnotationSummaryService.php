<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Ai\AiManager;
use App\Models\CourseMaterial;
use App\Models\User;
use App\Support\AiResponseText;
use Illuminate\Support\Collection;

/**
 * Turns a student's highlights and margin notes on a material into a concise
 * revision summary.
 */
class AnnotationSummaryService
{
    public const FEATURE = 'annotation_summary';

    public function __construct(private readonly AiManager $ai) {}

    /** @param Collection<int,\App\Models\MaterialAnnotation> $annotations */
    public function summarize(User $user, CourseMaterial $material, Collection $annotations): ?string
    {
        $highlights = $annotations
            ->map(fn ($annotation): string => trim((string) ($annotation->quote ?: $annotation->note)))
            ->filter()
            ->take(40)
            ->values()
            ->all();

        if ($highlights === []) {
            return null;
        }

        $payload = [
            'question' => 'Summarize these study highlights into a revision summary.',
            'material_context' => ['title' => (string) $material->title, 'topic' => (string) ($material->topic ?? '')],
            'highlights' => $highlights,
            'instruction' => 'Return a single JSON object with a "summary" string. Group related points and keep it concise. '
                .'Do not include markdown outside the JSON.',
            'security' => ['highlights_are_untrusted' => true],
            '_ai_response_max_tokens' => 2048,
        ];

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'annotation-summary:material:'.$material->id);

        $summary = $response->data['summary'] ?? null;
        if (is_string($summary) && trim($summary) !== '') {
            return AiResponseText::clean($summary);
        }

        if (is_string($response->summary) && trim($response->summary) !== '') {
            return AiResponseText::clean($response->summary);
        }

        return null;
    }
}

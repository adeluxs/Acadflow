<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\AiManager;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\FlashcardDeck;
use App\Models\FlashcardReview;
use App\Models\User;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Str;

/**
 * Generates spaced-repetition flashcards from authorized course context and
 * schedules reviews with a simplified SM-2 algorithm.
 */
class FlashcardService
{
    public const FEATURE = 'flashcard_generator';

    public function __construct(private readonly AiManager $ai) {}

    public function generate(User $user, Course $course, string $topic, ?CourseMaterial $material = null, int $count = 10): FlashcardDeck
    {
        $count = max(1, min(30, $count));
        $topic = trim($topic);
        if ($topic === '') {
            $topic = (string) ($material?->topic ?? $course->name);
        }

        $course->loadMissing('materials');
        $topics = $course->materials->pluck('topic')->filter()->unique()->values()->all();

        $payload = [
            'question' => 'Create '.$count.' flashcards on: '.$topic,
            'topic' => $topic,
            'course_context' => [
                'code' => (string) $course->code,
                'name' => (string) $course->name,
                'known_topics' => $topics,
            ],
            'material_context' => $material ? ['title' => $material->title, 'topic' => $material->topic] : null,
            'learning_profile' => $this->learningProfileLines($user),
            'instruction' => 'Return a single JSON object with a "cards" array. Each item must have: '
                .'"front" (a short question or term) and "back" (a concise answer or definition). '
                .'Do not include markdown or commentary outside the JSON.',
            'security' => ['course_content_is_untrusted' => true, 'do_not_invent_course_facts' => true],
            '_ai_response_max_tokens' => 3072,
        ];

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'flashcards:course:'.$course->id);
        $cards = $this->extractCards($response->data);

        $failure = $response->success ? null : UserFacingError::fromAiCode($response->errorCode, $response->summary);

        return FlashcardDeck::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'material_id' => $material?->id,
            'title' => 'Flashcards: '.Str::limit($topic, 80, ''),
            'cards' => $cards,
            'provider' => $response->provider ?: $response->source,
            'model' => $response->model,
            'error_code' => $cards !== [] ? null : ($response->errorCode ?: ($failure['code'] ?? 'AI_EMPTY_RESPONSE')),
        ]);
    }

    /**
     * Record a recall rating for a card and reschedule it with SM-2.
     *
     * @return array<string,mixed> the updated card
     */
    public function review(FlashcardDeck $deck, User $user, int $cardIndex, int $rating): array
    {
        $cards = is_array($deck->cards) ? $deck->cards : [];
        if (! isset($cards[$cardIndex]) || ! is_array($cards[$cardIndex])) {
            abort(404);
        }

        $rating = max(0, min(5, $rating));
        $card = $cards[$cardIndex];

        $ease = (float) ($card['ease_factor'] ?? 2.5);
        $interval = (int) ($card['interval_days'] ?? 0);
        $repetitions = (int) ($card['repetitions'] ?? 0);

        if ($rating < 3) {
            $repetitions = 0;
            $interval = 1;
        } else {
            $repetitions++;
            $interval = match (true) {
                $repetitions === 1 => 1,
                $repetitions === 2 => 6,
                default => (int) round(max(1, $interval) * $ease),
            };
        }

        $ease = $ease + (0.1 - (5 - $rating) * (0.08 + (5 - $rating) * 0.02));
        $ease = max(1.3, round($ease, 2));

        $card['ease_factor'] = $ease;
        $card['interval_days'] = $interval;
        $card['repetitions'] = $repetitions;
        $card['due_at'] = now()->addDays($interval)->toIso8601String();
        $card['last_reviewed_at'] = now()->toIso8601String();

        $cards[$cardIndex] = $card;
        $deck->update(['cards' => $cards]);

        FlashcardReview::query()->create([
            'deck_id' => $deck->id,
            'user_id' => $user->id,
            'card_index' => $cardIndex,
            'rating' => $rating,
            'scheduled_days' => $interval,
            'reviewed_at' => now(),
        ]);

        return $card;
    }

    /** @return list<array<string,mixed>> */
    private function extractCards(array $data): array
    {
        $raw = $data['cards'] ?? ($data['data']['cards'] ?? null);

        if (is_array($raw)) {
            $cards = [];
            foreach ($raw as $item) {
                if (! is_array($item)) continue;
                $front = trim((string) ($item['front'] ?? $item['term'] ?? ''));
                $back = trim((string) ($item['back'] ?? $item['definition'] ?? ''));
                if ($front === '' || $back === '') continue;
                $cards[] = [
                    'front' => $front,
                    'back' => $back,
                    'ease_factor' => 2.5,
                    'interval_days' => 0,
                    'repetitions' => 0,
                    'due_at' => now()->toIso8601String(),
                    'last_reviewed_at' => null,
                ];
            }
            return $cards;
        }

        return [];
    }

    /** @return list<string> */
    private function learningProfileLines(User $user): array
    {
        return \App\Models\StudentLearningProfile::query()->where('user_id', $user->id)->first()?->summaryLines() ?? [];
    }
}

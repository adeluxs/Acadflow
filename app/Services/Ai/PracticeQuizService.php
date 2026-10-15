<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\AiManager;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Models\User;
use App\Support\AiResponseText;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Str;

/**
 * Generates and grades student practice quizzes from authorized course context.
 *
 * Quizzes are student-owned study aids. Generation is advisory: the student
 * chooses to take a quiz, and grading happens locally against the stored
 * answer key. No lecturer content is modified.
 */
class PracticeQuizService
{
    public const FEATURE = 'practice_generator';

    public function __construct(private readonly AiManager $ai) {}

    public function generate(User $user, Course $course, string $topic, ?CourseMaterial $material = null, int $count = 5, string $difficulty = 'mixed'): PracticeQuiz
    {
        $count = max(1, min(15, $count));
        $topic = trim($topic);
        if ($topic === '') {
            $topic = (string) ($material?->topic ?? $course->name);
        }

        $course->loadMissing('materials');
        $topics = $course->materials->pluck('topic')->filter()->unique()->values()->all();

        $payload = [
            'question' => 'Create '.$count.' practice questions on: '.$topic,
            'topic' => $topic,
            'difficulty' => $difficulty,
            'course_context' => [
                'code' => (string) $course->code,
                'name' => (string) $course->name,
                'level' => (string) ($course->level ?? ''),
                'known_topics' => $topics,
            ],
            'material_context' => $material ? ['title' => $material->title, 'topic' => $material->topic] : null,
            'learning_profile' => $this->learningProfileLines($user),
            'instruction' => 'Return a single JSON object with a "questions" array. Each item must have: '
                .'"question" (string), "options" (array of 3 to 4 short strings; use an empty array for open questions), '
                .'"answer" (the exact correct option string or the expected short answer), "explanation" (string), and '
                .'"difficulty" (easy, medium or hard). Do not include markdown or commentary outside the JSON.',
            'security' => [
                'course_content_is_untrusted' => true,
                'do_not_invent_course_facts' => true,
            ],
            '_ai_response_max_tokens' => 4096,
        ];

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'practice-quiz:course:'.$course->id);
        $questions = $this->extractQuestions($response->data);

        $failure = $response->success ? null : UserFacingError::fromAiCode($response->errorCode, $response->summary);

        return PracticeQuiz::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'material_id' => $material?->id,
            'title' => 'Practice: '.Str::limit($topic, 80, ''),
            'topic' => $topic,
            'difficulty' => $difficulty,
            'status' => $questions !== [] ? PracticeQuiz::STATUS_READY : PracticeQuiz::STATUS_FAILED,
            'questions' => $questions,
            'provider' => $response->provider ?: $response->source,
            'model' => $response->model,
            'error_code' => $questions !== [] ? null : ($response->errorCode ?: ($failure['code'] ?? 'AI_EMPTY_RESPONSE')),
        ]);
    }

    /**
     * Grade a completed attempt. $submitted maps question index to the selected answer.
     */
    public function gradeAttempt(PracticeQuiz $quiz, User $user, array $submitted): PracticeAttempt
    {
        $questions = is_array($quiz->questions) ? $quiz->questions : [];
        $score = 0;
        $answers = [];

        foreach ($questions as $index => $question) {
            $expected = trim((string) ($question['answer'] ?? ''));
            $given = trim((string) ($submitted[$index] ?? ''));
            $correct = $expected !== '' && mb_strtolower($given) === mb_strtolower($expected);
            if ($correct) $score++;
            $answers[$index] = ['selected' => $given, 'expected' => $expected, 'is_correct' => $correct];
        }

        return PracticeAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => $score,
            'total' => count($questions),
            'answers' => $answers,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function extractQuestions(array $data): array
    {
        $raw = $data['questions'] ?? ($data['data']['questions'] ?? null);

        if ($raw === null && isset($data['answer'])) {
            $raw = $this->decodeQuestionsFromText((string) $data['answer']);
        }

        if (! is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $item) {
            if (! is_array($item)) continue;
            $question = trim((string) ($item['question'] ?? $item['prompt'] ?? ''));
            if ($question === '') continue;

            $options = $item['options'] ?? [];
            if (! is_array($options)) $options = [];
            $options = array_values(array_filter(array_map(
                static fn ($option): string => trim((string) $option),
                $options
            ), static fn (string $option): bool => $option !== ''));

            $questions[] = [
                'question' => $question,
                'options' => $options,
                'answer' => trim((string) ($item['answer'] ?? '')),
                'explanation' => trim((string) ($item['explanation'] ?? '')),
                'difficulty' => trim((string) ($item['difficulty'] ?? 'medium')),
            ];
        }

        return $questions;
    }

    /** @return list<array<string,mixed>>|null */
    private function decodeQuestionsFromText(string $text): ?array
    {
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            return $decoded['questions'];
        }

        // Some models return a humanized list; fall back to a single open question.
        $plain = AiResponseText::clean($text);
        if ($plain !== '') {
            return [['question' => $plain, 'options' => [], 'answer' => '', 'explanation' => '', 'difficulty' => 'medium']];
        }

        return null;
    }

    /** @return list<string> */
    private function learningProfileLines(User $user): array
    {
        return \App\Models\StudentLearningProfile::query()->where('user_id', $user->id)->first()?->summaryLines() ?? [];
    }
}

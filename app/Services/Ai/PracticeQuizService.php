<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\AiManager;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Models\StudentLearningProfile;
use App\Models\StudentStudySession;
use App\Models\TopicMastery;
use App\Models\User;
use App\Services\Student\StudySourceService;
use App\Support\AiResponseText;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function generate(User $user, Course $course, string $topic, ?CourseMaterial $material = null, int $count = 5, string $difficulty = 'mixed', ?StudentStudySession $session = null): PracticeQuiz
    {
        $sourceService = app(StudySourceService::class);
        $sourceService->authorizeCourse($user, $course);
        if ($session) {
            abort_unless((int) $session->course_id === (int) $course->id, 404);
            $sourceText = $sourceService->sessionText($user, $session);
        } else {
            abort_if($material && (int) $material->course_id !== (int) $course->id, 404);
            $sourceText = $material ? $sourceService->materialText($user, $material) : '';
        }
        if (($material || $session) && mb_strlen($sourceText) < 20) {
            throw ValidationException::withMessages(['source' => 'This source has no readable text. Use a text note or another material.']);
        }
        $count = max(1, min(15, $count));
        $topic = trim($topic);
        if ($topic === '') {
            $topic = (string) ($material?->topic ?? $course->name);
        }

        $topics = $course->materials()->where('is_visible', true)->whereNotNull('topic')->select('topic')->distinct()->limit(30)->pluck('topic')->all();

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
            'source_text' => $sourceText,
            'source_title' => $session?->title() ?? $material?->title,
            'grounding' => $sourceText !== '' ? 'Use only the supplied source text. Do not invent facts, sources, past papers or exam predictions. Include an exact source_excerpt for each question when possible.' : 'General topic practice; do not claim that questions came from a lecturer or a past exam.',
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
        $questions = $response->success ? array_slice($this->extractQuestions($response->data), 0, $count) : [];
        foreach ($questions as &$question) {
            if (($question['source_excerpt'] ?? '') !== '' && ! str_contains(preg_replace('/\s+/u', ' ', $sourceText), preg_replace('/\s+/u', ' ', $question['source_excerpt']))) {
                $question['source_excerpt'] = '';
            }
        }
        unset($question);

        $failure = $response->success ? null : UserFacingError::fromAiCode($response->errorCode, $response->summary);

        return PracticeQuiz::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'material_id' => $material?->id,
            'study_session_id' => $session?->id,
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
    public function gradeAttempt(PracticeQuiz $quiz, User $user, array $submitted, ?string $token = null): PracticeAttempt
    {
        app(StudySourceService::class)->authorizeQuiz($user, $quiz);
        abort_unless($quiz->status === PracticeQuiz::STATUS_READY && $quiz->questionCount() > 0, 422, 'This quiz is not ready to practise.');
        foreach ($submitted as $answer) {
            if (! is_string($answer) || mb_strlen($answer) > 1000) {
                throw ValidationException::withMessages(['answers' => 'Choose or enter a short answer for each question.']);
            }
        }

        return DB::transaction(function () use ($quiz, $user, $submitted, $token): PracticeAttempt {
            $quiz = PracticeQuiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();
            if ($token && ($existing = $quiz->attempts()->where('user_id', $user->id)->where('submission_token', $token)->first())) {
                return $existing;
            }
            $questions = is_array($quiz->questions) ? $quiz->questions : [];
            $score = 0;
            $answers = [];

            foreach ($questions as $index => $question) {
                $expected = trim((string) ($question['answer'] ?? ''));
                $given = trim((string) ($submitted[$index] ?? ''));
                $correct = $expected !== '' && mb_strtolower($given) === mb_strtolower($expected);
                if ($correct) {
                    $score++;
                }
                $answers[$index] = ['selected' => $given, 'expected' => $expected, 'is_correct' => $correct];
            }

            $attempt = PracticeAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'score' => $score,
                'total' => count($questions),
                'answers' => $answers,
                'started_at' => now(),
                'completed_at' => now(),
                'submission_token' => $token,
            ]);
            $interval = $score / count($questions) < 0.8 ? 1
                : ($quiz->last_practiced_at?->isToday() ? max(1, $quiz->review_interval_days)
                    : min(30, max(3, (int) ceil($quiz->review_interval_days * 2))));
            $quiz->update(['last_practiced_at' => now(), 'review_interval_days' => $interval, 'next_review_at' => now()->addDays($interval)]);
            if ($quiz->study_session_id) {
                StudentStudySession::whereKey($quiz->study_session_id)->where('user_id', $user->id)->update(['last_studied_at' => now()]);
            }
            if ($quiz->topic) {
                $totals = PracticeAttempt::where('user_id', $user->id)->whereHas('quiz', fn ($q) => $q->where('user_id', $user->id)->where('course_id', $quiz->course_id)->where('topic', $quiz->topic))
                    ->selectRaw('COALESCE(SUM(score), 0) AS correct, COALESCE(SUM(total), 0) AS total')->first();
                TopicMastery::updateOrCreate(['user_id' => $user->id, 'course_id' => $quiz->course_id, 'topic' => $quiz->topic], ['mastery' => $totals->total ? (int) round($totals->correct / $totals->total * 100) : 0, 'signals' => ['correct' => (int) $totals->correct, 'total' => (int) $totals->total]]);
            }

            $answered = array_filter($answers, fn ($answer) => $answer['selected'] !== '');
            if (count($answered) >= 3) {
                $content = collect($questions)->pluck('question')->implode(' ').' '.collect($answers)->pluck('selected')->implode(' ');
                app(\App\Services\AmbassadorQualificationService::class)->recordStudyAction($user, 'practice_quiz', $content);
            }
            return $attempt;
        });
    }

    /** @return list<array<string,mixed>> */
    private function extractQuestions(array $data): array
    {
        $raw = $data['questions'] ?? ($data['data']['questions'] ?? null);

        if ($raw === null && is_string($data['answer'] ?? null)) {
            $raw = $this->decodeQuestionsFromText($data['answer']);
        }

        if (! is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $prompt = $item['question'] ?? $item['prompt'] ?? '';
            $question = is_string($prompt) ? trim($prompt) : '';
            if ($question === '') {
                continue;
            }

            $options = $item['options'] ?? [];
            if (! is_array($options)) {
                $options = [];
            }
            $options = array_slice(array_values(array_filter(array_map(
                static fn ($option): string => trim((string) $option),
                array_filter($options, 'is_scalar')
            ), static fn (string $option): bool => $option !== '' && mb_strlen($option) <= 1000)), 0, 5);
            $answer = is_scalar($item['answer'] ?? null) ? trim((string) $item['answer']) : '';
            if ($answer === '' || mb_strlen($answer) > 1000 || ($options !== [] && ! in_array($answer, $options, true))) {
                continue;
            }

            $questions[] = [
                'question' => mb_substr($question, 0, 2000),
                'options' => $options,
                'answer' => mb_substr($answer, 0, 1000),
                'explanation' => is_string($item['explanation'] ?? null) ? mb_substr(trim($item['explanation']), 0, 4000) : '',
                'source_excerpt' => is_string($item['source_excerpt'] ?? null) ? mb_substr(trim($item['source_excerpt']), 0, 1000) : '',
                'difficulty' => in_array($item['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $item['difficulty'] : 'medium',
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
        return StudentLearningProfile::query()->where('user_id', $user->id)->first()?->summaryLines() ?? [];
    }
}

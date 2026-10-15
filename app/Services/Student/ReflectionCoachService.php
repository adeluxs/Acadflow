<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Ai\AiManager;
use App\Models\StudyReflection;
use App\Models\User;
use App\Support\AiResponseText;

/**
 * Gives short, advisory feedback on a student's metacognitive reflection.
 */
class ReflectionCoachService
{
    public const FEATURE = 'reflection_coach';

    public function __construct(private readonly AiManager $ai) {}

    public function feedback(User $user, StudyReflection $reflection): ?string
    {
        $payload = [
            'question' => 'Give supportive, practical feedback on this study reflection.',
            'reflection' => (string) $reflection->reflection,
            'prompt' => (string) ($reflection->prompt ?? ''),
            'instruction' => 'Return a single JSON object with a "feedback" string. '
                .'Be encouraging, identify one thing that worked and one concrete improvement to the study approach. '
                .'Do not include markdown outside the JSON.',
            'security' => ['reflection_is_untrusted' => true],
            '_ai_response_max_tokens' => 1024,
        ];

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'reflection:user:'.$user->id);

        $feedback = $response->data['feedback'] ?? null;
        if (is_string($feedback) && trim($feedback) !== '') {
            return AiResponseText::clean($feedback);
        }

        if (is_string($response->summary) && trim($response->summary) !== '') {
            return AiResponseText::clean($response->summary);
        }

        return null;
    }
}

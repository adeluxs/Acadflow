<?php

namespace App\Ai\Features;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Ai\Support\TextExtractor;
use App\Models\User;
use App\Services\SettingService;

/**
 * AI Academic Writing Assistant (Phase 13).
 *
 * Provides grammar, tone, clarity, structure and readability suggestions while
 * students prepare reports. Suggestions are always reviewable - the assistant
 * never silently rewrites work.
 */
class WritingAssistantModule
{
    public function __construct(
        protected AiManager $manager,
        protected TextExtractor $extractor,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) SettingService::get('ai_feature_writing_assistant', true);
    }

    public function analyze(string $text, ?string $type = null, ?User $user = null): AiResponse
    {
        if (! $this->isEnabled()) {
            return new AiResponse('disabled', 'writing_assistant', false, summary: 'Writing assistant disabled.');
        }

        return $this->manager->analyze('writing_assistant', [
            'text' => $text,
            'type' => $type,
        ], $user);
    }
}

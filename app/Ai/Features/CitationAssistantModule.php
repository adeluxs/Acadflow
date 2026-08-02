<?php

namespace App\Ai\Features;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\User;
use App\Services\SettingService;

/**
 * AI Citation & Reference Assistant (Phase 14).
 *
 * Validates citations and bibliography against a chosen style (APA, MLA,
 * Chicago, Harvard, IEEE). Detects missing/uncited references, inconsistent
 * formatting, ordering issues, DOI and URL problems.
 */
class CitationAssistantModule
{
    public function __construct(protected AiManager $manager) {}

    public function isEnabled(): bool
    {
        return (bool) SettingService::get('ai_feature_citation_assistant', true);
    }

    public function supportedStyles(): array
    {
        return config('ai.citation_styles', ['apa', 'mla', 'chicago', 'harvard', 'ieee']);
    }

    public function analyze(string $text, string $style = 'apa', ?User $user = null): AiResponse
    {
        if (! $this->isEnabled()) {
            return new AiResponse('disabled', 'citation_assistant', false, summary: 'Citation assistant disabled.');
        }

        if (! in_array($style, $this->supportedStyles(), true)) {
            $style = 'apa';
        }

        return $this->manager->analyze('citation_assistant', [
            'text' => $text,
            'style' => $style,
        ], $user);
    }
}

<?php

namespace App\Ai\Rules;

/**
 * Discussion Rule Pack - moderation rules for discussion threads.
 */
class DiscussionRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'discussion';
    }

    public function label(): string
    {
        return 'Discussion Moderation';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';

        if ($text === '') {
            return $issues;
        }

        $profanity = ['spam', 'scam', 'idiot', 'stupid'];
        foreach ($profanity as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $text)) {
                $issues[] = $this->issue(
                    'inappropriate_language',
                    'Potentially inappropriate or unprofessional language detected.',
                    'warning',
                    2,
                    'Revise the language to keep discussions professional and respectful.',
                    'moderation'
                );
                break;
            }
        }

        return $this->result($issues);
    }
}

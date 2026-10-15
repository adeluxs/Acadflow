<?php

namespace App\Ai\Rules;

/**
 * Institution Rule Pack - validates documents against institution-specific rules.
 */
class InstitutionRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'institution';
    }

    public function label(): string
    {
        return 'Institution Policy';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $department = $this->department($context);
        $university = $department?->faculty?->university ?? null;

        if (! $university) {
            return $issues;
        }

        $required = $this->setting('ai_institution_required_sections', '');
        if (! empty($required)) {
            $text = strtolower($context['text'] ?? $context['content'] ?? '');
            foreach (array_map('trim', explode(',', $required)) as $section) {
                if ($section !== '' && ! str_contains($text, strtolower($section))) {
                    $issues[] = $this->issue(
                        'institution_missing_section',
                        "Missing institution-required section: {$section}.",
                        'warning',
                        3,
                        "Include the '{$section}' section per {$university->name} guidelines.",
                        'policy'
                    );
                }
            }
        }

        return $this->result($issues);
    }
}

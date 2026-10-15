<?php

namespace App\Ai\Rules;

class ResearchRulePack extends BaseRulePack
{
    public function key(): string { return 'research'; }
    public function label(): string { return 'Research Studio'; }

    public function analyze(array $context): array
    {
        $text = (string) ($context['text'] ?? $context['content'] ?? '');
        $sections = $context['sections'] ?? [];
        $requirements = $context['research_requirements'] ?? [];
        $issues = [];

        foreach (($requirements['required_sections'] ?? []) as $required) {
            $key = is_array($required) ? ($required['key'] ?? '') : (string) $required;
            $label = is_array($required) ? ($required['title'] ?? $key) : str($key)->headline()->toString();
            $section = collect($sections)->first(fn ($item) => ($item['key'] ?? null) === $key);
            if (! $section || trim(strip_tags((string) ($section['content'] ?? ''))) === '') {
                $issues[] = $this->issue('research_section_missing_'.$key, "Required research section '{$label}' is missing or empty.", 'critical', 1, "Complete the {$label} section before review.", 'research_structure');
            }
        }

        foreach (['research question', 'objective', 'methodology', 'limitation'] as $concept) {
            if ($text !== '' && ! preg_match('/\b'.preg_quote($concept, '/').'s?\b/i', $text)) {
                $issues[] = $this->issue('research_'.$concept.'_unclear', ucfirst($concept).' is not clearly identifiable in the research text.', 'warning', 3, 'Add an explicit, evidence-based '.str_replace(' ', ' ', $concept).' statement.', 'research_quality');
            }
        }

        if (! empty($context['open_corrections'])) {
            $issues[] = $this->issue('open_research_corrections', 'The project still has unresolved supervisor corrections.', 'critical', 1, 'Resolve or formally respond to all open corrections before advancing.', 'workflow');
        }

        return $this->result($issues, [
            'required_section_count' => count($requirements['required_sections'] ?? []),
            'open_corrections' => (int) ($context['open_corrections'] ?? 0),
        ]);
    }
}

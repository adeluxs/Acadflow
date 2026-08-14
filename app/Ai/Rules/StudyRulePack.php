<?php

namespace App\Ai\Rules;

class StudyRulePack extends BaseRulePack
{
    public function key(): string { return 'study'; }
    public function label(): string { return 'Study and Materials'; }

    public function analyze(array $context): array
    {
        $text = trim(strip_tags((string) ($context['text'] ?? $context['content'] ?? '')));
        if ($text === '') {
            return $this->result([$this->issue('study_source_missing', 'No authorized source material was supplied.', 'critical', 1, 'Select an accessible course material or publication.', 'grounding')]);
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
        $keywords = collect(str_word_count(mb_strtolower($text), 1))
            ->filter(fn ($word) => mb_strlen($word) > 5)
            ->countBy()->sortDesc()->keys()->take(12)->values()->all();

        return $this->result([], [
            'extractive_summary' => implode(' ', array_slice($sentences, 0, 5)),
            'keywords' => $keywords,
            'learning_objectives' => array_map(fn ($keyword) => "Explain {$keyword} using the authorized material.", array_slice($keywords, 0, 5)),
        ]);
    }
}

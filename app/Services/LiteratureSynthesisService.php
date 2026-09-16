<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LiteratureSynthesisService
{
    /**
     * Build deterministic synthesis signals from researcher-authored literature notes.
     * This service never invents missing findings; it only aggregates saved review fields.
     *
     * @param Collection<int, array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function build(Collection $rows): array
    {
        $reviewed = $rows->filter(fn (array $row): bool => ($row['reading_status'] ?? 'saved') === 'reviewed')->values();

        $themes = $reviewed
            ->flatMap(fn (array $row) => (array) ($row['keywords'] ?? []))
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->map(fn (string $keyword) => Str::lower($keyword))
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn (int $count, string $keyword) => ['label' => Str::headline($keyword), 'count' => $count])
            ->values();

        $methods = $this->methodologySignals($reviewed);

        return [
            'reviewed_count' => $reviewed->count(),
            'themes' => $themes,
            'methodologies' => $methods,
            'findings' => $this->fieldSnippets($reviewed, 'findings'),
            'limitations' => $this->fieldSnippets($reviewed, 'limitations'),
            'gaps' => $this->fieldSnippets($reviewed, 'research_gap'),
            'contradictions' => $this->fieldSnippets($reviewed, 'contradictions'),
            'coverage' => [
                'with_methodology' => $reviewed->filter(fn (array $row) => filled($row['methodology'] ?? null))->count(),
                'with_findings' => $reviewed->filter(fn (array $row) => filled($row['findings'] ?? null))->count(),
                'with_limitations' => $reviewed->filter(fn (array $row) => filled($row['limitations'] ?? null))->count(),
                'with_gaps' => $reviewed->filter(fn (array $row) => filled($row['research_gap'] ?? null))->count(),
            ],
        ];
    }

    /** @param Collection<int, array<string,mixed>> $reviewed */
    private function fieldSnippets(Collection $reviewed, string $field): Collection
    {
        return $reviewed
            ->filter(fn (array $row) => filled($row[$field] ?? null))
            ->map(fn (array $row) => [
                'reference_uuid' => $row['reference_uuid'] ?? null,
                'title' => $row['title'] ?? 'Untitled reference',
                'citation' => $row['in_text_citation'] ?? '',
                'text' => Str::limit(trim(strip_tags((string) $row[$field])), 260),
            ])
            ->take(8)
            ->values();
    }

    /** @param Collection<int, array<string,mixed>> $reviewed */
    private function methodologySignals(Collection $reviewed): Collection
    {
        $patterns = [
            'Random Forest' => ['random forest'],
            'Support Vector Machine' => ['support vector machine', 'svm'],
            'Neural Network / Deep Learning' => ['neural network', 'deep learning', 'cnn', 'lstm', 'transformer'],
            'Regression' => ['regression', 'logistic regression', 'linear regression'],
            'Survey / Questionnaire' => ['survey', 'questionnaire'],
            'Interview / Qualitative' => ['interview', 'qualitative'],
            'Mixed Methods' => ['mixed method'],
            'Experimental / Quasi-experimental' => ['experiment', 'quasi-experiment', 'randomized'],
            'Case Study' => ['case study'],
            'Systematic Review / Meta-analysis' => ['systematic review', 'meta-analysis', 'meta analysis'],
        ];

        $counts = collect();
        foreach ($reviewed as $row) {
            $text = Str::lower((string) ($row['methodology'] ?? ''));
            if ($text === '') {
                continue;
            }
            foreach ($patterns as $label => $needles) {
                if (collect($needles)->contains(fn (string $needle): bool => str_contains($text, $needle))) {
                    $counts[$label] = (int) ($counts[$label] ?? 0) + 1;
                }
            }
        }

        return $counts
            ->sortDesc()
            ->take(8)
            ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
            ->values();
    }
}

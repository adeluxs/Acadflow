<?php

namespace App\Ai\Rules;

/**
 * Plagiarism Rule Pack - analyzes a submission against a local corpus of prior
 * submissions (same course/university) using shingle overlap.
 */
class PlagiarismRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'plagiarism';
    }

    public function label(): string
    {
        return 'Plagiarism (Local Corpus)';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? '';
        $corpus = $context['local_corpus'] ?? [];
        $threshold = (int) ($context['threshold'] ?? 20);

        if ($text === '') {
            return $issues;
        }

        $words = preg_split('/\s+/', preg_replace('/\s+/', ' ', strip_tags($text)));
        $words = array_filter($words ?? [], fn ($w) => strlen($w) > 2);

        if (count($words) < 20) {
            return $issues;
        }

        $own = [];
        for ($i = 0; $i + 4 < count($words); $i++) {
            $own[] = md5(implode(' ', array_slice($words, $i, 5)));
        }
        $ownSet = array_flip($own);

        $best = 0;
        $bestSource = null;
        $matched = 0;

        foreach ($corpus as $doc) {
            $overlap = 0;
            foreach ($doc['shingles'] ?? [] as $sh) {
                if (isset($ownSet[$sh])) {
                    $overlap++;
                }
            }
            $total = count($doc['shingles'] ?? []);
            if ($total === 0) {
                continue;
            }
            $similarity = $overlap / $total * 100;

            if ($similarity > $best) {
                $best = $similarity;
                $bestSource = $doc;
                $matched = $overlap;
            }
        }

        $sourceList = [];
        if ($bestSource) {
            $sourceList[] = [
                'title' => $bestSource['title'] ?? 'Unknown',
                'uuid' => $bestSource['submission_uuid'] ?? null,
                'similarity' => round($best, 1),
            ];
        }

        $metrics = [
            'similarity' => round($best, 1),
            'risk_score' => round($best, 1),
            'sources' => $sourceList,
            'matched_paragraphs' => $matched,
        ];

        if ($best >= $threshold) {
            $issues[] = $this->issue(
                'high_similarity',
                "Significant similarity ({$best}%) detected with a previously submitted document.",
                'critical',
                1,
                'Review and properly paraphrase / cite sources. Ensure original work.',
                'plagiarism'
            );
        } elseif ($best >= ($threshold / 2)) {
            $issues[] = $this->issue(
                'moderate_similarity',
                "Moderate similarity ({$best}%) detected with existing submissions.",
                'warning',
                3,
                'Check that overlapping passages are properly cited.',
                'plagiarism'
            );
        }

        return $this->result($issues, $metrics);
    }
}

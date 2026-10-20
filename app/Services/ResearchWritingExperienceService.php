<?php

namespace App\Services;

use App\Models\ResearchProject;
use App\Models\ResearchSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResearchWritingExperienceService
{
    /**
     * Build the lightweight writing journey used by the Research Studio UI.
     * The project's immutable/template-created ResearchSection rows remain authoritative.
     */
    public function journey(ResearchProject $project): Collection
    {
        return $project->sections
            ->sortBy('position')
            ->values()
            ->map(function (ResearchSection $section): array {
                return [
                    'section' => $section,
                    'label' => $this->shortLabel($section),
                    'status_label' => $this->statusLabel($section),
                    'status_tone' => $this->statusTone($section),
                    'progress' => $this->sectionProgress($section),
                    'group' => $this->sectionGroup($section),
                ];
            });
    }

    public function activeSection(ResearchProject $project, ?string $requested): ?ResearchSection
    {
        $sections = $project->sections->sortBy('position')->values();
        if ($sections->isEmpty()) {
            return null;
        }

        if ($requested) {
            $selected = $sections->first(fn (ResearchSection $section) =>
                (string) $section->uuid === $requested || (string) $section->key === $requested
            );
            if ($selected) {
                return $selected;
            }
        }

        return $sections->first(fn (ResearchSection $section) => ! in_array($section->status, ['approved'], true))
            ?? $sections->first();
    }

    public function guidance(?ResearchSection $section): array
    {
        if (! $section) {
            return [
                'eyebrow' => 'Writing workspace',
                'title' => 'Start your research document',
                'description' => 'Choose a section from the research navigator to start writing.',
                'checklist' => [],
                'ai_prompts' => [],
            ];
        }

        $configured = app(ResearchMethodologyService::class)->guidance($section->project, $section->key);
        if ($configured && ! empty($configured['guidance'])) {
            return $this->pack('Methodology guidance', $section->title,
                (string) data_get($section->project->metadata, 'research_setup.method.decision', 'Follow your institutional requirements.'),
                $configured['guidance'], ['Review this section against my methodology and objectives.', 'Identify unsupported claims and missing evidence.']);
        }
        $group = $this->sectionGroup($section);

        return match ($group) {
            'abstract' => $this->pack(
                'Research summary',
                'Abstract',
                'Give a concise, self-contained summary of the problem, approach, major result and conclusion.',
                ['Background / problem', 'Purpose or objective', 'Method / approach', 'Main result', 'Conclusion / implication'],
                ['Review my abstract for clarity and completeness.', 'Which parts of this abstract are too vague?', 'Check whether the abstract matches the rest of my project context.']
            ),
            'chapter_1' => $this->pack(
                'Foundation',
                'Chapter 1 · Introduction',
                'Establish the research problem, why it matters, and exactly what the study intends to answer.',
                ['Background of the study', 'Statement of the problem', 'Aim / purpose', 'Research objectives', 'Research questions / hypotheses', 'Significance of the study', 'Scope / definitions'],
                ['Review whether my research problem is clear and evidence-based.', 'Check alignment between my objectives and research questions.', 'What important context is missing from this introduction?']
            ),
            'chapter_2' => $this->pack(
                'Evidence base',
                'Chapter 2 · Literature Review',
                'Synthesize prior work by themes and evidence. Use the reviewed-reference panel to bring verified literature notes into your draft.',
                ['Introduction to the review', 'Conceptual review', 'Theoretical framework', 'Empirical review', 'Literature synthesis', 'Research gap', 'Chapter summary'],
                ['What themes are emerging from my reviewed literature?', 'Compare the methodologies in my reviewed studies.', 'Which research gaps are repeatedly supported by my literature notes?']
            ),
            'chapter_3' => $this->pack(
                'Research design',
                'Chapter 3 · Methodology',
                'Explain exactly how the study will answer the research questions so another researcher could understand and evaluate the process.',
                ['Research design', 'Population / study context', 'Sample and sampling technique', 'Research instrument / data source', 'Validity / trustworthiness', 'Reliability / dependability', 'Data collection procedure', 'Data analysis method', 'Ethical considerations'],
                ['Check whether this methodology aligns with my research questions.', 'Identify unclear or missing methodological decisions.', 'Review whether my sampling and analysis plan are internally consistent.']
            ),
            'chapter_4' => $this->pack(
                'Evidence and analysis',
                'Chapter 4 · Results / Analysis',
                'Present the evidence in a traceable order, then explain what it shows without overstating the results.',
                ['Results overview', 'Research question / hypothesis results', 'Tables / figures where appropriate', 'Analysis / interpretation', 'Link results back to the research questions', 'Discussion of important patterns'],
                ['Check whether my results answer each research question.', 'Help me separate results from interpretation.', 'Which claims in this analysis need stronger evidence?']
            ),
            'chapter_5' => $this->pack(
                'Research contribution',
                'Chapter 5 · Discussion & Conclusion',
                'Bring the project together: explain the meaning of the findings, conclusions, recommendations and contribution.',
                ['Summary of findings', 'Discussion of findings', 'Conclusion', 'Recommendations', 'Contribution to knowledge / practice', 'Suggestions for further research'],
                ['Check whether my conclusion follows from the findings.', 'Which recommendations are not sufficiently supported?', 'Help me distinguish contribution from general summary.']
            ),
            'references' => $this->pack(
                'Research integrity',
                'References',
                'Keep the final reference list consistent with the project citation style and the sources actually used in the document.',
                ['Every in-text citation has a reference entry', 'Reference style is consistent', 'Author/year/title metadata is complete', 'Duplicate references are removed', 'DOIs or stable URLs are included where available'],
                ['Check my reference-list consistency.', 'Which saved references may be missing bibliographic metadata?', 'Explain the citation-style issues I should verify.']
            ),
            'appendix' => $this->pack(
                'Supporting material',
                $section->title,
                'Keep supporting instruments, extended tables, protocols or supplementary material organized and clearly labelled.',
                ['Clear appendix title', 'Referenced from the main document', 'Sensitive information removed where required', 'Tables/instruments are readable'],
                ['Review whether this appendix is necessary and clearly labelled.', 'What should be referenced from the main document?']
            ),
            'literature_review' => $this->pack(
                'Evidence base',
                $section->title,
                'Synthesize prior work by themes and evidence. Use reviewed references rather than treating the literature review as a list of summaries.',
                ['Review scope', 'Key concepts / themes', 'Theoretical basis', 'Empirical evidence', 'Synthesis', 'Research gap'],
                ['What themes are emerging from my reviewed literature?', 'Compare the methodologies in my reviewed studies.', 'Which gaps are supported by my notes?']
            ),
            'methods' => $this->pack(
                'Research design',
                $section->title,
                'Describe the study design, data, procedures and analysis decisions clearly enough to be evaluated.',
                ['Study design', 'Participants / data source', 'Procedure', 'Measures / instruments', 'Analysis', 'Ethics / limitations'],
                ['Check whether my methods are reproducible.', 'Which methodological choices need justification?']
            ),
            'results' => $this->pack(
                'Evidence and analysis',
                $section->title,
                'Present results clearly, link them to the research questions and avoid unsupported interpretation.',
                ['Main results', 'Tables / figures', 'Research-question alignment', 'Statistical / qualitative evidence', 'Key patterns'],
                ['Check whether the results answer my questions.', 'Which claims need stronger evidence?']
            ),
            'discussion' => $this->pack(
                'Interpretation',
                $section->title,
                'Interpret the findings in relation to prior evidence, limitations and the study objectives.',
                ['Meaning of findings', 'Comparison with literature', 'Unexpected findings', 'Limitations', 'Implications'],
                ['Compare my discussion with the reviewed literature.', 'Which interpretations go beyond the evidence?']
            ),
            default => $this->pack(
                'Research writing',
                $section->title,
                'Work on this section in a focused editor. Follow the project template and use the Research Assistant for source-aware review.',
                ['State the purpose of the section', 'Use evidence where claims require support', 'Keep terminology consistent', 'Conclude or transition clearly'],
                ['Review this section for academic clarity.', 'What is missing from this section?', 'Identify unsupported claims or structural problems.']
            ),
        };
    }

    public function nextAction(ResearchProject $project): array
    {
        $sections = $project->sections->sortBy('position')->values();
        $current = $sections->first(fn (ResearchSection $section) => ! in_array($section->status, ['approved'], true));
        if ($current) {
            return [
                'label' => 'Continue '.$this->shortLabel($current),
                'description' => $current->status === 'correction_requested'
                    ? 'A correction is waiting in this section.'
                    : 'Continue the next unfinished part of your research document.',
                'section' => $current,
            ];
        }

        return [
            'label' => 'Review completed document',
            'description' => 'All configured sections are approved. Review the complete document and validation status.',
            'section' => $sections->first(),
        ];
    }

    private function pack(string $eyebrow, string $title, string $description, array $checklist, array $aiPrompts): array
    {
        // Keep the guidance contract consistent with the Blade/API consumers.
        // Using compact() here previously exposed `aiPrompts` while the UI
        // correctly expected the public snake_case key `ai_prompts`.
        return [
            'eyebrow' => $eyebrow,
            'title' => $title,
            'description' => $description,
            'checklist' => $checklist,
            'ai_prompts' => $aiPrompts,
        ];
    }

    private function sectionProgress(ResearchSection $section): int
    {
        if ($section->status === 'approved') {
            return 100;
        }

        $stored = (float) ($section->completion_percent ?? 0);
        if ($stored > 0) {
            return (int) max(0, min(100, round($stored)));
        }

        $hasBody = trim(strip_tags((string) ($section->document?->body ?? ''))) !== '';
        return match ($section->status) {
            'review', 'ready_for_review' => 75,
            'correction_requested' => 65,
            'in_progress' => $hasBody ? 45 : 25,
            default => $hasBody ? 25 : 0,
        };
    }

    private function statusLabel(ResearchSection $section): string
    {
        return match ($section->status) {
            'approved' => 'Completed',
            'review', 'ready_for_review' => 'Ready for review',
            'correction_requested' => 'Correction needed',
            'in_progress' => 'In progress',
            default => 'Not started',
        };
    }

    private function statusTone(ResearchSection $section): string
    {
        return match ($section->status) {
            'approved' => 'emerald',
            'review', 'ready_for_review' => 'blue',
            'correction_requested' => 'amber',
            'in_progress' => 'indigo',
            default => 'slate',
        };
    }

    private function shortLabel(ResearchSection $section): string
    {
        $title = trim((string) $section->title);
        if (preg_match('/^(Chapter\s+\d+)/i', $title, $match)) {
            return $match[1];
        }

        return Str::limit($title, 32);
    }

    private function sectionGroup(ResearchSection $section): string
    {
        $key = Str::lower((string) $section->key);
        $title = Str::lower((string) $section->title);
        $haystack = $key.' '.$title;

        if (str_contains($haystack, 'abstract')) return 'abstract';
        if (preg_match('/chapter[_\s:-]*1\b/', $haystack) || str_contains($haystack, 'introduction')) return 'chapter_1';
        if (preg_match('/chapter[_\s:-]*2\b/', $haystack)) return 'chapter_2';
        if (str_contains($haystack, 'literature')) return 'literature_review';
        if (preg_match('/chapter[_\s:-]*3\b/', $haystack)) return 'chapter_3';
        if (str_contains($haystack, 'method')) return 'methods';
        if (preg_match('/chapter[_\s:-]*4\b/', $haystack)) return 'chapter_4';
        if (str_contains($haystack, 'result') || str_contains($haystack, 'analysis')) return 'results';
        if (preg_match('/chapter[_\s:-]*5\b/', $haystack)) return 'chapter_5';
        if (str_contains($haystack, 'discussion') || str_contains($haystack, 'conclusion')) return 'discussion';
        if (str_contains($haystack, 'reference') || str_contains($haystack, 'bibliograph')) return 'references';
        if (str_contains($haystack, 'append')) return 'appendix';

        return 'generic';
    }
}

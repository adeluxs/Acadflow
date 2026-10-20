<?php

namespace App\Services;

use App\Models\ResearchProject;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResearchMethodologyService
{
    public function rules(): array
    {
        return [
            'methodology' => ['nullable', Rule::in(array_keys(config('research_methodologies.methodologies')))],
            'research_level' => ['nullable', Rule::in(array_keys(config('research_methodologies.levels')))],
            'discipline' => ['nullable', 'string', 'max:180'],
            'objectives' => ['nullable', 'string', 'max:6000'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'studio_mode' => ['nullable', Rule::in(['research', 'final_year'])],
            'report_structure' => ['nullable', Rule::in(['methodology', 'five_chapter', 'institutional'])],
            'citation_style' => ['nullable', Rule::in(array_keys(config('research_methodologies.styles')))],
            'citation_instructions' => ['nullable', 'string', 'max:3000'],
            'citation_template' => $this->citationTemplateRules(),
        ];
    }

    public function citationTemplateRules(): array
    {
        return ['nullable', 'string', 'max:1000', function ($attribute, $value, $fail) {
            preg_match_all('/\{([^}]+)\}/', $value, $matches);
            if (array_diff($matches[1], ['authors', 'year', 'title', 'container', 'identifier', 'number']) !== [] || !str_contains($value, '{title}')) {
                $fail('Use a pattern containing {title} and only supported bibliography fields.');
            }
        }];
    }

    public function snapshot(array $data): ?array
    {
        $key = $data['methodology'] ?? null;
        if (! $key) return null; // Existing clients and institution templates remain authoritative.
        $method = config('research_methodologies.methodologies.'.$key);
        abort_unless(is_array($method), 422, 'Choose a supported methodology.');
        return [
            'catalogue_version' => config('research_methodologies.version'),
            'methodology' => $key, 'method' => $method,
            'research_level' => $data['research_level'] ?? 'professional',
            'discipline' => $data['discipline'] ?? '', 'objectives' => $data['objectives'] ?? '',
            'institution_name' => $data['institution_name'] ?? '',
            'studio_mode' => $data['studio_mode'] ?? 'research',
            'report_structure' => $data['report_structure'] ?? 'methodology',
            'citation_style' => $data['citation_style'] ?? 'apa',
            'citation_instructions' => $data['citation_instructions'] ?? '',
            'citation_template' => $data['citation_template'] ?? '',
        ];
    }

    public function sections(array $setup): array
    {
        if ($setup['report_structure'] === 'five_chapter') {
            $sections = [
                'preliminary_pages' => ['Preliminary pages', ['Title page and declaration', 'Certification placeholders for authorised signatures only', 'Acknowledgements', 'Lists of tables, figures and abbreviations']],
                'abstract' => ['Abstract', ['Problem and aim', 'Actual methods', 'Verified findings when available', 'Conclusion']],
                'chapter_1' => ['Chapter 1: Introduction', ['Background', 'Problem statement', 'Aim and objectives', 'Research questions', 'Hypotheses only where appropriate', 'Significance', 'Scope and limitations']],
                'chapter_2' => ['Chapter 2: Literature Review', ['Conceptual framework', 'Theoretical framework where appropriate', 'Empirical review', 'Synthesis and supported research gaps']],
                'chapter_3' => ['Chapter 3: Methodology', array_merge($setup['method']['requirements'], ['Design and context', 'Sampling or source selection', 'Instruments and procedures', 'Ethics and analysis plan'])],
                'chapter_4' => ['Chapter 4: Results, Implementation and Discussion', ['Actual evidence or implementation', 'Analysis and findings', 'Discussion against objectives and prior research', 'Uncertainty and alternative explanations']],
                'chapter_5' => ['Chapter 5: Conclusions and Recommendations', ['Summary of findings', 'Conclusions supported by evidence', 'Recommendations', 'Limitations', 'Future work']],
            ];
        } else {
            $sections = [];
            foreach ($setup['method']['sections'] as $title) {
                $sections[Str::slug($title, '_')] = [$title, array_merge([$setup['method']['decision']], $setup['method']['requirements'])];
            }
            $sections = ['abstract' => ['Abstract', ['Research question', 'Actual approach', 'Supported findings and contribution']]] + $sections;
        }
        $sections += [
            'references' => ['References', ['Cite only sources actually consulted', 'Verify author, title, date and DOI', 'Follow the selected citation style and institutional instructions']],
            'appendices' => ['Appendices', ['Instruments and supplementary evidence', 'Anonymise participant information', 'Use authorised documents only']],
        ];
        if ($setup['studio_mode'] === 'final_year') $sections['defence_preparation'] = ['Defence preparation', ['Explain the problem and contribution', 'Justify methodology and alternatives', 'Prepare evidence for key claims', 'Discuss limitations and likely questions']];
        return collect($sections)->map(fn ($row, $key) => ['key' => $key, 'title' => $row[0], 'required' => ! in_array($key, ['appendices', 'defence_preparation']), 'initial_content' => '', 'guidance' => $row[1]])->values()->all();
    }

    public function guidance(ResearchProject $project, string $sectionKey): ?array
    {
        return collect(data_get($project->metadata, 'research_setup.section_schema', []))->firstWhere('key', $sectionKey);
    }
}

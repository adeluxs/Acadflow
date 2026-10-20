<?php

namespace App\Services;

use App\Models\AcademicReference;
use App\Models\AcademicReferenceLink;
use App\Models\ContentDocument;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Str;

class AcademicReferenceService
{
    public function create(array $data, User $owner): AcademicReference
    {
        $data['authors'] = is_string($data['authors'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $data['authors']))))
            : ($data['authors'] ?? []);
        $data['university_id'] = $owner->university_id;
        $data['owner_id'] = $owner->id;
        $data['citation_key'] ??= Str::slug(($data['authors'][0] ?? 'source').'-'.($data['publication_year'] ?? 'nd'), '_');

        return AcademicReference::create($data);
    }

    public function attach(AcademicReference $reference, ResearchProject|KnowledgePublication|ContentDocument $target, string $purpose = 'citation'): AcademicReferenceLink
    {
        $attributes = ['academic_reference_id' => $reference->id, 'purpose' => $purpose];
        $attributes[match (true) {
            $target instanceof ResearchProject => 'research_project_id',
            $target instanceof KnowledgePublication => 'knowledge_publication_id',
            default => 'content_document_id',
        }] = $target->id;

        return AcademicReferenceLink::firstOrCreate($attributes);
    }

    /**
     * Format a stored reference in an institution-enabled citation style.
     * The formatter intentionally uses stored metadata only; it never invents
     * missing authors, dates, journals, identifiers or access dates.
     */
    public function format(AcademicReference $reference, string $style = 'apa', int $number = 1): string
    {
        $style = strtolower($style);
        if (! in_array($style, config('ai.citation_styles', []), true)) {
            $style = 'apa';
        }

        $authors = $this->authors($reference);
        $year = $reference->publication_year ? (string) $reference->publication_year : 'n.d.';
        $title = trim((string) $reference->title);
        $container = trim((string) ($reference->journal ?: $reference->publisher));
        $identifier = $reference->doi ? 'https://doi.org/'.preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $reference->doi) : trim((string) $reference->url);

        return trim(match ($style) {
            'mla' => $authors.'. “'.$title.'.” '.($container ? $container.', ' : '').$year.($identifier ? ', '.$identifier : '').'.',
            'chicago' => $authors.'. “'.$title.'.” '.($container ? $container.' ' : '').'('.$year.')'.($identifier ? '. '.$identifier : '').'.',
            'harvard' => $authors.' ('.$year.') ‘'.$title.'’'.($container ? ', '.$container : '').($identifier ? '. Available at: '.$identifier : '').'.',
            'ieee' => '['.max(1, $number).'] '.$authors.', “'.$title.',”'.($container ? ' '.$container.',' : '').' '.$year.($identifier ? '. '.$identifier : '').'.',
            'vancouver' => max(1, $number).'. '.$authors.'. '.$title.'.'.($container ? ' '.$container.'.' : '').' '.$year.'.'.($identifier ? ' '.$identifier.'.' : ''),
            default => $authors.' ('.$year.'). '.$title.'.'.($container ? ' '.$container.'.' : '').($identifier ? ' '.$identifier : ''),
        });
    }

    public function formatForProject(AcademicReference $reference, ResearchProject $project, int $number = 1, ?string $style = null): string
    {
        $template = trim((string) data_get($project->metadata, 'research_setup.citation_template', ''));
        if ($template === '' || $style !== null) return $this->format($reference, $style ?? data_get($project->metadata, 'citation_style', $project->templateVersion?->citation_style ?? 'apa'), $number);
        return strtr($template, [
            '{authors}' => $this->authors($reference),
            '{year}' => $reference->publication_year ? (string) $reference->publication_year : 'n.d.',
            '{title}' => (string) $reference->title,
            '{container}' => (string) ($reference->journal ?: $reference->publisher),
            '{identifier}' => $reference->doi ? 'https://doi.org/'.preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $reference->doi) : (string) $reference->url,
            '{number}' => (string) max(1, $number),
        ]);
    }

    public function inText(AcademicReference $reference, string $style = 'apa', int $number = 1): string
    {
        $style = strtolower($style);
        if (in_array($style, ['ieee', 'vancouver'], true)) {
            return $style === 'ieee' ? '['.max(1, $number).']' : (string) max(1, $number);
        }

        $authors = $this->authorNames($reference);
        $lead = trim((string) ($authors[0] ?? 'Unknown'));
        if (count($authors) > 1) {
            $lead .= $style === 'mla' ? ' et al.' : ' et al.';
        }
        $year = $reference->publication_year ?: 'n.d.';

        return $style === 'mla' ? '('.$lead.')' : '('.$lead.', '.$year.')';
    }

    /** @param iterable<AcademicReference> $references */
    public function bibliography(iterable $references, string $style = 'apa'): array
    {
        $formatted = [];
        foreach ($references as $index => $reference) {
            $formatted[] = $this->format($reference, $style, $index + 1);
        }

        if (! in_array(strtolower($style), ['ieee', 'vancouver'], true)) {
            natcasesort($formatted);
            $formatted = array_values($formatted);
        }

        return $formatted;
    }

    private function authors(AcademicReference $reference): string
    {
        $authors = $this->authorNames($reference);
        if ($authors === []) {
            return 'Unknown author';
        }
        if (count($authors) === 1) {
            return $authors[0];
        }
        if (count($authors) === 2) {
            return $authors[0].' and '.$authors[1];
        }

        return implode(', ', array_slice($authors, 0, -1)).', and '.$authors[array_key_last($authors)];
    }

    /** @return array<int,string> */
    private function authorNames(AcademicReference $reference): array
    {
        return collect($reference->authors ?? [])
            ->map(function ($author): ?string {
                if (is_string($author)) {
                    return trim($author) ?: null;
                }
                if (! is_array($author)) {
                    return null;
                }

                $name = data_get($author, 'author.display_name')
                    ?? ($author['display_name'] ?? null)
                    ?? ($author['name'] ?? null)
                    ?? trim((string) (($author['given'] ?? '').' '.($author['family'] ?? '')));

                return filled($name) ? trim((string) $name) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

}

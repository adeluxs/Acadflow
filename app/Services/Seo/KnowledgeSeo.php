<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeSeries;
use App\Models\KnowledgeWritingChallenge;
use App\Services\SettingService;
use Illuminate\Support\Str;

/**
 * Search and AI-engine metadata for public Knowledge Hub pages: title, description,
 * canonical URL, social cards and schema.org JSON-LD. Pages hand the result to the
 * `partials.seo` head partial; pages that don't are treated as private app pages.
 */
class KnowledgeSeo
{
    public function siteName(): string
    {
        return (string) SettingService::getGlobal('site_name', 'AcadSyntra');
    }

    public function defaultImage(): string
    {
        return asset('branding/acadsyntra-social.png');
    }

    /** True when search engines may list this guide (published, public, not institution-only). */
    public function indexable(KnowledgePublication $publication): bool
    {
        return $publication->status === 'published' && $publication->visibility === 'public'
            && in_array($publication->access_type, ['free', 'premium'], true);
    }

    public function description(?string $text, string $fallback = ''): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($text ?: $fallback))));

        return Str::limit($text, 158, '…');
    }

    /** @return array<string,mixed> */
    public function publication(KnowledgePublication $publication, bool $fullTextShown): array
    {
        $url = route('knowledge.show', $publication);
        $publication->loadMissing(['creator:id,uuid,first_name,last_name', 'category:id,name,slug', 'university:id,name', 'publicationType:id,name']);
        // Never build the summary from text this visitor can't read (premium or locked guides).
        $description = $this->description($publication->excerpt, $fullTextShown && $publication->access_type === 'free'
            ? (string) ($publication->document?->body ?? '')
            : $publication->title.' · a '.Str::lower((string) ($publication->publicationType?->name ?? 'guide')).' on the '.$this->siteName().' Knowledge Hub.');
        $author = $publication->creator ? array_filter([
            '@type' => 'Person', 'name' => $publication->creator->full_name,
            'url' => \Illuminate\Support\Facades\Route::has('knowledge.creator') && $publication->creator->uuid ? route('knowledge.creator', $publication->creator->uuid) : null,
            'affiliation' => $publication->university ? ['@type' => 'CollegeOrUniversity', 'name' => $publication->university->name] : null,
        ]) : ['@type' => 'Organization', 'name' => $this->siteName()];
        $isFree = $publication->access_type === 'free';

        $article = array_filter([
            '@type' => $publication->publicationType?->name && Str::contains(Str::lower($publication->publicationType->name), ['research', 'thesis', 'journal', 'preprint']) ? 'ScholarlyArticle' : 'Article',
            '@id' => $url.'#article',
            'headline' => Str::limit((string) $publication->title, 110, ''),
            'description' => $description,
            'url' => $url,
            'mainEntityOfPage' => $url,
            'image' => [$this->defaultImage()],
            'datePublished' => $publication->published_at?->toAtomString(),
            'dateModified' => ($publication->updated_at ?? $publication->published_at)?->toAtomString(),
            'author' => $author,
            'publisher' => $this->organization(),
            'articleSection' => $publication->category?->name,
            'genre' => $publication->publicationType?->name,
            'inLanguage' => $publication->language ?: 'en',
            'wordCount' => $publication->document?->word_count ?: null,
            'isAccessibleForFree' => $isFree,
            'educationalLevel' => 'Higher education',
        ], fn ($value) => $value !== null && $value !== '');
        if (! $isFree) {
            // Premium text isn't sent to visitors at all; only the summary is public.
            $article['isAccessibleForFree'] = false;
        }

        $crumbs = [['Knowledge Hub', route('knowledge.index')]];
        if ($publication->category) {
            $crumbs[] = [$publication->category->name, route('knowledge.index', ['category' => $publication->category->slug])];
        }
        $crumbs[] = [(string) $publication->title, $url];

        return [
            'title' => $publication->title.' · '.$this->siteName().' Knowledge Hub',
            'description' => $description,
            'canonical' => $url,
            'image' => $this->defaultImage(),
            'type' => 'article',
            'robots' => $this->indexable($publication) ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, follow',
            'article' => [
                'published_time' => $publication->published_at?->toAtomString(),
                'modified_time' => $publication->updated_at?->toAtomString(),
                'section' => $publication->category?->name,
                'author' => $publication->creator?->full_name,
            ],
            'schema' => [$article, $this->breadcrumbs($crumbs)],
        ];
    }

    /** @return array<string,mixed> */
    public function hub(bool $filtered): array
    {
        $url = route('knowledge.index');

        return [
            'title' => 'Knowledge Hub: study guides, exam tips and student stories · '.$this->siteName(),
            'description' => 'Free study guides, exam preparation, SIWES and career advice, project help and real student stories written by students and lecturers across Nigerian universities.',
            'canonical' => $url,
            'image' => $this->defaultImage(),
            'type' => 'website',
            // Search results and filtered lists are thin duplicates of the hub itself.
            'robots' => $filtered ? 'noindex, follow' : 'index, follow, max-image-preview:large',
            'schema' => [[
                '@type' => 'CollectionPage', '@id' => $url.'#hub', 'url' => $url, 'name' => $this->siteName().' Knowledge Hub',
                'description' => 'Study guides, exam preparation, career and SIWES advice, and student stories.',
                'isPartOf' => ['@type' => 'WebSite', 'name' => $this->siteName(), 'url' => route('home')],
                'publisher' => $this->organization(),
            ]],
        ];
    }

    /** @return array<string,mixed> */
    public function writingChallenge(KnowledgeWritingChallenge $challenge): array
    {
        $url = route('knowledge.writing.show', $challenge);
        $prize = '₦'.number_format(intdiv($challenge->prizePoolMinor(), 100));
        $description = $this->description($challenge->description, $challenge->title.': win '.$prize.' on the '.$this->siteName().' Knowledge Hub.');

        return [
            'title' => $challenge->title.' · '.$prize.' writing challenge · '.$this->siteName(),
            'description' => $description,
            'canonical' => $url,
            'image' => $this->defaultImage(),
            'type' => 'website',
            'robots' => $challenge->isVisible() ? 'index, follow, max-image-preview:large' : 'noindex, nofollow',
            'schema' => [array_filter([
                '@type' => 'Event', 'name' => $challenge->title, 'description' => $description, 'url' => $url,
                'startDate' => $challenge->starts_at?->toAtomString(), 'endDate' => $challenge->submission_deadline?->toAtomString(),
                'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode', 'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => ['@type' => 'VirtualLocation', 'url' => $url], 'organizer' => $this->organization(),
                'isAccessibleForFree' => true, 'image' => [$this->defaultImage()],
            ])],
        ];
    }

    /** @return array<string,mixed> */
    public function series(KnowledgeSeries $series): array
    {
        $url = route('knowledge.series.show', $series);

        return [
            'title' => $series->title.' · '.$this->siteName().' Knowledge Hub',
            'description' => $this->description($series->description ?? null, $series->title),
            'canonical' => $url,
            'image' => $this->defaultImage(),
            'type' => 'website',
            'robots' => 'index, follow',
            'schema' => [['@type' => 'CreativeWorkSeries', 'name' => $series->title, 'url' => $url, 'publisher' => $this->organization()]],
        ];
    }

    /** @return array<string,mixed> */
    public function organization(): array
    {
        return ['@type' => 'Organization', 'name' => $this->siteName(), 'url' => route('home'),
            'logo' => ['@type' => 'ImageObject', 'url' => asset('branding/acadsyntra-icon-512.png')]];
    }

    /** @param list<array{0:string,1:string}> $crumbs */
    private function breadcrumbs(array $crumbs): array
    {
        return ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn ($crumb, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1],
        ], $crumbs, array_keys($crumbs))];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeSeries;
use App\Models\KnowledgeWritingChallenge;
use App\Services\FeatureAccessService;
use App\Services\PublicWebsite;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * sitemap.xml is an index pointing at smaller sitemaps: the marketing pages, every public
 * Knowledge Hub guide (in chunks so it stays fast with thousands of guides), and the
 * series, challenge and creator pages. Each part is cached for an hour.
 */
class SitemapController extends Controller
{
    public const PER_FILE = 5000;

    public function index(): Response
    {
        $parts = Cache::remember('sitemap:index', 3600, function (): array {
            $parts = [['loc' => route('sitemap.part', 'pages'), 'lastmod' => null]];
            if ($this->hubEnabled()) {
                $guides = $this->guides();
                $chunks = max(1, (int) ceil((clone $guides)->count() / self::PER_FILE));
                for ($i = 1; $i <= $chunks; $i++) {
                    $parts[] = ['loc' => route('sitemap.part', 'knowledge-'.$i), 'lastmod' => (clone $guides)->max('updated_at')];
                }
                $parts[] = ['loc' => route('sitemap.part', 'knowledge-collections'), 'lastmod' => null];
            }

            return $parts;
        });

        return response()->view('public.sitemap-index', compact('parts'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function part(string $name): Response
    {
        $urls = Cache::remember('sitemap:'.$name, 3600, function () use ($name): ?array {
            if ($name === 'pages') {
                $urls = [['loc' => route('home'), 'lastmod' => null]];
                foreach (PublicWebsite::PATHS as $slug => $path) {
                    $urls[] = ['loc' => route('public.'.$slug), 'lastmod' => null];
                }
                foreach (['documentation', 'security'] as $slug) {
                    $urls[] = ['loc' => route('public.page', ['page' => $slug]), 'lastmod' => null];
                }

                return $urls;
            }
            if (! $this->hubEnabled()) {
                return null;
            }
            if (preg_match('/^knowledge-(\d+)$/', $name, $m)) {
                $page = max(1, (int) $m[1]);

                return $this->guides()->orderBy('id')->offset(($page - 1) * self::PER_FILE)->limit(self::PER_FILE)
                    ->get(['slug', 'updated_at', 'published_at'])
                    ->map(fn ($row) => ['loc' => route('knowledge.show', $row->slug), 'lastmod' => $row->updated_at ?? $row->published_at])->all() ?: null;
            }
            if ($name === 'knowledge-collections') {
                $urls = [['loc' => route('knowledge.index'), 'lastmod' => null]];
                $urls[] = ['loc' => route('knowledge.writing.index'), 'lastmod' => null];
                foreach (KnowledgeWritingChallenge::query()->where('status', 'published')->latest('starts_at')->limit(200)->get(['uuid', 'updated_at']) as $challenge) {
                    $urls[] = ['loc' => route('knowledge.writing.show', $challenge), 'lastmod' => $challenge->updated_at];
                }
                foreach (KnowledgeSeries::query()->withoutGlobalScopes()->where('status', 'published')->where('visibility', 'public')->whereNull('deleted_at')->limit(2000)->get(['slug', 'updated_at']) as $series) {
                    $urls[] = ['loc' => route('knowledge.series.show', $series->slug), 'lastmod' => $series->updated_at];
                }
                $creators = DB::table('knowledge_publications')->join('users', 'users.id', '=', 'knowledge_publications.creator_id')
                    ->where('knowledge_publications.status', 'published')->where('knowledge_publications.visibility', 'public')
                    ->whereIn('knowledge_publications.access_type', ['free', 'premium'])->whereNotNull('users.uuid')
                    ->groupBy('users.uuid')->selectRaw('users.uuid, MAX(knowledge_publications.updated_at) AS lastmod')->limit(5000)->get();
                foreach ($creators as $creator) {
                    $urls[] = ['loc' => route('knowledge.creator', $creator->uuid), 'lastmod' => $creator->lastmod];
                }

                return $urls;
            }

            return null;
        });
        abort_if($urls === null, 404);

        return response()->view('public.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** Guides search engines may list: published, public, free or premium (not institution-only). */
    private function guides()
    {
        return KnowledgePublication::query()->withoutGlobalScopes()->where('status', 'published')
            ->where('visibility', 'public')->whereIn('access_type', ['free', 'premium'])->whereNull('deleted_at');
    }

    private function hubEnabled(): bool
    {
        return FeatureAccessService::effectiveStatus('knowledge_hub', null) === FeatureAccessService::STATUS_ENABLED;
    }
}

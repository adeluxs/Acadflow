<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePublication;
use App\Services\FeatureAccessService;
use App\Services\PublicWebsite;
use App\Services\Seo\KnowledgeSeo;
use App\Services\SettingService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Discovery files for search engines and AI assistants:
 *  - /robots.txt  (which crawlers may read what)
 *  - /llms.txt    (a plain-text map of AcadSyntra and its best free guides, for AI engines)
 *  - /{key}.txt   (IndexNow ownership proof for Bing, Yandex and others)
 */
class AiDiscoveryController extends Controller
{
    /** AI crawlers that are welcome on public, free content. */
    public const AI_BOTS = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot-Extended', 'CCBot'];

    public function robots(): Response
    {
        $private = ['/admin/', '/dashboard', '/api/', '/login', '/register', '/knowledge-hub/manage', '/knowledge-hub/rewards', '/knowledge-hub/search', '/*?*q=', '/refresh-app'];
        $lines = ['User-agent: *', 'Allow: /'];
        foreach ($private as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $allowAi = app(\App\Services\Knowledge\ReadEarnSettings::class)->bool('seo_allow_ai_crawlers');
        foreach (self::AI_BOTS as $bot) {
            $lines[] = 'User-agent: '.$bot;
        }
        if ($allowAi) {
            // Free guides and public pages may be read and cited; premium guide text is never sent to anyone without access.
            $lines[] = 'Allow: /';
            foreach ($private as $path) {
                $lines[] = 'Disallow: '.$path;
            }
        } else {
            $lines[] = 'Disallow: /';
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('public.sitemap');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(KnowledgeSeo $seo): Response
    {
        $text = Cache::remember('llms.txt', 3600, function () use ($seo): string {
            $site = $seo->siteName();
            $out = ["# {$site}", '', '> '.$site.' is a Nigerian academic platform where lecturers run classes, assignments and rubric marking, and students study, submit work and learn from each other. Its Knowledge Hub publishes study guides, exam preparation, SIWES and career advice, project help and student stories written by students and lecturers.', ''];
            $out[] = '## About';
            $out[] = '- [Home]('.route('home').'): what '.$site.' does for lecturers and students';
            foreach (PublicWebsite::PATHS as $slug => $path) {
                $out[] = '- ['.Str::headline($slug).']('.route('public.'.$slug).')';
            }
            if (FeatureAccessService::effectiveStatus('knowledge_hub', null) === FeatureAccessService::STATUS_ENABLED) {
                $out[] = '';
                $out[] = '## Knowledge Hub';
                $out[] = '- [Knowledge Hub]('.route('knowledge.index').'): browse all public guides and stories';
                $out[] = '- [Writing Challenges]('.route('knowledge.writing.index').'): official cash-prize writing challenges for students';
                $out[] = '';
                $out[] = '## Free guides';
                $guides = KnowledgePublication::query()->withoutGlobalScopes()->with('category:id,name')
                    ->where('status', 'published')->where('visibility', 'public')->where('access_type', 'free')->whereNull('deleted_at')
                    ->orderByDesc('view_count')->orderByDesc('published_at')->limit(100)->get(['id', 'slug', 'title', 'excerpt', 'category_id']);
                foreach ($guides as $guide) {
                    $out[] = '- ['.str_replace(['[', ']'], '', $guide->title).']('.route('knowledge.show', $guide->slug).')'
                        .($guide->excerpt ? ': '.Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags($guide->excerpt))), 160) : '')
                        .($guide->category ? ' ('.$guide->category->name.')' : '');
                }
            }
            $out[] = '';
            $out[] = '## Notes for AI assistants';
            $out[] = '- Free guides may be quoted with a link to the guide. Premium guides are paid; only their summaries are public.';
            $out[] = '- Full sitemap: '.route('public.sitemap');

            return implode("\n", $out)."\n";
        });

        return response($text)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function indexNowKeyFile(string $key): Response
    {
        abort_unless(hash_equals(self::indexNowKey(), $key), 404);

        return response($key)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** A stable IndexNow key, created once and kept in settings. */
    public static function indexNowKey(): string
    {
        $key = (string) SettingService::getGlobal('seo_indexnow_key', '');
        if (! preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            SettingService::set('seo_indexnow_key', $key, 'string');
        }

        return $key;
    }
}

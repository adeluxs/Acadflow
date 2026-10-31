<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicWebsite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicPageController extends Controller
{
    public function home(): View|RedirectResponse
    {
        return auth()->check() ? redirect()->route('dashboard') : view('landing', [
            'features' => PublicWebsite::features(), 'faqs' => PublicWebsite::faqs(),
            'workflows' => PublicWebsite::workflows(), 'slug' => 'home',
            'page' => ['title' => 'Learning, research and knowledge, connected', 'description' => 'Connect courses, research projects, academic publishing and collaboration in AcadSyntra. Find a clearer path from your next task to your next milestone.'],
        ]);
    }

    public function show(Request $request, ?string $page = null): View
    {
        $slug = $page ?? $request->route()->defaults['page'] ?? '';
        $pages = PublicWebsite::pages();
        abort_unless(isset($pages[$slug]), 404);
        $data = ['page' => $pages[$slug], 'slug' => $slug,
            'features' => PublicWebsite::features(), 'faqs' => PublicWebsite::faqs(),
            'workflows' => PublicWebsite::workflows()];

        if ($slug === 'contact') {
            $tokens = array_filter((array) $request->session()->get('public_contact_tokens', []), fn ($issued) => $issued > time() - 86400);
            $token = Str::random(48);
            $tokens[$token] = time();
            $request->session()->put('public_contact_tokens', array_slice($tokens, -5, null, true));
            $data['formToken'] = $token;
        }

        if ($slug === 'ambassadors') {
            $data['faqs'] = PublicWebsite::ambassadorFaqs();
            $data['ambassadorApplication'] = $request->user()
                ? \App\Models\AmbassadorApplication::where('user_id', $request->user()->id)->first(['id', 'status'])
                : null;
        }

        $template = match ($slug) {
            'features', 'how-it-works', 'contact', 'ambassadors' => $slug,
            'faq', 'help' => 'help',
            default => 'page',
        };

        return view('public.'.$template, $data);
    }

    public function sitemap()
    {
        $urls = [route('home')];
        foreach (PublicWebsite::PATHS as $slug => $path) $urls[] = route('public.'.$slug);
        foreach (['documentation', 'security'] as $slug) $urls[] = route('public.page', ['page' => $slug]);

        return response()->view('public.sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /dashboard\nDisallow: /api/\nDisallow: /login\nDisallow: /register\nSitemap: ".route('public.sitemap')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

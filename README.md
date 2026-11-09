# SEO + GEO for the Knowledge Hub (9 November 2026)

Apply on top of the full project from 9 November (after the challenge pop-up update).

    rsync -av ~/Downloads/acadsyntra-seo-geo/ ~/Projects/acadsyntra/ --exclude README.md
    php artisan optimize:clear

No migration. IMPORTANT: if your server has a physical file `public/robots.txt` or `public/sitemap.xml`,
delete it, or the web server will serve the old file instead of the new generated one.

## What's new

- `/sitemap.xml` is now a sitemap index: marketing pages, every public guide (5,000 per file,
  with last-updated dates), writing challenges, series and creator pages. Drafts, institution-only
  and deleted guides are never listed. Cached for an hour.
- Every guide page: real title, meta description (from the excerpt; never from paid/locked text),
  canonical URL, Open Graph + Twitter cards (link previews on WhatsApp, X, LinkedIn, Facebook),
  article dates/author/section, and schema.org JSON-LD: Article (ScholarlyArticle for research),
  author with university, publisher, BreadcrumbList and isAccessibleForFree (paywall markup).
- Knowledge Hub, writing-challenge (as an Event), challenge list and series pages also get metadata.
- Search results and filtered hub lists are `noindex, follow`; private app pages (dashboard etc.)
  are `noindex, nofollow`.
- `/robots.txt`: public pages allowed; admin, dashboard, API, login, authoring, rewards and search blocked;
  AI assistants (GPTBot, ClaudeBot, PerplexityBot, Google-Extended…) allowed on public pages.
  Switch: Admin > Knowledge Hub > Read & Earn > "Search engines and AI assistants".
- `/llms.txt`: a plain-text map of AcadSyntra and its top 100 free guides for AI engines.
- IndexNow: when a public guide is published, Bing/Yandex are notified at once (production only,
  key served at /<key>.txt automatically).

## You need to do (once)

1. Google Search Console: add the domain property acadsyntra.com, verify via DNS, then
   Sitemaps > submit https://acadsyntra.com/sitemap.xml
2. Bing Webmaster Tools: "Import from Google Search Console" (fastest), or add the site and submit the sitemap.
3. Check a guide with Google's Rich Results Test (search.google.com/test/rich-results) and a link preview
   by pasting a guide link into WhatsApp.

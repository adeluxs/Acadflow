# AcadSyntra public website

The public website explains implemented course, Research Studio, Knowledge Hub, collaboration, contextual assistance and institutional workflows. It avoids customer-count claims, invented testimonials, certifications, pricing/free-plan promises, social accounts and unconfigured contact addresses.

## Pages and navigation

| Page | Canonical URL | Purpose |
|---|---|---|
| Home | `/` | Hero, problems/solutions, features, workflow, interactive product previews, audiences, differentiators, FAQs, final CTA |
| Features | `/features` | Searchable capabilities with category filters and practical use cases |
| How it works | `/how-it-works` | Account/onboarding journey and learning/research/publishing paths |
| About | `/about` | Product purpose, audiences, scope and human academic authority |
| Contact | `/contact` | Validated enquiry form, success reference and support guidance |
| FAQs / Help center | `/faqs`, `/help` | Search, topic filters, native expandable answers, account recovery and support links |
| Policies | `/privacy`, `/terms`, `/cookies`, `/disclaimer` | Implemented data handling, responsibilities and explicit deployment review items |
| Existing resources | `/information/{page}` | Existing information URLs retained; shared layout and canonical aliases where appropriate |
| Discovery | `/sitemap.xml`, `/robots.txt` | Canonical public sitemap and installation-aware crawl guidance |

The sticky header shares active indicators and authentication-aware links. Mobile navigation uses a native modal dialog with keyboard focus containment, Escape/close controls and close-after-navigation. Without JavaScript a Menu link jumps to the functional footer navigation. Search/filter and preview tabs are progressive enhancements; page content and native FAQ disclosures remain available without scripts. Reduced-motion preference disables scrolling/reveal motion.

## Source map

- `app/Services/PublicWebsite.php`: route inventory, feature/workflow/FAQ content, configured-support-address filtering.
- `config/public_website.php`, `config/public_pages.php`: dedicated and retained informational page content.
- `app/Http/Controllers/PublicPageController.php`: page rendering, form tokens, sitemap and robots.
- `resources/views/layouts/public.blade.php`, `resources/views/landing.blade.php`, `resources/views/public/*`: reusable public shell, homepage, dedicated pages and illustrative product components.
- `resources/css/public.css`, `resources/js/public-website.js`, `vite.config.js`: isolated responsive styling and small interactions; no third-party font, tracker or UI-library requests.
- `app/Http/Controllers/PublicContactController.php`, `app/Models/ContactMessage.php`, `database/migrations/2026_10_20_000004_create_contact_messages.php`: validation and durable enquiry storage.
- `app/Jobs/NotifyPublicContact.php`: optional alert through existing queue/mail infrastructure.
- `app/Http/Controllers/Admin/ContactInboxController.php`, `resources/views/admin/contact/index.blade.php`, `resources/views/partials/sidebar.blade.php`: Super Admin support inbox.
- `routes/web.php`, `app/Providers/AppServiceProvider.php`: public/admin routes and abuse limits. Static `public/robots.txt` was replaced by the dynamic route.

## Deployment and configuration

```bash
php artisan migrate --force
npm ci
npm run build
php artisan config:clear
php artisan queue:restart
```

Set `APP_URL` to the correct public HTTPS origin. Branding comes from existing global site name/logo/primary color settings, falling back to bundled AcadSyntra assets. Configure a real support email in System Settings and the existing mail transport if alerts are wanted; keep a worker processing `default`. The contact inbox works even if alerts cannot be delivered. No new environment keys or paid integrations are required. Apply migrations before enabling the form on a deployment.

Privacy, Terms and Cookie pages explicitly flag missing business/legal specifics. The operator must review its identity/contact, eligibility, legal bases, provider/hosting locations, retention, rights/complaint process, commercial/refund terms and applicable governing law. Closing an enquiry is not deletion. No universal retention duration, legal compliance certification or consent banner for nonexistent tracking is claimed. If optional tracking is added, implement the appropriate controls before loading it. Useful primary references for review include [NDPC legislation](https://ndpc.gov.ng/download/nigeria-data-protection-act-2023) where applicable, and [Laravel session configuration](https://laravel.com/docs/12.x/session); the content must be adapted to the actual operator rather than assuming a jurisdiction.

## Verification

Backend coverage is in `tests/Feature/PublicWebsiteTest.php`: page/SEO rendering, canonical sitemap, auth continuity, validation, form timing/replay, throttling, queue failure durability, inbox authorization/escaping and email payload privacy.

`tests/Browser/public-website.test.mjs` checks every canonical page plus documentation/security at 320, 390, 768 and 1440 pixels, missing assets/console errors, horizontal overflow, modal keyboard behavior, filters/search, preview tab keyboard navigation, working links/anchors, native validation, CSRF and an actual isolated contact submission. It also checks no-JavaScript navigation and FAQ use. Test browser data belongs in a separate SQLite database, never a live support inbox. The default browser suite targets `http://127.0.0.1:8012`; another origin requires `PUBLIC_WEBSITE_TEST_URL`, and contact POSTs outside that test origin require `PUBLIC_WEBSITE_ALLOW_CONTACT=1`.

```bash
php vendor/bin/phpunit tests/Feature/PublicWebsiteTest.php
node --test tests/Browser/public-website.test.mjs
npm run build
```

The browser suite writes desktop/mobile home, features and contact screenshots to `/private/tmp/acadflow-public-qa` (override with `PUBLIC_WEBSITE_ARTIFACTS`). UI previews contain only fictional content. Real SMTP delivery is an operator configuration check; automated mail tests use the array transport.

Implementation validation: all **405 PHP tests (1,871 assertions)** passed, all **six public browser suites** plus **seven push browser tests** passed, and the production build passed. Runtime Blade, feature management, MySQL identifier, security/safe-error, documentation and environment preflights passed. The public bundle is about 10 KB CSS and 1.4 KB JavaScript compressed, excluding the logo. Existing seeder-idempotency and central/assistant AI-routing static audits still report issues in unchanged seeders and AI files; these were not changed as part of the public website work. Grounded Companion and specialized-assistant audits passed. The contact migration was applied locally; other deployments still need the deployment commands above.

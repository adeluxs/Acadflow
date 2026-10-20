# AcadSyntra identity

AcadSyntra is the current platform name. Application-owned display copy, SEO, public product previews, authentication/onboarding, dashboards, AI messages/instructions, course invitations, notifications, email templates and new PDF/CSV/calendar exports use the new identity.

## Visual assets

The native vector mark combines an academic book/letterform with a connection point. Primary violet is `#4f46e5`, dark ink is `#171b33`, and the small mint accent is `#98dfc5`. Existing system typography, responsive layout and configurable primary-color settings remain in use. There are no external font or asset dependencies.

Masters are `public/branding/acadsyntra-mark.svg` and `acadsyntra-logo.svg`. `node scripts/generate-brand-assets.mjs` produces mark/wordmark PNGs, 32/180/192/512px icons, a 1200×630 social card and ICO favicons. Public layouts, loader, push payloads and PWA assets reference the new paths. Existing `acadflow-*.png` and duplicate public icon/favicon paths contain the **same new artwork**, preserving bookmarked/cached URLs without displaying the old identity. Old static screenshot illustrations also display AcadSyntra. The marketing pipeline recaptures real demo screens rather than painting new text over outdated screenshots.

## Data-safe upgrade

```bash
php artisan migrate --force
npm ci
npm run build
php artisan config:clear
php artisan view:clear
php artisan queue:restart
```

The branding migration changes exact legacy defaults and known built-in asset paths, preserving custom institution names/logos, user data and credentials. It creates a new version for recognized shipped AI prompts and retains original prompt text/history. Custom AI instructions are not overwritten; the prompt composer normalizes only product identity in the application instruction, while source/user context stays unchanged. Historical migrations, user documents, immutable archives and historical messages are not rewritten.

Set `APP_NAME=AcadSyntra` for display defaults. Existing installations should preserve explicit session/cache/Redis identifiers before changing the name, including derived defaults. This workspace retains its existing cookie/cache names. An active provider’s registered SMS sender must be approved separately before changing that identifier. No domains, contact addresses, keys or external accounts are invented or renamed.

The service-worker cache version advances for the new graphics; IndexedDB databases and pending offline actions retain their existing identities. Device-managed installed-app metadata can update on the browser’s schedule. Existing authenticator entries retain their secrets and may retain their device-side labels; new enrollments use AcadSyntra.

## Intentional compatibility references

The project was searched for the former names. Remaining runtime references are technical contracts: `acadflow:*` Artisan commands, `AcadFlowEcosystemSeeder`, `window.AcadFlow*` globals, `X-AcadFlow-*` headers, DOM/CSS/event keys, `acadflow_push_account`, offline IndexedDB/sync keys and `acadflow.research.archive.v1`. These do not display the old product name in page chrome. Compatibility normalizers and the upgrade migration necessarily recognize old values. Historical source filenames, previous migrations, logs, quarantine backups and user-authored content are not product branding and are preserved.

## Validation

- `php scripts/check-rebranding.php` checks runtime text, new assets, legacy asset equivalence and the static manifest.
- `php scripts/check-scholarly-timeout-and-branding.php` verifies shared branding and the existing loader contract.
- `php vendor/bin/phpunit tests/Feature/AcadSyntraBrandingTest.php` checks migration idempotence, custom setting/prompt/user preservation, prompt version history, instruction/context separation, public/auth pages, email templates, manifest/icon dimensions and compatibility contracts.
- `node --test tests/Browser/public-website.test.mjs tests/Browser/browser-push.test.mjs` checks public layouts/interactions and push compatibility.
- The marketing capture script rejects HTTP errors and visible old-brand text while capturing public, student, lecturer and administrator screens. It supports `MARKETING_BASE_URL`, `MARKETING_FIXTURE_FILE` and `MARKETING_AUTH_DIR` for isolated QA without replacing existing account sessions.

Verified locally: **405 PHP tests (1,871 assertions)**, **13 browser tests**, the production build and the branding/runtime/documentation/environment preflights passed. All **35** demo captures completed without HTTP failures or visible old-brand text; all **70** existing raw/framed PNGs were replaced with regenerated artwork, with old outputs backed up outside the project. Public PWA screenshots now use the regenerated example dashboards. Database integrity passed, fingerprints of **15** protected user/academic/payment tables were unchanged, and active shipped AI prompts/templates contain no old product name. Migration applied locally; other installations must run the upgrade commands above.

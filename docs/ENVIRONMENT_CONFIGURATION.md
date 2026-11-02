# AcadSyntra Environment Configuration

For new installations use `APP_NAME=AcadSyntra`; mail/OpenRouter display names can inherit it. On an existing installation, keep the current `SESSION_COOKIE`, `CACHE_PREFIX` and `REDIS_PREFIX` when updating the display name so active sessions and shared cache/queue keys keep working. If a prefix was previously derived from `APP_NAME`, make that old prefix explicit first. Never change database names, URLs, payment/provider credentials or registered SMS sender IDs merely because the brand changed. Register/approve AcadSyntra with an SMS provider before replacing an already active sender identifier; unused/new-install defaults use AcadSyntra.

AcadSyntra now ships with a complete root `.env.example` intended to be copied to `.env` for each deployment.

## Coverage

The example contains every environment key referenced through `env()` by the current PHP source, including Laravel framework configuration, database/cache/session/queue/broadcasting, storage, logging, mail, media security, submissions, academic catalogue synchronization, AI providers and limits, scholarly integrations, reputation configuration, API authentication and development seeding.

Deployment-sensitive values that had previously been hardcoded in AcadSyntra-specific config files were moved behind environment variables, including submission limits and notification defaults, submission storage settings, academic catalogue source URLs, and reputation point/level defaults.

## Deliberate exclusions

Not every literal inside `config/*.php` should become an environment variable. Static application metadata and code-level enumerations remain in source, including the centralized feature registry metadata, supported citation/document types, provider identifiers, MIME type maps, reputation level display names, framework driver definitions and dependency mappings.

Feature/module availability is deliberately **not** controlled by `.env`. Runtime feature status remains authoritative in AcadSyntra's centralized Feature & Module Management system/database. Duplicating those switches in `.env` would violate the project's single-source-of-truth architecture.

## Validation

Existing AI transport settings are represented explicitly in the example: `AI_CONTEXTUAL_UI_TIMEOUT_MS=28000` bounds foreground contextual UI waits, `AI_DEFER_ANALYTICS_AFTER_RESPONSE=true` defers observability work, and `AI_HEALTH_CHECK_BUDGET_SECONDS=12`, `AI_HEALTH_CHECK_TIMEOUT=6`, `AI_HEALTH_CHECK_CONNECT_TIMEOUT=3` bound administrator provider checks. These document existing runtime defaults; the public website introduces no additional environment variables.

Run:

```bash
php scripts/check-env-example.php
```

The check fails if a PHP source file introduces a new `env('KEY')` reference that is not represented in `.env.example`, or if `.env.example` contains duplicate keys.

## Deployment

1. Copy `.env.example` to `.env`.
2. Fill database credentials and production URLs.
3. Configure only the integrations/providers you actually use.
4. Generate the application key with `php artisan key:generate`.
5. Run `php artisan optimize:clear` after changing environment/configuration values.
6. In production, cache configuration with `php artisan config:cache` after the `.env` is finalized.

Never commit the real `.env` or API secrets to version control.

## SQLite lock handling

For `DB_CONNECTION=sqlite`, `DB_BUSY_TIMEOUT=15000` allows a connection to wait up to 15 seconds for a database lock. `DB_JOURNAL_MODE=WAL` permits readers alongside a writer; SQLite still allows only one writer at a time. These settings do not affect MySQL. Leave SQLite databases on local storage; WAL requires shared memory on the same host. Use `DB_JOURNAL_MODE=DELETE` if WAL is unsuitable for the storage environment. See [SQLite isolation](https://www.sqlite.org/isolation.html) and [WAL restrictions](https://www.sqlite.org/wal.html).

After changing these values, clear cached configuration with `php artisan config:clear` and restart long-running PHP/queue/scheduler processes. Do not delete journal, `-wal`, or `-shm` files to clear a lock. Back up active SQLite databases using SQLite's backup API, rather than copying only the main file.

If starter academic template seeding was interrupted, resume with `php artisan acadflow:sync-nigeria-catalog --templates-only`. This adds missing templates to active institutions without fetching registry data or overwriting existing academic rows. Applying application updates requires `php artisan migrate`; a full `db:seed` is optional.


## Session cookie safety

`SESSION_COOKIE` must resolve to a non-empty cookie name. The supplied `.env.example` now uses `acadflow_session`. The session configuration also normalizes a blank `SESSION_COOKIE=` to a safe application-derived fallback so older deployments do not fail with Symfony's `The cookie name cannot be empty` exception. After changing session-related environment values, run `php artisan optimize:clear` so cached configuration cannot retain an invalid value.


## Browser Web Push

`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` and `VAPID_SUBJECT` configure a stable Web Push identity. Supply all three together; the subject must be a `mailto:` contact email or an HTTPS contact URL. Keep the private key secret. As an alternative, leave both environment keys empty and generate encrypted database-backed keys from Super Admin → Notification Management. Never regenerate an established pair during normal deployment. Queue workers must be restarted after environment/configuration changes. Browser enrollment requires HTTPS (localhost is allowed for development). SMTP setup remains required for verification emails when email verification is enabled.

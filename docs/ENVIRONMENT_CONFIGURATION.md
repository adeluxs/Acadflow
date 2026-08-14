# AcadFlow Environment Configuration

AcadFlow now ships with a complete root `.env.example` intended to be copied to `.env` for each deployment.

## Coverage

The example contains every environment key referenced through `env()` by the current PHP source, including Laravel framework configuration, database/cache/session/queue/broadcasting, storage, logging, mail, media security, submissions, academic catalogue synchronization, AI providers and limits, scholarly integrations, reputation configuration, API authentication and development seeding.

Deployment-sensitive values that had previously been hardcoded in AcadFlow-specific config files were moved behind environment variables, including submission limits and notification defaults, submission storage settings, academic catalogue source URLs, and reputation point/level defaults.

## Deliberate exclusions

Not every literal inside `config/*.php` should become an environment variable. Static application metadata and code-level enumerations remain in source, including the centralized feature registry metadata, supported citation/document types, provider identifiers, MIME type maps, reputation level display names, framework driver definitions and dependency mappings.

Feature/module availability is deliberately **not** controlled by `.env`. Runtime feature status remains authoritative in AcadFlow's centralized Feature & Module Management system/database. Duplicating those switches in `.env` would violate the project's single-source-of-truth architecture.

## Validation

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

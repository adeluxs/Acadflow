# Contributing to AcadFlow

This guide applies to developers, maintainers and coding agents changing the AcadFlow source.

## 1. Read before modifying

Start with:

1. `docs/DEVELOPER_GUIDE.md`
2. `docs/DOCUMENTATION_MAINTENANCE.md`
3. `CHANGELOG.md`
4. the relevant specialist/detailed audit document

Search the codebase before creating a new service, setting, route, feature flag, provider adapter or database table. AcadFlow intentionally centralizes cross-cutting behavior; duplicate implementations are a regression.

## 2. Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate
```

Run:

```bash
composer dev
```

Run the scheduler separately when testing scheduled features:

```bash
php artisan schedule:work
```

## 3. Architecture rules

- Use `SettingService` for database runtime settings.
- Use `FeatureAccessService` for module release/maintenance availability.
- Use model policies and tenant scoping for sensitive resources.
- Use `AiManager`/`AiRouter` for AI provider execution.
- Use existing secure media services for sensitive file delivery.
- Use existing queue names and services instead of creating disconnected workers.
- Keep provider adapters free of application business logic.
- Keep controllers thin.

## 4. Database and seeding

- Create new migrations; do not destructively edit production history.
- Provide short explicit MySQL index names.
- Do not truncate/clear production tables in migrations/seeders.
- Seeders must be idempotent and preserve admin edits.
- Never use `migrate:fresh` as a production upgrade instruction.

## 5. Tests and preflights

At minimum run the checks related to your change. Before release, run:

```bash
php scripts/check-documentation.php
php scripts/check-env-example.php
php scripts/check-runtime-regressions.php
php scripts/check-feature-management.php
php scripts/check-mysql-identifiers.php
php scripts/check-idempotent-seeders.php
php scripts/check-ai-central-routing.php
php scripts/check-ai-assistant-routing.php
php scripts/check-grounded-companion.php
php scripts/check-specialized-ai-assistants.php
```

With Composer dependencies installed:

```bash
php artisan test
```

Frontend:

```bash
npm run build
```

## 6. Definition of Done

A change is complete only when:

- implementation is finished;
- authorization/tenant boundaries are correct;
- error/loading/empty states are handled;
- tests/preflights are updated;
- `docs/DEVELOPER_GUIDE.md` is updated if architecture/developer behavior changed;
- `docs/USER_GUIDE.md` is updated if user behavior changed;
- specialist docs are updated if needed;
- `CHANGELOG.md` contains the change under Unreleased;
- no existing administrator settings/data are silently reset;
- no unrelated working feature was broken.

## 7. Commit/PR guidance

Keep changes coherent. For large architectural work, separate discovery/audit notes from implementation but ship one consistent final architecture. PR descriptions should include migration/deployment steps and rollback risks.

Use `.github/pull_request_template.md` as the review checklist.

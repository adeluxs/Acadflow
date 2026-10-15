# AcadFlow Seeder Idempotency and Data Preservation

## Purpose

AcadFlow seeders are designed to be safe to run more than once on an existing installation.

The rule is:

> **Seed missing defaults; do not reset, clear, duplicate, or overwrite existing administrator/user data.**

## Behaviour

All files in `database/seeders` now follow create-missing-only behaviour.

- Existing rows are detected using stable seed identities such as setting key, feature name, email, course code within its department, coupon code, plan name, workflow key, prompt feature/version, and similar scoped identifiers.
- Existing rows are preserved as they are.
- Missing seed rows are created.
- Re-running `php artisan db:seed` does not intentionally reset passwords, settings, feature states, prices, workflow configuration, academic structure, or administrator edits.
- Seeders do not call `truncate()`, `delete()`, `forceDelete()`, `updateOrCreate()`, or bulk upsert operations.

## Important distinction: seeding vs explicit synchronization

The Nigerian academic catalogue has two separate use cases:

1. **Database seeding** uses `preserveExisting: true`. It can create institutions that are missing but does not overwrite an existing institution row during a repeated seed.
2. **Explicit catalogue synchronization/import commands** remain allowed to refresh regulator/catalogue metadata because the administrator deliberately invoked a synchronization operation.

This keeps `db:seed` safe without removing the ability to intentionally refresh the catalogue.

## Starter catalogue templates

Starter faculties, departments, and courses use `firstOrCreate` identities. Re-running the seeder therefore creates only missing template rows.

The `starter_template_seeded_at` metadata is no longer rewritten every time the seeder runs. Existing institution catalogue settings are preserved.

## Demo users and academic data

The demo `UniversitySeeder` now preserves existing rows, including:

- super-admin account
- demo university
- faculty and department
- academic session and semester
- courses
- university/department administrators
- lecturers
- students
- lecturer-course assignments
- student enrollments

For example, if a demo user's password, name, active state, or role has been changed after initial seeding, re-running the seeder does not intentionally reset that record.

Production still respects the existing `ACADEMIC_SEED_DEMO` safeguard.

## Settings and feature flags

Settings are defaults only. Existing settings are not overwritten by `SettingsSeeder` or `AcadFlowEcosystemSeeder`.

Feature flags continue to preserve administrator-selected Enabled / Maintenance / Disabled states.

## AI prompts and workflow definitions

Seeded AI prompt versions, research workflow definitions, stages, research types, categories, and achievements now use create-missing-only behaviour. Existing customized prompt/workflow content is not silently replaced by a repeated seed.

Schema/data migrations remain the correct mechanism for deliberate versioned upgrades that must alter an existing default safely.

## Subscription plans and coupons

Subscription plans and coupons were already using `firstOrCreate`, so existing prices, descriptions, usage counts, expiry values, plan configuration, and coupon state are preserved when seeders are re-run.

## Commands

Safe repeated seeding:

```bash
php artisan db:seed
```

Seed one seeder:

```bash
php artisan db:seed --class=SettingsSeeder
```

Run the source-level idempotency preflight:

```bash
php scripts/check-idempotent-seeders.php
```

## Do not confuse `db:seed` with destructive database commands

The seeders themselves are now non-destructive, but the following commands are intentionally destructive and should **not** be used on an existing production database unless you explicitly want to erase data:

```bash
php artisan migrate:fresh --seed
php artisan db:wipe
```

`migrate:fresh` drops tables before running migrations and seeders. No seeder implementation can preserve data that has already been removed by `migrate:fresh`.

For an existing production installation, normally use:

```bash
php artisan migrate --force
php artisan db:seed --force
```

only when you deliberately need to add missing default seed data.

## Regression rule for future seeders

New seeders should follow the same policy:

```php
Model::firstOrCreate(
    ['stable_unique_identity' => $value],
    ['default_field' => $default]
);
```

Do not use `updateOrCreate()` in a normal seeder when the second argument contains administrator-editable values. If an existing record genuinely needs a versioned upgrade, implement that change in a migration or a dedicated explicit maintenance command with appropriate safeguards.

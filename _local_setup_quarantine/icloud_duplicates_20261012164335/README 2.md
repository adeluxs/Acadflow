# AcadFlow

AcadFlow is a Laravel-based academic collaboration platform for universities, lecturers, students, independent platform members, research teams, knowledge creators, and administrators. It brings course workflows, assignments and submissions, attendance, research supervision, Knowledge Hub publishing, academic communities, events, AI assistance, notifications, subscriptions, commerce, documents, and reporting into one application.

> **Canonical documentation:** Start with [`docs/README.md`](docs/README.md). The older dated documents in `docs/` are implementation-history records; the current developer and user guides are the primary source for how the present codebase works.

## Current platform snapshot

- Laravel `^12.40`, PHP `^8.2`
- MySQL-oriented schema and identifier-safety checks
- Blade + Tailwind CSS + Vite; Vue is present for selected frontend components
- Sanctum API authentication
- Database or Redis queues; production Redis is recommended where hosting supports it
- 30 centrally managed feature/module switches
- 16 centrally registered AI capabilities through one AI router
- Role-aware dashboards for Super Admin, University Admin, Department Admin, Lecturer, Student, and Platform Member
- Research Studio, Knowledge Hub, communities, groups, SIWES, seminars, project defense, billing, marketplace/wallet, notifications, documents/exports, and PWA support

## Documentation

| Document | Purpose |
|---|---|
| [`docs/DEVELOPER_GUIDE.md`](docs/DEVELOPER_GUIDE.md) | Canonical technical guide for developers working on the codebase |
| [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) | End-user guide for every supported role and major feature |
| [`docs/DOCUMENTATION_MAINTENANCE.md`](docs/DOCUMENTATION_MAINTENANCE.md) | Mandatory documentation/changelog update policy |
| [`CHANGELOG.md`](CHANGELOG.md) | Human-readable history of product and technical changes |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Development workflow, quality gates, migration/seeding rules, and PR checklist |
| [`docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md`](docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md) | Queue, scheduler, Redis, local and shared-hosting operations |
| [`docs/ENVIRONMENT_CONFIGURATION.md`](docs/ENVIRONMENT_CONFIGURATION.md) | Environment variable reference |
| [`docs/ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md`](docs/ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md) | Current centralized AI routing architecture and audit |

## Quick local start

1. Install PHP 8.2+, Composer 2, Node.js/npm, and MySQL.
2. Copy the environment template:

```bash
cp .env.example .env
```

On Windows Command Prompt:

```bat
copy .env.example .env
```

3. Install dependencies and initialize the app:

```bash
composer install
php artisan key:generate
npm install
php artisan migrate
```

4. Seed only when you intentionally want the default/demo/bootstrap records:

```bash
php artisan db:seed
```

Normal AcadFlow seeders are designed to be idempotent and preserve matching existing records. **Never use `migrate:fresh --seed` against an existing production database.**

5. Start the application:

```bash
composer dev
```

The `composer dev` command starts the Laravel development server, a queue worker for `default,ai,indexing,analytics`, Laravel Pail, and Vite. Run the scheduler separately when testing scheduled workflows:

```bash
php artisan schedule:work
```

## Production deployment

For production, keep `APP_DEBUG=false`, run migrations with `--force`, use a real queue worker/scheduler, and configure Redis where available. See [`docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md`](docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md).

Typical post-deployment commands:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

## Quality checks

AcadFlow includes project-specific preflight checks:

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

PHPUnit tests can be run after Composer dependencies are installed:

```bash
php artisan test
```

Frontend production build:

```bash
npm run build
```

## Documentation rule

Every functional change to AcadFlow must update the relevant canonical documentation and `CHANGELOG.md` in the same change. New roles, feature flags, AI capabilities, or major modules are checked by `scripts/check-documentation.php` so they cannot quietly appear without documentation.

# AcadFlow Documentation Index

**Canonical documentation date:** 2026-08-16

This folder contains both **current operational documentation** and **dated implementation-history reports**. Developers should use the canonical documents below first and consult dated reports when they need the history behind a specific subsystem.

## Start here

### For developers

1. [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) — complete codebase orientation, architecture, modules, AI, settings, queues, testing, deployment, and extension rules.
2. [`CONTRIBUTING.md`](../CONTRIBUTING.md) — how to make changes safely.
3. [`DOCUMENTATION_MAINTENANCE.md`](DOCUMENTATION_MAINTENANCE.md) — documentation and changelog policy.
4. [`CHANGELOG.md`](../CHANGELOG.md) — recent changes.
5. [`OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md`](OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md) — local queue/scheduler and production shared-hosting operations.

### For users, trainers, support staff, and administrators

- [`USER_GUIDE.md`](USER_GUIDE.md) — complete role-based product guide.

## Current specialist references

- [`ENVIRONMENT_CONFIGURATION.md`](ENVIRONMENT_CONFIGURATION.md)
- [`API.md`](API.md)
- [`DATABASE.md`](DATABASE.md)
- [`SECURITY.md`](SECURITY.md)
- [`NIGERIA_ACADEMIC_CATALOG.md`](NIGERIA_ACADEMIC_CATALOG.md)
- [`SEEDER_IDEMPOTENCY_AND_DATA_PRESERVATION.md`](SEEDER_IDEMPOTENCY_AND_DATA_PRESERVATION.md)
- [`MYSQL_IDENTIFIER_SAFETY.md`](MYSQL_IDENTIFIER_SAFETY.md)
- [`WINDOWS_COMPOSER_INSTALL.md`](WINDOWS_COMPOSER_INSTALL.md)

## AI implementation references

- [`ACADFLOW_2026_08_16_PROVIDER_ACCESS_UI_REMEDIATION.md`](ACADFLOW_2026_08_16_PROVIDER_ACCESS_UI_REMEDIATION.md)
- [`ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md`](ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md)
- [`ACADFLOW_2026_08_15_AI_ASSISTANT_ROUTING_CONSISTENCY_FIX.md`](ACADFLOW_2026_08_15_AI_ASSISTANT_ROUTING_CONSISTENCY_FIX.md)
- [`ACADFLOW_2026_08_15_SPECIALIZED_AI_ASSISTANTS.md`](ACADFLOW_2026_08_15_SPECIALIZED_AI_ASSISTANTS.md)
- [`ACADFLOW_2026_08_14_GROUNDED_AI_INTELLIGENCE_UPGRADE.md`](ACADFLOW_2026_08_14_GROUNDED_AI_INTELLIGENCE_UPGRADE.md)

## Feature/platform implementation history

Files named `ACADFLOW_YYYY_MM_DD_*.md` are release/audit notes written when those changes were made. They are valuable for history and rationale, but where they conflict with the current source or the canonical guides, use this priority:

1. Current source code and migrations
2. `DEVELOPER_GUIDE.md` / `USER_GUIDE.md`
3. Current specialist reference documents
4. Dated implementation-history reports
5. Old planning documents (`PRD.md`, `ROADMAP.md`, `MVP.md`, etc.)

Planning documents describe intended direction and must not be treated as proof that a feature is currently implemented.

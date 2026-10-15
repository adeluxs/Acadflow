# AcadFlow Documentation & Changelog Maintenance Policy

**Effective:** 2026-08-15

Documentation is part of the AcadFlow implementation. A feature is not considered complete when code works but the canonical documentation still describes the old behavior.

## 1. Mandatory rule

Every functional change must update the relevant documentation and `CHANGELOG.md` in the **same code change**.

```text
Implementation
+ tests/preflights
+ developer documentation
+ user documentation when user-visible
+ changelog
= complete AcadFlow change
```

## 2. Canonical documents

- `README.md` — project entry point and quick start
- `docs/DEVELOPER_GUIDE.md` — current architecture and engineering behavior
- `docs/USER_GUIDE.md` — current user-facing behavior for all roles
- `CHANGELOG.md` — change history
- `CONTRIBUTING.md` — contribution rules
- `docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md` — operations/deployment/queue reference

Dated `ACADFLOW_YYYY_MM_DD_*.md` files are implementation history. They can be added for large audits/releases but must not replace updates to the canonical docs.

## 3. What must be updated for each kind of change

| Change | Required documentation updates |
|---|---|
| New user-visible feature | Developer Guide + User Guide + Changelog |
| Changed workflow/behavior | Relevant Developer/User sections + Changelog |
| New role/permission | Developer role/authorization section + User Guide role section + Changelog |
| New feature flag/module key | Developer feature table + User Guide availability/module section + Changelog |
| New AI feature/provider/routing behavior | Developer AI section + User Guide AI section + Changelog + AI audit/history note if large |
| New queue/job/schedule | Developer queue/scheduler + Operations guide + Changelog |
| New `.env` variable | Environment Configuration + Developer/Operations if important + Changelog |
| Database schema/migration | Developer database section if architectural + Changelog |
| New API endpoint | API documentation + Developer Guide summary + Changelog |
| Security behavior | Security docs + Developer/User guide as appropriate + Changelog |
| UI-only text/layout change | User Guide only if workflow changed; always Changelog for meaningful product changes |
| Bug fix | Changelog; update docs when the old docs would otherwise be wrong |
| Deprecated/removed feature | Developer + User docs + Changelog with migration/replacement path |

## 4. Changelog format

Use `CHANGELOG.md` and add changes under `## [Unreleased]` first.

Use categories when helpful:

```markdown
### Added
### Changed
### Fixed
### Security
### Deprecated
### Removed
```

Write user/developer impact, not commit messages.

Good:

> Fixed AI Assistant role routing so lecturer Ask/Explain resolves `lecturer_assistant` consistently in both the page metadata and actual request path.

Weak:

> fixed ai

When a release/date is finalized, move Unreleased items into a dated heading:

```markdown
## [2026-08-16]
```

## 5. Source-of-truth rule

When documentation conflicts:

1. current executable source/migrations;
2. current canonical guides;
3. specialist docs;
4. dated historical audit notes;
5. planning/roadmap documents.

Never document a planned feature as already available unless the source has a real route/UI/use case, permission path and tests/preflight appropriate to it.

## 6. Documentation quality checklist

Before release confirm:

- terminology matches current route/model/feature names;
- role names match `UserRole`;
- feature keys match `config/features.php`;
- AI feature keys match `config/ai.php`;
- provider names are not invented;
- instructions do not expose credentials;
- production commands are safe (`migrate --force`, not `migrate:fresh`);
- queue and scheduler instructions are both documented where required;
- screenshots/examples do not contain secrets or real student private data;
- user docs explain permissions/feature availability rather than promising universal access;
- limitations are stated rather than hidden.

## 7. Automated documentation preflight

Run:

```bash
php scripts/check-documentation.php
```

The script verifies the existence of canonical docs and ensures the current role keys, Feature Management keys and central AI feature keys are represented. If a developer adds a new registered role/feature/AI capability without updating docs, the check fails.

A Composer shortcut is available:

```bash
composer docs-check
```

## 8. Pull request/release checklist

Every PR/release should answer:

- Does this change affect users?
- Does it affect developers/architecture?
- Does it add/change a feature key?
- Does it add/change an AI capability?
- Does it add a queue job or schedule?
- Does it alter `.env`, schema, settings, permissions or API?
- Which docs were updated?
- What was added to `CHANGELOG.md`?

If the answer is “no documentation change required”, the PR should explain why.

## 9. Future coding-agent instruction

Any developer or AI coding agent working on AcadFlow must treat this policy as a standing requirement. When implementing new functionality, update canonical documentation and the changelog before generating the final source archive/release.

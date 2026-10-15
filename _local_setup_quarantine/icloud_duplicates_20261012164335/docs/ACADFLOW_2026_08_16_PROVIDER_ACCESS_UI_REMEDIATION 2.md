# AcadFlow — 2026-08-16 Provider, Access and Workspace Remediation

## Scope

This change resolves the scheduled AI health false-failure, central external-provider transport diagnostics, lecturer course-material access, Knowledge Hub author workflow access, and the requested academic/knowledge workspace UI modernization.

## AI provider transport

`ExternalProvider` is the single transport for both provider Test Connection and real AI feature execution. Connection failures are classified rather than all being reported as timeouts. Safe diagnostics are written to `storage/logs/ai-provider.log`; credentials, authorization headers and prompts are not logged.

Provider protocol adapters were reviewed and normalized for OpenAI, Claude, Gemini, DeepSeek, Azure OpenAI and Ollama. Gemini uses header-based API-key authentication. Azure supports current v1 endpoints while preserving legacy deployment URLs. Full endpoint URLs are normalized to prevent route suffix duplication. Bootstrap model defaults were refreshed without overwriting database/admin selections.

The scheduled `acadflow:ai-health` command is observational by default and only returns a failure code when `--strict` is supplied. This prevents an unavailable upstream AI service from appearing as a Laravel scheduler failure.

## Access-control fixes

Course material `show` and download paths authorize through `CourseMaterialPolicy`. The uploader and authorized lecturer/admin can access managed material even if students cannot.

Knowledge creators now have a dedicated publication management/read-only workspace. The creator can view their own publication at every workflow stage; edit/submit remains status-aware.

## UI modernization

Modernized: student My Courses, course detail, materials list/detail, assignments, discussions, attendance, communities, groups, learning paths, reading lists, events, challenges, leaderboard and creator profiles. The separate lecturer My Courses listing is preserved.

## Regression protection

- `scripts/check-ai-provider-transport.php`
- `tests/Feature/AiProviderTransportTest.php`
- `tests/Unit/AccessRegressionPolicyTest.php`
- existing runtime/Blade, centralized AI, Feature Management, environment, MySQL identifier and documentation preflights

## Operations

After deployment run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan queue:restart
php artisan optimize
```

For an explicit provider health gate:

```bash
php artisan acadflow:ai-health --force --strict
```

For normal scheduled monitoring, keep the scheduled non-strict command.

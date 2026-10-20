# AcadSyntra AI Admin Provider Resilience Fix — 2026-09-16

## Problems fixed

1. **Gemini / provider Test Connection could fatal at PHP's 30-second limit.**
   Health checks inherited the normal generation timeout and could perform an IPv4 fallback, allowing the Admin POST request to consume almost the entire PHP execution window inside Guzzle/cURL.

2. **OpenRouter model discovery could exceed `settings.value`.**
   The complete catalog includes names, context windows and pricing metadata and can exceed MySQL `TEXT` capacity. AcadSyntra runtime only needs the discovered model IDs.

## Implementation

- Provider health checks now have independent defaults:
  - total health-check budget: **12 seconds**
  - per-attempt request timeout: **6 seconds**
  - connect timeout: **3 seconds**
- IPv4 fallback receives only the time remaining in the total health-check budget.
- Admin `Test Connection` explicitly uses the bounded health-check profile and disables generation retry settings.
- Normal AI generation keeps its existing lifecycle and request timeout configuration.
- OpenRouter `/models` discovery is bounded to a short interactive timeout.
- AcadSyntra persists only `ai_provider_openrouter_models` (the model IDs used by runtime/UI).
- The complete OpenRouter discovery payload is stored only in a six-hour cache entry for diagnostics.
- A cleanup migration removes the obsolete `ai_provider_openrouter_model_catalog` settings row from older deployments.

## Environment defaults

```env
AI_HEALTH_CHECK_BUDGET_SECONDS=12
AI_HEALTH_CHECK_TIMEOUT=6
AI_HEALTH_CHECK_CONNECT_TIMEOUT=3
```

## Expected UX

A slow/unreachable provider should return a controlled provider status (`timeout`, `dns_error`, `tls_error`, `connection_refused`, etc.) instead of ending in a Symfony/Guzzle `Maximum execution time of 30 seconds exceeded` fatal page.

OpenRouter model discovery no longer attempts to insert its large full catalog JSON into the generic settings table, preventing MySQL error `SQLSTATE[22001] / 1406 Data too long for column 'value'`.

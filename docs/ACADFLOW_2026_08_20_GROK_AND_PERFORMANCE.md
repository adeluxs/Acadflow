# AcadFlow — Grok (xAI) Provider & Performance Upgrade

**Date:** 2026-08-20

## Scope

This release adds Grok/xAI through the existing centralized AI provider architecture and improves user-perceived/runtime performance without introducing a second router, provider manager or client-side navigation framework.

## Grok integration

- Provider key: `grok`
- Label: `Grok (xAI)`
- Adapter: `App\Ai\Providers\GrokProvider`
- Base API: `https://api.x.ai/v1`
- Chat endpoint: `/chat/completions`
- Authentication: Bearer API key
- Bootstrap environment: `XAI_API_KEY`, `XAI_BASE_URL`, `XAI_MODEL`, `XAI_TEMPERATURE`
- Default bootstrap model: `grok-4.5`
- Cost tracking defaults to zero unless current pricing is explicitly configured; AcadFlow does not invent cost data.

Grok uses the existing `ExternalProvider` transport for JSON encoding, TLS/CA bundle, proxy, IPv4 options, timeouts, safe logs, request IDs and normalized failures. It is automatically included in Default/Fallback/Secondary Fallback and per-feature routing because the existing UI/controller iterate `AiProviderName`.

The production-safe migration `2026_08_20_140000_add_grok_provider_and_fast_ai_failover.php` inserts missing Grok settings with `insertOrIgnore()` and has a deliberately non-destructive rollback.

## AI latency improvements

A new `ai_fast_failover` setting is enabled by default. Retryable provider/network failures can return immediately to `AiManager` so the configured fallback chain advances instead of waiting through repeated full upstream attempts. Test Connection retains its more diagnostic connection behavior.

The default shared AI connect timeout bootstrap is reduced from 10 seconds to 6 seconds. Deployments can override `AI_HTTP_CONNECT_TIMEOUT` when necessary.

## Frontend/navigation performance

- Added `resources/js/performance.js`.
- Conservative same-origin document prefetch (limited per page and disabled for slow/save-data connections).
- Immediate progress feedback on the first normal link click without hijacking browser navigation.
- Duplicate native write-form submissions are disabled after the first valid submission.
- Vue and Vue components are dynamically loaded only on pages with `#app`.
- The sync manager script is deferred.

## PWA correctness/performance

The service worker now:

- enables Navigation Preload;
- does not cache authenticated HTML navigations;
- does not cache private API reads;
- caches static assets only;
- avoids precaching invalid non-Vite paths such as `/css/app.css` and `/js/app.js`;
- retains offline background write-sync support.

This removes stale account-page behavior and avoids service-worker cache processing from the normal online navigation path.

## Server/query improvements

- Main layout calculates unread notification count once instead of hydrating/counting the unread notification collection multiple times.
- Course workspace no longer eager-loads all enrolled users when it only displays aggregate enrollment counts.
- Lecturer attendance listing uses `withCount('records')` instead of hydrating every attendance record and user for every session card.
- Redis queue connection supports `REDIS_QUEUE_BLOCK_FOR` (default 2 seconds) for low-latency efficient blocking workers.

## Validation

Run:

```bash
php scripts/check-grok-performance.php
php scripts/check-ai-provider-transport.php
php scripts/check-ai-central-routing.php
php scripts/check-documentation.php
```

Framework tests include Grok transport and central-router selection coverage. Full PHPUnit execution still requires installed Composer dependencies.

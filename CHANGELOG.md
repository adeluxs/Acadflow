# AcadFlow Changelog

All notable product, architecture, security, data and operations changes should be recorded here. AcadFlow currently uses date-based release headings because a formal semantic version has not yet been established.

## [2026-08-28]

### Added
- Added the Nigeria-first Monetization Center, minor-unit money handling, double-entry ledger journals/postings, idempotency records, versioned pricing rules, independent feature entitlements, B2B commercial accounts, prepaid wallet funding and AI usage charging.
- Added separate spending, pending earnings, available earnings, lifetime earnings and recovery-debt wallet accounting, including creator settlement holds, withdrawals and refund recovery.
- Added resumable external-refund processing and Admin reconciliation so uncertain provider outcomes cannot be blindly replayed.
- Added OpenRouter as a first-class AI provider through the existing central AI provider registry/router/fallback architecture, including model discovery and usage/cost capture.
- Added dry-run-first `acadflow:monetization-migrate`, scheduled creator-earnings release and monetization regression tests/preflight.

### Changed
- Retired recurring subscription plans as the normal runtime entitlement mechanism; core academic/API access now uses feature management/independent entitlements while historical subscription records remain available for migration and audit.
- Converted new commerce, wallet, refund, withdrawal, institutional billing and AI financial operations to server-authoritative minor-unit accounting and auditable ledger entries.
- Moved institutional commercial limits/fees to commercial-account configuration and centralized monetization settings.
- Updated Knowledge Hub purchase UX to support prepaid wallet or payment-gateway funding with transparent balance/earning states.

### Fixed
- Prevented duplicated wallet funding, purchases, refunds, AI debits and other financial operations through idempotency and provider-reference checks.
- Fixed refund-after-withdrawal accounting by creating a recovery receivable repaid from future creator earnings instead of allowing silent financial loss.
- Fixed cross-tenant commerce/refund/withdrawal administrative visibility and callback authorization gaps.
- Fixed institutional invoice/payment currency persistence and exact provider amount/currency verification.


## [2026-08-20]

### Added
- Added live password-policy guidance on account registration and password reset, driven by the existing centralized Security Settings rather than hardcoded frontend rules.
- Added centralized Admin-controlled request limits for login, registration, password reset, email verification and two-factor challenge attempts, plus production-safe default setting migration.
- Added friendly web/API 429 handling with the actual `Retry-After` value, structured API `TOO_MANY_REQUESTS` responses and live wait countdowns.
- Added `scripts/check-security-policy.php` / `composer security-policy-check` regression checks for password-policy and authentication-throttle consistency.
- Integrated Grok (xAI) as a first-class provider through the existing `AiProviderName` → `AiProviderRegistry` → `AiRouter` → `AiManager` architecture, using the shared `ExternalProvider` HTTP transport rather than a separate provider stack.
- Added production-safe Grok AI settings/migration and `XAI_*` environment bootstrap variables, with `grok-4.5` as the bootstrap model and no invented default cost pricing.
- Added `ai_fast_failover` so interactive AI requests can advance through the configured fallback chain without repeatedly waiting on the same failed upstream provider.
- Added global interaction-performance helpers for conservative page prefetch, first-click navigation feedback and duplicate write-form submission protection.
- Added `scripts/check-grok-performance.php` / `composer performance-check` regression checks.

### Changed
- Unified registration, password reset and API password-change validation through `SettingService::getPasswordPolicy()` / `getPasswordRules()`, removing the reset flow's conflicting hardcoded mixed-case/number/uncompromised rule.
- Existing named Laravel auth rate limiters now read their limits from centralized Security Settings; existing failed-login lockout settings remain a separate credential-failure protection and now provide remaining-attempt feedback.
- Exposed the effective password policy through the existing public/API settings payload so mobile/API clients can render the same requirements enforced by the backend.
- Lazy-load Vue and Vue components only on pages that actually render a Vue mount point; normal Blade pages no longer eagerly include the Vue application bundle.
- Reduced the bootstrap AI connect timeout to 6 seconds while keeping it environment-configurable.
- Reworked the PWA service worker to use Navigation Preload, cache static assets only, and avoid caching authenticated HTML pages/private API reads.
- Deferred the sync-manager script, reduced local queue idle sleep to one second, and added `REDIS_QUEUE_BLOCK_FOR=2` for responsive Redis workers.
- Reduced unnecessary data hydration on common course/attendance pages and calculate the unread-notification count once per layout render.

### Fixed
- Prevented technical Laravel 429/ThrottleRequestsException pages from becoming the primary user experience on existing rate-limited authentication/security actions.
- Fixed password-rule drift where registration/Admin settings and the password-reset controller could enforce different requirements.
- Fixed login lockout messaging so users receive the real remaining wait time and, on failed credentials before lockout, the remaining-attempt count.
- Removed avoidable sources of “click twice / wait before anything happens” perception by keeping native one-click navigation authoritative and providing immediate loading feedback instead of interception.
- Prevented stale personalized pages/API data from being returned from the PWA cache during normal authenticated navigation.
- Prevented repeatable upstream AI failures from consuming multiple full provider timeouts before centralized fallback when Fast Interactive Failover is enabled.

## [Unreleased]

### Added
- Added safe provider payload-shape diagnostics (`payload_model`, top-level keys and byte size) without logging prompts or credentials.
- Added safe AI-provider transport diagnostics with dedicated `storage/logs/ai-provider.log`, request IDs, provider request IDs and classified TLS/DNS/connection/auth/model/rate-limit/timeout errors.
- Added configurable AI HTTP connect timeout, CA bundle, proxy, IPv4 and TLS verification controls shared by Test Connection and real AI execution.
- Added AI provider/access regression preflight plus provider protocol and creator-access regression tests.
- Added creator-safe Knowledge Hub publication workspace so authors can always open their own publication record and see status/workflow actions even while editing is locked.
- Added canonical `docs/DEVELOPER_GUIDE.md` covering current Laravel architecture, roles/tenancy, modules, settings, centralized AI, queues/scheduler, API, security, testing and deployment.
- Added canonical `docs/USER_GUIDE.md` for Super Admin, University Admin, Department Admin, Lecturer, Student, Platform Member and contextual roles such as Supervisor and Group Leader.
- Added `docs/DOCUMENTATION_MAINTENANCE.md`, `CONTRIBUTING.md`, documentation index, PR checklist and automated documentation preflight.
- Added operations documentation for Redis queues, scheduler, local development and cPanel/shared-hosting production setup.

### Changed
- External provider POST requests now use explicit raw JSON bodies, and retryable connection failures may perform one automatic IPv4 transport fallback before giving up.
- Updated provider adapter compatibility for OpenAI/compatible Chat Completions, Claude Messages, Gemini `generateContent`, DeepSeek Chat Completions, Azure OpenAI v1/legacy endpoints and Ollama local/cloud chat. Full endpoint URLs are now accepted without duplicating route suffixes.
- Updated safe bootstrap model defaults for new installations to `claude-sonnet-5`, `gemini-3.6-flash` and `deepseek-v4-flash`; existing database/admin provider selections are not overwritten.
- Made scheduled `acadflow:ai-health` observational by default so an upstream provider outage does not make Laravel report the scheduler itself as failed; `--strict` remains available for CI/manual health gates.
- Modernized the student My Courses experience, individual course workspace, materials, assignments, discussions and attendance pages while preserving the separate lecturer My Courses page.
- Modernized communities, groups, learning paths, reading lists, events, challenges, leaderboard and creator profile experiences with responsive, interactive academic workspace layouts.
- Replaced the default Laravel-oriented root README with an AcadFlow-specific project entry point and current quick-start instructions.
- Refreshed the current Tech Stack, Architecture, Structure, Roles, API, Frontend, Security and Database references so older planning assumptions do not mislead developers.
- Updated the in-application public Documentation/Changelog summaries to reflect centralized AI assistants and central Feature Management.
- Established a mandatory rule that every future functional change updates canonical documentation and `CHANGELOG.md` in the same release.

### Fixed
- Fixed OpenAI requests that could reach `/v1/chat/completions` without the upstream API seeing the required `model` field by explicitly encoding/sending the JSON payload and asserting the model in transport tests.
- Added runtime and migration safety for Gemini 1.5/2.0 model IDs that Google has shut down, mapping only known retired IDs to supported replacements.
- Fixed the generic provider connection error that incorrectly described TLS/DNS/refused/network failures as a timeout across all providers.
- Fixed Gemini API-key transport so credentials use the `x-goog-api-key` header instead of being placed in the request URL.
- Fixed current Claude Sonnet 5 compatibility by omitting unsupported non-default sampling temperature while preserving temperature for compatible older Claude models.
- Fixed lecturer access to course materials they uploaded/are authorized to manage, including hidden or enrollment-protected material.
- Fixed Knowledge Hub creators receiving Access Denied when opening their own publication; edit/submit actions now remain workflow-status aware rather than blocking the publication workspace itself.

## [2026-08-15]

### Added
- Activated six contextual AI assistants: Research, Assignment, SIWES, Project, Material/Study and Discussion Assistant.
- Expanded centralized AI capability registry to 16 active feature keys.
- Added AI feature middleware so disabled assistants stop before context retrieval/file extraction.
- Added safe prompt baselines for all active AI features without overwriting existing customized prompts.
- Added centralized AI diagnostics/provider health visibility and routing metadata.

### Changed
- Consolidated AI Settings/provider routing around `AiRuntimeConfigService`, `AiRouter`, `AiProviderRegistry` and `AiManager`.
- Made feature-specific provider/model overrides inherit from the global AI route when set to Use Global Default.
- Separated Provider AI, Hybrid, Rule-Based Only and Disabled modes so rule behavior cannot silently pretend to be a configured external provider.
- Corrected AI Assistant role/tool resolution so Student Ask/Explain uses `study_assistant`, Lecturer Ask/Explain uses `lecturer_assistant`, Improve Writing uses `writing_assistant`, and Citation Review uses `citation_assistant` consistently in UI metadata and execution.
- Preserved legacy AI settings as compatibility/history data while removing them as competing runtime sources of truth.

### Fixed
- Fixed provider-switching consistency so saved Default/Fallback provider settings are resolved at runtime and relevant caches are invalidated.
- Fixed the lecturer AI Assistant page-route metadata mismatch.

## [2026-08-14]

### Added
- Added centralized Feature & Module Management with 30 registered module/feature groups and Enabled/Maintenance/Disabled states.
- Added Grounded AI Companion intelligence improvements including gibberish detection, publication-first retrieval, evidence gating, citation/source validation, abstention and useful-pattern learning.
- Added complete `.env.example` coverage/audit and environment configuration documentation.
- Added cache-lock/session-cookie safety checks for blank runtime-critical environment values.
- Added seeder idempotency/data-preservation checks and regression tests.

### Changed
- Consolidated feature availability into `FeatureAccessService` and reused existing feature tables rather than creating duplicate availability settings.
- Made administrators able to preview maintenance/disabled features while normal users receive controlled unavailable responses.
- Hardened Knowledge Hub and community workflows, media/file delivery and notification routes.
- Made normal seed execution create missing defaults without clearing or overwriting matching existing administrator data.

### Fixed
- Fixed empty `SESSION_COOKIE` handling that caused Symfony “cookie name cannot be empty”.
- Fixed blank database cache-lock table handling that produced MySQL `update `` ... Incorrect table name ''` scheduler errors.
- Fixed media file-size metadata failure handling and safer missing-file responses.
- Fixed malformed Knowledge/Research Blade templates and notification read-all HTTP method/route ordering.
- Fixed community poll validation so poll options are only required for poll posts.

## [2026-08-13]

### Added
- Added dedicated AI Assistant routes/workspace instead of reusing unrelated Knowledge search/settings screens.
- Added Academic Performance service/dashboard improvements and rich-editor AI writing suggestions.
- Added Nigerian academic catalogue service/seeder/import template and provenance metadata.
- Added course invitation/membership workflows and lecturer course self-assignment controls.

### Changed
- Consolidated runtime settings through `SettingService` with tenant/global override behavior and caching.
- Improved dashboard, landing page and shared application UI.

### Fixed
- Fixed multiple runtime AI/settings/submission errors, including Blade placeholder parsing, array settings rendering and null submission-task handling.
- Fixed seeder indexing issue caused by associative lecturer collection keys.

## Earlier implementation history

Earlier planning, implementation and audit detail remains available under `docs/` (PRD, architecture, submission-system reviews, PWA phases and other dated notes). New releases must continue from this changelog rather than creating disconnected history files only.

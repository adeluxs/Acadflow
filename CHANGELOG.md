## [Unreleased]

### Changed

- Added role-specific **Study Pro** and **Teaching Pro** packages to the existing pricing, wallet, entitlement and institutional-sponsorship system. Both remain free until priced and can offer monthly/semester access, included AI requests, one-time trials and read-only grace. Daily free requests and package allowances apply before AI overage, users can set a monthly AI spending limit, and failed generation restores reserved package allowance. Core courses, attendance, assignments and submissions remain outside the access gate.

- Fixed independent classroom creation on MySQL by widening `courses.semester` and `courses.level` to match validated values, and bounded course API updates to the database contract.

- Optimized admin users, faculties, institution search, audit history, commercial accounts, pricing history, lecturer assignment and import selectors with scoped pagination, deferred searches and smaller records. Added restartable management indexes, batched settings/summary reads, bounded revenue iteration and less shared activity/audit write work; documented reproducible measurements and deployment steps.

- Retained and redesigned Creator Profile as the Knowledge Hub author workspace, with clear identity/expertise/visibility sections, live reader preview, optional ORCID import and scoped review history. Refreshed the public reader page with biography, expertise, links and public published work.

- Rebranded the platform as AcadSyntra across public/authenticated pages, academic assistants, exports, email/notification copy, manifests, settings defaults and documentation.
- Added reproducible violet/navy vector branding, PNG icons, favicons and social artwork; legacy asset URLs serve the new graphics. Refreshed demo screenshot capture and bumped PWA asset caches while preserving offline databases, session identifiers, API headers and existing commands.
- Added a non-destructive branding migration that updates known defaults, preserves institution customizations and academic records, and versions shipped AI instructions without rewriting prompt history. Fixed the dynamic manifest’s string-helper import and missing icon fallbacks.

### Added

- Added a lecturer-only Teaching Studio with a prioritized feedback/question/preparation loop, bounded course pulse, dashboard entry point and direct access to existing review, discussion, material, assignment and AI-draft workflows. It remains useful without AI and does not rank lecturers.
- Restricted all legacy and connected study routes to students, including direct URLs, and made AI Assistant shortcuts role-specific so lecturers see teaching tools while students retain private study tools.

- Added My Study Space and the student Today dashboard: connect published material or private pasted/imported notes to saved explanations, source-based practice, detailed answer feedback and scheduled revision. Reused current AI availability, provider, allowance and billing controls; saved practice grades locally without affecting official grades.
- Added scoped resume/revision queries, paginated source lists, upload extraction limits, verified source quotations, retry-safe attempts and restartable study-session/revision indexes. Added private-access, AI failure/disablement, migration, performance and mobile/desktop browser coverage with deployment documentation.

- Added Manage Onboarding from dashboards and profiles, immediate editing of completed/skipped sections, applicable-field completion scores, role-specific academic fields, separate organisation setup checklists and preference autosave using existing records.
- Connected profile management to verified memberships/invitations and existing department module/calendar configuration; bounded institution search replaces full-directory onboarding loads.
- Added consent-based, infrequent optional profile suggestions and required-field reminder links; completed users with optional gaps are not classified as abandoned. Preserved academic affiliations, work, completion dates and notification preferences across revisits.

- Expanded searchable step-by-step onboarding/help guides, consent-based browser push invitations and device management, category/timezone/quiet-hour preferences, bounded onboarding and useful-work inactivity reminders.
- Added a central after-commit notification outbox, queued academic/research/organisation events, safe retries and uncertain-outcome handling, signed interactions/unsubscribe, optional provider-confirmation adapter, retention maintenance and queued account mail.
- Added Super Admin notification analytics with recorded channel delivery, push subscriptions, onboarding and meaningful-activity metrics, interactive charts, scoped filters, streamed CSV, template/settings controls and failure monitoring; added migration-preservation and lifecycle/browser regression coverage.
- Added 41 modular research designs with saved setup plans, methodology-based or five-chapter structures, ND-to-doctoral project context, instruments/analysis guidance, milestones and scoped official reporting-standard links.
- Added a private, versioned research planning library, reviewed institutional guideline extraction/heading application, source-bound qualitative coding/theme summaries, reproducible descriptive CSV analysis and assumption-preserving sample-size planning.
- Added genuine DOCX export, configurable document formatting, contents generation and resource-to-section insertion; retained existing PDF/HTML, scholarly discovery, AI, supervision and institutional workflows.
- Added responsive research setup/planning interfaces and refreshed AcadSyntra authentication/onboarding, accessible password/validation states, resumable setup and optional first-project entry.

- Rebuilt the public website around actual AcadSyntra learning, research and publishing workflows, with shared responsive navigation/footer, interactive illustrative previews, searchable features/help, About/Contact and expanded policy pages.
- Added a validated, throttled contact form with CSRF, honeypot and session form timing checks, durable Super Admin enquiry inbox, and optional queued email alerts through the configured support channel.
- Added unique public metadata, canonical links, structured data and dynamic sitemap/robots routes, plus isolated responsive browser and backend regression coverage.
- Added a platform-wide Email Verification Enabled switch under Super Admin → System Settings → Security. The existing requirement remains enabled by default; disabling it permits access without falsely marking addresses as verified. Web/API registration, sign-in, onboarding and publishing/community policies use the same runtime rule.
- Added browser push enrollment, per-device disable/test controls, and Super Admin setup for stable encrypted delivery keys. Environment-supplied VAPID keys are also supported.

### Fixed

- Fixed stale/invalid PWA asset responses breaking styles on navigation: bypassed worker asset caching locally, validated cached MIME types, respected reloads, revalidated unhashed assets and automatically repaired missing/empty styles during worker upgrades. Added real-worker browser coverage while preserving notification registration and offline queues.

- Removed duplicate My Profile links from student and lecturer sidebars. Fixed creator visibility checkboxes and partial saves so privacy choices take effect without erasing stored links, verification decisions or unrelated preferences. Public creator pages honour affiliation/impact privacy and exclude restricted or future publications; hidden impact no longer triggers reputation queries.

- Fixed live MySQL deployment error 1067 for `user_activity_days.last_activity_at` by using required `DATETIME` activity values. Made the notification upgrade resumable after partially committed MySQL DDL while preserving notification history, preferences, existing tables and all indexes.

- Implemented actual research editor autosave with optimistic version checks and recoverable conflicts, stable no-op saves and visible offline/session/permission states. Shared web/API saves, table sanitisation, restore progress and approval/correction locks preserve research history.
- Fixed rich-content sanitisation of nested unknown wrappers so active attributes cannot survive unwrapping.
- Required two-factor authentication for onboarding and prevented caching of Research Studio responses. New library files always use private storage and current project permissions.

- Prevented academic starter-template seeding from failing on short SQLite write contention: enabled configurable WAL/busy waiting, acquired the writer before catalogue reads, and retried transactions with fresh state and counters. Added `acadflow:sync-nigeria-catalog --templates-only` to resume safely without a registry refresh.
- Replaced placeholder push HTTP requests with encrypted `aes128gcm` payloads and signed VAPID delivery using `minishlink/web-push`. Added active-worker push display and safe same-origin click handling, including a standalone worker when PWA caching is disabled.
- Enforced notification module/channel/user preferences in push delivery, cleaned expired endpoints, retained transient failures, protected endpoint ownership, restricted outbound push destinations, and supported long provider endpoints without oversized MySQL indexes.
- Fixed unchecked notification preference fields not disabling their values; added browser recovery/error states and logout cleanup for shared devices.

### Fixed

- Hardened Paystack wallet, marketplace and invoice payments: validate raw webhook signatures and exact successful references, amounts and currencies before granting value.
- Fixed gateway credential saving, blank-secret preservation, test/live selection and the admin connection-test POST; credentials remain encrypted and are excluded from validation session flashes and model serialization.
- Persist payment references before provider requests, serialize checkout creation, reuse pending checkout URLs and preserve uncertain outcomes without automatically repeating payment/refund POSTs.
- Unified both payment webhook URLs across commerce, invoices and legacy payments; transient settlement failures request redelivery, while deactivated gateways can still settle earlier payments.
- Added the missing authenticated invoice return route and payment-status links for pending wallet funding and marketplace orders.
- Keep pending refunds reserved until provider processing is verified; prevent duplicate refunds, verify refund events against the stored provider refund ID, and expose administrator reconciliation controls.
- Added a non-destructive migration for encrypted gateway credential storage and MySQL wallet-funding transaction types; added payment-flow regression tests and a gateway operations guide.

## 2026-09-17 — Lecturer material upload + Research Assistant delivery fix

- Normalized material visibility/enrollment checkboxes before Laravel boolean validation so native browser values such as `on` no longer fail `boolean` rules.
- Added explicit hidden `0` + checkbox `1` values for `is_public`, `requires_enrollment` and `is_visible` on lecturer create/edit material forms.
- Removed the institution-profile equality guard that incorrectly denied independent lecturers after material submission; course authorization and the course academic context now determine access.
- Material creation now resolves the active semester from the course's academic context and persists `is_visible` consistently.
- Deferred AI analytics recording until after the HTTP response in normal web requests so a successful Research Assistant provider response cannot be held behind observability/database work.
- Changed the contextual Research Assistant to use its own inline loading state without invoking the global full-screen loader, added a bounded browser wait/abort path, and guaranteed button/loading cleanup in `finally`.
- Added feature/static regression coverage for independent lecturer material upload and post-provider Research Assistant response delivery.

## 2026-09-16 — AI Admin provider timeout + OpenRouter catalog persistence fix

- Bounded provider Test Connection health checks to a 12-second total budget with 6-second request and 3-second connect defaults, including IPv4 fallback.
- Prevented Gemini and other provider connectivity tests from reaching PHP's common 30-second fatal execution limit.
- Bounded OpenRouter model discovery and stopped persisting the oversized full model catalog in the generic settings table.
- Persist only the OpenRouter model IDs required by AcadSyntra runtime; cache the full discovery payload for short-lived diagnostics.
- Added cleanup migration for the obsolete `ai_provider_openrouter_model_catalog` setting and a regression preflight.

## 2026-09-16 — Research Studio chapter guidance runtime fix

- Fixed `ResearchWritingExperienceService` returning `aiPrompts` while the Writing Workspace consumed `ai_prompts`, which caused an HTTP 500 on chapter pages such as Chapter 3.
- Standardized the writing-guidance contract on snake_case `ai_prompts`.
- Made the Blade prompt renderer null-safe so optional guidance data can never crash the chapter editor.
- Added runtime/static regression coverage for the guidance contract.

## 2026-09-16 — Professional Research Studio dashboard and chapter workspace

- Reorganized Research Studio around a unified professional project Control Center and template-driven Research Journey.
- Replaced the long all-sections writing page with a focused Chapter Navigator + one active versioned section editor.
- Added chapter-aware structure guidance and Research Assistant prompts for Introduction, Literature Review, Methodology, Results/Analysis, Discussion/Conclusion, References and other template sections.
- Preserved Chapter Two reviewed-literature insertion and References bibliography insertion in the existing rich editor.
- Added optional results-section dataset tools only when Admin enables Research Dataset Management.
- Kept all institution-related UI absent unless Research Institutional Expansion is enabled and the project is institutional.
- Enforced disabled Meetings, Collaboration, Archives, Datasets, SIWES and Seminar modules at controller endpoints as well as in the UI.
- Added regression coverage for the professional Research Studio workflow.

## 2026-09-16 — Chapter Two reviewed-literature insertion fix

- Fixed Research Studio literature actions reporting "Inserted" without placing the selected citation/evidence into the visible Chapter Two Quill editor.
- Unified Quill and fallback editors behind one insertion registry and synchronization contract.
- Added a pre-initialization insertion queue so clicks made while the dynamically loaded editor is still booting are not lost.
- Added a dedicated regression preflight for Chapter Two literature insertion.

## 2026-09-16 — Literature Review Workspace and Chapter Two integration

## 2026-09-16 — Scholarly timeout fix + AcadSyntra global branding/loading

- Replaced sequential Research Studio scholarly-provider calls with bounded Laravel HTTP pooling and partial-provider resilience.
- Added 5s request / 2s connect defaults and removed retry chains from interactive literature discovery.
- Added short query/filter caching without caching total provider outages.
- Replaced per-result scholarly `updateOrCreate()` persistence with stable-UUID batch upserts.
- Added generated AcadSyntra brand assets, favicon/PWA icons, and shared logo treatment across app/auth/onboarding/landing/public layouts.
- Added global branded loader for page navigation, form submissions, foreground fetch/Axios mutations, AI actions, and Research Studio operations while excluding background sync/polling.
- Added `scripts/check-scholarly-timeout-and-branding.php`.

- Implemented the approved five-tab Literature & References UI: Discover, Saved References, Reading Queue, Synthesis and Compare.
- Added Saved → Reading → Reviewed lifecycle, researcher-defined collections, review-completion indicators and migration backfill for existing structured notes.
- Added deterministic literature synthesis from reviewed researcher notes only, including themes, methodology signals, findings, limitations, research gaps and contrasting evidence.
- Added side-by-side comparison for 2–5 project references without fabricating missing review fields.
- Connected reviewed references to the existing Chapter 2 / Literature Review rich editor with explicit citation/evidence/methodology/gap insertion at the cursor; insertions remain unsaved until the researcher saves a section version.
- Added reviewed bibliography insertion to the existing References section using the project citation style.
- Hardened citation formatting for nested author metadata from scholarly providers.
- Added `LiteratureSynthesisService`, workflow migration, feature tests and `check-literature-review-workflow.php`.

## 2026-09-16 — Professional Research Studio and literature workflow

- Fixed the Research Studio literature navigation label to render a valid HTML entity (`Literature &amp; references`), matching Laravel feature-test escaping while preserving the visible text.
- Split Research Studio into a focused default workspace plus Admin-controlled institutional, meeting, collaboration, archive and dataset modules without deleting existing enterprise functionality.
- Hid all institutional project/department/supervisor UI from normal users while Research Institutional Expansion is disabled and enforced the same rule in web/API authorization.
- Replaced direct supervisor assignment with expiring, auditable lecturer invitations; supervisor access activates only after acceptance and invitations can also be sent later from the project page.
- Rebuilt the Project Control Center around core milestones/tasks with optional relations loaded only for enabled modules.
- Redesigned Literature & References with provider/year filters, multi-select saving, lawful source links, saved-state indicators and structured researcher notes that reopen populated.
- Fixed DOI-less reference collisions and improved scholarly-result deduplication across OpenAlex, Crossref, Semantic Scholar, CORE, DOAJ, PubMed and arXiv.
- Added `ResearchStudioProfessionalizationTest` and a dedicated preflight check for the new release profile.

## 2026-09-16 — Course view and independent identity regression fix

- Reworked `resources/views/courses/show.blade.php` into compiler-safe multiline Blade directives, removing adjacent/compressed control directives that produced an unexpected `@endif` in compiled views.
- Restored web/API backend validation for all existing `OnboardingService::ACCOUNT_PATHS` while keeping the public registration UI limited to Student, Lecturer/Supervisor and Researcher.
- Updated enterprise identity regression coverage to match the independent-first product model: students may skip affiliation, institutional representative paths may not.
- Added a dedicated course-view/identity regression preflight.


## 2026-09-16 — PHPUnit Vite asset isolation

- Fixed feature-test HTTP 500s caused by a missing `public/build/manifest.json` in source-only/test checkouts.
- `Tests\TestCase::setUp()` now uses Laravel's `withoutVite()` helper for all application tests.
- Production/development `@vite` usage is unchanged; real deployments still require `npm run build` (or the Vite dev server).
- Added `scripts/check-test-vite-isolation.php` regression protection.


## 2026-09-15 — Independent-first launch profile

- Made Student, Lecturer / Supervisor and Researcher the public self-service personas with optional institution affiliation.
- Fixed public lecturer accounts to receive the existing non-admin `lecturer` role; release migration repairs legacy lecturer/member accounts.
- Added independent classrooms with explicit ownership and join-link enrollment while preserving the existing course/semester/assignment architecture.
- Added independent Research Studio workspaces with participant-based authorization for web and API clients.
- Added a hidden system-managed academic context for legacy semester/department foreign-key compatibility without attaching users to a fake institution.
- Split wallet/billing runtime availability from marketplace and creator-payout feature controls.
- Applied an Admin-controlled launch profile that disables non-core/institution-heavy modules without removing them.
- Made classroom adoption features free by default for launch while preserving AI/document monetization.
- Added independent-first static and Laravel regression tests and hid the system compatibility institution from public affiliation/search flows.

## 2026-09-14 - PHPUnit route-cache memory isolation

- Isolated PHPUnit from production route/config/event caches via `tests/bootstrap.php` and explicit testing cache paths in `phpunit.xml`.
- Prevented a generated production `bootstrap/cache/routes-v7.php` from being loaded during every Laravel test application boot, avoiding 128 MB memory exhaustion on Windows test environments.
- Added `RESET_LOCAL_LARAVEL_CACHES.bat` for one-time recovery from pre-existing generated bootstrap caches without requiring Laravel to boot first.
- Clarified that route caching is an optional production optimization and should only be enabled where PHP memory headroom has been verified.

# AcadSyntra Changelog

## 2026-09-12 — Codebase-wide performance optimization

- Replaced the wildcard Blade view composer with targeted layout composers so shared feature/platform data is no longer rebuilt for every partial/component.
- Added batched settings retrieval plus request-local caches with explicit HTTP/queue-worker flush hooks while preserving the existing one-hour shared cache and invalidation behavior.
- Collapsed submission, attendance, billing, AI and admin dashboard scalar metrics into SQL conditional aggregates; removed per-course grade query loops.
- Removed multiple N+1/query-in-loop patterns across citations, mentions, conflicts, submission grading, research configuration, student imports, plagiarism checks and learning-path progress.
- Converted large CSV/document exports, invoice generation, notification dispatch/retries and scheduled scans to lazy/chunked processing to bound memory use.
- Moved slow notification delivery outside research-meeting database transactions and kept billing/financial correctness protections intact.
- Added audited composite indexes for notification unread/recent lookups, invoice user/status and user/semester access, and attendance session/status aggregation.
- Removed closure-based web routes so Laravel route caching is safe, and dynamically loads the rich editor only on pages that need it.
- Added `scripts/check-performance-optimization.php` and `PERFORMANCE_OPTIMIZATION_REPORT_2026-09-12.md`. Final source validation: 24/24 preflight checks and 812/812 PHP/Blade parse checks passed.

## 2026-09-12 — Complete runtime monetization, AI deductions and three pricing modes

- Fixed Admin **Runtime pricing & wallet controls** so global values saved by Platform Admin are the same values enforced by runtime billing services.
- Rebuilt provider-AI charging around durable `reserved → completed / released / reconciliation_required` lifecycle records with user-wallet and verified institution sponsorship funding.
- Added assistant-specific AI pricing/free allowances for all 16 currently registered AI features, while retaining global AI pricing as fallback.
- Closed cached-response, duplicate-request and concurrent free-allowance billing loopholes; cached provider answers are billable interactions with zero new provider cost.
- Added generic `feature_usage_charges` ledger/audit records and complete **fixed access**, **pay per use**, and **AI usage** pricing modes.
- Connected pay-per-use billing to group submissions, rubric-enabled assignments, attendance sessions, and document/PDF generation; Knowledge Hub Premium uses fixed-access entitlements.
- Added **Paid Feature Access** user page with activation, pricing, sponsorship eligibility and recent usage/billing history.
- Corrected feature/AI clearing-account routing so paid-feature purchases are not mixed into marketplace settlement accounts.
- Hardened post-success settlement and refund/release failures with reconciliation states so successful delivered work is not accidentally refunded and failed work is not falsely finalized.
- Added duplicate/in-flight request replay protection, per-user billing serialization and fixed-access double-purchase protection.
- Added `MonetizationRuntimeBillingTest` and expanded monetization regression preflights. Final artifact validation: 23/23 preflight scripts and 810/810 PHP syntax checks passed.

## 2026-09-11 — Monetization Blade compiler regression fix

- Removed the inline `@php($errors = ...)` fallback that caused Laravel Blade to emit an unmatched compiled `endif` in the Admin Monetization page.
- Replaced direct `$errors`/`@error` usage with `isset($errors)`-guarded validation rendering so the page works both with normal middleware and with `withoutMiddleware()` regression tests.
- Kept the known-good Monetization template structure intact; only the three validation-error access points differ from the previously compiling version.
- Strengthened `scripts/check-monetization-blade-regression.php` and `scripts/check-pasted-test-regressions.php` to reject the broken inline error-bag fallback and require middleware-safe guards.
- Re-ran every repository `scripts/check-*.php` preflight and a full PHP/Blade source syntax sweep before packaging.

## 2026-09-10 — Institution search, AI lifecycle, monetization and Blade reliability

- Fixed a remaining AI response-cache miss caused by volatile `_ai_deadline_at` metadata being rendered into the prompt context; identical requests can now reuse deterministic cached results.
- Restored backwards-compatible `AiManager::analyze(..., requestId: ...)` support while preserving one logical request ID through the AI lifecycle.
- Restored the full five-argument `AcademicAssistantService::ask()` controller contract so course context, request correlation ID and lifecycle start time match the endpoint regression contract.
- Hardened the Admin Monetization view for middleware-free/regression rendering by guarding validation error access when Laravel has not shared `$errors`.
- Added `scripts/check-pasted-test-regressions.php` to guard the four supplied PHPUnit regressions without requiring a booted Laravel runtime.
- Replaced the institution-onboarding Alpine-only search UI with a dependency-free vanilla browser implementation including partial/case-insensitive search, debounce, abortable stale requests, loading/empty/error/retry states and correct claim/access continuation.
- Made institution catalogue search portable across MySQL/PostgreSQL/SQLite using normalized `LOWER(... LIKE ...)` matching for names, short names, codes, states and regulators.
- Added an end-to-end AI lifecycle budget and post-provider reserve so a provider attempt/retry cannot consume the entire PHP execution window before AcadSyntra can parse and return the answer.
- Preserved one logical AI request ID through assistant context building, AiManager, provider transport, parser and response lifecycle logging.
- Hardened Gemini HTTP-200 parsing with explicit empty, blocked and malformed-response categories.
- Made AI cache writes, usage counters, analytics and post-provider billing settlement failure-tolerant so non-critical bookkeeping cannot discard a valid generated answer.
- Moved Monetization `$setupCurrency` resolution into the controller using commercial-account currency, institution settings and platform fallback in that order; removed obsolete Blade-local currency dependency.
- Simplified the Monetization metric-card Blade loop and added source-level Blade directive regression checks; compiled views remain deployment artifacts and must be cleared rather than edited.
- Added institution search, AI response lifecycle/post-success resilience and Monetization page regression tests plus `scripts/check-four-critical-fixes.php`.
## 2026-08-31 — Institution Commercial Account UX

- Split commercial account responsibilities into a safer two-sided workflow: Institution Admins can view approved terms and submit requests; only Platform/Super Admin can create, approve or financially modify commercial accounts.
- Added a dedicated Institution Commercial Account dashboard with prepaid budget, account status, institution-funded AI usage, member count, sponsored features, administrator limits, semester fee/grace terms and request history.
- Added guided requests for account setup, additional prepaid budget, commercial-term changes and sponsored AI/feature access, with duplicate-pending-request protection and throttling.
- Added Platform Admin request review queue, guided three-step commercial-account setup, safe institution locking during review, prefilled requested changes, rejection notes and approval notifications.
- Added `commercial_account_requests` audit records so institution requests remain separate from actual financial mutations.
- Added institution-sponsored paid feature access to the central entitlement engine; sponsorship works only while the approved commercial account is active.
- Updated University Admin navigation/settings to use **Commercial Account** instead of exposing the Platform Monetization control center.
- Added `scripts/check-commercial-account-ux.php` and architecture/feature regression coverage.

## 2026-08-31 — Pricing Rule Admin UX

- Replaced developer-only Pricing Rule inputs with a plain-language **Feature to charge for** dropdown.
- Added a centralized allowlist of runtime entitlement features in `config/monetization.php`.
- Pricing-rule keys, display names and currency are now derived server-side instead of accepted from browser input.
- Removed the confusing percentage field from paid-feature pricing; marketplace/institution percentages remain in their dedicated commission settings.
- Added feature descriptions, inline validation, activation guidance and improved immutable pricing-history presentation.
- Added `scripts/check-pricing-rule-ux.php` to prevent regressions between runtime entitlement checks and the Admin dropdown.


All notable product, architecture, security, data and operations changes should be recorded here. AcadSyntra currently uses date-based release headings because a formal semantic version has not yet been established.

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
- Replaced the default Laravel-oriented root README with an AcadSyntra-specific project entry point and current quick-start instructions.
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

## 2026-09-02 - Test-suite compatibility and release packaging fix

- Updated AI rule-engine tests to resolve the current container-managed `AiManager`/runtime configuration instead of constructing obsolete `AiRouter`/`AiCache` signatures.
- Updated specialized AI module tests to resolve modules through Laravel's container so `AiRuntimeConfigService` dependencies are supplied consistently.
- Corrected enterprise identity fixtures to obey the production `alpha_dash` username policy rather than weakening registration validation.
- Updated Grounded Companion provider-switch coverage to exercise institution-scoped runtime routing explicitly.
- Rebuilt submission flow fixtures around the current tenant/onboarding/active-semester requirements, including a real submission version before submit and a real lecturer assignment for grading.
- Restored the root `.env.example` that was omitted from the prior packaged release.
- Removed a stale nested previous-release directory from the application package.

## 2026-09-02 — Remaining PHPUnit regression fixes
- Fixed AI cache fingerprints so per-request correlation IDs no longer prevent cache hits.
- Fixed tenant-context capture in RuleEngine feature-pack selection.
- Updated AI module/unit tests to use the centralized runtime configuration deterministically.
- Aligned Hybrid-mode regression expectations with the explicit `rule_engine_fallback` contract.
- Fixed Submission create-page `$resubmitsLeft` view data by calculating and passing it from the controller.

## 2026-09-03 — Progressive institution adoption
- Added search-first catalogue institution claims and backend duplicate prevention.
- Added department-first adoption and in-place transition to institution-managed departments.
- Added self-declared/pending/verified institutional affiliations for existing accounts.
- Added later institution verification without duplicate users or loss of academic/commerce history.
- Restricted institution-funded AI and sponsored features to verified members of officially active institutions.
- Added affiliation/access review UX, API endpoints, verified institution email domains, audit events and migration tooling.
- Enhanced student CSV import to reconcile and verify existing accounts instead of recreating them.

### 2026-09-10 — PHPUnit regression second-pass fix
- Restored the five-argument `AcademicAssistantService::ask()` endpoint contract used by the existing regression suite.
- Preserve valid provider answers when billing settlement fails and mark `billing_settlement_pending` for reconciliation.
- Removed the monetization Blade `$metricCards` render-path dependency and corrected regression preflight coverage.

### 2026-09-11 — Monetization Blade parser regression
- Repaired `resources/views/admin/monetization/index.blade.php` after Laravel compiled the multiline inline-array `@foreach` into invalid PHP ending in an unexpected `endif`.
- Restored the parser-safe inline metric-card loop while preserving the middleware-free `ViewErrorBag` fallback.
- Updated the four-critical-fixes preflight to validate the safe loop instead of an implementation-specific formatting assumption.
- Added `scripts/check-monetization-blade-regression.php` to prevent the same Blade compilation regression from returning.

### 2026-09-11 — Monetization view-data contract hardening
- Fixed `Undefined variable $suggestedTargetMinor` on the admin Monetization page.
- Moved commercial-account setup/display calculations from Blade-local `@php` state into `MonetizationController::index()` and passed them explicitly to the view.
- Removed all `@php` directives from the Monetization page and added a controller/view data-contract preflight.
- Preserved existing commercial-account currency as authoritative for both setup rendering and saving.

### 2026-09-12 — AI post-provider resilience compatibility
- Restored the required reservation release call when a valid provider/cached AI answer survives a settlement failure.
- Reconciliation records now retain whether the reservation was released, keeping duplicate-request protection and auditability intact.
- Added a runtime monetization preflight assertion for this regression.

# AcadFlow Developer Guide

**Canonical technical guide**  
**Source snapshot:** 2026-08-20  
**Framework:** Laravel ^12.40 / PHP ^8.2

This document is the primary orientation for any developer, maintainer, DevOps engineer, reviewer, or coding agent working on AcadFlow. It describes the current implementation rather than an aspirational architecture. Dated audit documents remain useful for history, but this guide and the current source code take precedence.

---

## 1. Product and architecture at a glance

AcadFlow is a multi-role academic platform that combines institutional academic workflows and an open/controlled knowledge ecosystem. Major domains include:

- universities, faculties, departments, semesters and courses;
- course membership, lecturer assignments and student enrolment;
- materials, assignments, submissions, grading and correction workflows;
- attendance;
- groups and discussions;
- Research Studio, SIWES, seminars and project defense;
- Knowledge Hub publishing, communities, learning paths, reading lists, events, challenges and certificates;
- centrally routed AI assistants, validators and grounded AI;
- notifications and push subscriptions;
- subscriptions, payment gateways, marketplace, wallet, payouts and refunds;
- documents/exports and analytics;
- feature release/maintenance control and tenant-aware settings.

High-level request path:

```text
Browser / Mobile API
        ↓
Laravel routes
        ↓
Authentication / verification / onboarding / 2FA
        ↓
Feature availability middleware
        ↓
Role / policy / tenant authorization
        ↓
Controller
        ↓
Domain service(s)
        ↓
Eloquent models / external adapters / queues
        ↓
Blade/JSON response
```

For AI-powered paths:

```text
Feature UI/controller
        ↓
Domain AI service
        ↓
AiManager
        ↓
AiRuntimeConfigService
        ↓
AiRouter
        ↓
AiProviderRegistry
        ↓
Primary → Fallback → Secondary fallback
        ↓
Normalized AiResponse
        ↓
Feature-specific grounding/validation/guardrails
```

---

## 2. Technology stack actually used

### Backend

- PHP `^8.2`
- Laravel `^12.40`
- Laravel Sanctum `^4.3`
- Laravel Tinker
- DomPDF through `barryvdh/laravel-dompdf`
- `smalot/pdfparser` for PDF text extraction where supported
- Eloquent ORM and Laravel migrations

### Frontend

- Blade is the primary rendered UI
- Tailwind CSS 3.x
- Vite 6.x
- Axios
- Vue 3 is available and used for selected components such as notification UI
- `resources/js/rich-editor.js` lazy-loads Quill 2.0.3 from CDN, with a contenteditable fallback

### Data and infrastructure

- MySQL is the recommended database target in `.env.example`
- Cache supports Laravel's normal stores; database is the template default, Redis is recommended for capable production environments
- Queue supports database and Redis; queue names used by AcadFlow include `default`, `ai`, `indexing`, and `analytics`
- Files use Laravel Flysystem; media is delivered through security-aware services and expiring tokens
- PWA assets and service workers are included

### Testing/developer tools

- PHPUnit 11.5
- Laravel Pint
- Laravel Pail
- Laravel Sail available as a dev dependency
- Project-specific PHP preflight scripts under `scripts/`

Do not rely on older planning documents that mention Laravel 11, PostgreSQL, Horizon, Livewire, Inertia, or other packages unless those packages are actually present in `composer.json`/`package.json`.

---

## 3. Repository map

```text
app/
├── Actions/                  focused application actions
├── Ai/                       provider contracts, adapters, router, requests/responses, rules
│   ├── Contracts/
│   ├── Features/
│   ├── Providers/
│   ├── Rules/
│   └── Support/
├── Console/Commands/         custom commands
├── Contracts/                non-AI domain contracts
├── Enums/                    roles, permissions, statuses, AI enums, etc.
├── Events/                   domain events
├── Http/
│   ├── Controllers/          web/API/admin/contextual AI controllers
│   └── Middleware/           auth/feature/AI/subscription/session guards
├── Jobs/                     queued background jobs
├── Listeners/                event listeners
├── Models/                   Eloquent models
├── Notifications/            notification classes/channels
├── Observers/                model observers (including searchable content)
├── Policies/                 model-level authorization
├── Providers/                Laravel service providers
├── Services/                 domain/business services
└── Support/                  infrastructure helpers and DB guards

bootstrap/app.php             route/middleware wiring
config/                       framework + AcadFlow configuration
routes/web.php                browser routes
routes/api.php                Sanctum REST API v1
routes/console.php            scheduler + custom Artisan commands
database/migrations/          schema evolution
database/seeders/             idempotent bootstrap/default data
resources/views/              Blade UI
resources/js/                 JS/Vue/rich editor
resources/css/                frontend CSS entry points
public/                       public/PWA assets
scripts/                      source preflight/regression scripts
tests/                        PHPUnit feature/unit/architecture tests
docs/                         product/developer/history documentation
```

### Important central services

- `SettingService` — runtime application settings and tenant overrides
- `FeatureAccessService` — single runtime source for module Enabled/Maintenance/Disabled state
- `AcademicContextService` — academic context such as active semester and tenant-aware scope
- `AiRuntimeConfigService` — authoritative runtime AI settings
- `AiManager` — single application-level AI execution gateway
- `AiRouter` — provider/model/fallback resolution
- `AiProviderRegistry` — provider construction, capabilities and health
- `DiscoverySearchService`, `SearchIndexService`, `LocalEmbeddingService` — search/index/retrieval
- `SafeFileDeliveryService`, `MediaSecurityService`, `SecureMediaDeliveryService` — secure media/file delivery
- `NotificationService` and `SocialNotificationService` — notifications
- `PaymentGatewayManager`, `PaymentService`, `CommerceService`, `WalletService` — payments/commerce

---

## 4. Roles, identity, tenancy and authorization

The canonical role enum is `App\Enums\UserRole`:

| Role key | Meaning |
|---|---|
| `super_admin` | Platform-wide administrator |
| `university_admin` | Administrator scoped primarily to one university |
| `department_admin` | Administrator scoped primarily to one department |
| `lecturer` | Academic staff assigned to courses/research responsibilities |
| `student` | Institutional learner |
| `member` | Platform member for knowledge/research/community participation |

`Group Leader` and `Supervisor` are **contextual responsibilities**, not separate `UserRole` enum values. A group leader is a user who leads a group; a supervisor is typically a lecturer associated with a research/project workflow.

### Authorization layers

Do not treat role middleware as the only security boundary. AcadFlow combines:

1. authentication;
2. email verification;
3. 2FA/session requirements;
4. onboarding completion;
5. feature/module availability;
6. subscription feature gates where applicable;
7. role middleware for coarse route groups;
8. Eloquent policies for resource authorization;
9. controller/service tenant scoping;
10. AI-specific feature middleware and context authorization.

Policies exist for courses, materials, submissions, assignments, attendance, research, Knowledge publications, faculties, universities, events, challenges, communities, groups and other key resources.

### Tenant rules

Most institutional data must remain within the user's university and, where appropriate, department/course assignment. Before adding queries, check existing policy/service/query scoping patterns. Never expose one university's knowledge, settings, prompts, AI credentials, student submissions or research context to another tenant.

Super Admin has broad platform access. University/Department Admin queries are still scoped by controller/service rules. Feature management permits administrators to preview disabled/maintenance features for testing; normal users receive a controlled unavailable response.

---

## 5. Feature & Module Management

`config/features.php` is the registry metadata. Runtime status is stored in the existing `feature_flags` / `feature_flag_overrides` architecture and resolved by `FeatureAccessService`.

Statuses:

- `enabled` — normal access;
- `maintenance` — normal users receive a maintenance response; admins can preview;
- `disabled` — hidden/unreleased for normal users; admins can preview.

Dependencies are resolved centrally. A child feature can become effectively unavailable if a required parent is unavailable.

Current registered feature keys:

| Feature key | Module |
|---|---|
| `dashboard` | Dashboards |
| `courses` | Courses & Enrolment |
| `course_materials` | Course Materials |
| `assignments` | Assignments |
| `submissions` | Submissions & Grading |
| `attendance` | Attendance |
| `course_discussions` | Course Discussions |
| `group_collaboration` | Groups & Collaboration |
| `research_studio` | Research Studio |
| `research_to_knowledge_hub` | Research → Knowledge Hub Publishing |
| `siwes_module` | SIWES Workspace |
| `seminar_module` | Seminar Workspace |
| `final_year_project` | Project Defense |
| `knowledge_hub` | Knowledge Hub |
| `knowledge_communities` | Knowledge Communities |
| `learning_paths` | Learning Paths |
| `reading_lists` | Reading Lists |
| `academic_events` | Academic Events & Calendar |
| `academic_challenges` | Academic Challenges |
| `course_certificates` | Certificates |
| `knowledge_ai_companion` | Knowledge AI Companion |
| `ai_assistant` | AI Assistant |
| `notifications` | Notifications |
| `push_notifications` | Push Notifications |
| `monetization_commerce` | Monetization & Commerce |
| `knowledge_hub_premium` | Premium Knowledge Resources |
| `commerce_marketplace` | Marketplace, Wallet & Payouts |
| `documents_exports` | Documents & Academic Exports |
| `advanced_analytics` | Reports & Advanced Analytics |
| `pwa_enabled` | Progressive Web App (PWA) |

When adding a module:

1. reuse an existing feature definition if it already covers the route/UI;
2. add metadata to `config/features.php` only if it is genuinely a new independently releasable capability;
3. map both web route names and API paths where necessary;
4. declare dependencies;
5. seed via the existing feature seeder without overwriting runtime admin state;
6. update the developer/user docs and changelog;
7. extend `FeatureModuleManagementTest` or relevant tests.

Do **not** add availability switches to System Settings or `.env`; feature release state belongs here.

---

## 6. Settings architecture

`SettingService` is the central application settings source. Settings are stored in the database and may have tenant/institution overrides where the existing architecture supports them.

General principles:

- runtime database settings should not be frozen into Laravel config cache;
- `.env` is for installation/bootstrap defaults and infrastructure secrets;
- AI-specific runtime settings belong under AI Settings, not general System Settings;
- Feature availability belongs to Feature & Module Management, not AI/System settings;
- changes must invalidate the relevant runtime cache immediately;
- do not create multiple keys for the same concept.

Current broad setting areas include general platform settings, academic behavior, notifications, subscription/billing, security, media, PWA and AI-specific configuration through the dedicated AI controller/settings flow.

`SettingsSeeder` must remain idempotent: use stable identities and create missing defaults without resetting administrator choices.

---

## 7. Central AI architecture

### 7.1 Operating modes

AcadFlow supports explicit AI modes through `AiMode`/runtime settings:

- Provider AI — configured provider chain; no hidden deterministic fallback
- Hybrid — rules/retrieval/LLM/validation; deterministic fallback only where explicitly allowed
- Rule-Based Only — no external provider call
- Disabled — AI entry points fail gracefully

### 7.2 Provider registry

Supported provider identifiers currently include:

- `rule_based`
- `openai`
- `claude`
- `gemini`
- `deepseek`
- `grok` (xAI)
- `azure_openai`
- `ollama`

Provider adapters implement the shared AI provider contract. Features must never instantiate a provider directly.

### 7.3 Routing priority

```text
Feature-specific provider/model override
        ↓ otherwise
Global AI default provider/model
        ↓
Fallback provider/model
        ↓
Secondary fallback provider/model
```

Database/admin runtime choices take precedence over `.env` bootstrap defaults. Queued AI jobs should generally resolve the active route when the job executes rather than serialize an old provider choice.

### 7.4 Active AI feature keys

| AI feature key |
|---|
| `submission_validator` |
| `plagiarism` |
| `writing_assistant` |
| `citation_assistant` |
| `study_assistant` |
| `lecturer_assistant` |
| `research_assistant` |
| `research_validator` |
| `assignment_assistant` |
| `siwes_assistant` |
| `project_assistant` |
| `material_assistant` |
| `discussion_assistant` |
| `knowledge_publication_validator` |
| `knowledge_moderation` |
| `knowledge_companion` |

### 7.5 User-facing assistant mapping

```text
Main AI Assistant
├── Student Ask/Explain       → study_assistant
├── Lecturer Ask/Explain      → lecturer_assistant
├── Improve Writing           → writing_assistant
└── Citation Review           → citation_assistant

Research Studio
├── Research Assistant        → research_assistant
└── Research Validator        → research_validator

Knowledge Hub
├── Grounded AI Companion     → knowledge_companion
├── Publication Validator     → knowledge_publication_validator
└── Knowledge Moderator       → knowledge_moderation

Assignments / Submissions
├── Assignment Assistant      → assignment_assistant
├── Submission Validator      → submission_validator
└── Integrity / Plagiarism    → plagiarism

SIWES                         → siwes_assistant
Projects                      → project_assistant
Course Materials              → material_assistant
Discussions                   → discussion_assistant
```

### 7.6 Grounded AI

The Grounded Companion is publication-scoped. Its expected pipeline is:

```text
input-quality validation
→ intent/query normalization
→ current-publication retrieval
→ relevance/evidence gate
→ central AI router
→ provider reasoning over supplied context
→ citation/source-support validation
→ answer or abstention
```

Obvious gibberish is rejected before provider use. Retrieved documents are treated as untrusted data, not system instructions. If evidence is insufficient, the companion should abstain rather than fill gaps from general model knowledge.

### 7.7 Contextual assistants

`ContextualAssistantService` builds bounded, authorized context for Research, Assignment, SIWES, Project, Material and Discussion assistants. Route middleware checks the specific AI feature before expensive retrieval/file extraction. Model policies remain the authorization boundary.

### 7.8 Adding a new AI capability

A real AI capability is not complete until all of the following exist:

1. actual product use case and UI/entry point;
2. permission/policy/tenant boundary;
3. context/retrieval source;
4. prompt baseline and safety instructions;
5. key in `config('ai.features')`;
6. capability metadata and compatible provider requirements;
7. central `AiManager`/`AiRouter` execution;
8. feature-management integration when user-facing;
9. failure handling and rate limiting;
10. usage/provider trace metadata;
11. tests for provider switching, disabled state and tenancy;
12. developer/user documentation and changelog entry.

Never activate an unused historical prompt name merely because it exists in old seed data.

---

## 8. Major domain modules

### Courses and enrolment

Core models include `Course`, `Enrollment`, `LecturerCourseAssignment`, `CourseInvitation`, `Semester`, `Department`, `Faculty`, and `University`. Students gain course access through enrolled status; lecturers through assignment. Administrative hierarchy can manage course structure within scope. Course invitation and lecturer self-assignment behavior is controlled through academic settings.

### Materials

`CourseMaterial` content is protected by policy and media/file delivery controls. Searchable material can be indexed by the shared discovery infrastructure. The Material / Study Assistant builds bounded context from the current authorized material and indexed course chunks.

### Assignments and submissions

Assignments are represented by `SubmissionTask` plus requirements, attachments and rubrics. `Submission` keeps versions, comments, extensions, grades and correction/approval state. Do not bypass existing `SubmissionPolicy`, task policy, deadline, attempt, late-submission and tenant rules.

AI analysis can be triggered through domain events/jobs; keep synchronous user-facing actions fast and push large analysis to queues.

### Attendance

`AttendanceSession` and `AttendanceRecord` support lecturer-managed sessions and student check-in. API endpoints expose active sessions, check-in, records and exports. Policy/course membership must be respected.

### Groups and discussions

Groups include membership, invitations/join requests, tasks, resources and collaborative workflows. Course discussions support replies, tags, reactions/subscriptions/reporting. Discussion AI may summarize only authorized visible content.

### Research Studio

Research is built around `ResearchProject`, workflow definitions/instances/stages, structured `ResearchSection`s, versions/authorship, meetings/reminders, tasks/action items, milestones, literature notes, references, datasets, archives, amendments, corrections and specialized links. SIWES, seminars and project-defense workflows are specialized research workspaces.

Publishing approved research to Knowledge Hub is a separately controllable feature (`research_to_knowledge_hub`).

### Knowledge Hub

Knowledge Hub includes `KnowledgePublication`, categories/tags, citations, bookmarks, follows, comments/reactions/shares, creator profiles, reputation, communities/posts/polls, learning paths, reading lists, events, challenges and certificates. Publication indexing connects the hub to search and Grounded AI.

### Notifications

Every authenticated role has a notification centre and personal preferences. Admin users can manage notification channels and announcements within permitted scope. Events/listeners generate notifications for submissions, corrections, grades/approvals, assignments, attendance, materials, discussions and system announcements.

### Billing, subscriptions and commerce

Institutional billing uses invoices/payments/subscriptions. Subscription plans can gate product features. Payment integration is abstracted through `GatewayInterface`/`PaymentGatewayManager`; the present concrete gateway implementation includes Paystack. Knowledge commerce adds orders, entitlements, wallet ledger/account, payout accounts, withdrawals, refunds and revenue allocation.

Do not mix payment webhook verification with normal browser authentication; webhook routes are deliberately separate.

### Documents and exports

Generated documents, templates, reports, transcripts/grade exports and research exports use the document/PDF services and secure download policies. Treat generated/exported data as tenant-sensitive.

---

## 9. Search, indexing and recommendations

Searchable models are observed by `SearchableContentObserver`, including `ContentDocument`, `CourseMaterial`, `KnowledgePublication`, and `ResearchProject`.

Relevant jobs:

- `IndexSearchableContent`
- `RemoveSearchableContent`

The indexing layer stores `SearchDocument` and `SearchChunk` records and uses `LocalEmbeddingService` for local vector-like relevance representation. `DiscoverySearchService` enforces authorization scope before returning results.

Do not run embeddings/re-indexing on every read request. Index on content lifecycle changes and retrieve bounded relevant chunks.

---

## 10. Queue and scheduler architecture

Queued jobs currently include:

- `IndexSearchableContent`
- `ModerateKnowledgePublication`
- `ProcessSubmissionAiAnalysis`
- `PublishScheduledKnowledgePublication`
- `RecalculateReputation`
- `RemoveSearchableContent`
- `ValidateResearchProject`

Queue names used by the application include:

```text
default
ai
indexing
analytics
```

Local worker:

```bash
php artisan queue:work --queue=default,ai,indexing,analytics --sleep=2 --tries=3
```

The scheduler in `routes/console.php` currently covers:

- scheduled Knowledge Hub publication (every minute);
- research meeting reminders (every minute);
- creator reputation recalculation (daily 02:30);
- academic event reminders (every minute);
- event/challenge lifecycle advancement (every minute);
- failed-job pruning (daily);
- AI provider health checks (hourly, when enabled).

Local scheduler:

```bash
php artisan schedule:work
```

Production scheduler should invoke `php artisan schedule:run` every minute from cron. See `OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md` for Redis/shared-hosting guidance.

---

## 11. API architecture

The REST API is under `/api/v1`.

Public/limited bootstrap endpoints include login, registration, password reset and public settings. Authenticated onboarding uses Sanctum. Fully protected application routes use:

```text
auth:sanctum
feature.access
api.account.ready
subscription.feature:allow_api_access
```

API groups include:

- user/profile/auth logout;
- courses/enrolment;
- submissions/review/grading;
- Research Studio;
- Knowledge Hub and Grounded Companion;
- attendance;
- billing/payments/subscriptions;
- document generation/download;
- reports/analytics;
- notifications;
- push subscriptions;
- offline sync.

Use controller validation, policies and service-layer scoping. Never assume a mobile client has enforced permissions correctly.

---

## 12. Media and file security

Media is represented through `MediaAsset` plus access logs/tokens. Central services include malware scanner adapters and safe/secure delivery services. Production can be configured to fail closed on malware scanning based on environment settings.

Rules for new upload flows:

- validate MIME/type/size server-side;
- use existing media/file services rather than direct public paths;
- do not trust original filenames;
- keep private academic data off public storage URLs;
- use expiring tokens or policy-controlled responses for sensitive downloads;
- avoid reading full large files into memory when streaming is possible;
- sanitize extracted text before using it in AI prompts.

---

## 13. Frontend conventions

Blade remains the primary UI layer. The global application layout shares:

- platform settings;
- feature-state snapshot;
- role navigation;
- notification status;
- PWA/service-worker behavior where enabled.

Use the existing CSS design tokens, `primary_color` setting and shared components/partials. Do not hardcode a second visual theme inside a new module.

Rich content should use the shared rich-editor integration and server-side `RichTextSanitizer`/`SafeHtmlService` rather than rendering arbitrary HTML.

For JS API calls:

- include CSRF on web POST/PUT/DELETE;
- use `Accept: application/json` for JSON flows;
- show loading/error/success states;
- avoid repeated requests when state can be cached or loaded once.

---

## 14. Database, migrations and MySQL safety

The source currently contains a large normalized schema across academic, research, knowledge, AI, commerce and notification domains. Migrations are append-only history: never edit an already-deployed migration merely to change production state unless you are fixing an unreleased build.

### Migration rules

- write production-safe forward migrations;
- preserve existing data;
- do not truncate live tables;
- add new nullable/defaulted columns before relying on them;
- use explicit short MySQL index names for composite indexes;
- run `php scripts/check-mysql-identifiers.php` before shipping;
- avoid destructive `down()` methods where rollback could destroy administrator/user data;
- use transactions where the database operation supports them and failure semantics are clear.

AcadFlow previously encountered MySQL identifier-length failures, so long autogenerated index names are specifically prohibited.

---

## 15. Seeder policy

Normal seeders must be idempotent and non-destructive.

Current seeders include:

- `DatabaseSeeder`
- `UniversitySeeder`
- `NigeriaAcademicCatalogSeeder`
- `AcadFlowEcosystemSeeder`
- `SettingsSeeder`
- `FeatureFlagSeeder`
- `SubscriptionSeeder`
- `CouponSeeder`

Required behavior:

- use stable identity fields with `firstOrCreate`/safe create-if-missing behavior;
- do not overwrite administrator customization;
- do not truncate/delete existing seeded data;
- do not reset API keys, providers, feature states, prices, prompts or passwords;
- distinguish an explicit synchronization command from normal seeding.

Run:

```bash
php scripts/check-idempotent-seeders.php
```

Remember: `migrate:fresh` drops tables before seeders execute and is destructive regardless of seeder safety.

---

## 16. Local development setup

### Required software

- PHP 8.2+
- Composer 2
- MySQL 8-compatible server
- Node.js/npm
- Git
- optional Redis for cache/queues

### Standard install

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate
php artisan db:seed   # only when desired
```

Windows helpers are included (`FIX_COMPOSER_WINDOWS.bat`, `install-windows.ps1`, `WINDOWS_INSTALL.md`).

### Run development services

```bash
composer dev
```

And in another terminal when scheduled workflows matter:

```bash
php artisan schedule:work
```

If you prefer separate terminals:

```bash
php artisan serve
php artisan queue:work --queue=default,ai,indexing,analytics --sleep=2 --tries=3
php artisan schedule:work
npm run dev
```

### Development data

Demo academic seeding is controlled by `ACADEMIC_SEED_DEMO`. Keep it disabled in production.

---

## 17. Production deployment

### Core environment rules

```env
APP_ENV=production
APP_DEBUG=false
```

Use strong database credentials, configure mail, filesystem, session/cache and payment secrets, and restrict `.env` permissions.

### Recommended production steps

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process.

### Redis

Where production hosting supports Redis, prefer:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=360
```

Use a worker timeout shorter than `retry_after` on Linux. On shared hosting without Supervisor, use a once-per-minute cron worker with `--stop-when-empty`. Full commands and failure modes are in `OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md`.

### Scheduler

Production cron:

```cron
* * * * * cd /path/to/acadflow && /path/to/php artisan schedule:run >> /dev/null 2>&1
```

---

## 18. Testing and quality gates

Run project preflights before release:

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

When dependencies are installed:

```bash
php artisan test
```

Frontend:

```bash
npm run build
```

PHP syntax for first-party PHP can also be checked with:

```bash
find app bootstrap config database routes scripts tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

On Windows, use a PowerShell equivalent or rely on the included scripts/tests.

Important test areas already present include authentication, authorization, course access, submissions, tenancy, settings overrides, Research Studio, Knowledge publishing, AI routing/cache/grounding, feature management, seeder idempotency and MySQL identifier safety.

---

## 19. Common debugging workflow

### Configuration appears not to change

```bash
php artisan optimize:clear
```

Then verify whether the value should come from database settings, feature management, AI Settings or `.env`. Do not fix a runtime database-setting problem by hardcoding `config()`.

### Queue job not processing

```bash
php artisan queue:failed
php artisan queue:work --queue=default,ai,indexing,analytics -v
```

Confirm the job's intended queue and active connection.

### Scheduled task not running

```bash
php artisan schedule:list
php artisan schedule:run
```

Check cache-lock configuration and production cron.

### AI provider mismatch

Use Admin → AI Settings / diagnostics and inspect AI usage metadata. Verify:

- AI mode;
- feature-specific provider override;
- global default provider/model;
- provider enabled/credential state;
- fallback chain;
- route feature key;
- settings cache invalidation.

### Feature unavailable

Check Admin → Settings → Feature & Module Management, dependencies and tenant override. Admins may preview unavailable features; normal users cannot.

### MySQL migration error

Run:

```bash
php scripts/check-mysql-identifiers.php
php artisan migrate:status
```

Do not rename a production index/migration blindly; add a corrective migration.

---

## 20. Coding and architectural conventions

- Keep controllers thin; put business logic in services/actions.
- Reuse existing services before creating new managers.
- Use policies/authorization before loading sensitive context.
- Apply tenant scoping at query/service level, not only in UI.
- Do not duplicate settings or feature switches.
- Do not call AI provider SDKs from domain controllers/services; use `AiManager`.
- Do not place application-specific business logic inside provider adapters.
- Keep queue jobs serializable and avoid embedding stale provider configuration.
- Keep prompts and retrieved data clearly separated; treat user/doc content as untrusted.
- Use typed enums/status objects where the codebase already has them.
- Avoid N+1 queries; eager load intentionally.
- Use existing secure media services for files.
- Make migrations/data changes production-safe.
- Keep seeders idempotent.
- Preserve public API response consistency and status codes.
- Update documentation and changelog with every functional change.

---

## 21. How to add a normal feature

1. Confirm the capability does not already exist.
2. Identify owning domain/module and required roles/policies.
3. Design schema migration with production safety.
4. Add model/service/action/controller/request validation as appropriate.
5. Add web/API route using existing middleware conventions.
6. Add policy/tenant scope.
7. Add UI using shared layout/components/settings.
8. Decide whether it requires an independent Feature Management key.
9. Add notifications/queue work only if needed.
10. Add tests.
11. Update `USER_GUIDE.md` and relevant developer sections.
12. Add a `CHANGELOG.md` entry under Unreleased.
13. Run all relevant preflights and `scripts/check-documentation.php`.

---

## 22. How to modify existing features safely

Before changing behavior, search:

- route name and controller;
- service(s);
- model/policy;
- setting keys;
- feature registry entries;
- jobs/listeners/events;
- API counterpart;
- tests;
- dated implementation note.

AcadFlow has intentionally centralized several cross-cutting concerns. Bypassing them creates the exact inconsistencies that previous audits fixed. In particular, do not bypass `SettingService`, `FeatureAccessService`, `AiManager`/`AiRouter`, policies, secure media delivery, or the existing queue architecture.

---

## 23. Documentation and changelog are part of the code

Every new or changed user-visible behavior must update documentation in the same change. See `DOCUMENTATION_MAINTENANCE.md`.

Minimum rule:

```text
Code change
+ tests
+ relevant developer documentation
+ relevant user documentation
+ CHANGELOG.md
= complete change
```

The `scripts/check-documentation.php` preflight verifies that all registered roles, central feature keys and AI feature keys are represented in the canonical docs. This makes future additions visible to maintainers.

---

## 24. Reference documents

Use these when deeper detail is required:

- `docs/ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md`
- `docs/ACADFLOW_2026_08_15_SPECIALIZED_AI_ASSISTANTS.md`
- `docs/ACADFLOW_2026_08_14_GROUNDED_AI_INTELLIGENCE_UPGRADE.md`
- `docs/ACADFLOW_2026_08_14_FEATURE_MODULE_MANAGEMENT.md`
- `docs/SEEDER_IDEMPOTENCY_AND_DATA_PRESERVATION.md`
- `docs/ENVIRONMENT_CONFIGURATION.md`
- `docs/NIGERIA_ACADEMIC_CATALOG.md`
- `docs/MYSQL_IDENTIFIER_SAFETY.md`
- `docs/SECURITY.md`
- `docs/API.md`

When a reference document describes an earlier implementation, use the current code and this canonical guide as the final authority.

## 28. AI provider transport, diagnostics and health checks

All external AI providers must use `app/Ai/Providers/ExternalProvider.php`. The same transport is used by **AI Settings → Test Connection** and real `AiManager` execution. Do not implement a separate test-only HTTP call because it can report a provider as healthy while production traffic follows different TLS, proxy, endpoint or timeout behavior.

The shared transport provides request/connect timeouts, TLS verification, optional CA bundle, proxy support, optional IPv4 resolution, safe endpoint logging, request IDs and normalized error classes. Provider-specific secrets and prompt payloads must not be written to logs. Operational diagnostics are written to `storage/logs/ai-provider.log` using the `ai_provider` logging channel. Common normalized errors include `AI_TLS_ERROR`, `AI_DNS_ERROR`, `AI_CONNECTION_REFUSED`, `AI_PROVIDER_TIMEOUT`, `AI_NETWORK_ERROR`, `AI_PROVIDER_AUTH_FAILED`, `AI_PROVIDER_RATE_LIMITED`, `AI_MODEL_NOT_FOUND` and `AI_INVALID_CONFIGURATION`.

Relevant infrastructure variables are:

```env
AI_HTTP_CONNECT_TIMEOUT=10
AI_HTTP_CA_BUNDLE=
AI_HTTP_PROXY=
AI_HTTP_FORCE_IPV4=false
AI_HTTP_VERIFY_TLS=true
AI_PROVIDER_LOG_LEVEL=info
AI_PROVIDER_LOG_DAYS=14
```

On Windows, a certificate-chain problem must be solved with the PHP/cURL CA configuration or `AI_HTTP_CA_BUNDLE`; do not disable TLS verification as a normal fix.

The scheduled command `acadflow:ai-health` refreshes cached provider health but returns success by default even when an upstream provider is unhealthy, so provider downtime is not misreported as scheduler failure. Use `php artisan acadflow:ai-health --strict` when a non-zero exit code is deliberately required by CI/operations.

Provider adapters remain protocol-only:

- OpenAI: Chat Completions-compatible endpoint and Bearer API key.
- Claude: Messages API with `x-api-key` and `anthropic-version`. Current Claude 5 models must not be sent unsupported non-default temperature settings.
- Gemini: `models/*:generateContent` with `x-goog-api-key`. Never put the API key into the URL/log.
- DeepSeek: `/chat/completions` with Bearer authentication.
- Azure OpenAI: current `/openai/v1/chat/completions` plus backward-compatible deployment/api-version route.
- Ollama: `/api/chat`; local Ollama may be keyless, cloud endpoints can use a Bearer API key.

Administrators may paste either a provider API root or, where supported, a complete endpoint. Adapters normalize full endpoint suffixes to avoid duplicate paths such as `/chat/completions/chat/completions`. Runtime database settings still take priority over bootstrap `.env` defaults.

## 29. Course material and Knowledge creator access invariants

`CourseMaterialPolicy` is the authoritative permission layer for opening/downloading a material. A material uploader and authorized course lecturer/admin must be able to inspect managed material even when the material is hidden or requires student enrollment. Student visibility remains constrained by publication state, course access and enrollment. Controllers must call `$this->authorize('view', $material)` instead of re-implementing access rules.

Knowledge Hub publication **view/manage** permission is intentionally different from **edit/submit** permission. A creator can always open their own publication record in `knowledge.manage.show`, including pending-review or published records. Editing and resubmission remain limited to workflow states such as draft, changes requested and rejected. Never point a generic “Open” action directly at the edit route because read-only workflow states would incorrectly become 403 responses.

## 30. Current academic workspace UI contract

The current interface uses modern, responsive workspace layouts for student courses, course detail, materials, assignments, discussions, attendance, communities, groups, learning paths, reading lists, events, challenges, leaderboard and creator profiles. Preserve authorization, form routes, filters, pagination, status controls, AI contextual tools and server-side business rules when redesigning these pages. The lecturer `courses.lecturer` My Courses page is intentionally separate from the student `courses.index` experience.



## 31. AI provider request-body and Gemini network/model remediation (2026-08-18)

All external providers continue to route through `App\Ai\Providers\ExternalProvider`. Provider POST payloads are explicitly JSON-encoded and sent with Laravel HTTP `withBody(..., 'application/json')`. This removes ambiguity in request serialization and guarantees required top-level fields such as OpenAI's `model` parameter are present in the upstream body.

The dedicated `storage/logs/ai-provider.log` channel may record only safe payload-shape metadata: resolved provider/model, top-level payload keys, JSON byte length, endpoint, request ID, latency and normalized errors. Never log prompts, retrieved academic context, API keys, Authorization headers or other secrets.

When automatic address selection fails with a retryable cURL/DNS/connect error, the transport adds at most one IPv4-only fallback attempt. This does not change the selected provider/model or central routing decision. Installations may still force IPv4 for all provider traffic with `AI_HTTP_FORCE_IPV4=true`.

Google shut down Gemini 1.5 models in 2025 and Gemini 2.0 Flash models in 2026. `config/ai.php` therefore contains a narrow `retired_model_replacements` map. `AiRuntimeConfigService` normalizes only exact known retired IDs, and migration `2026_08_18_220000_replace_retired_gemini_models.php` updates matching persisted global/tenant model values without overwriting unrelated/custom administrator selections.


## 42. Grok (xAI) provider and interaction-performance architecture — 2026-08-20

Grok is integrated as a normal AcadFlow external provider, not as a parallel AI subsystem.

Runtime path:

```text
AI feature
  -> AiManager
  -> AiRuntimeConfigService
  -> AiRouter
  -> AiProviderRegistry
  -> GrokProvider
  -> ExternalProvider shared HTTP transport
  -> https://api.x.ai/v1/chat/completions
```

The provider key is `grok`; the user-facing label is **Grok (xAI)**. Bootstrap secrets and endpoint defaults use `XAI_*` environment variables. Runtime routing/model choices remain database/admin driven like every other provider. Provider credentials can be saved through the existing encrypted AI Settings flow and are never returned to the browser.

Do not instantiate `GrokProvider` directly from features/controllers. New Grok calls must continue through `AiManager` and the central router. Test Connection and real requests share `ExternalProvider`, so TLS/proxy/timeouts/logging/error classification remain identical.

### Fast AI failover

`ai_fast_failover` defaults to enabled. When enabled, a retryable network/429/5xx failure returns control to `AiManager` immediately so the configured fallback provider can be attempted rather than consuming another complete provider timeout on the same upstream service. The slower same-provider retry/automatic IPv4 retry path remains available by disabling Fast Interactive Failover or explicitly setting `AI_HTTP_FORCE_IPV4=true` where the deployment requires IPv4.

### Browser/navigation performance

`resources/js/performance.js` provides conservative same-origin document prefetching, immediate navigation progress feedback and duplicate native write-form protection. It never prevents a normal anchor navigation and it is not a client-side router.

Vue is dynamically imported only when a page has a `#app` mount point. Most Blade pages therefore no longer pay the Vue/component bundle cost.

The PWA service worker uses navigation preload and no longer stores authenticated HTML navigation responses or private API reads in Cache Storage. Only static assets are cached. This prevents stale account pages and removes service-worker cache work from normal online navigation.

High-volume server-rendered pages should prefer counts/selects over hydrating unused relationships. Current examples include attendance session cards using `withCount('records')` and the course workspace no longer loading every enrolled user when only the enrollment count is rendered.

For Redis queues, `REDIS_QUEUE_BLOCK_FOR` defaults to `2` seconds so workers can block efficiently while waking promptly when background jobs arrive.

Run the performance/Grok regression check after relevant changes:

```bash
php scripts/check-grok-performance.php
# or
composer performance-check
```


## 43. Central password policy and authentication rate-limit UX — 2026-08-20

Password/security behavior must continue to use the existing **Security Settings** group and `SettingService`; do not introduce a second password-policy service, a second security settings page, or controller-local copies of the same limits.

### Password policy source of truth

The canonical runtime APIs are:

```php
SettingService::getPasswordPolicy(?int $universityId = null);
SettingService::getPasswordRules(?int $universityId = null);
```

The current configurable rules are:

- `password_min_length`
- `password_require_uppercase`
- `password_require_numbers`
- `password_require_special`

The current backend does **not** require a lowercase letter as a separate configurable condition, so the registration/reset UI must not claim that it does. The allowed special-character set used by the existing policy is `@$!%*#?&`. If the policy is expanded in the future, update `SettingService`, Admin Security Settings, server validation, the reusable password-policy UI, API policy payloads, tests and documentation together.

Registration and password reset include `resources/views/auth/partials/password-policy.blade.php`. `resources/js/password-policy.js` reads the server-rendered policy data and updates each completed/not-completed condition as the user types. It is dynamically loaded only on pages containing `[data-password-policy]`. Do not hardcode a second copy of the rules in JavaScript.

The API/public integration point for clients that render their own registration UI is:

```text
GET /api/v1/settings/public
→ password_policy
```

Authenticated account bootstrap responses also expose the effective scoped policy where useful. A separate mobile application is not contained in this Laravel repository; mobile clients should consume the API policy rather than embedding independent password requirements.

### Existing authentication throttles

The named Laravel rate limiters remain registered in `AppServiceProvider` and now resolve values through `SettingService::getSecurityRateLimits()`:

| Limiter | Security setting | Scope/key behavior |
|---|---|---|
| `login` | `login_requests_per_minute` | email + IP |
| `register` | `registration_requests_per_hour` | IP |
| `password-reset` | `password_reset_requests_per_minute` | email + IP |
| `verification` | `verification_requests_per_minute` | authenticated user or IP |
| `two-factor` | `two_factor_attempts_per_minute` | authenticated user or IP |

Do not confuse the `login` request throttle with the existing failed-credential lockout. The latter continues to use `max_login_attempts` and `lockout_duration_minutes`, and is intentionally separate because it counts failed credentials rather than every HTTP request.

Public unauthenticated throttle settings that cannot reliably resolve a university before authentication are platform/global controls. Tenant-resolvable verification/two-factor and login lockout behavior may use the current university scope where the existing tenancy model permits it.

There is no separate OTP-resend subsystem in this codebase. Two-factor challenge codes are TOTP/recovery-code based. Do not add fictitious OTP resend settings merely to mirror another product; use the existing email-verification and two-factor limiters unless a real resendable OTP workflow is implemented later.

### Friendly 429 contract

`bootstrap/app.php` centrally renders `ThrottleRequestsException`. The retry duration comes from the real `Retry-After` response header via `App\Support\Security\RetryAfter`; do not hardcode “wait 1 minute” in a controller or view.

API/JSON clients receive HTTP 429 in the existing simple JSON style:

```json
{
  "message": "Too many attempts. Please try again in 2 minutes.",
  "code": "TOO_MANY_REQUESTS",
  "retry_after": 120
}
```

Web requests are redirected back safely with the same friendly message and `retry_after` session value. Passwords, confirmation values, 2FA codes and tokens are excluded from flashed input. `resources/js/rate-limit-feedback.js` renders the live countdown only when such a value exists.

When the wait is below 60 seconds, show seconds; at 60 seconds or above, display the remaining time in minutes using the central formatter. Keep the HTTP status and `Retry-After` header intact so browsers/API clients can still behave correctly.

### Admin setting safety

New authentication-rate defaults are added by migration `2026_08_20_150000_add_security_rate_limit_settings.php` using `insertOrIgnore`. It must not overwrite existing administrator choices, and rollback intentionally does not delete potentially customized values. `SettingsSeeder` remains idempotent.

Run the scoped regression check after changing password/security/rate-limit behavior:

```bash
php scripts/check-security-policy.php
# or
composer security-policy-check
```

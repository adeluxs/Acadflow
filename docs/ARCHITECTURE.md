# AcadFlow Architecture

**Current source snapshot:** 2026-08-15  
**Canonical deep guide:** `DEVELOPER_GUIDE.md`

AcadFlow is a Laravel modular monolith. Domain boundaries are expressed through models, controllers, services, policies, jobs/events/listeners and central cross-cutting services rather than separate deployable microservices.

## Runtime layers

```text
Web / API client
    ↓
Routes (`routes/web.php`, `routes/api.php`)
    ↓
Middleware
    - auth / Sanctum
    - verified email
    - two-factor/session/onboarding readiness
    - FeatureAccessService
    - subscription feature gate
    - role / AI feature middleware
    ↓
Policies + tenant scope
    ↓
Controller
    ↓
Domain service/action
    ↓
Models / queues / external adapters
    ↓
Blade or JSON
```

## Cross-cutting sources of truth

| Concern | Source of truth |
|---|---|
| Runtime settings | `SettingService` |
| Module release/maintenance state | `FeatureAccessService` + `config/features.php` metadata |
| AI runtime configuration | `AiRuntimeConfigService` |
| AI provider/model routing | `AiRouter` / `AiProviderRegistry` / `AiManager` |
| Authorization | Laravel policies + role middleware + tenant-scoped services/queries |
| Search/indexing | `SearchIndexService`, `DiscoverySearchService`, observers/jobs |
| Secure media | media/security/delivery services |

## Domain areas

- Identity, onboarding and security
- Institution/academic catalogue
- Courses and enrolment
- Materials
- Assignments/submissions/grading
- Attendance
- Discussions/groups
- Research Studio and specialized research workflows
- Knowledge Hub ecosystem
- AI assistance/validation
- Notifications
- Billing/subscriptions
- Commerce/wallet/payouts
- Documents/exports/analytics
- PWA/mobile API support

## Async architecture

Events trigger notifications and AI processing. Jobs handle indexing, moderation, research validation, scheduled publication and reputation recalculation. The scheduler coordinates reminders/lifecycle work and AI provider health checks.

## Tenancy

Institutional records are scoped by university and often department/course membership. Tenant safety is enforced in policies and service/query logic; UI hiding is never the only security control.

## Feature management

30 module groups have Enabled/Maintenance/Disabled state. Dependencies are centrally resolved. Admin users can preview restricted features for maintenance/testing; normal users cannot.

## AI architecture

```text
AI Settings
   ↓
AiRuntimeConfigService
   ↓
AiRouter
   ↓
AiProviderRegistry
   ↓
Primary → fallback → secondary fallback
   ↓
AiResponse
```

Rule-based processing is a distinct operating mode/fallback mechanism, not a fake external provider result.

## Extension rule

Before adding a new manager/table/setting, verify that an existing central service does not already own that responsibility. See `DEVELOPER_GUIDE.md` and `CONTRIBUTING.md`.

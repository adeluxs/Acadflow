# AcadFlow Centralized Feature / Module / View Management

**Implementation date:** 14 August 2026  
**Baseline:** `AcadFlow-enhanced-2026-08-14-runtime-community-knowledge-fixed`  
**Objective:** one feature = one authoritative availability state = one centralized access mechanism.

## Executive summary

AcadFlow now has a centralized runtime availability layer for supported user-facing modules. It reuses the existing `feature_flags` and `feature_flag_overrides` schema, the existing Settings architecture, the existing role system, Laravel middleware, the existing cache layer, and `AuditLog`. No second settings table, permission system, or audit system was introduced.

A Super Admin can manage supported features from **Admin → Settings → Feature & Module Management** and place each feature into one of three states:

- **Enabled** — normal authorized users and administrators can use it.
- **Maintenance** — normal users are blocked with a professional maintenance response; administrators retain access for testing/configuration.
- **Disabled / Unreleased** — hidden from normal-user navigation and blocked at direct web/API access; administrators retain preview/test access.

The backend is authoritative. Web menus and dashboards reflect the state, but direct URLs and protected APIs are also enforced by middleware so a user cannot bypass a disabled feature by manually entering a URL or calling an endpoint.

---

## 1. Existing functionality discovered during the audit

The audit found that AcadFlow already contained important pieces that should be reused rather than rebuilt:

1. **`feature_flags` table** with `name`, `is_enabled`, `description`, `enabled_at`, `enabled_by` and timestamps.
2. **`feature_flags.settings` JSON column** added by the existing cross-cutting ecosystem migration.
3. **`feature_flag_overrides` table** supporting institution-specific overrides.
4. **`FeatureFlag` model** with binary feature helper behavior.
5. **`FeatureFlagMiddleware`** for existing feature middleware checks.
6. **Settings / `SettingService`** with caching, aliases, grouping and centralized settings access.
7. **Existing global maintenance middleware** (`CheckMaintenanceMode`) for whole-platform maintenance.
8. **Existing role system** including `super_admin`, `university_admin`, `department_admin` and `User::isAdmin()`.
9. **Existing permission/policy/middleware architecture** used by application routes.
10. **Existing `AuditLog`** implementation.
11. **Existing subscription feature entitlements** through `User::hasFeature()` and subscription middleware.
12. Existing notification channel settings, AI provider/capability settings, PWA settings, Knowledge Hub premium settings and related specialist configuration.

The existing whole-platform maintenance system remains separate because it serves a different purpose: taking the entire application into maintenance, whereas the new feature system controls individual runtime modules.

---

## 2. Existing functionality reused

The implementation deliberately builds on the existing structure:

- `feature_flags` remains the authoritative global runtime feature-state storage.
- `feature_flag_overrides` remains available for scoped overrides.
- `FeatureFlagMiddleware` remains a supported named middleware entry point, but now delegates to the centralized service.
- `SettingService` remains the settings access layer and delegates runtime availability to the feature service where necessary.
- Existing Laravel authentication, roles and route middleware remain authoritative.
- Existing `AuditLog::log(...)` records feature state changes.
- Existing cache infrastructure is used; no additional cache system was introduced.
- Existing specialist settings remain in their specialist pages where they configure *how* an enabled feature operates.

---

## 3. Duplicate settings / controls discovered

The audit identified several cases where runtime availability could be confused with other configuration:

### PWA

Before consolidation, PWA availability existed in both:

- `feature_flags.pwa_enabled`
- Settings key `pwa_enabled`

These could disagree.

### Knowledge Hub Premium

Premium Knowledge Hub availability existed in both:

- feature flag `knowledge_hub_premium`
- setting `knowledge_hub_premium_enabled`

This was also mixed conceptually with subscription entitlement.

### Generic feature settings

`SettingService` supported generic keys such as `feature_{feature}` while a dedicated `feature_flags` table already existed.

### AI controls

The AI settings area contained capability switches that could look like runtime release switches. These are not necessarily duplicates: provider/model/capability configuration answers **how AI works**, while Feature Management answers **whether AI Assistant is available**.

### Notification controls

Notification channel toggles configure in-app/email/push delivery. They are not the same as the module's release state. The UI now explicitly distinguishes these responsibilities.

---

## 4. Duplicate functionality consolidated

The final responsibility split is:

- **Runtime availability:** `FeatureAccessService` + `feature_flags` / `feature_flag_overrides`.
- **Specialist configuration:** existing Settings / AI / Notifications / integration configuration.
- **Subscription entitlement:** existing subscription/plan architecture.
- **Whole-system maintenance:** existing `CheckMaintenanceMode`.

Specific consolidation:

- `SettingService::isFeatureEnabled()` and `setFeatureEnabled()` now delegate to `FeatureAccessService`.
- `SettingService::isPwaEnabled()` and PWA helper behavior use the central runtime feature state.
- Legacy `pwa_enabled` and `knowledge_hub_premium_enabled` settings are compatibility aliases into the authoritative runtime feature state rather than independent switches.
- Fresh-install seeders no longer seed duplicate independent PWA/premium runtime settings.
- Re-running feature seeders no longer overwrites administrator-selected production feature states.
- Existing `FeatureFlagMiddleware` no longer contains an independent binary decision implementation.

No production setting rows are silently deleted.

---

## 5. Settings moved / reorganized

The old generic Feature Flags switch grid was removed from the general Settings page.

Runtime availability is now managed at:

**Admin → Settings → Feature & Module Management**

AI provider/model/capability configuration remains under AI Settings. Notification channel delivery remains under Notification Management. Those pages now clarify that module release state is controlled centrally.

The route inconsistency around institution Settings was also corrected: general Settings routes now match the existing controller/sidebar behavior for University Admin and Super Admin, while Feature Management remains Super-Admin-only.

---

## 6. Database changes

No new feature-management table was created because the existing schema already supports the requirement.

Runtime state uses:

- `feature_flags.is_enabled` for backward compatibility.
- `feature_flags.settings.access_status` for `enabled`, `maintenance` or `disabled`.
- `feature_flags.settings.maintenance_message` for optional user-facing maintenance copy.
- `feature_flags.settings.admin_note` for internal administrator notes.
- existing `feature_flag_overrides` for scoped override capability.

This avoids duplicating feature state across additional tables.

---

## 7. New migration

Created:

`database/migrations/2026_08_14_070000_normalize_feature_module_management.php`

Behavior:

- Idempotently registers missing feature rows.
- Preserves existing `is_enabled` values when deriving an initial state for an existing installation.
- Existing live features remain enabled unless current configuration says otherwise.
- New Premium Knowledge Resources defaults to disabled/unreleased.
- Preserves legacy settings rows instead of deleting them.
- Uses safe, MySQL-compatible identifier lengths.
- `down()` is intentionally non-destructive so rolling back code does not silently delete administrator-selected production release states.

---

## 8. Middleware / services / helpers changed

### New central service

`app/Services/FeatureAccessService.php`

Responsibilities:

- Registry access.
- Status normalization.
- Global/scoped state resolution.
- Dependency-aware effective status.
- Admin preview/bypass rules.
- Navigation visibility.
- Custom maintenance messages.
- Standard HTML/API unavailable responses.
- Backend-authoritative client snapshot.
- Cached feature state loading.
- State persistence and cache invalidation.

### New request middleware

`app/Http/Middleware/FeatureAccessMiddleware.php`

It resolves the current route/path to its registered feature and enforces the effective state.

### Existing middleware standardized

`FeatureFlagMiddleware` now delegates to the same central service.

### Middleware ordering

- The feature access middleware is appended to the **web middleware group**, after session middleware is available, allowing authenticated administrators to retain preview access.
- Protected API routes run `auth:sanctum` before `feature.access`, allowing the service to identify the authenticated user before enforcing state.

---

## 9. Features / modules centrally controlled

The current registry contains **30 supported runtime features/modules** derived from the current source code:

1. `dashboard` — Dashboards
2. `courses` — Courses & Enrolment
3. `course_materials` — Course Materials
4. `assignments` — Assignments
5. `submissions` — Submissions & Grading
6. `attendance` — Attendance
7. `course_discussions` — Course Discussions
8. `group_collaboration` — Groups & Collaboration
9. `research_studio` — Research Studio
10. `research_to_knowledge_hub` — Research → Knowledge Hub Publishing
11. `siwes_module` — SIWES Workspace
12. `seminar_module` — Seminar Workspace
13. `final_year_project` — Project Defense
14. `knowledge_hub` — Knowledge Hub
15. `knowledge_communities` — Knowledge Communities
16. `learning_paths` — Learning Paths
17. `reading_lists` — Reading Lists
18. `academic_events` — Academic Events & Calendar
19. `academic_challenges` — Academic Challenges
20. `course_certificates` — Certificates
21. `knowledge_ai_companion` — Knowledge AI Companion
22. `ai_assistant` — AI Assistant
23. `notifications` — Notifications
24. `push_notifications` — Push Notifications
25. `billing_subscriptions` — Billing & Subscriptions
26. `knowledge_hub_premium` — Premium Knowledge Resources
27. `commerce_marketplace` — Marketplace, Wallet & Payouts
28. `documents_exports` — Documents & Academic Exports
29. `advanced_analytics` — Reports & Advanced Analytics
30. `pwa_enabled` — Progressive Web App (PWA)

The registry uses stable machine-readable identifiers. Nested routes are matched by route-name/path patterns so individual CRUD actions do not need their own duplicate setting.

### Intentionally non-disableable recovery/core infrastructure

The following are deliberately *not* registered as disableable user features:

- Login / admin authentication
- Authorization / permissions
- Password recovery / account security
- Onboarding required for account recovery/readiness
- Admin Settings
- Feature & Module Management itself
- Required bootstrap/recovery infrastructure

This prevents an administrator from locking the platform out of the controls required to recover it.

---

## 10. Admin authorization

Feature state mutation routes are restricted to **Super Admin**.

This is backend authorization, not merely hidden UI. A normal user, lecturer, member, student, Department Admin or University Admin cannot manually call the feature-state update endpoint to change global release state.

All administrator roles recognized by the existing `User::isAdmin()` logic retain runtime preview access to a feature that is in Maintenance or Disabled state. This lets authorized administrators test a hidden module without releasing it to normal users.

---

## 11. Mobile / API integration

The backend remains authoritative.

Protected APIs are enforced by `feature.access`, so manually calling a disabled endpoint still fails even if a client ignores UI state.

Authenticated/bootstrap responses expose one compact feature map rather than requiring one API call per screen. The following Auth API responses now include the feature snapshot where applicable:

- Login
- Registration
- Account status
- `me`
- Profile update

Example shape:

```json
{
  "features": {
    "knowledge_hub": "enabled",
    "ai_assistant": "maintenance",
    "knowledge_communities": "disabled"
  }
}
```

For an unavailable protected API feature, the central response is predictable:

```json
{
  "success": false,
  "status_code": "FEATURE_MAINTENANCE",
  "message": "This feature is temporarily unavailable while maintenance is being performed. Please try again later.",
  "feature": "ai_assistant",
  "feature_status": "maintenance"
}
```

Disabled/unreleased uses `FEATURE_DISABLED`.

---

## 12. Caching strategy

The feature service does not query dozens of feature rows on every helper call.

It:

1. Loads a feature snapshot for a scope.
2. Caches it with Laravel Cache.
3. Reuses a request-local snapshot.
4. Caches dependency-resolved effective status within the request.
5. Invalidates feature cache immediately after `FeatureFlag` or `FeatureFlagOverride` changes.
6. Uses a cache-generation/version key to make state changes effective without waiting for the normal TTL.

This avoids N+1 feature-state queries from sidebar items, dashboard cards and route checks.

---

## 13. Maintenance-mode behavior

For a normal user:

- Feature remains visible in navigation with a **Maintenance** badge where navigation exists.
- Direct URL is blocked.
- Protected API is blocked.
- A professional maintenance page/message is shown.
- A custom administrator-defined maintenance message can replace the default.

For administrators:

- Feature remains visible.
- Feature remains accessible for testing/configuration.
- A restricted-feature preview banner makes the current production state clear.

---

## 14. Disabled / unreleased behavior

For a normal user:

- Navigation entry is hidden.
- Applicable dashboard cards/actions are hidden.
- Direct URL is blocked.
- Protected API is blocked.
- Known disabled state produces a controlled response rather than a 500/blank page.

For administrators:

- Disabled feature remains visible with a status badge.
- It can be opened for preview/testing.

---

## 15. Files modified

Compared with the supplied baseline, the implementation modifies application files including:

- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/TestController.php`
- `app/Http/Middleware/FeatureFlagMiddleware.php`
- `app/Jobs/ProcessSubmissionAiAnalysis.php`
- `app/Jobs/PublishScheduledKnowledgePublication.php`
- `app/Models/FeatureFlag.php`
- `app/Models/FeatureFlagOverride.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/KnowledgeDiscoveryService.php`
- `app/Services/NotificationService.php`
- `app/Services/SettingService.php`
- `bootstrap/app.php`
- `database/seeders/AcadFlowEcosystemSeeder.php`
- `database/seeders/FeatureFlagSeeder.php`
- `database/seeders/SettingsSeeder.php`
- `resources/views/admin/notifications/index.blade.php`
- `resources/views/ai/settings.blade.php`
- `resources/views/dashboard/admin.blade.php`
- `resources/views/dashboard/lecturer.blade.php`
- `resources/views/dashboard/member.blade.php`
- `resources/views/dashboard/student.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/settings/index.blade.php`
- `routes/api.php`
- `routes/web.php`

---

## 16. Files created

- `app/Http/Middleware/FeatureAccessMiddleware.php`
- `app/Services/FeatureAccessService.php`
- `config/features.php`
- `database/migrations/2026_08_14_070000_normalize_feature_module_management.php`
- `resources/views/errors/feature-unavailable.blade.php`
- `resources/views/settings/features.blade.php`
- `scripts/check-feature-management.php`
- `tests/Feature/FeatureModuleManagementTest.php`
- `docs/ACADFLOW_2026_08_14_FEATURE_MODULE_MANAGEMENT.md`

---

## 17. Files removed

**None.**

The task required backward-compatible consolidation. Obsolete runtime control paths were redirected/delegated rather than deleting files that may still be referenced by upgrades or existing integrations.

---

## 18. Tests / validation performed

The final source was statically validated with the tools available in the supplied environment:

- **551 PHP source/test/script files:** PHP syntax passed.
- **8 JavaScript files:** Node syntax passed.
- **156 Blade templates:** existing runtime-regression structural preflight passed.
- Feature Management inline JavaScript: syntax passed after Blade-expression neutralization for static parsing.
- Main app-layout inline JavaScript: syntax passed after Blade-expression neutralization for static parsing.
- **Feature-management preflight:** passed with 30 registered features/modules.
- **MySQL identifier preflight:** passed; migration identifiers stay within MySQL's 64-character limit.
- Seeder regression checks verify feature state is not reset by `updateOrCreate` on re-seed.
- Registry checks verify stable identifiers, valid dependencies and no dependency cycles.
- Middleware checks verify web/API feature enforcement wiring and API auth ordering.
- Duplicate fresh-install PWA/premium release-setting seeds are checked as absent.

A feature test suite was added for:

- Disabled normal-user access vs administrator preview.
- Maintenance API response contract.
- Parent/child dependency restriction.
- Normal-user vs administrator navigation behavior.

---

## 19. Regression-test results

Static regression checks passed for:

- PHP syntax.
- JavaScript syntax.
- Blade control-structure integrity.
- Feature registry integrity.
- Feature dependency integrity.
- Feature middleware wiring.
- Existing runtime regression checker.
- MySQL migration identifier safety.
- Seeder state-preservation checks.
- Central feature use in sidebar/dashboard/mobile-auth payload.

The implementation intentionally leaves authentication, password reset, authorization, onboarding and Feature Management outside the disableable registry so recovery paths are not accidentally blocked.

---

## 20. Remaining limitations / deployment notes

1. The supplied source archive does **not** contain installed `vendor/` or `node_modules/`; therefore a real Laravel HTTP/database PHPUnit run and a real Vite production build could not be executed in this environment. Static tests were run, but deployment should still run the project's normal integration suite.
2. This source tree is the Laravel web/backend project. If AcadFlow has a separate Flutter/mobile repository, that client must consume the new `features` map to hide/label unavailable screens. The backend already blocks protected feature APIs even if an old mobile build ignores the map.
3. The existing `feature_flag_overrides` schema is retained and supported by the service, but this management screen intentionally exposes platform-global Super Admin release state only. If the product later requires per-university release controls, the existing override table/service can be surfaced without creating another architecture.
4. The registry covers the current major user-facing modules/routes. When a genuinely new module is added in future, its stable identifier and route/API patterns should be added to `config/features.php` rather than creating a standalone switch elsewhere.
5. External provider outages/configuration are different from module availability. For example, enabling AI Assistant does not guarantee an external AI provider is configured; provider configuration remains under AI Settings.

---

## Deployment / upgrade commands

After replacing the project with this release, run the normal deployment flow, including:

```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=FeatureFlagSeeder
```

If front-end assets are built on the server:

```bash
npm install
npm run build
```

Then verify the following from **Admin → Settings → Feature & Module Management**:

1. Set a non-core feature to **Maintenance**.
2. Confirm Super Admin/Admin can still open it.
3. Confirm a normal user receives the maintenance state.
4. Set it to **Disabled / Unreleased**.
5. Confirm the normal-user menu/card disappears and a direct URL/API request is blocked.
6. Re-enable it and confirm access returns immediately after cache invalidation.

---

## Final architecture rule

AcadFlow now follows this runtime release principle:

> **One feature = one authoritative availability setting = one centralized access mechanism.**

Specialist configuration and subscription entitlements remain separate because they answer different questions:

- **Feature Management:** Is this module currently released/available?
- **AI/Notification/etc. Settings:** How does the available module behave?
- **Subscription entitlement:** Is this user/plan entitled to the available premium capability?

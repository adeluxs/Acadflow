# AcadFlow Frontend / Page Map

**Current source snapshot:** 2026-08-15

Blade is the primary UI. Route names and feature middleware determine which navigation entries appear. The list below is a functional map, not an exhaustive 400-route dump.

## Public

- `/` — landing / authenticated redirect to dashboard
- `/information/{page}` — help, documentation, status, about, careers, API, source, changelog, security, terms, privacy, cookies, licenses
- `/knowledge-hub` and public Knowledge ecosystem views according to publication/module access
- `/login`, `/register`, password reset
- public certificate verification
- protected/expiring media preview/download routes

## Account lifecycle

- email verification
- two-factor challenge
- onboarding
- account security / 2FA management
- notifications/preferences

## Shared authenticated modules

- `/dashboard`
- `/research-studio/...`
- `/knowledge-hub/...` authoring/ecosystem
- `/groups/...`
- course/material/discussion/submission pages according to role/policy
- `/ai-assistant` and contextual AI endpoints/UI
- billing/subscription/commerce/document pages according to plan/role

## Student-oriented pages

- enrolled courses
- course assignments/materials/discussions
- course join/enrolment
- submissions and versions
- attendance/check-in/history
- invoices/subscription
- groups/research/Knowledge Hub where enabled

## Lecturer-oriented pages

- lecturer course workspace
- materials management
- assignment/task management
- attendance session management
- submission review/grade/correction/approval
- AI Assistant + lecturer layout preferences
- Research Studio supervision/review

## Admin pages

Shared admin:

- notifications/announcements
- users
- reports
- billing/invoices/subscriptions

Department Admin:

- department workspace
- course management
- lecturer assignment management

University Admin / Super Admin:

- settings
- faculties
- semester invoice generation

Super Admin:

- universities
- Feature & Module Management
- permissions/audit logs
- subscription plans/analytics
- student import
- payment gateways
- platform-level AI administration through AI Settings routes

## UI implementation rules

- use `resources/views/layouts/app.blade.php` and shared partials/components;
- use `platformSettings`/`featureStates` shared by the provider;
- use the central `primary_color` CSS behavior instead of hardcoding another brand color;
- use feature/navigation helpers so disabled modules do not produce broken links;
- use the shared rich editor for supported long-form fields;
- render untrusted rich text only through existing sanitization patterns;
- provide loading/error/empty states for async controls.

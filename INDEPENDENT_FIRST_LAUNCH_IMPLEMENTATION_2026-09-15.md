# AcadSyntra Independent-First Launch Implementation — 2026-09-15

## Objective

AcadSyntra now launches bottom-up for individual **Students**, **Lecturers / Supervisors**, and **Researchers** while retaining the existing institution/enterprise architecture for later department, faculty and full-institution adoption.

No institutional module was deleted or rebuilt. Non-core launch modules remain registered in centralized **Admin → Feature & Module Management** and can be re-enabled by Super Admin.

## Public onboarding

- Public registration exposes Student, Lecturer / Supervisor and Researcher.
- Institution affiliation is optional for those three public personas.
- Lecturer registration maps to the existing non-admin `lecturer` role so course/assignment/supervision permissions work immediately.
- Researcher remains a non-admin member account; Research Studio explicitly authorizes `account_type=researcher` without introducing a new role/permission architecture.
- Existing lecturer accounts accidentally created as `member` are repaired by the release migration.
- The internal compatibility institution is never attached to user profiles and is hidden from affiliation/institution search.

## Independent classrooms

Independent classrooms reuse the existing Course, Enrollment, LecturerCourseAssignment, Assignment, Material, Submission and Grading architecture.

New course fields:

- `access_mode`: `institutional` or `independent`
- `owner_user_id`: independent classroom owner

For an independent classroom AcadSyntra creates/uses an internal system-managed academic context only to satisfy existing semester-dependent foreign keys. Students and lecturers keep `university_id = null` unless they voluntarily connect a real institution.

Lecturers can create an independent classroom and share the existing join-link flow. Independent student enrollment bypasses university/department matching while institutional classes keep the existing scope rules.

Web and API tenant scoping both use explicit ownership/teaching/enrollment participation for independent classrooms.

## Independent Research Studio

New research-project field:

- `workspace_mode`: `institutional` or `independent`

Independent research projects use the same internal academic context behind the scenes, but authorization is based on explicit project participation: owner, supervisor, co-supervisor or project member. The synthetic institution never grants access.

Student, Lecturer / Supervisor and Researcher personas can create independent research workspaces. Institutional projects retain the existing university/department rules. Web and API flows use the same model.

## Admin-controlled launch profile

Enabled initially:

- Dashboard
- Courses / Classrooms
- Course Materials
- Assignments
- Submissions & Grading
- Research Studio
- Knowledge Hub core
- AI Assistant
- Notifications
- Monetization core
- Wallet & Billing
- Documents / Exports
- PWA

Launch-disabled but still installed and Admin-controlled:

- Attendance
- Course Discussions
- Groups / Collaboration
- Research → Knowledge Hub publishing bridge
- SIWES
- Seminar workspace
- Formal Final-Year Project / Defense workflow
- Knowledge Communities
- Learning Paths
- Reading Lists
- Academic Events
- Academic Challenges
- Course Certificates
- Knowledge AI Companion
- Knowledge Hub Premium
- Knowledge Marketplace
- Creator Payouts
- Advanced Analytics
- Push Notifications
- Institution Self-Service

The release migration writes these initial statuses into `feature_flags`. Administrators can later change each status from Feature & Module Management without a deployment.

## Commerce separation

The previous broad commerce surface is separated at feature-control level:

- `wallet_billing` remains enabled for AI/paid-feature billing.
- `commerce_marketplace` starts disabled.
- `creator_payouts` starts disabled.

This preserves the working monetization/wallet architecture without forcing marketplace complexity into the first launch.

## Adoption pricing posture

Global pricing rules for Group Submissions, Rubrics and Attendance are deactivated for the launch migration so classroom adoption is not paywalled by default. Admin can publish pricing later. Existing AI/document monetization remains available.

## Compatibility

Institution claiming, university/department administration, sponsorship, commercial accounts, institution analytics, SIWES/defense and related enterprise code remain intact. They are not removed; normal-user exposure is controlled centrally.

## Deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan test
```

After verifying production, normal production caches can be rebuilt according to the deployment environment. On low-memory local Windows development, route caching remains optional and PHPUnit uses isolated test cache paths.

## Regression protection

Added:

- `scripts/check-independent-first-launch.php`
- `tests/Feature/IndependentFirstLaunchTest.php`

The checks protect public persona onboarding, lecturer role mapping, Admin-controlled launch flags, independent classroom scoping, independent Research Studio isolation, hidden internal academic context and web/API parity.

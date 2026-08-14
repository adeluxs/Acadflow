# AcadFlow Enhancement Pack — 2026-08-12

This source keeps AcadFlow's existing Laravel models, controllers, routes and Blade structure and extends them rather than replacing the application architecture.

## Implemented

- Nigerian university/polytechnic registry sync, institution metadata, tenant-safe academic codes, starter faculty/department/course catalogs, exact CSV curriculum importer and catalog provenance.
- Lecturer course self-assignment, direct student enrolment, expiring course invitations, active-semester enforcement, capacity checks and institution/department boundaries.
- Central `SettingService` with request/cache layer, canonical aliases, institution overrides, duplicate legacy-key migration and platform-only setting protection.
- Settings connected to notifications, login throttling/lockout, password rules, sessions, 2FA availability, PWA/platform controls, course membership, rich-editor AI suggestions and interface primary color.
- Visible default form borders with primary-color hover/focus states across the application.
- Quill-based rich editor (with no-CDN fallback) in Research Studio and Knowledge Hub, plus reviewable AI suggestions while typing and server-side HTML sanitization.
- Lecturer/student dashboards rebuilt around the supplied visual references; member dashboard rebuilt in the same design language.
- Landing page rebuilt around the supplied AcadFlow visual reference with responsive hero/dashboard preview, role cards and feature strip.
- Security hardening for course/discussion/submission boundaries, rich HTML, invitation tokens, production demo seeding, assignment semester/rubric integrity and global maintenance controls.
- Performance work including cached settings, reduced admin settings queries and high-use academic/submission database indexes.
- Front-end stability fix preventing Vue from mounting when a Blade page has no Vue root.

## Validation performed in this package

- PHP syntax lint across `app`, `database`, `routes` and `config`.
- JavaScript syntax checks for all files in `resources/js`.
- MySQL identifier preflight (`scripts/check-mysql-identifiers.php`).

A full Laravel runtime test/build still requires installing the Composer and npm dependencies on the target development/deployment environment.

## 2026-08-13 UniversitySeeder hotfix

- Fixed `TypeError: Unsupported operand types: string * int` when seeding lecturer course assignments.
- Cause: the lecturer collection retained email-address keys after `map()`, so the loop key was a string rather than a numeric offset.
- Reindexed lecturer and sliced course collections with `values()` before numeric indexing.
- Corrected coordinator assignment so the first course in each lecturer's assigned slice is marked as coordinator.

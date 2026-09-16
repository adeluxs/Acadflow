# Course View & Enterprise Identity Regression Fix — 2026-09-16

## Failures addressed

The local PHPUnit run reported four failures:

1. `CourseAccessTest::student can view enrolled course`
2. `CourseAccessTest::lecturer can view own course`
3. `EnterpriseIdentityTest::independent registration creates neutral member and resumable onboarding`
4. The older affiliation-skip assertion for student onboarding

## Fixes

### Course Blade compiler safety

`resources/views/courses/show.blade.php` was reformatted so Blade control directives are no longer compressed inline or adjacent (for example `@endif@if`). The rendered content, routes and permissions are unchanged.

### Backend account-type compatibility

The web and API registration endpoints continue to expose only Student, Lecturer/Supervisor and Researcher in the public UI, but backend validation again accepts all existing `OnboardingService::ACCOUNT_PATHS`. This preserves compatibility for existing integrations and legacy account paths without granting privileged roles.

### Independent-first affiliation behavior

Students, lecturers and researchers are intentionally independent-first and may skip affiliation. Institutional representative paths still cannot skip affiliation.

### Regression coverage

Added `scripts/check-course-view-and-identity-regressions.php` and updated `EnterpriseIdentityTest` so the suite now protects:

- compiler-safe course Blade directives;
- public three-persona registration UI;
- legacy backend account-type compatibility;
- lecturer role mapping;
- optional affiliation for independent students;
- mandatory affiliation for institutional representatives.

# AcadSyntra Test Failure Fix Report — 2026-09-02

The reported PHPUnit run contained 13 failures and 120 passes. The failures were caused by tests/fixtures that had not been updated after the centralized AI runtime, enterprise onboarding, tenant-safe submission workflow, and institutional routing changes.

## Fixes

1. `AiRuleEngineTest`
   - Removed obsolete direct construction of `AiRouter`, `AiCache`, `AiAnalytics`, and the old short `AiManager` constructor.
   - Tests now configure runtime AI settings through `SettingService` and resolve the canonical `AiManager` from Laravel's service container.

2. `AiFeatureModulesTest`
   - Submission Validator, Plagiarism, Writing Assistant, and Citation Assistant are resolved from the container so their current runtime-config dependencies are injected.

3. `EnterpriseIdentityTest`
   - Test usernames now obey the production `alpha_dash` policy (`ada-okafor`, `tunde-bello`).
   - Production validation was intentionally not weakened to permit dots.

4. `GroundedCompanionIntelligenceTest`
   - Provider switching now sets the institution-scoped default provider/model for the test user's institution before invalidating the AI runtime/cache generations.

5. `SubmissionFlowTest`
   - Fixtures now create a real University → Faculty → Department → Academic Session → active Semester hierarchy.
   - Student enrollment and assignment task use the same active semester and tenant.
   - Submission tests create an actual current submission version before attempting submission.
   - Grading uses a lecturer assigned to the course rather than a `super_admin` attempting to enter lecturer-only routes.

6. Release packaging
   - Restored `.env.example`.
   - Removed the accidentally nested older release tree.

## Validation performed in this environment

- All 779 PHP files lint successfully with `php -l`.
- All 17 repository `scripts/check-*.php` preflight checks pass.
- The supplied source does not include `vendor/`, so PHPUnit cannot be executed in this environment without installing Composer dependencies.

## Verify on your machine

After extracting the fixed ZIP in the project directory:

```bash
composer install
php artisan optimize:clear
php artisan test
```

For only the previously failing areas:

```bash
php artisan test tests/Unit/AiRuleEngineTest.php
php artisan test tests/Feature/AiFeatureModulesTest.php
php artisan test tests/Feature/EnterpriseIdentityTest.php
php artisan test tests/Feature/Knowledge/GroundedCompanionIntelligenceTest.php
php artisan test tests/Feature/SubmissionFlowTest.php
```

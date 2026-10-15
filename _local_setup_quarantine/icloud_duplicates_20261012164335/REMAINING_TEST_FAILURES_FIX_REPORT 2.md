# AcadFlow — Remaining PHPUnit Failure Fixes

Date: 2026-09-02

This patch addresses the seven failures reported after the previous test-fix release.

## Fixed

1. **Hybrid AI rule fallback assertion**
   - The production AI contract intentionally labels an explicit Hybrid fallback as `rule_engine_fallback` rather than pretending the request ran in pure rule-only mode.
   - Updated the unit test to assert the current contract, including `fallbackUsed=true`.

2. **AI response cache never hit**
   - Fixed a real runtime issue in `AiCache`: `_ai_request_id` is generated for every call and was included in the cache fingerprint.
   - The correlation ID is now excluded from the deterministic cache key while the routing fingerprint remains, so provider/model/settings changes still invalidate effective cache entries.

3. **AI feature module tests returned unsuccessful responses**
   - `SubmissionValidatorModule`, `PlagiarismModule`, `WritingAssistantModule`, and `CitationAssistantModule` tests now exercise the modules through the real container/runtime config in deterministic `rule_based` mode.
   - Live provider transport remains covered by the dedicated provider transport tests; module tests no longer depend on external API credentials.

4. **RuleEngine tenant-context closure bug**
   - Fixed `RuleEngine::packsForFeature()` to capture `$context` in its filter closure before reading `_tenant_university_id`.

5. **Submission create page 500 (`$resubmitsLeft`)**
   - Moved resubmission-allowance calculation into `SubmissionController::create()` and pass it explicitly to the Blade view.
   - Removed the fragile Blade-local calculation and retained an output fallback.

## Validation performed in this environment

- Repository-wide PHP syntax validation: PASS.
- All repository `scripts/check-*.php` preflight checks: PASS.
- ZIP integrity validation: performed after packaging.

## PHPUnit execution note

The supplied source archive intentionally does not include `vendor/`, and Composer is not installed in this execution environment. Therefore the Laravel PHPUnit suite cannot be booted here. On a development/staging machine with dependencies installed, run:

```bash
php artisan optimize:clear
php artisan test
```

The changes above directly target the seven failures in the supplied `Pasted text(5).txt` test output.

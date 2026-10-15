# AcadFlow runtime and AI Assistant fixes — 2026-08-13

## Runtime errors fixed

1. `media_access_logs.updated_at` SQL error
   - `media_access_logs` is an append-only table with `created_at` only.
   - `MediaAccessLog` now disables Eloquent automatic timestamps so Laravel no longer attempts to insert `updated_at`.

2. Settings JSON/array rendering error
   - `settings/partials/field.blade.php` now serializes arrays/objects to readable JSON before HTML escaping.
   - Input borders are visible by default and use the primary indigo state on hover/focus.

3. AI Settings `Undefined constant "feature"`
   - Prompt placeholders are now escaped as literal template placeholders (`@{{feature}}`, `@{{context_json}}`) instead of being executed by Blade.

4. Submission create null assignment error
   - `/submissions/create` can safely be opened without `task_id`.
   - It now shows only published assignments from the student's active enrollments.
   - Invalid/unavailable `task_id` values are rejected and redirected safely.
   - Nullable due dates/deadlines are rendered safely.

5. Additional append-only timestamp mismatches
   - Models for append-only tables that truly have no `updated_at` column (media access, engagement, discovery, reputation, and research authorship logs) no longer ask Eloquent to write one. Existing compatibility migrations that add timestamps to audit, attendance, and group membership remain respected.

## Dedicated AI Academic Assistant

AcadFlow now has a real assistant workspace instead of sending the AI Assistant navigation item to Knowledge Hub search or lecturer layout preferences.

Routes:
- `GET /ai-assistant` → `ai.assistant`
- `POST /ai-assistant/ask` → `ai.assistant.ask` (uses the existing `throttle:ai` limiter)

The page supports:
- Ask / Explain
- Improve Writing
- Citation Review
- Optional course context
- Provider/mode status
- Grounding-source display
- Student, lecturer and member dashboard/sidebar entry points

## How AI requests work

The assistant reuses AcadFlow's existing architecture; it does not introduce a separate AI stack.

1. The user sends a request from the AI Assistant page.
2. The selected course is authorization-checked with `User::canAccessCourse()`.
3. For Ask/Explain, `AcademicAssistantService` retrieves relevant chunks through `DiscoverySearchService`.
4. Discovery privacy now distinguishes institution-visible material from course-visible material. Course material is searchable only when the user is enrolled in/assigned to that specific course (or has the appropriate administrative course scope).
5. Retrieved text is treated as untrusted source data and prompt-injection-like instructions are removed before AI use.
6. The request flows through the existing centralized `AiManager`.
7. `AiManager` enforces institution feature switches, mode, provider settings, budget/request limits, caching, prompts, rule packs and analytics.
8. If external AI is configured and enabled, the configured provider can answer. If external AI is disabled, AcadFlow uses its local rule engine; when authorized indexed sources exist the assistant returns a grounded extractive answer with `[S#]` citations.
9. Writing and citation tools continue to use the existing `WritingAssistantModule` and `CitationAssistantModule`.

## AI settings scoping

The AI Settings screen now reads the same institution-scoped settings that it writes. University/department administrators no longer see global AI values while saving institution overrides.

## Deployment notes

After replacing the source, clear Laravel caches:

```bash
php artisan optimize:clear
```

No new database migration is required for the `media_access_logs` fix because the model was corrected to match the existing intentional append-only schema.

If dependencies are installed, run the project's normal tests/build commands before production deployment.

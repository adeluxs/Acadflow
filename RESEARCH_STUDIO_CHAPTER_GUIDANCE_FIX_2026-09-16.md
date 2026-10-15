# Research Studio Chapter Guidance Runtime Fix — 2026-09-16

## Symptom

`ResearchStudioProfessionalWorkflowTest` returned HTTP 500 when opening Chapter 3 with `Undefined array key "ai_prompts"` in `resources/views/research/show.blade.php`.

## Root cause

`ResearchWritingExperienceService::pack()` used PHP `compact()` with the local variable `$aiPrompts`, which produced the array key `aiPrompts`. The Blade view and the default empty guidance contract use the public key `ai_prompts`.

## Fix

- `pack()` now returns an explicit, stable array contract using `ai_prompts`.
- The Blade view renders `($writingGuidance['ai_prompts'] ?? [])` defensively.
- Added a feature-test assertion and preflight checks for the contract.

No database migration is required.

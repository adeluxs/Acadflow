# AcadFlow Specialized AI Assistants — Implementation Report

**Date:** 2026-08-15  
**Architecture:** Existing centralized `AiManager` → `AiRouter` → runtime-selected provider/model  
**Goal:** Turn the previously dormant module-specific assistant concepts into real, permission-scoped AcadFlow features without creating a second AI architecture.

## Final AI capability map

```text
AI Assistant
├── Student Assistant              study_assistant
├── Lecturer Assistant             lecturer_assistant
├── Writing Assistant              writing_assistant
└── Citation Assistant             citation_assistant

Research Studio
├── Research Assistant             research_assistant
└── Research Validator             research_validator

Knowledge Hub
├── Grounded AI Companion          knowledge_companion
├── Publication Validator          knowledge_publication_validator
└── Knowledge Moderator            knowledge_moderation

Assignments / Submissions
├── Assignment Assistant           assignment_assistant
├── Submission Validator           submission_validator
└── Academic Integrity/Plagiarism  plagiarism

SIWES
└── SIWES Assistant                siwes_assistant

Projects
└── Project Assistant              project_assistant

Courses
└── Material / Study Assistant     material_assistant

Discussions
└── Discussion Assistant           discussion_assistant
```

AcadFlow therefore exposes **16 centrally registered AI feature keys**. Six contextual assistants were activated in this change; the existing ten AI capabilities remain in place.

## Central routing rule

None of the six new contextual assistants contains OpenAI/Gemini/Claude/etc. selection logic. Each builds authorized academic context and then executes:

```text
Module page
   ↓
ContextualAiController
   ↓
ContextualAssistantService
   ↓
AiManager
   ↓
AiRuntimeConfigService
   ↓
AiRouter
   ↓
Feature override OR Global Default
   ↓
Primary provider/model
   ↓ failure
Fallback provider/model
   ↓ failure
Secondary fallback
```

This means changing the Default Provider, Default Model, AI Mode, fallback chain, or a feature-specific route in **Admin → AI Settings** affects these assistants through the same runtime used by the rest of AcadFlow.

## Six activated assistants

### Research Assistant

**Feature:** `research_assistant`  
**Endpoint:** `POST /ai/context/research/{research}`  
**UI:** Research Studio project page.

Authorized context includes the current research project, sections, validation result, corrections, milestone status, research tasks, bounded literature notes, and authorized indexed chunks. It supports research planning, methodology review, evidence interpretation, revision planning and source-aware writing guidance.

Guardrails prohibit fabricated sources, data, findings, citations and experiments.

### Assignment Assistant

**Feature:** `assignment_assistant`  
**Endpoint:** `POST /ai/context/assignments/{course}/{task}`  
**UI:** Student and lecturer assignment pages.

Context includes assignment description/instructions, dates, requirements, rubric, bounded text from authorized clean assignment attachments, and authorized course search context. For a student, AcadFlow may additionally include only that student's own latest submission/draft text; it never loads another student's work into this assistant. Lecturer usage remains task/rubric/course scoped unless the lecturer intentionally enters the separate authorized submission-review workflow.

Student mode focuses on explanation, hints, planning, concept learning and self-checking. It explicitly avoids producing a ready-to-submit final answer for graded work. Lecturer mode focuses on instruction clarity, rubric alignment, likely misunderstandings and educational feedback without inventing student work or grades.

### SIWES Assistant

**Feature:** `siwes_assistant`  
**Endpoint:** `POST /ai/context/siwes/{research}`  
**UI:** SIWES specialized research workspace.

Context can include the real placement, organization, dates, completed/required hours, recent logbook entries, recent attendance records and evaluations. The assistant must never invent attendance, workplace activities, hours, employers, supervisors, skills or workplace events.

### Project Assistant

**Feature:** `project_assistant`  
**Endpoint:** `POST /ai/context/projects/{submission}`  
**UI:** Project submission/review pages only when `submission.type = project`.

Context includes the project submission, task requirements, available project rubric, current authorized document text, course context, grading feedback and recent comments. It supports structure critique, methodology reasoning, revision planning and defense preparation. It cannot fabricate data/results/references or ghostwrite a complete final project.

### Material / Study Assistant

**Feature:** `material_assistant`  
**Endpoint:** `POST /ai/context/materials/{course}/{material}`  
**UI:** Course material page.

Context combines the current material metadata, safe extracted file text where available, exact indexed material chunks and authorized course chunks. It can explain concepts, generate revision questions and connect authorized course material. Unsupported course claims must be identified as unsupported instead of invented.

### Discussion Assistant

**Feature:** `discussion_assistant`  
**Endpoint:** `POST /ai/context/discussions/{course}/{discussion}`  
**UI:** Discussion thread page.

Context includes the discussion, tags, related material and visible thread replies. When indexed course/material evidence exists, the assistant also receives only authorized relevant chunks so factual course claims can carry source labels instead of being guessed. It can summarize viewpoints, identify unresolved questions and help the user prepare a constructive response. It must not impersonate participants or invent consensus.

## Shared intelligence and safety

The contextual assistants share `AcademicInputQualityService`. Obvious keyboard-smash/gibberish is rejected before an external provider request, reducing cost and preventing meaningless generated answers.

Retrieved source material is explicitly treated as **untrusted data**. Source text is sanitized for common prompt-injection patterns and the provider receives instructions not to obey commands embedded inside retrieved content.

The reusable UI only appears when both:

1. the central Feature Management `ai_assistant` feature is accessible; and
2. the specific AI feature is enabled in AI Settings.

Every endpoint also has module-specific Feature Management middleware and model-policy authorization, so hiding a UI control is not the security boundary.

A dedicated `ai.feature:<feature>` middleware now checks the runtime AI feature switch **before** controllers build retrieval context or extract files. This means disabling `research_assistant`, `assignment_assistant`, `siwes_assistant`, `project_assistant`, `material_assistant`, or `discussion_assistant` stops the request at the HTTP boundary instead of doing unnecessary context work and only failing later inside `AiManager`. `AiManager` still repeats the authoritative feature check as defense in depth.

## Settings and backward compatibility

`config/ai.php` is the authoritative registry for these live features. `SettingsSeeder` derives feature settings from that registry using `firstOrCreate`, so normal reseeding does not overwrite existing administrator choices.

Migration `2026_08_15_120000_activate_specialized_ai_assistants.php` safely reactivates historical keys where they already exist while preserving the saved value/provider/model. Missing keys are inserted with defaults. Existing customized prompts and tenant prompt overrides are not overwritten.

Migration `2026_08_15_121000_ensure_ai_feature_prompt_baselines.php` guarantees that every active feature in the centralized `config('ai.features')` registry has at least one safe global prompt baseline on upgraded installations. It only inserts a baseline when no global prompt exists for that feature, so existing prompt versions, administrator customizations, tenant prompts, and credentials are preserved. Its rollback is intentionally non-destructive.

The six features support:

```text
Enabled / Disabled
Use Global Default provider
Feature-specific provider override
Use Global/provider model
Feature-specific model override
Hybrid deterministic fallback control
```

## Performance

- Provider/model selection is not duplicated in module services.
- Retrieved source document titles are loaded in one query rather than through per-chunk relation queries.
- Material file text is bounded before provider context construction.
- Existing AI context limits are still applied centrally by `AiPromptService`.
- Provider response caching and routing fingerprints remain handled centrally.

## Files added

```text
app/Services/Ai/AcademicInputQualityService.php
app/Services/Ai/ContextualAssistantService.php
app/Http/Controllers/ContextualAiController.php
app/Http/Middleware/EnsureAiFeatureEnabled.php
resources/views/ai/_contextual-assistant.blade.php
database/migrations/2026_08_15_120000_activate_specialized_ai_assistants.php
database/migrations/2026_08_15_121000_ensure_ai_feature_prompt_baselines.php
tests/Feature/SpecializedAiAssistantsTest.php
tests/Feature/AiFeatureMiddlewareTest.php
tests/Feature/Ai/AiCapabilityMapTest.php
tests/Feature/Ai/AiContextRoutesSecurityTest.php
scripts/check-specialized-ai-assistants.php
docs/ACADFLOW_2026_08_15_SPECIALIZED_AI_ASSISTANTS.md
```

## Key files modified

```text
config/ai.php
config/features.php
bootstrap/app.php
routes/web.php
app/Ai/Rules/RuleEngine.php
app/Services/Ai/AcademicAssistantService.php
database/seeders/AcadFlowEcosystemSeeder.php
scripts/check-ai-central-routing.php
docs/ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md
resources/views/research/show.blade.php
resources/views/research/specialized.blade.php
resources/views/submission-tasks/show.blade.php
resources/views/submission-tasks/student-show.blade.php
resources/views/submissions/show.blade.php
resources/views/submissions/review.blade.php
resources/views/materials/show.blade.php
resources/views/discussions/show.blade.php
```

## Post-deployment

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan queue:restart
php artisan optimize
```

Then review **Admin → AI Settings → Feature Configuration**. The six new assistants can inherit the global provider/model or receive intentional feature-specific routing overrides.

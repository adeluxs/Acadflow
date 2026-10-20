# AcadSyntra Research Studio — Professional Dashboard & Chapter Workspace

Date: 2026-09-16

## Goal

Reorganize the existing Research Studio into a professional, easier-to-understand workflow for independent students and researchers without replacing working research, literature, AI, versioning, workflow, or institutional infrastructure.

## Implemented

### Project Control Center

- Rebuilt as the Research Studio project home.
- Project hero with overall progress, research type, keywords, and continue-writing action.
- Template-driven Research Journey derived from the project's existing `ResearchSection` records.
- Next-action card that takes the user to the first unfinished section.
- Literature reading progress (Saved / Reading / Reviewed).
- Quick academic-paper search from the Control Center.
- Existing Research Assistant, Milestones, Project Tasks, and enabled optional modules remain available below the project overview.

### Professional Writing Workspace

- Replaced the long all-sections-on-one-page layout with a focused one-section-at-a-time editor.
- Left-side Chapter Navigator displays every section from the project's configured template.
- Section selection uses the existing `research.show` route with `?section=<uuid>`; no duplicate document system was created.
- Existing ContentDocument versioning, comments, corrections, approval, AI writing suggestions, and save endpoints remain authoritative.
- Project progress and section status remain visible while writing.

### Chapter-aware guidance

`ResearchWritingExperienceService` provides non-destructive professional guidance based on the active section:

- Chapter 1 / Introduction: background, problem, objectives, questions, significance, scope.
- Chapter 2 / Literature Review: conceptual review, theoretical framework, empirical review, synthesis, gap.
- Chapter 3 / Methodology: design, population, sampling, instrument, validity, reliability, collection, analysis, ethics.
- Chapter 4 / Results / Analysis: results, research questions/hypotheses, tables/figures, analysis and interpretation.
- Chapter 5 / Discussion / Conclusion: findings, discussion, conclusion, recommendations, contribution, further research.
- Abstract, References, Appendices, Methods, Results, Discussion and generic template sections receive appropriate guidance too.

The guidance is not stored as new mandatory database sections. This avoids forcing one institution's structure onto every research project.

### Literature-to-Chapter-Two preserved

- Reviewed-literature panel remains available only on Literature Review / Chapter Two sections.
- Insert citation, evidence, methodology, and research-gap actions still feed the existing rich editor.
- References section retains reviewed-bibliography insertion.
- Literature search, saved references, reading queue, synthesis and comparison remain unchanged.

### Chapter Four optional dataset support

- Dataset tools appear in the writing rail only when `research_datasets` is enabled by Admin and the active section is results/analysis-related.
- No dataset controls appear by default.

### Institutional expansion remains hidden by default

When `research_institutional_expansion` is disabled, normal users do not see:

- institutional workspace labels,
- department controls,
- supervisor controls or invitations,
- institutional supervision cards.

When Admin enables it and the project is actually institutional, those controls become available.

### Admin-controlled optional Research modules hardened

The UI already hid disabled modules. This release also adds server-side guards so users cannot bypass Admin settings by manually calling endpoints.

Protected modules:

- Research Meetings & Action Items
- Research Collaboration & Contribution Tracking
- Research Archives & Controlled Amendments
- Research Dataset Management
- SIWES Workspace
- Seminar Workspace

### Template compatibility

The chapter navigator is not hard-coded to Chapters 1–5. It renders the existing project's `ResearchSection` rows, which are generated from the project's research template.

This supports:

- Standard final-year projects
- Research articles
- Thesis/dissertation templates
- Future institution-specific templates when institutional expansion is enabled

without rebuilding the Writing Workspace.

## New regression coverage

- `tests/Feature/ResearchStudioProfessionalWorkflowTest.php`
- `scripts/check-research-studio-professional-workflow.php`

The checks verify:

- focused active-section writing,
- professional guidance,
- template-driven navigation,
- independent-first Control Center,
- hidden institutional UI by default,
- optional-module server-side enforcement.

## Validation

- 33/33 AcadSyntra preflight/regression checks passed.
- 833/833 PHP + Blade files passed `php -l`.
- 11/11 JavaScript files passed `node --check`.
- No database migration is required for this UI/workflow release.
- Full Laravel PHPUnit execution was not possible in the source-only release environment because `vendor/` is intentionally absent.

# AcadSyntra Literature Review Workflow Implementation — 2026-09-16

## Purpose

This release implements the approved professional literature-review UI on top of the existing Research Studio architecture. It does not create a second research editor, reference system, or AI pipeline.

## Research workflow

1. **Discover** — Search configured scholarly providers, filter by year/provider, open lawful source/DOI destinations, and save one or many papers.
2. **Saved References** — Manage the project library, track review completion, organise papers into collections, and maintain structured researcher-authored notes.
3. **Reading Queue** — Move papers through `Saved → Reading → Reviewed`.
4. **Synthesis** — Aggregate only Reviewed notes into theme counts, methodology signals, reviewed findings, recorded limitations, gaps, and contrasting evidence. No missing finding is invented.
5. **Compare** — Select 2–5 saved papers and compare methodology, findings, limitations, research gaps, and contradiction/comparison notes side by side.
6. **Writing Workspace** — Reviewed papers appear in the Chapter 2 / Literature Review side panel. Researchers explicitly insert an in-text citation, reviewed evidence, methodology note, or research-gap note into the existing versioned rich editor.
7. **References section** — Reviewed bibliography entries can be inserted in the project citation style into the existing References editor.

## Data model

`research_literature_notes` is extended with:

- `reading_status`: `saved`, `reading`, or `reviewed`
- `collections`: JSON collection/tag names
- `reviewed_at`: timestamp set when the paper is marked Reviewed

Existing structured notes are backfilled as Reviewed during migration so previous work remains immediately useful.

Migration:

`database/migrations/2026_09_16_040000_expand_research_literature_workflow.php`

## Citation handling

The existing `AcademicReferenceService` remains authoritative. It now safely normalizes nested scholarly-author payloads returned by providers before formatting bibliography or in-text citations.

The Research Studio continues to use the project/template citation style (APA, MLA, Chicago, Harvard, IEEE, or Vancouver where configured).

## Academic integrity behavior

- Search results do not imply AcadSyntra possesses full-text access.
- Opening a paper follows lawful provider/DOI links and does not bypass publisher restrictions.
- Synthesis is deterministic and uses only researcher-entered structured notes from papers marked Reviewed.
- Chapter Two insertion does not silently save or rewrite the section. Inserted text remains editable until the researcher deliberately saves a new version.
- Only Reviewed references appear in the Chapter Two insertion panel.

## Files changed/added

- `app/Http/Controllers/ResearchWorkspaceController.php`
- `app/Http/Controllers/ResearchProjectController.php`
- `app/Models/ResearchLiteratureNote.php`
- `app/Services/AcademicReferenceService.php`
- `app/Services/LiteratureSynthesisService.php` (new)
- `resources/views/research/literature.blade.php`
- `resources/views/research/show.blade.php`
- `resources/js/rich-editor.js`
- `database/migrations/2026_09_16_040000_expand_research_literature_workflow.php` (new)
- `tests/Feature/ResearchStudioProfessionalizationTest.php`
- `scripts/check-literature-review-workflow.php` (new)
- `scripts/check-research-studio-professionalization.php`

## Deployment

```bash
php artisan optimize:clear
php artisan migrate --force
npm ci
npm run build
php artisan test --filter=ResearchStudioProfessionalizationTest
php artisan test
```

The frontend build is required in real development/production because `resources/js/rich-editor.js` changed. PHPUnit remains isolated from Vite through the existing test bootstrap.

# Research Studio Professionalization — 2026-09-16

## Goal

Make Research Studio useful and professional for independent students/researchers at launch while preserving AcadFlow's institutional research capabilities for later Admin-controlled expansion.

## Launch profile

Core/default Research Studio:

- structured writing and version history;
- milestones;
- project tasks;
- Research Assistant;
- Literature & Reference Library;
- validation/similarity where configured;
- exports.

Admin-controlled optional Research Studio modules:

| Feature key | Default | Purpose |
|---|---|---|
| `research_institutional_expansion` | Disabled | Institutional project mode, departments and supervisor invitations |
| `research_meetings` | Disabled | Meetings, attendance, notes and action items |
| `research_collaboration` | Disabled | Formal collaborators, granular permissions and contribution evidence |
| `research_archives` | Disabled | Immutable archives and controlled amendments |
| `research_datasets` | Disabled | Research dataset management |
| `research_to_knowledge_hub` | Disabled | Publish approved research into Knowledge Hub |
| `siwes_module` | Disabled | SIWES workflows |
| `seminar_module` | Disabled | Seminar workflows |
| `final_year_project` | Disabled | Formal project-defense workflows |

All are retained in the source and managed from **Admin → Settings → Feature & Module Management**.

## Institutional project mode

Institution-related UI is not rendered for normal users when `research_institutional_expansion` is disabled. Direct web/API payloads attempting institutional mode, department assignment or supervisor assignment are rejected as well.

When enabled and the user has an affiliation, the Create Research Project page can opt into **Institutional project** mode. Department controls are enabled only while that checkbox is selected.

Existing institutional projects remain stored. When the expansion is disabled, normal users cannot browse/directly access those institutional workspaces; admins retain preview/management capability.

## Supervisor invitations

Supervisor assignment is now consent-based:

1. owner chooses/invites an eligible active lecturer from the same institution;
2. an auditable `research_project_invitations` record is created with `pending` status and seven-day expiry;
3. `research_projects.supervisor_id` remains null;
4. invited lecturer accepts or declines;
5. only acceptance writes `supervisor_id`;
6. other pending supervisor invitations are superseded after an acceptance.

The project page also supports inviting later when no supervisor/pending invitation exists.

## Project Control Center

The Control Center is the operational workspace rather than an institutional admin dashboard. Core cards/sections show progress, open tasks, literature count (when Literature is enabled), next milestone, milestones and project tasks.

Optional module relations are loaded only when their corresponding Admin feature is enabled, reducing both UI complexity and database work.

## Literature & Reference Library

Scholarly discovery retains AcadFlow's existing provider architecture:

- OpenAlex
- Crossref
- Semantic Scholar
- CORE
- DOAJ
- PubMed
- arXiv

Researchers can search by topic/question, restrict sources, filter publication years and select 20/30/50 results. Results are normalized and deduplicated by DOI, or normalized title + publication year when DOI is unavailable.

The redesigned result cards show source, year, authors, DOI, abstract preview, lawful external paper/DOI link and saved state. Multiple results can be selected and saved in one action.

Saving creates/updates an `AcademicReference` with a stable citation key and links it to the current project. DOI-less records use provider + external identifier rather than `doi = null`, preventing unrelated papers from collapsing into one reference.

Each saved reference supports structured researcher notes:

- summary;
- methodology;
- findings;
- limitations;
- contradictions/comparisons;
- research gap/relevance;
- keywords.

Previously saved note fields are prefilled when the library is reopened.

AcadFlow does not bypass publisher/paywall restrictions. Search stores metadata/abstracts returned by scholarly services; opening a paper follows an HTTP(S) provider or DOI URL.

## Web/API parity

API research creation applies the same institutional-expansion checks and converts legacy `supervisor_id` input into a pending invitation rather than direct assignment. Disabled institutional data is not returned to normal users by the API project view.

## Migration

Run:

```bash
php artisan migrate --force
```

Migration added:

`database/migrations/2026_09_16_030000_professionalize_research_studio.php`

## Verification

New regression coverage:

- `tests/Feature/ResearchStudioProfessionalizationTest.php`
- `scripts/check-research-studio-professionalization.php`

The feature tests verify Admin gating, supervisor acceptance, rejection of institution payloads while disabled, distinct DOI-less reference saving/note persistence, and the focused default Control Center.

## Final static verification

- Project preflight/regression checks: **29/29 passed**.
- PHP/Blade lexical checks: **824/824 passed**.
- Full Laravel PHPUnit execution is not available in this source-only environment because `vendor/` is intentionally not packaged; run `php artisan test` after installing dependencies locally.

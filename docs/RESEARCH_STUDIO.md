# Research Studio and account setup

AcadSyntra supports a saved, methodology-specific research plan alongside the existing writing, reference, supervision and workflow infrastructure. New features extend existing projects; they do not rewrite them or change account roles.

## Research design and project setup

Open Research Studio → New research project, or Open Final-Year Project Studio. The three-stage wizard collects level (ND, HND, undergraduate, postgraduate, master's, doctoral or professional), discipline, institution if relevant, methodology, report structure, reference style, title, objectives and expected completion. A server-validated submission creates real private documents, membership and milestones in one transaction.

`config/research_methodologies.php` defines 41 research designs across quantitative, qualitative, mixed methods, reviews, humanities, computational/design and creative practice. Families share appropriate report structures; individual designs add decisions, instruments and analysis guidance. `ResearchMethodologyService` snapshots the selected catalogue version, guidance and section schema into `research_projects.metadata.research_setup`. Catalogue changes affect future projects. A project's existing sections remain authoritative.

Choose a methodology structure, the Nigerian five-chapter format, or the administrator's research-type template. The five-chapter format includes preliminary pages, abstract, introduction, literature, methodology, results/implementation and discussion, conclusions/recommendations, references and appendices. Final-Year Project Studio also adds defence preparation. ND through doctoral levels are supported without assuming identical institutional requirements. Levels are recorded context, not automatic certification of programme compliance.

Existing clients that omit methodology retain their research-type template and version. Existing workflow definitions and institutional approvals remain in force. Methodology milestones guide planning; they do not create permission to advance an institutional workflow.

## Plan and private library

Plan & library contains actual versioned documents for topic/gap identification, proposals, instruments, sampling, ethics/consent drafts, qualitative work, analysis plans, manuscripts and defence preparation. Starter worksheets contain headings and prompts only. Upload research documents or institution guidelines through the existing `MediaSecurityService`, including its configured size, extension and scanner policies. PDF/DOCX/TXT guidelines are accepted by this feature when allowed by the administrator's upload settings. CSV/XLSX uploads additionally require `research_datasets`.

The `2026_10_20_000006_create_research_resources.php` migration adds `research_resources`, linking a project to existing `ContentDocument`/`ContentVersion` and `MediaAsset` infrastructure. New research library files are always stored on the private local disk, even when general uploads target a public disk. Downloads require current project view permission, correct project/resource association, an accepted scanner status and no quarantine. They use `SafeFileDeliveryService` with no-store headers. Plan pages are private/no-store. Deployments must back up `storage/app/private` with the database.

Guideline uploads retain the original and a bounded, best-effort extracted text version. DOCX archives have expanded-size/entry limits before extraction. Heading suggestions are a preview, not an automatic change. Confirm the original and submit 1–30 headings to apply them. Matching sections are reused, new sections are inserted first, and all existing sections and drafts are preserved. No content is deleted. Scanned PDFs may need manual transcription; complex tables/formatting are not reliably extracted. Set font, point size, line spacing, margins, citation style and institutional instructions in the plan. An optional plain-text institutional bibliography pattern accepts `{authors}`, `{year}`, `{title}`, `{container}`, `{identifier}` and `{number}`; it must include the title. Library bibliography previews and exports use that pattern with stored metadata; in-text citations retain the selected base style. Patterns never execute code or fetch invented missing fields. Institutional rules and supervisor decisions remain authoritative.

Archive library resources to hide them without deleting documents, versions or attachments; Include archived resources makes them accessible again. Saved resource content can be inserted into an unlocked section as a new recoverable version. Concurrent section changes produce a conflict rather than overwriting work.

## Writing and collaboration

The existing focused section editor now autosaves after 1.6 seconds without input. Successful changed saves create `ContentVersion` snapshots; unchanged requests do not create duplicate versions. The form sends `expected_version`, checked under a database row lock in `ContentWorkspaceService`. A stale session receives HTTP 409. Keep the tab open and copy unsaved work before reloading to compare versions. Offline, expired-session, permission and locked-section states are explicit. Confidential drafts are not put in localStorage by the new autosave code. The browser warns before leaving unsaved work. Standard form submission remains usable if the enhancement script fails to load.

Version history exposes previous content and restore-as-new-version controls. Rich text supports headings, lists, emphasis, links and basic tables. Section progress derives from stored completion values; blank work starts at zero. Milestone updates no longer overwrite writing progress. These are workflow indicators, not a quality score.

Supervisor invitation/acceptance, comments, corrections, section approval, tasks, deadlines and collaboration reuse the existing routes, policies and feature checks. Requesting a correction clears the section lock/approval so the author can respond. A locked approved section cannot be edited through an administrator bypass. Restoring a section updates progress. The API section endpoint uses the same save path and accepts `expected_version`.

## Analysis and evidence

The single-proportion sample planner computes `n0 = z² p(1-p)/e²`, optional finite-population correction, completed responses rounded up and invitation count adjusted by the expected response rate. It records every assumption. It is for simple random sampling precision planning, not power analysis, cluster sampling or qualitative sample adequacy.

When `research_datasets` is enabled, CSV analysis accepts a header plus at most 5,000 rows, 50 columns and 2 MB. It computes numeric observed/missing counts, mean, median, sample SD, minimum and maximum; categorical output contains counts of observed/missing/distinct values. A table and completeness chart expose the same results. Only the summary, SHA-256 input checksum and actor are saved; the raw CSV is not retained by this tool. Empty cells are missing; other missing-value codes must be recoded before upload. No category labels, raw participant rows or fabricated observations are added to results. Stored computed results are immutable; create an interpretation worksheet or run a new analysis rather than edit them.

Qualitative coding requires a project-bound source document/register, a locator, a researcher-supplied anonymised excerpt, code definition and optional theme/reflexive memo. Coded records preserve provenance and support a theme/code/excerpt count matrix. Archive superseded coding records; do not treat counts as proof of thematic significance. Raw identifiers and consent/ethics documents should not be included in routine AI prompts.

Specialised inferential tests, meta-analytic pooling, bibliometric networks, simulation execution and model training are research decisions supported by workflow guidance and evidence documents; this release does not execute those external analytical environments. Use appropriate validated tools and import the resulting evidence. No UI should describe these recommendations as computed findings.

The existing scholarly provider discovery/reference library, six citation styles, similarity service and contextual `research_assistant` are reused. AI context includes the saved setup and selected planning documents, within project authorisation. Uploaded documents are untrusted evidence, never system instructions. AI must request missing evidence and never fabricate sources, participant responses, findings, experiments, ethics approval or bibliographic identifiers. Availability, credentials, limits and queues still come from the central AI architecture. A similarity score is not a misconduct finding.

## Reporting standards and academic sources

Saved reporting-preparation checkboxes provide short, authored self-review prompts for each selected framework. They are not the complete checklists or a compliance assessment. Reporting links are scoped by study design and remain links to the complete official checklists; they do not certify compliance:

- [EQUATOR reporting guideline library](https://www.equator-network.org/) — design-dependent reporting guidance.
- [PRISMA 2020](https://www.prisma-statement.org/prisma-2020) and [PRISMA-ScR](https://www.prisma-statement.org/scoping) — systematic and scoping reviews respectively.
- [CONSORT and SPIRIT 2025](https://www.consort-spirit.org/) — randomised trial results and protocols. These do not apply indiscriminately to every clinical study.
- [STROBE](https://www.strobe-statement.org/) — specified observational epidemiological designs.
- [COREQ](https://www.equator-network.org/reporting-guidelines/coreq/) — interviews and focus groups; [SRQR](https://www.equator-network.org/reporting-guidelines/srqr/) — qualitative reporting.
- [Cornell evidence synthesis guidance](https://guides.library.cornell.edu/evidence-synthesis/) — selecting a review approach and documenting searches.
- [Stanford design project guide](https://dschool.stanford.edu/tools/design-project-guide) — iterative design practice, requirements and evaluation.
- [University of Lagos Pharmacognosy graduate handbook](https://pharm.unilag.edu.ng/wp-content/uploads/2025/06/Pharmacognosy-Graduate-Program-Handbook.pdf) — an institutional example demonstrating that submission structure must be confirmed against the relevant department's rules.

Consulted on 20 October 2026. AcadSyntra's authored planning prompts are not reproductions of these institutions' policies or full reporting checklists.

## Exports

PDF and HTML exports use sanitised stored sections, contents links and selected formatting. Blank reference sections can render the project's saved bibliography using stored source metadata. DOCX export creates a genuine ZIP-based Office Open XML package with paragraph styles, basic tables, emphasis and a contents field. Update the contents field in Word/LibreOffice to paginate it. Verify final institutional formatting, complex tables, mathematics, figures and bibliographic variations manually; exports do not create signatures or approval certificates.

## Login and onboarding

Auth screens share the AcadSyntra identity, responsive layout, field-associated validation, accessible password controls, recovery links and loading states that reset after browser history navigation. Login throttling, password policy, CSRF, session regeneration, email verification toggle and password-reset providers are retained.

Public account paths include students, lecturers/supervisors, researchers, independent professionals, authors/publishers and other academic participants. The latter paths retain the neutral member role and existing permissions; selecting a path never grants administrator authority.

The existing six-step onboarding persists each completed step and resumes from the saved state. Programme/discipline, level and interests populate the existing profile fields. The final step can launch a research or final-year project wizard; project creation remains an explicit user action with normal feature/policy checks. Existing completed users are not forced through onboarding again. Onboarding now also requires the current two-factor challenge to be satisfied. Supervisor/researcher context does not introduce new administrator roles.

## Validation and deployment

Run migrations with the PHP version supported by the installed Laravel dependencies, build assets and refresh the appropriate application caches. DOCX creation/inspection needs PHP ZIP support; document export also needs DOM support. This feature adds no Composer dependency and does not reset existing administrator settings or project data.

```bash
php artisan migrate --force
npm run build
php artisan test
php scripts/check-documentation.php
php scripts/check-mysql-identifiers.php
```

Focused suites: `ResearchStudioEnhancementTest`, `ResearchOnboardingExperienceTest`, existing research/identity/auth suites, and `tests/Browser/research-studio.test.mjs`. The browser fixture script refuses any database except a testing SQLite database named `/private/tmp/acadsyntra-studio-*`. It must never be run against live data. Optional research modules remain disabled until an administrator enables them; upload extension and malware scanner settings remain authoritative.

### Existing static-audit findings

The repository-wide AI routing audit still reports five previously exposed study-tool settings without verified entry points (`study_planner`, `practice_generator`, `flashcard_generator`, `reflection_coach`, `annotation_summary`). The seeder static audit still flags direct writes in `MarketingDemoSeeder` and the existing guarded update in `UniversitySeeder`. The existing assistant-resolver static audit also flags canonical tool/lecturer routing checks in the general assistant controller/resolver. These predate this research enhancement; the research workflow, feature-management, runtime, branding, environment, documentation and MySQL identifier checks are separate checks. Do not describe every repository preflight as passing.

### Local verification on 20 October 2026

The full PHP suite passed **421 tests / 2,295 assertions**. A final onboarding/profile-level check passed **4 tests / 38 assertions**. All **17 distinct browser checks** passed across the research, authentication/onboarding, public-site and push suites; the two public checks were rerun successfully after the build completed and the isolated contact-test flag was set. The production build and research/runtime/feature/branding/documentation/environment/MySQL preflights passed. The existing static-audit findings above remain.

The resource migration was applied locally after a SQLite backup. SHA-256 fingerprints across **213 pre-existing tables** were unchanged and `PRAGMA quick_check` returned `ok`. New research resources remain empty in the actual application database; browser fixtures and enquiries were confined to a temporary QA database.

For a fresh isolated browser run, first prepare a **testing** SQLite database at `/private/tmp/acadsyntra-studio-browser.sqlite` using `tests/Browser/prepare-research-studio.php` with `DB_CONNECTION=sqlite`, that `DB_DATABASE`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array` and a separate `APP_CONFIG_CACHE` path. Serve the same configuration locally on port 8013, build assets before testing, then run `node --test tests/Browser/research-studio.test.mjs`. Combined public tests need `PUBLIC_WEBSITE_TEST_URL=http://127.0.0.1:8013` and `PUBLIC_WEBSITE_ALLOW_CONTACT=1` only for this isolated installation. No live user, payment or institutional records should be used as browser fixtures.

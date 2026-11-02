# AcadSyntra performance investigation — 20 October 2026

Implemented and verified locally. This is not a production-capacity certification. Reproducible commands and deployment requirements are in [PERFORMANCE_OPERATIONS.md](PERFORMANCE_OPERATIONS.md); structured measurements are in [performance/2026-10-20-results.json](performance/2026-10-20-results.json).

## Root causes and evidence

1. **Unbounded admin rendering could occupy the web server before navigation.** The local catalog contains 3,821 research types and 382 workflows. Research Configuration rendered every workflow edit form and every type edit form; each type repeated all workflow options, producing about **1.46 million options**. On an isolated copy of the database, the original renderer was still running at a 12-second watchdog and had buffered **13,535,923 bytes**. Historical logs also contain 120-second execution failures and 256-MB memory exhaustion during rendering. Their original request URLs were unavailable, so individual historical errors cannot be conclusively attributed to a particular route.
2. **Speculative requests amplified that cost.** The global hover/focus/touch prefetcher could request arbitrary authenticated pages, including Configuration and Template Versions. Research responses explicitly use `private, no-store`, so those speculative document responses cannot accelerate a later navigation. They can consume the single local PHP worker. This is a concrete mechanism that can make New Project and Final-Year Project wait behind optional work; the user's exact historical pointer sequence was not recorded.
3. **Both creation modes used the same unnecessarily large payload.** Final-Year Project creation is `/research-studio/create?studio_mode=final_year`, not a separate frontend module. The controller selected complete research-type schemas/rules, fetched institutional departments/lecturers while institutional mode was off, and embedded the full methodology configuration despite the preview using only three fields.
4. **Shared costs affected every module.** Axios occupied most of the ordinary Blade JavaScript entry even though its consumers are optional Vue components. Browser push configuration was requested on ordinary pages for browsers that had never opted in. Arbitrary hover requests could also target expensive exports. The logout follow-up also found browser notification cleanup awaiting service-worker promises without a timeout.
5. **Research workspaces grew with accumulated data.** The editor loaded version bodies/comments for every chapter before choosing the active chapter. The plan loaded every resource document. Literature discovery loaded all saved references/notes and calculated synthesis even when the synthesis tab was not selected. Template management loaded all research types. Large PDF/DOCX exports ran inside web requests.

The empty independent creation form did not itself reproduce the historical 120-second failure. Its initial local display was already below 1.5 seconds. The work therefore addresses the demonstrated admin-render/prefetch mechanism and reduces normal creation costs, rather than claiming that a small form always took two minutes or blaming an unobserved AI-provider call.

## Changes delivered

- Configuration has independent workflow/type pages (10/15 rows); template management has 15-row pages. Lightweight workflow dropdown data is separate from editable schemas/stages. All later records remain reachable.
- Creation selects only type IDs/names and embeds only methodology label/decision/instruments. Institutional options load on activation, with tenant/role authorization, 25-row pages, search, cancellation of superseded requests, retry feedback and preserved selections/input. Existing validation and an explicit no-JavaScript fallback remain available.
- Arbitrary document prefetch is opt-in. Research creation links warm only the small JavaScript module. Authenticated HTML remains private and uncached.
- Axios loads with optional Vue screens; existing editor, AI, password-policy and push modules remain split. Ordinary browsers without push enrollment no longer request push configuration on every navigation. Existing SVG branding replaces the 26-KB shared logo raster without changing the brand.
- The centered, full-screen branded loader is restored for native navigation, searches and foreground mutations. Canceled actions, same-page anchors, new-window links and downloads do not leave an overlay. Duplicate native submissions are rejected, submitter values are preserved, and controls reset after back/forward navigation.
- Logout submits immediately without waiting for service-worker lookup, push cleanup requests or browser unsubscription. Web push enrollment records the current endpoint hash in the session; logout removes only matching subscriptions belonging to the signed-in user before invalidating the session. Browser unsubscription is best effort and cannot delay logout.
- Only the active chapter's history/comments are fetched, with 5/25-row pages. Optional literature/dataset relations wait until an applicable chapter is selected. Other chapters' version histories are excluded.
- Resources have 25-row pages; only the selected resource loads its body. Literature discovery checks only visible results against saved references; saved/reading pages filter in SQL before pagination. Synthesis runs on its selected tab and compares only requested papers. Citation numbering includes the page offset.
- Large PDF/DOCX exports use a dedicated `exports` queue, with deduplication, progress, failure/retry behavior, private requester-authorized downloads, configurable private storage and daily artifact pruning. Small downloads retain their existing behavior. Export requests have a per-user rate limit. Search indexing/removal uses the `indexing` queue.
- A reversible migration adds research-type, supervisor, project-list and session-activity indexes. SQLite query-plan comparison confirms use of `research_type_options_idx` for the creation-option query after migration. Queue reservation expiry is at least 360 seconds, preventing premature reassignment of long jobs.
- Optional `Server-Timing` and slow-request logging record route, duration, status and memory without logging form input, credentials or query values. Bundle, query/payload, pagination and browser regression checks are included.

## Before and after measurements

Chrome/Playwright, unthrottled localhost, service workers blocked, five samples per creation mode. These are local samples, not field Core Web Vitals or statistically controlled production benchmarks. Filesystem warmth, server configuration and scheduling affect timings. Source payload reductions are deterministic.

The measurements below precede the branded-loader restoration and logout follow-up. Run the bundle and browser checks against the current build for current asset sizes and behavior.

| Measurement | Before | After |
|---|---:|---:|
| Research creation HTML | 83,320 B | 48,556 B |
| Final-Year creation HTML | 83,328 B | 48,564 B |
| Global JavaScript entry | about 54.5 KB | 16,667 B |
| Global JavaScript gzip | about 20.75 KB | 5,825 B |
| Shared logo asset | about 26 KB PNG | 708 B existing SVG |
| Research median DOM-ready | 364.5 ms | 202.7 ms |
| Research median LCP | 360 ms | 212 ms |
| Final-Year median DOM-ready | 216.4 ms | 175.7 ms |
| Final-Year median LCP | 216 ms | 184 ms |
| Maximum measured CLS | 0.0128 | 0.0128 |
| Configuration render | incomplete at 12,002 ms | repeated-request median 401 ms |
| Configuration HTML | 13.5 MB already buffered at watchdog | 523,476 B complete |

The after Configuration benchmark includes a separate 630.75-ms application bootstrap and a first/cold request; repeated request p95 was 884.66 ms, with a 20-MiB process peak. The old watchdog result is censored, not a measured completed-render duration. No creation request loaded the editor/Vue bundles or institutional option endpoints before activation. The browser budget check passed after the stress test, confirming ordinary page behavior recovered.

INP is not claimed: the short scripted interactions did not provide a meaningful field INP sample. HTML load results also do not establish the 300-ms p95 target for every API endpoint.

## Concurrent-user checks and capacity limits

The final valid run used 1,000 distinct authenticated fixture users, isolated SQLite, file cache, `PHP_CLI_SERVER_WORKERS=4`, route caching and OPcache. Stages used a 10-second ramp, 5-second hold, 10-second think time, 3-second request timeout and 3-second cooldown. Every success required actual authenticated page content; HTTP status alone was insufficient.

| Virtual users | Requests | Successful | Errors/timeouts | All-request p95 | Peak requests in flight |
|---:|---:|---:|---:|---:|---:|
| 100 | 145 | 145 | 0 | 506.9 ms | 8 |
| 500 | 592 | 20 | 572 | 3,023.0 ms | 160 |
| 1,000 | 1,130 | 10 | 1,120 | 3,503.5 ms | 393 |

**The 500- and 1,000-user stages failed.** This local configuration is not validated for those levels. Virtual-user counts include think time; they are not simultaneous open requests. Stages ran sequentially, and backlog from the 500-user stage can affect the 1,000-user stage, so these are short saturation checks, not independent steady-state capacity estimates. Successful application-handler medians were 59.71/264.16/586.58 ms while client medians grew much more, consistent with request/bootstrap pressure and worker queueing. This does not isolate database locks or prove which production resource will limit capacity.

An earlier multiworker launch used the wrong document root and returned HTTP-200 PHP error documents. Those results were discarded. Browser verification caught the setup error; the load harness now performs an authenticated preflight and validates expected page content on every response. No invalid run is included in the table or structured results. Two harness regression tests verify rejection of HTTP-200 error documents and correct counting of valid responses.

Production-like PHP-FPM/MySQL/Redis staging tests with sustained traffic and worker/database monitoring remain necessary. No 500/1,000-user production guarantee, production API p95 or field INP result is asserted.

## Validation and audit scope

- Full PHP suite: **431 tests, 2,457 assertions passed**. A subsequent focused run passed **47 tests, 698 assertions**, and final export checks passed 2 tests/19 assertions after the export rate-limit change.
- Browser workflow suite: **5/5 passed**, covering institutional retry/input preservation, authentication and responsive layouts at 320/390/768/1440 px, both creation modes, autosave conflict protection, version history, guideline review, supplied-data analysis, exports and resumable onboarding.
- Browser performance check passed for both pages with HTML under 60 KB, DOM-ready under 1.5 seconds, LCP under 2.5 seconds, CLS at most 0.1 and no premature optional-module requests. Global bundle budgets passed at 5,825 gzip bytes JS and 18,712 gzip bytes CSS.
- Existing performance preflight and MySQL identifier checks passed. The new migration ran successfully on the isolated database copy; MySQL execution plans and production migration timing remain deployment checks.
- Reviewed shared layout/bootstrap, authentication/session/feature middleware, settings caches, service-worker cache rules, dashboard queries, course/admin list patterns, Research Studio controllers/views, queue jobs/configuration and deployment guidance. Existing bounded dashboard lists, grouped aggregates, feature/settings invalidation and lazy editor behavior were retained. No claim is made that every production route has been profiled with representative customer data.

The live database, secrets, production services and existing user log edits were preserved. See the operations guide before rollout: apply the migration, rebuild/cache assets and routes, start the separate export worker, retain the scheduler, and use shared private export storage/cache for multiple nodes. Guideline extraction retains its file/archive limits, and full-library synthesis runs only when requested; both remain synchronous and their production cost still needs measurement. Deploying Redis/PHP-FPM/CDN infrastructure and certifying production capacity require the actual hosting environment.

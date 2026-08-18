# AcadFlow AI Architecture Audit & Central Provider Routing — 15 Aug 2026

## Scope

This report documents the audit and implementation performed against the latest AcadFlow source used as the baseline for this release. The objective was not to add another AI configuration layer; it was to make the existing AI Settings, provider adapters, rule engine, grounding, AI features, queue jobs, prompts, caching and observability operate through one authoritative runtime path.

Final runtime path:

```text
AI Settings / SettingService
        ↓
AiRuntimeConfigService
        ↓
AiRouter
        ↓
AiProviderRegistry
        ↓
Primary Provider → Fallback → Secondary Fallback
        ↓
Normalized AiResponse
        ↓
Feature-specific validation / grounding / UI
```

The Rule Engine remains a separate deterministic subsystem. It is active by explicit `rule_based` mode or as an explicitly enabled Hybrid fallback. It is not an invisible provider.

---

## 1. Problems discovered

The audit found these important inconsistencies in the prior implementation:

1. `AiManager` did not reliably honor `ai_default_provider`; provider ordering could be influenced by the older `ai_provider_priority` path.
2. `ExternalProvider` had fallback behavior that blurred the boundary between an external provider response and deterministic Rule Engine output.
3. Legacy toggles (`ai_enable_external_ai`, `ai_enable_hybrid_mode`, `ai_enable_rule_engine`) overlapped with the newer `ai_mode` setting.
4. Rule-based behavior could become the effective answer even when Provider AI had been selected, making provider switching difficult to verify.
5. Provider/model/feature routing data was distributed between config defaults, settings and feature code rather than being resolved through one runtime service.
6. Provider credentials and health information did not have one registry/factory responsible for construction and capability metadata.
7. AI usage logs did not contain enough routing metadata to answer “which provider/model actually handled this request?”.
8. Grounded Companion could correctly retrieve publication sources, but the old provider/fallback behavior made it possible for its runtime behavior to look indistinguishable from the Rule Engine.
9. Some deterministic rule settings were not consistently tenant-aware.
10. System Settings still needed to be clearly separated from AI-specific runtime configuration.
11. The main assistant did not expose the actual resolved model to the user/admin-facing UI.
12. There was no centralized administrator diagnostics view for routing, provider health and recent provider usage.
13. The old seeder would still create deprecated legacy AI toggles on a fresh installation.

---

## 2. Duplicate AI settings discovered

The following keys were identified as legacy/overlapping runtime controls:

```text
ai_enable_external_ai
ai_enable_hybrid_mode
ai_enable_rule_engine
ai_provider_priority
```

Authoritative replacements are now:

```text
ai_mode
ai_default_provider
ai_default_model
ai_fallback_provider
ai_fallback_model
ai_secondary_fallback_provider
ai_secondary_fallback_model
ai_automatic_failover
ai_feature_{feature}_provider
ai_feature_{feature}_model
ai_feature_{feature}_rule_fallback
```

Existing legacy database rows are **not destructively deleted**. The migration moves them into `ai_legacy` for upgrade/audit safety, but runtime code no longer consumes them. Fresh installations no longer seed those obsolete keys.

Two additional AI settings were found to be effectively dead configuration rather than runtime controls:

```text
ai_max_document_size_mb
ai_document_formats
```

No active AI upload/ingestion path in this build consumes those values. Keeping them visible would violate the rule that every AI Settings control must affect real runtime behavior, so they were removed from the live AI Settings UI/seeder and preserved under `ai_legacy` rather than deleted. The unused `AI_MAX_DOCUMENT_SIZE_MB` bootstrap variable was also removed from `.env.example`.

The audit also found older feature-routing keys for AI features that have no verified runtime entry point in this build. Those values are preserved under `ai_legacy`, while the live feature matrix exposes only the AI features actually wired through `AiManager`.

---

## 3. Settings moved from System Settings

The general Settings controller now excludes both:

```text
ai
ai_legacy
```

AI-specific configuration is managed through:

```text
Admin / Settings → AI Settings
```

A direct **AI Settings** entry is provided from the Settings interface for authorized administrators.

General System Settings continue to own non-AI application configuration. Feature & Module Management continues to own release/maintenance availability. AI Settings owns AI runtime behavior and provider routing.

---

## 4. Legacy settings migration

Migration:

```text
database/migrations/2026_08_15_080000_consolidate_ai_runtime_settings.php
```

Behavior:

- uses `insertOrIgnore` for newly required canonical settings;
- does not truncate or reset Settings;
- does not overwrite existing provider credentials or administrator choices;
- marks discovered legacy toggles as `ai_legacy`;
- converts the old `ai_fallback_provider=rule_based` meaning to `none`, because deterministic fallback is now represented explicitly by Hybrid mode + per-feature rule fallback;
- uses a non-destructive `down()` path.

The existing idempotent seeding policy remains intact.

---

## 5. Central AI architecture used

### `AiRuntimeConfigService`

New authoritative runtime resolver:

```text
app/Services/Ai/AiRuntimeConfigService.php
```

Priority:

```text
1. Feature-specific institution/global runtime override
2. Institution/global AI SettingService configuration
3. Secure config/.env bootstrap fallback
4. Application defaults
```

Feature code should not decide the provider by reading `.env` directly.

### `AiRouter`

`AiRouter` now resolves:

- AI mode;
- feature primary provider/model;
- global vs feature override;
- fallback provider/model;
- secondary fallback;
- provider chain;
- capability requirements;
- compatibility;
- explicit Hybrid rule fallback state.

### `AiProviderRegistry`

One provider registry/factory creates adapters. No feature controller/service should directly instantiate a provider.

### `AiManager`

The existing `AiManager` remains the single application gateway and now executes normalized `AiRequest` objects through the router.

---

## 6. Provider routing implementation

Strict external provider chain:

```text
Feature override (if configured)
        ↓ otherwise
Global Default Provider
        ↓ failure
Fallback Provider
        ↓ failure
Secondary Fallback Provider
        ↓
Controlled AI_ALL_PROVIDERS_FAILED response
```

`ai_provider_priority` is not used by runtime routing.

Each attempt is recorded in response metadata as a provider attempt with provider, model, role, status and error code.

---

## 7. Default provider implementation

`ai_default_provider` now actually controls features configured as **Use Global Default**.

`AiRuntimeConfigService::featurePrimary()` resolves the provider/model at request time. It does not bake the selected provider into a queue payload or rely on config cache.

Changing the Default Provider through AI Settings invalidates runtime/cache generations immediately.

---

## 8. Fallback provider implementation

Supported chain:

```text
Primary
Fallback
Secondary Fallback
```

Fallbacks are external provider fallbacks. The Rule Engine is not treated as a provider fallback.

Rule fallback is separate and only permitted when:

```text
AI Mode = Hybrid
AND
feature_rule_fallback = enabled
```

Provider AI mode never silently changes into Rule-Based mode when providers fail.

---

## 9. AI mode implementation

Supported modes:

### Provider AI (`provider`)

- external configured provider chain only;
- Rule Engine may still be used internally for deterministic preparation/guardrails, but its output does not replace a failed provider answer;
- all providers failing produces a controlled failure.

### Hybrid (`hybrid`)

- deterministic validation/retrieval + configured LLM reasoning;
- provider-first generative features actually invoke the selected provider;
- explicit per-feature deterministic fallback may be used and is clearly labeled/logged.

### Rule-Based Only (`rule_based`)

- no external LLM call;
- responses identify the Rule Engine as the source.

### Disabled (`disabled`)

- no provider processing;
- returns a controlled “AI assistance is currently unavailable” result.

---

## 10. Provider/model management

Providers already present in AcadFlow and retained:

```text
OpenAI
Claude / Anthropic
Gemini / Google
DeepSeek
Azure OpenAI
Ollama
Rule-Based Engine (deterministic, not an external provider)
```

No unsupported provider was invented.

Provider registry metadata contains:

- enabled/configured status;
- default model;
- configured model list;
- capabilities;
- credential requirements;
- endpoint support.

Feature routing validates that a selected model belongs to the selected provider's configured model list.

Model selectors in AI Settings update when provider selectors change.

---

## 11. Provider credentials

Provider secrets can be supplied securely through environment bootstrap or saved by the platform Super Admin.

Saved API keys are encrypted with Laravel `Crypt` and:

- never rendered back into the form;
- are represented only as Configured / Not configured;
- are never included in diagnostics responses;
- are never included in AI usage metadata;
- are not logged by provider exception handlers.

Institution administrators inherit platform provider configuration and may select allowed routes, but cannot read or replace platform secrets from the AI Settings UI.

---

## 12. Test Connection / provider health

Every supported external provider has **Test Connection**.

The test performs a real provider request using the currently entered values, without requiring the credential to be persisted first.

Normalized health states include:

```text
healthy
authentication_failed
rate_limited
timeout
model_unavailable
configuration_incomplete
unavailable
disabled
```

Provider health is cached for a short interval. Because provider credentials/endpoints/model registries are platform-owned, the provider-health cache is also global rather than duplicated once per institution. Saving provider configuration invalidates the affected global health cache immediately. An hourly `acadflow:ai-health` schedule is available to warm health state without making excessive provider calls.

---

## 13. Feature configuration matrix

AI Settings now includes a feature routing matrix with:

```text
Feature
Enabled
Provider (Use Global Default or provider override)
Model (Use provider/global model or explicit configured model)
Hybrid Rule Fallback
Resolved Provider / Model
```

The active AI feature registry now contains **16 feature keys with verified runtime entry points in this build**:

```text
submission_validator
plagiarism
writing_assistant
citation_assistant
study_assistant
lecturer_assistant
research_assistant
research_validator
assignment_assistant
siwes_assistant
project_assistant
material_assistant
discussion_assistant
knowledge_publication_validator
knowledge_moderation
knowledge_companion
```

Six previously dormant assistant concepts (`research_assistant`, `assignment_assistant`, `siwes_assistant`, `project_assistant`, `material_assistant`, and `discussion_assistant`) now have real authorized application entry points and execute through the central `AiManager` / `AiRouter`. Each specialized route now also uses the `ai.feature:<feature>` runtime middleware so a disabled assistant or globally disabled AI mode is rejected before retrieval/file-context construction. A production-safe prompt-baseline migration derives from the same active feature registry and adds only missing global prompts without overwriting existing administrator or tenant prompt customizations. Older labels that still have no verified runtime feature—such as `ai_search`, `ai_analytics`, `literature_review`, `moderation_assistant`, `recommendation_assistant`, and `semantic_discovery`—remain excluded from the live feature matrix so the admin UI does not advertise nonexistent behavior.

---

## 14. Grounded Companion improvements

The previous Grounded Companion intelligence work is preserved and now connected to the central provider router.

Pipeline:

```text
Input validation / gibberish guard
        ↓
Publication-specific intent normalization
        ↓
Retrieve ONLY current publication chunks
        ↓
Evidence/relevance gate
        ↓
AiManager → current configured provider/model
        ↓
Provider answerability
        ↓
Citation/source support validation
        ↓
Final answer / controlled abstention
```

Important behavior:

- `gsgshhshsh`-style gibberish is rejected before expensive provider use.
- the open web is not used;
- general provider knowledge is forbidden for publication-grounded answers;
- provider answers must cite supplied `[S#]` evidence;
- unsupported provider prose is withheld;
- in Provider AI mode invalid grounded provider output is **not silently replaced by Rule Engine output**;
- in Hybrid mode a deterministic extractive fallback is allowed only when that feature's Hybrid fallback setting is enabled;
- Grounded sessions record the actual provider/model/router fallback metadata;
- successful, validated grounded sessions may contribute to the existing conservative pattern-learning mechanism;
- protected publications remain authorization-protected at the backend.

---

## 15. Research Studio improvements

Current Research Studio AI path audited:

```text
ResearchValidationService
    → AiManager('research_validator')
    → central router
```

Research editor writing suggestions use the centralized writing endpoint and institution-aware editor AI settings.

Research validation continues to combine deterministic academic checks with centralized provider reasoning according to AI Mode.

No independent provider SDK call exists in Research Studio.

---

## 16. Assignment AI improvements

The current source contains assignment-related deterministic rule packs and general study/writing assistance, but no separate direct external `AssignmentAIProvider` implementation was discovered.

Therefore no duplicate assignment provider architecture was invented.

Assignment-related AI that exists continues through the existing central manager paths, including writing/study assistance and rule packs.

---

## 17. Submission AI improvements

Current submission background path:

```text
SubmissionAiAnalysisRequested
    → ProcessSubmissionAiAnalysis queue job
    → SubmissionValidatorModule / PlagiarismModule
    → AiManager
    → current runtime provider configuration
```

The job stores feature/user/submission state, not a frozen provider configuration, so provider settings are resolved when the job actually executes.

The submission job also respects centralized Feature Management before spending provider resources.

The plagiarism module documentation was corrected so it no longer implies an LLM is performing a live open-web plagiarism search. External academic-integrity/similarity providers remain a distinct integration.

---

## 18. Workflow AI audit

`WorkflowService` was audited.

The current source manages deterministic workflow transitions, requirements, notifications and audit logs. It does **not** currently contain LLM workflow nodes/actions that directly call OpenAI/Gemini/etc.

Therefore a fake “Workflow AI” provider layer was not added. If real AI workflow actions are introduced later, they must call `AiManager` with a registered feature key rather than constructing provider adapters directly.

---

## 19. General AI Assistant improvements

Current general assistant path:

```text
AiController
    → AcademicAssistantService
    → authorized DiscoverySearchService context
    → AiManager
```

The page now shows:

- AI Mode;
- resolved Provider;
- resolved Model.

Course/Knowledge answers retain source restrictions. Provider AI mode with source material no longer silently replaces an unverifiable provider response with deterministic prose. Hybrid/Rule-Based behavior is explicitly distinguished.

Writing and citation tools continue through their central `AiManager` modules.

---

## 20. Prompt management

`AiPromptService` remains the prompt composition layer and now composes and bounds provider context according to the live `AI Settings → Context Limit` value. Context reduction is performed before JSON encoding so a large submission/retrieval payload cannot create malformed half-truncated JSON. The service preserves the beginning and ending of long text, reduces oversized collections, and records the before/after character budget in internal prompt metadata.

It composes:

```text
Global AI instruction
+
Feature prompt/version
+
Tenant/user context
+
Retrieved authorized context
+
Current request
+
Security boundary
```

Grounded retrieved content is explicitly labeled as untrusted data rather than system instructions.

Backward compatibility protection ensures old/custom Grounded Companion prompt versions that omit `{{context_json}}` still receive the authorized context rather than accidentally allowing an ungrounded provider request.

---

## 21. Performance improvements

Implemented or preserved:

- SettingService cached configuration;
- runtime routing fingerprint;
- immediate cache generation invalidation after AI settings changes;
- response cache key changes when provider/model/mode/routing changes;
- provider health cache;
- bounded Grounded retrieval;
- the configured `ai_context_limit` is now enforced before provider dispatch using deterministic UTF-8/JSON-safe context reduction; large lists and text are shortened before JSON encoding, and prompt metadata records whether truncation occurred;
- no repeated open-web requests;
- queue jobs resolve current settings when executing;
- rate limiter reads institution-aware runtime AI rate limit;
- deterministic Grounded input validation occurs before provider calls.

No user-specific grounded answer is deliberately cached under a cross-user key.

---

## 22. Security improvements

Audit and implementation cover:

- encrypted provider secrets;
- no provider secrets in UI/diagnostics/log metadata;
- authorization-protected AI Settings;
- platform-only provider credentials, endpoints and provider model registries;
- institution-aware runtime routing/default/model settings;
- explicit inheritance: platform provider configuration → institution runtime routing override → feature override;
- tenant-aware Rule Engine settings;
- course/Knowledge access checks before grounded context retrieval;
- Grounded prompt-injection filtering;
- retrieved documents treated as untrusted data;
- no live arbitrary-URL web research capability;
- provider request errors normalized rather than dumping provider secret-bearing internals;
- Feature Management continues to protect user-facing AI routes.

---

## 23. Queue/cache improvements

AI jobs use the existing queue architecture. The provider is resolved when the job executes rather than being serialized as stale provider configuration. AI-specific background jobs now explicitly use the configured `AI_QUEUE_CONNECTION` when present and the `ai` queue name; otherwise they inherit Laravel's default queue connection. This makes the existing infrastructure setting functional instead of decorative.

The Composer development command was also corrected to run `queue:work` across `default,ai,indexing,analytics`, so local development does not leave AI/indexing/analytics work waiting while only the default queue is consumed.

Relevant queues remain compatible with the Redis deployment design:

```text
default
ai
indexing
analytics
```

AI response caching uses a routing fingerprint containing the current mode/provider chain/feature state/grounding state so a changed provider cannot accidentally receive a cached response generated under an older provider route.

---

## 24. Observability and diagnostics

Migration:

```text
database/migrations/2026_08_15_081000_expand_ai_usage_observability.php
```

AI usage records now support:

```text
request_id
provider
model
fallback_used
fallback_provider
error_type
grounding_used
metadata
```

An administrator diagnostics view shows:

- current AI mode;
- default provider/model;
- fallbacks;
- provider status/capabilities;
- feature route matrix;
- cache generation;
- queue connection;
- recent requests with provider/model/fallback/error/latency.

It does not expose prompts, answers or API keys unnecessarily.

---

## 25. Database/migration changes

Added:

```text
database/migrations/2026_08_15_080000_consolidate_ai_runtime_settings.php
database/migrations/2026_08_15_081000_expand_ai_usage_observability.php
```

Both use short explicit index names where indexes are added. Existing administrator data is preserved.

---

## 26. Files modified

Compared with the idempotent-seeder baseline, the following existing files were modified:

```text
.env.example
app/Ai/AiAnalytics.php
app/Ai/AiCache.php
app/Ai/AiManager.php
app/Ai/AiRouter.php
app/Ai/Contracts/AiProviderInterface.php
app/Ai/Contracts/AiResponse.php
app/Ai/Features/CitationAssistantModule.php
app/Ai/Features/PlagiarismModule.php
app/Ai/Features/SubmissionValidatorModule.php
app/Ai/Features/WritingAssistantModule.php
app/Ai/Providers/AzureOpenAiProvider.php
app/Ai/Providers/ClaudeProvider.php
app/Ai/Providers/DeepSeekProvider.php
app/Ai/Providers/ExternalProvider.php
app/Ai/Providers/GeminiProvider.php
app/Ai/Providers/OllamaProvider.php
app/Ai/Providers/OpenAiProvider.php
app/Ai/Providers/RuleBasedProvider.php
app/Ai/Rules/BaseRulePack.php
app/Ai/Rules/RuleEngine.php
app/Enums/AiMode.php
app/Http/Controllers/AiController.php
app/Http/Controllers/SettingsController.php
app/Listeners/DispatchSubmissionAiAnalysis.php
app/Models/AiUsageLog.php
app/Providers/AppServiceProvider.php
app/Services/Ai/AcademicAssistantService.php
app/Services/Ai/AiPromptService.php
app/Services/Ai/GroundedCompanionService.php
app/Services/Knowledge/ModerationService.php
app/Services/ResearchValidationService.php
composer.json
config/ai.php
database/seeders/SettingsSeeder.php
resources/views/ai/assistant.blade.php
resources/views/ai/settings.blade.php
resources/views/knowledge/form.blade.php
resources/views/research/show.blade.php
resources/views/settings/index.blade.php
routes/console.php
routes/web.php
tests/Feature/Knowledge/GroundedCompanionIntelligenceTest.php
```

---

## 27. Files added

```text
app/Ai/AiProviderRegistry.php
app/Ai/Contracts/AiRequest.php
app/Services/Ai/AiRuntimeConfigService.php
database/migrations/2026_08_15_080000_consolidate_ai_runtime_settings.php
database/migrations/2026_08_15_081000_expand_ai_usage_observability.php
docs/ACADFLOW_2026_08_15_AI_ARCHITECTURE_AUDIT.md
resources/views/ai/diagnostics.blade.php
scripts/check-ai-central-routing.php
tests/Feature/AiCentralProviderRoutingTest.php
```

No working AI provider file was removed merely to simplify the refactor. No existing source file was deleted by this AI architecture audit.

---

## 28. Tests added

`tests/Feature/AiCentralProviderRoutingTest.php` covers:

1. OpenAI Default Provider is actually used.
2. Changing Default Provider to Gemini changes the next runtime request.
3. Feature-specific provider override takes priority over global default.
4. Primary failure uses the configured first fallback.
5. Primary + first fallback failure uses the configured secondary fallback.
6. Disabled primary provider is not called and fallback may handle the request.
7. Runtime SettingService provider changes are visible without rebuilding Laravel config cache.
8. Institution runtime routing can override the platform default while platform provider protocol/model configuration remains authoritative.
9. Rule-Based Only makes no external provider request.
10. Disabled mode makes no provider request.
11. A disabled AI sub-feature makes no provider request.
12. Configured context limits bound large provider context safely.
13. Provider mode failure does not silently return Rule Engine output.
14. Authentication failures are normalized without Rule Engine fallback.
15. A rate-limited primary provider can fail over to the configured fallback.

`GroundedCompanionIntelligenceTest` was extended so Grounded Companion provider metadata follows runtime Default Provider switching.

---

## 29. Static checks

New checker:

```bash
php scripts/check-ai-central-routing.php
```

It verifies:

- required central AI files exist;
- provider construction is centralized;
- external adapters have no Rule Engine dependency;
- deprecated runtime toggles are not consumed by application code;
- AI feature calls correspond to registered feature keys;
- Grounded Companion has source/provider fallback safeguards;
- System Settings excludes both canonical and legacy AI groups;
- provider endpoints are not duplicated throughout unrelated feature code;
- inactive/deprecated AI features are not re-seeded as live controls;
- provider credentials/protocol configuration remain platform-owned;
- AI background jobs honor `AI_QUEUE_CONNECTION` and the `ai` queue;
- AI Settings saves visibly invalidate runtime and AI response cache generations.

The project's existing environment, MySQL identifier, feature-management, runtime/Blade, grounded-companion and seeder preflights remain part of final validation.

---

## 30. Provider switching acceptance behavior

Expected runtime behavior after migration:

```text
AI Mode = Provider AI
Default Provider = Gemini
Default Model = configured Gemini model
Fallback Provider = OpenAI
```

A feature configured as `Use Global Default` resolves Gemini first.

If Gemini succeeds:

```text
provider = gemini
fallback_used = false
```

If Gemini fails with a retryable/unavailable condition and OpenAI is configured:

```text
provider = openai
fallback_used = true
fallback_provider = openai
```

If the administrator changes Default Provider to OpenAI, future non-cached requests route to OpenAI. Cache keys include routing state and AI Settings saves invalidate AI runtime/cache generations.

---

## 31. Remaining limitations

These are intentional limitations of the **actual current source**, not hidden simulated capabilities:

1. **No live open-web search adapter exists.** `ai_web_research_enabled` therefore stays disabled. An LLM provider is not described as “web research”.
2. **No external embedding adapter exists in the supported provider contract.** Current semantic indexing/search uses AcadFlow's existing local deterministic embedding/index approach. It is not falsely labeled as OpenAI/Gemini embeddings.
3. **No LLM workflow node currently exists in `WorkflowService`.** A fake Workflow AI implementation was not invented.
4. **Streaming is not currently implemented by the existing feature response architecture**, so no nonfunctional Streaming switch was added merely to fill the UI.
5. **Full PHPUnit/Laravel HTTP execution requires Composer dependencies.** The supplied source baseline does not include `vendor/`, and Composer is unavailable in the build environment used for this source regeneration. Tests are included but live framework execution cannot honestly be claimed here.
6. **Provider Test Connection requires real credentials and network access** on the deployed server.
7. Exact provider pricing changes over time; the system only estimates costs from administrator/environment configured rates and does not invent live prices.

---

## 32. Deployment

After replacing the production source:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan queue:restart
php artisan optimize
```

For local development:

```bash
php artisan optimize:clear
php artisan migrate
php artisan queue:restart
```

If using Redis queue workers, ensure the configured workers are running for the AcadFlow queue names used by the application.

---

## 33. Final source-of-truth rule

The resulting architecture follows:

```text
Feature override
    ↓
Global/institution AI Settings
    ↓
Secure environment/bootstrap fallback
    ↓
Application default
```

and all provider execution follows:

```text
AI feature
    ↓
AiManager
    ↓
AiRuntimeConfigService / AiRouter
    ↓
AiProviderRegistry
    ↓
Provider adapter
```

No application feature should secretly choose OpenAI/Gemini/Claude/etc. outside that path.

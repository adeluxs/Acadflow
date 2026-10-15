# AcadFlow Grounded AI Companion Intelligence Upgrade

## Purpose

This update strengthens the Knowledge Hub Grounded AI Companion so it behaves as a **publication-scoped academic assistant**, not a general chatbot that tries to answer every string submitted to it.

A question such as `gsgshhshsh` is now rejected before an external AI provider is called. A meaningful but unrelated question is rejected if the current publication does not contain adequate evidence. Provider output is validated before it is shown.

## New request pipeline

```text
User question
   ↓
Input intelligence guard
   ├─ keyboard smash / gibberish / excessive symbols → reject
   ↓
Academic intent classification
   ↓
STRICT current-publication retrieval
   ↓
Publication relevance/evidence gate
   ├─ unrelated / unsupported → reject
   ├─ not indexed → queue indexing job + ask user to retry
   ↓
Existing AcadFlow AiManager
   ├─ rule based
   └─ configured external provider / hybrid
   ↓
Strict Grounded Companion prompt
   ↓
Citation + source-support validator
   ├─ weak / uncited provider answer → safe extractive fallback
   ↓
Grounded answer + stored S1/S2... evidence
```

## 1. Gibberish and low-quality input detection

`GroundedQuestionIntelligenceService` runs locally before `AiManager`.

It checks:

- minimum meaningful text length;
- amount of readable alphabetic/numeric content;
- excessive symbols/noise;
- repeated-character patterns;
- common keyboard-smash patterns;
- long tokens with implausible vowel/consonant structure;
- publication vocabulary before rejecting unusual technical terms.

The reported example `gsgshhshsh` is classified as likely gibberish and does not reach an external AI provider.

## 2. Intent recognition

Questions are classified into useful academic intents such as:

- summary;
- methodology;
- findings;
- limitations;
- conclusion;
- evidence;
- citation/source location;
- comparison;
- definition;
- explanation;
- general question.

Intent is used to improve retrieval without letting generic intent words make an unrelated topic look relevant.

## 3. Retrieval is scoped before ranking

The previous companion performed a broad Knowledge Hub retrieval and then filtered results down to the current publication.

The new implementation uses:

```php
DiscoverySearchService::relevantChunksForSubject(...)
```

The exact publication is selected **before** chunks are ranked.

This prevents another publication from influencing whether the current question looks answerable.

For broad summary requests, `representativeChunksForSubject()` samples the publication across its length so summaries are not accidentally based on one narrow section.

## 4. Relevance gate

A syntactically valid question is not automatically sent to AI.

Specific questions must have real evidence in the current publication. The gate combines:

- topical lexical coverage;
- publication-wide topic overlap;
- local semantic similarity;
- stricter coverage requirements for short/specific questions.

If evidence is weak, AcadFlow replies that the publication does not support the question instead of generating a generic response.

## 5. No open web

The Grounded AI Companion remains closed to the open web.

The payload explicitly declares:

```text
allow_open_web = false
allow_general_knowledge = false
reject_unsupported_questions = true
```

The provider is used only as a reasoning/writing engine over authorized publication excerpts.

## 6. Strict external-AI prompt version

A new global `knowledge_companion` prompt version 2 is installed by migration.

An important issue was fixed: the old generic prompt template did not necessarily include `{{context_json}}`, meaning a configured external provider could receive a generic instruction without the actual grounded question/source context.

Version 2 explicitly includes:

```text
{{context_json}}
```

and requires JSON fields:

- `answerable`
- `answer`
- `confidence`
- `human_review_required`
- optional `reason`

If a provider decides the evidence cannot answer the question, AcadFlow accepts the abstention instead of forcing an answer.

Institution-specific custom prompts are not overwritten by the migration.

## 7. Provider answer validation

A response is no longer accepted merely because it contains one `[S1]` marker.

`GroundedAnswerValidator` checks:

- all source references point to real supplied sources;
- a configurable percentage of substantive sentences contain citations;
- cited sentences have support in the excerpts they cite;
- unsupported URLs are not introduced;
- the response does not claim to have searched the web or used outside publication knowledge.

If validation fails, the fluent external-provider response is discarded and AcadFlow returns a directly source-backed extractive fallback.

## 8. Adaptive pattern learning

The companion now has conservative **pattern learning**.

This is not foundation-model training and it does not send previous user questions to the open web for training.

For each publication, AcadFlow builds a cached pattern profile from previous successful grounded sessions. It learns signals such as:

- common successful academic intents;
- recurring valid topic terms;
- patterns from answers users marked Helpful.

It deliberately excludes:

- input rejected as gibberish;
- questions rejected by the evidence gate;
- low-confidence sessions;
- responses later marked `Not helpful`.

This allows retrieval to adapt gradually to how a particular publication is being queried without allowing nonsense to poison the learned profile.

## 9. User feedback

The companion answer page now provides:

- **Helpful**
- **Not helpful**

Feedback is stored inside the existing `ai_grounding_sessions.metadata` field, so no duplicate feedback table was required.

The publication pattern cache is invalidated immediately after feedback so the next profile can incorporate the new signal.

## 10. Protected/premium content safeguard

The publication detail page previously showed the Grounded AI form to every authenticated viewer even when `$hasAccess` was false.

The form is now shown only when the user is entitled to the protected publication body.

`GroundedCompanionService` also enforces this on the backend. Direct web/API calls cannot use the AI companion to leak premium publication content.

Allowed access includes the existing rules for:

- free publications;
- institution publications for the matching institution;
- publication creator;
- administrators;
- active commerce entitlement for premium content.

## 11. Indexing recovery

If a publication has no search index, the companion does not hallucinate.

It queues:

```text
IndexSearchableContent → indexing queue
```

and tells the user to retry after indexing has run.

Production/local queue workers therefore still need to process the `indexing` queue.

## 12. Confidence correction

The companion stores grounding confidence as a 0–100 value.

The old view multiplied that value by 100 again, which could display values such as `7000%`.

The redesigned result page now displays the stored percentage correctly and shows:

- evidence match;
- answer confidence;
- grounding sources;
- citation/support validation details;
- fallback status;
- learned pattern signal count.

## 13. Admin settings

AI Settings now exposes a dedicated **Grounded Companion Intelligence** section for:

- pattern learning enabled;
- minimum question characters;
- gibberish threshold;
- relevance threshold;
- lexical evidence floor;
- citation coverage minimum;
- source support threshold;
- support coverage minimum.

These are behavior/configuration controls. Runtime availability remains in the centralized Feature & Module Management system under Knowledge AI Companion.

## 14. Files added

- `app/Services/Ai/GroundedQuestionIntelligenceService.php`
- `app/Services/Ai/GroundedAnswerValidator.php`
- `database/migrations/2026_08_14_220000_upgrade_grounded_companion_prompt.php`
- `tests/Feature/Knowledge/GroundedCompanionIntelligenceTest.php`
- `scripts/check-grounded-companion.php`
- `docs/ACADFLOW_2026_08_14_GROUNDED_AI_INTELLIGENCE_UPGRADE.md`

## 15. Main files changed

- `app/Services/Ai/GroundedCompanionService.php`
- `app/Services/Discovery/DiscoverySearchService.php`
- `app/Http/Controllers/KnowledgeEcosystemController.php`
- `app/Http/Controllers/AiController.php`
- `database/seeders/SettingsSeeder.php`
- `database/seeders/AcadFlowEcosystemSeeder.php`
- `resources/views/knowledge/show.blade.php`
- `resources/views/knowledge/companion.blade.php`
- `resources/views/ai/settings.blade.php`
- `routes/web.php`

## 16. Expected behavior examples

### Gibberish

Input:

```text
gsgshhshsh
```

Expected:

```text
Rejected by input_guard.
No external provider call.
No source excerpts presented as an answer.
```

### Meaningful but unrelated

Input on a photosynthesis publication:

```text
What is the weather forecast in Lagos tomorrow?
```

Expected:

```text
Rejected by scope_guard when the publication does not support the topic.
No guessing and no open-web answer.
```

### Valid specific question

```text
What does the publication explain about chlorophyll and photosynthesis?
```

Expected:

```text
Retrieve only chunks from the current publication.
Answer using those excerpts.
Cite claims with [S1], [S2], etc.
Validate citations/support before display.
```

### Broad summary

```text
Summarize the main argument and key points.
```

Expected:

```text
Use representative chunks across the same publication.
Do not search other Knowledge Hub publications or the open web.
```

## 17. Deployment

After replacing the source:

```bash
php artisan optimize:clear
php artisan migrate --force
```

For a local environment use:

```bash
php artisan migrate
```

Ensure the queue worker includes:

```text
indexing
```

because missing publication indexes are automatically queued for indexing.

## 18. Important limitation

The local semantic embedding engine is intentionally lightweight and offline. The new design therefore does not pretend that it is a full language model. Intelligence comes from layered controls: input-quality analysis, publication-scoped retrieval, lexical/semantic evidence gates, optional external reasoning, strict provider abstention, citation validation, extractive fallback, and adaptive successful-query patterns.

This architecture is safer than asking a language model to decide everything itself, because a provider cannot bypass the local evidence and output-validation gates.


## Additional intelligence refinements

- **Conservative typo tolerance:** meaningful academic terms of five or more characters can match small edit-distance mistakes (for example `photosynthsis` -> `photosynthesis`). Short/noisy tokens are intentionally excluded from fuzzy matching so typo tolerance cannot turn keyboard smash into evidence.
- **Adaptive prompt suggestions:** the companion promotes question intents that have historically produced successful, well-grounded, and helpful sessions for the current publication. The cached pattern profile is updated after successful answers without re-querying the database on every request. Negative feedback clears/rebuilds the profile so rejected or unhelpful patterns do not persist.
- **Pattern learning does not override evidence:** learned patterns can improve suggestions and weak ranking hints, but they cannot make an unsupported question answerable. The publication evidence gate remains authoritative.
- **Rare publication terminology is protected:** a long unusual token that genuinely appears in the publication is not rejected just because it resembles an uncommon word.


### Legacy/custom prompt fail-safe

`AiPromptService` now enforces the Grounded Companion contract even when an older or institution-specific custom prompt is active. If a `knowledge_companion` prompt omits `{{context_json}}`, AcadFlow appends the authorized publication context automatically. It also appends a non-negotiable grounding policy to the system prompt: publication context only, no open web/general model knowledge, abstain when unsupported, and cite substantive claims. This closes the grounding gap without deleting an institution's custom prompt history.

### Production-safe settings migration

A dedicated migration inserts the new Grounded Companion intelligence settings only when each key is missing. It does not run the general `SettingsSeeder` and therefore does not overwrite existing production configuration. Default answer validation is intentionally strict: 85% citation coverage, 20% cited-sentence support threshold, and 70% supported-citation coverage. Administrators can tune these values from AI Settings.

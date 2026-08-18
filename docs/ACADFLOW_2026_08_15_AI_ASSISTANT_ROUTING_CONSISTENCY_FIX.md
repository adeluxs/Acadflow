# AcadFlow AI Assistant Routing Consistency Fix

## Problem

The AI Assistant runtime request path already distinguished between `study_assistant` and `lecturer_assistant`, but the initial AI Assistant page always resolved the `study_assistant` route. A lecturer could therefore see provider/model metadata for the student assistant while the submitted request correctly used the lecturer assistant route.

The page also hosts Writing and Citation tools, which have their own feature routing keys. Provider/model badges could remain on the Ask/Explain route after switching tools before submitting.

## Fix

`AcademicAssistantService::featureFor()` is now the canonical feature resolver for the AI Assistant surface:

- Ask/Explain + student -> `study_assistant`
- Ask/Explain + lecturer -> `lecturer_assistant`
- Improve Writing -> `writing_assistant`
- Citation Review -> `citation_assistant`

Both page rendering and Ask/Explain runtime execution use this resolver. `AiController::assistant()` resolves route snapshots for all three tools through the central `AiRouter`, and the page updates its Mode / Provider / Model badges when the tool selector changes.

No provider SDK is called directly from the controller. Actual requests continue to flow through `AiManager` and the centralized provider router.

## Regression protection

Added:

- `tests/Feature/AiAssistantRoutingConsistencyTest.php`
- `scripts/check-ai-assistant-routing.php`

The preflight prevents reintroduction of a hardcoded `study_assistant` page route and verifies that all exposed assistant tools use the shared feature resolver.

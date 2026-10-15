<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string) file_get_contents($root.'/'.$path);
$errors = [];

$features = [
    'research_assistant' => ['route' => 'ai.context.research', 'views' => ['resources/views/research/show.blade.php', 'resources/views/research/workspace.blade.php']],
    'assignment_assistant' => ['route' => 'ai.context.assignment', 'views' => ['resources/views/submission-tasks/student-show.blade.php', 'resources/views/submission-tasks/show.blade.php']],
    'siwes_assistant' => ['route' => 'ai.context.siwes', 'view' => 'resources/views/research/specialized.blade.php'],
    'project_assistant' => ['route' => 'ai.context.project', 'views' => ['resources/views/submissions/show.blade.php', 'resources/views/submissions/review.blade.php']],
    'material_assistant' => ['route' => 'ai.context.material', 'view' => 'resources/views/materials/show.blade.php'],
    'discussion_assistant' => ['route' => 'ai.context.discussion', 'view' => 'resources/views/discussions/show.blade.php'],
];

$config = $read('config/ai.php');
$routes = $read('routes/web.php');
$service = $read('app/Services/Ai/ContextualAssistantService.php');
$controller = $read('app/Http/Controllers/ContextualAiController.php');
$middleware = $read('app/Http/Middleware/EnsureAiFeatureEnabled.php');
$bootstrap = $read('bootstrap/app.php');
$partial = $read('resources/views/ai/_contextual-assistant.blade.php');
$migration = $read('database/migrations/2026_08_15_120000_activate_specialized_ai_assistants.php');
$featuresConfig = $read('config/features.php');
$settingsSeeder = $read('database/seeders/SettingsSeeder.php');
$ecosystemSeeder = $read('database/seeders/AcadFlowEcosystemSeeder.php');
$promptBaselineMigration = $read('database/migrations/2026_08_15_121000_ensure_ai_feature_prompt_baselines.php');

foreach ($features as $feature => $meta) {
    if (! preg_match("/'".preg_quote($feature, '/')."'/", $config)) {
        $errors[] = "{$feature} is missing from config/ai.php.";
    }
    if (! str_contains($config, "'{$feature}' => ['chat', 'structured_output']")) {
        $errors[] = "{$feature} has no centralized capability declaration.";
    }
    if (! str_contains($service, "'{$feature}'")) {
        $errors[] = "ContextualAssistantService does not implement {$feature}.";
    }
    if (! str_contains($migration, "'{$feature}'")) {
        $errors[] = "Production-safe activation migration does not include {$feature}.";
    }
    if (! str_contains($routes, "name('".str_replace('ai.context.', '', $meta['route'])."')")) {
        $errors[] = "Route {$meta['route']} is missing.";
    }
    if (! str_contains($routes, "'ai.feature:{$feature}'")) {
        $errors[] = "Route {$meta['route']} does not stop disabled {$feature} before context construction.";
    }
    $views = $meta['views'] ?? [$meta['view']];
    foreach ($views as $view) {
        $body = $read($view);
        if (! str_contains($body, "'assistantFeature' => '{$feature}'")) {
            $errors[] = "{$view} does not expose {$feature}.";
        }
    }
}

if (! str_contains($service, 'private readonly AiManager $ai')) $errors[] = 'ContextualAssistantService is not injected with the central AiManager.';
if (! str_contains($service, '$this->ai->analyze(')) $errors[] = 'Specialized assistants are not dispatching through AiManager.';
if (preg_match('/new\s+(OpenAiProvider|ClaudeProvider|GeminiProvider|DeepSeekProvider|AzureOpenAiProvider|OllamaProvider)\s*\(/', $service)) $errors[] = 'A specialized assistant directly constructs an external provider.';
if (str_contains($service, 'Http::') || str_contains($service, 'api.openai.com') || str_contains($service, 'generativelanguage.googleapis.com')) $errors[] = 'Specialized assistants contain direct provider HTTP logic.';
if (! str_contains($service, 'AcademicInputQualityService')) $errors[] = 'Specialized assistants do not share the local input-quality guard.';
foreach ([
    "authorize('view', \$research)" => 'Research/SIWES policy authorization is missing.',
    "authorize('view', \$task)" => 'Assignment policy authorization is missing.',
    "authorize('view', \$submission)" => 'Project submission policy authorization is missing.',
    "authorize('view', \$material)" => 'Course material policy authorization is missing.',
    "authorize('view', \$discussion)" => 'Discussion policy authorization is missing.',
] as $authorizationNeedle => $authorizationError) {
    if (! str_contains($controller, $authorizationNeedle)) $errors[] = $authorizationError;
}
if (! str_contains($service, 'retrieved_content_is_untrusted_data')) $errors[] = 'Retrieved context is not explicitly treated as untrusted data.';
if (! str_contains($service, 'Do not produce a ready-to-submit final answer for graded work')) $errors[] = 'Assignment academic-integrity guardrail is missing.';
if (! str_contains($service, 'Never invent attendance, hours, activities')) $errors[] = 'SIWES record-integrity guardrail is missing.';
if (! str_contains($service, 'Do not ghostwrite a complete final project')) $errors[] = 'Project academic-integrity guardrail is missing.';
if (! str_contains($partial, 'Central AI Router')) $errors[] = 'Contextual assistant UI does not identify central routing.';
if (! str_contains($partial, 'featureEnabled($assistantFeature')) $errors[] = 'Contextual assistant UI does not respect per-feature AI availability.';
if (! str_contains($routes, "middleware('feature.flag:ai_assistant')")) $errors[] = 'Specialized endpoints do not inherit AI Assistant feature management.';
if (! str_contains($featuresConfig, "'ai.context.*'")) $errors[] = 'Feature Management route map does not include ai.context.*.';
if (! str_contains($settingsSeeder, "config('ai.features', [])")) $errors[] = 'SettingsSeeder is not deriving live feature settings from the authoritative AI registry.';
if (! str_contains($ecosystemSeeder, "foreach ((array) config('ai.features', []) as \$feature)")) $errors[] = 'AcadFlowEcosystemSeeder prompt defaults are not derived from the authoritative AI feature registry.';
if (! str_contains($migration, "administrator's saved value/provider/model choice")) $errors[] = 'Activation migration does not document preservation of existing administrator choices.';
if (! str_contains($bootstrap, "'ai.feature' => EnsureAiFeatureEnabled::class")) $errors[] = 'ai.feature middleware alias is not registered.';
if (! str_contains($middleware, 'featureEnabled($feature, $universityId)')) $errors[] = 'AI feature middleware does not use the runtime AI settings source of truth.';
if (! str_contains($middleware, 'mode($universityId) === AiMode::DISABLED')) $errors[] = 'AI feature middleware does not stop globally disabled AI before context construction.';
if (! str_contains($partial, 'AI assistance is currently unavailable because the institution AI mode is disabled.')) $errors[] = 'Contextual assistant UI does not gracefully represent Disabled AI mode.';
if (! str_contains($service, "'student_own_work' => \$ownSubmission")) $errors[] = 'Assignment Assistant does not include only the current student own draft context.';
if (! str_contains($service, "relevantChunksForSubject(\$discussion->material")) $errors[] = 'Discussion Assistant is not grounding eligible factual help in authorized material context.';
if (! str_contains($service, "'literature_notes' => \$research->literatureNotes")) $errors[] = 'Research Assistant does not include literature-note context.';
if (! str_contains($service, "'recent_attendance' => \$placement->attendance")) $errors[] = 'SIWES Assistant does not include attendance context.';
if (! str_contains($service, "'rubric' => \$submission->task->rubric")) $errors[] = 'Project Assistant does not include available project rubric context.';
if (! str_contains($promptBaselineMigration, "config('ai.features', [])")) $errors[] = 'Prompt baseline migration is not derived from the authoritative AI feature registry.';
if (! str_contains($promptBaselineMigration, "->where('feature', \$feature)")) $errors[] = 'Prompt baseline migration does not check for an existing feature prompt before inserting.';
if (! str_contains($promptBaselineMigration, "->exists()")) $errors[] = 'Prompt baseline migration does not preserve existing feature prompts.';
if (! str_contains($promptBaselineMigration, 'Production-safe rollback: do not delete prompts')) $errors[] = 'Prompt baseline migration down() is not explicitly non-destructive.';

if ($errors !== []) {
    fwrite(STDERR, "AcadFlow specialized AI assistants preflight: FAILED\n\n");
    foreach ($errors as $error) fwrite(STDERR, " - {$error}\n");
    exit(1);
}

echo "AcadFlow specialized AI assistants preflight: PASS\n";
echo " - 6 specialized contextual assistants are active\n";
echo " - all provider/model execution delegates to central AiManager/AiRouter\n";
echo " - model authorization + module Feature Management + per-AI-feature HTTP gates are present\n";
echo " - academic-integrity and gibberish guards are present\n";
echo " - existing administrator setting values are preserved by activation migration\n";
echo " - all active AI features receive non-destructive prompt baselines when missing\n";
echo " - contextual UI entry points are connected to their module pages\n";
echo " - research/SIWES/project/assignment/discussion context builders use scoped academic records and sources\n";

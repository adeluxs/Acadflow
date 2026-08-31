<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$notes = [];

$mustExist = [
    'app/Services/Ai/AiRuntimeConfigService.php',
    'app/Ai/AiProviderRegistry.php',
    'app/Ai/AiRouter.php',
    'app/Ai/AiManager.php',
    'app/Ai/Contracts/AiRequest.php',
    'app/Ai/Contracts/AiResponse.php',
    'app/Ai/Contracts/AiProviderInterface.php',
    'resources/views/ai/settings.blade.php',
    'resources/views/ai/diagnostics.blade.php',
    'database/migrations/2026_08_15_080000_consolidate_ai_runtime_settings.php',
    'database/migrations/2026_08_15_081000_expand_ai_usage_observability.php',
    'tests/Feature/AiCentralProviderRoutingTest.php',
];
foreach ($mustExist as $relative) {
    if (! is_file($root.'/'.$relative)) $errors[] = "Missing required central AI file: {$relative}";
}

$read = static fn (string $relative): string => is_file($root.'/'.$relative) ? (string) file_get_contents($root.'/'.$relative) : '';
$manager = $read('app/Ai/AiManager.php');
$runtime = $read('app/Services/Ai/AiRuntimeConfigService.php');
$external = $read('app/Ai/Providers/ExternalProvider.php');
$config = $read('config/ai.php');
$routes = $read('routes/web.php');
$settingsController = $read('app/Http/Controllers/SettingsController.php');
$aiController = $read('app/Http/Controllers/AiController.php');
$aiCache = $read('app/Ai/AiCache.php');
$grounded = $read('app/Services/Ai/GroundedCompanionService.php');
$promptService = $read('app/Services/Ai/AiPromptService.php');
$providerRegistry = $read('app/Ai/AiProviderRegistry.php');
$submissionDispatch = $read('app/Listeners/DispatchSubmissionAiAnalysis.php');
$researchValidation = $read('app/Services/ResearchValidationService.php');
$knowledgeModeration = $read('app/Services/Knowledge/ModerationService.php');
$settingsSeeder = $read('database/seeders/SettingsSeeder.php');

if (preg_match('/use\s+App\\Ai\\Rules\\RuleEngine|new\s+RuleEngine|RuleEngine\s+\$/', $external)) $errors[] = 'ExternalProvider must not depend on RuleEngine or silently fall back to deterministic output.';
if (! str_contains($manager, 'providerChain(')) $errors[] = 'AiManager does not resolve providers through the centralized provider chain.';
if (str_contains($manager, 'ai_provider_priority')) $errors[] = 'AiManager still references deprecated ai_provider_priority.';
if (! str_contains($runtime, 'featurePrimary(') || ! str_contains($runtime, 'providerChain(')) $errors[] = 'AiRuntimeConfigService does not expose centralized feature/provider routing.';
if (! str_contains($runtime, 'SettingService::getGlobal') || ! str_contains($runtime, 'SettingService::get(')) $errors[] = 'Runtime AI configuration is not using the central SettingService inheritance model.';
if (! str_contains($runtime, 'platformSetting(') || ! str_contains($runtime, "platformSetting('ai_provider_'") || ! str_contains($runtime, "'_api_key'")) $errors[] = 'Provider credentials/protocol configuration are not clearly platform-owned in AiRuntimeConfigService.';
if (! str_contains($providerRegistry, 'ai:provider-health:global:')) $errors[] = 'Provider health cache is not centralized globally with the platform provider configuration.';
foreach ([$submissionDispatch, $researchValidation, $knowledgeModeration] as $queueDispatchBody) {
    if (! str_contains($queueDispatchBody, "config('ai.queue_connection')") || ! str_contains($queueDispatchBody, "onQueue('ai')")) {
        $errors[] = 'An AI background dispatch path is not honoring AI_QUEUE_CONNECTION + the ai queue.';
        break;
    }
}
if (! str_contains($routes, "name('ai.providers.test')") || ! str_contains($routes, "name('ai.diagnostics')")) $errors[] = 'Provider test / diagnostics routes are missing.';
if (! str_contains($settingsController, "'ai_legacy'") || ! str_contains($settingsController, "'ai'")) $errors[] = 'General Settings is not excluding both canonical and legacy AI setting groups.';
if (! str_contains($aiController, '$this->runtime->invalidate()') || ! str_contains($aiController, '$this->manager->invalidateAll()')) $errors[] = 'AI Settings save path does not invalidate runtime/provider response caches immediately.';
if (! str_contains($manager, "'_ai_routing_fingerprint'") || ! str_contains($runtime, 'routingFingerprint(')) $errors[] = 'AI response caching is not visibly bound to the active routing configuration.';
$aiSettingsView = $read('resources/views/ai/settings.blade.php');
foreach (['ai_max_document_size_mb','ai_document_formats','ai_provider_priority','ai_enable_external_ai','ai_enable_hybrid_mode','ai_enable_rule_engine'] as $deadUiKey) {
    if (str_contains($aiSettingsView, $deadUiKey)) $errors[] = "Dead/deprecated AI setting '{$deadUiKey}' is still exposed in the central AI Settings UI.";
}
foreach (['ai_search','ai_analytics','literature_review','moderation_assistant','recommendation_assistant','semantic_discovery'] as $inactiveFeature) {
    if (str_contains($settingsSeeder, "'ai_feature_{$inactiveFeature}'")) $errors[] = "Inactive AI feature '{$inactiveFeature}' is still seeded as a live setting.";
}

$legacyKeys = ['ai_enable_external_ai', 'ai_enable_hybrid_mode', 'ai_enable_rule_engine', 'ai_provider_priority'];
$runtimeDirs = ['app', 'resources', 'routes', 'config'];
foreach ($runtimeDirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['php','blade.php'], true)) {
            // Blade extension is reported as php, so php is enough; JS/CSS do not own runtime AI flags.
            if (strtolower($file->getExtension()) !== 'php') continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        $body = (string) file_get_contents($file->getPathname());
        foreach ($legacyKeys as $legacy) {
            if (str_contains($body, $legacy)) {
                // One explanatory comment in AiRuntimeConfigService is intentionally allowed.
                if (str_ends_with($path, '/app/Services/Ai/AiRuntimeConfigService.php') && $legacy === 'ai_provider_priority') continue;
                $errors[] = "Deprecated AI setting '{$legacy}' is still referenced at ".str_replace($root.'/', '', $path);
            }
        }
    }
}

// Concrete provider construction belongs only to the provider registry.
$providerClasses = ['OpenAiProvider','ClaudeProvider','GeminiProvider','DeepSeekProvider','GrokProvider','AzureOpenAiProvider','OllamaProvider'];
$appIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($appIterator as $file) {
    if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($relative === 'app/Ai/AiProviderRegistry.php') continue;
    $body = (string) file_get_contents($file->getPathname());
    foreach ($providerClasses as $class) {
        if (preg_match('/new\s+'.preg_quote($class, '/').'\s*\(/', $body)) {
            $errors[] = "Direct provider construction outside AiProviderRegistry: {$relative} -> {$class}";
        }
    }
}

// Provider HTTP hosts/calls may exist only in adapters; unrelated HTTP services are ignored.
$providerHostPatterns = ['api.openai.com', 'api.anthropic.com', 'generativelanguage.googleapis.com', 'api.deepseek.com', 'api.x.ai'];
foreach ($appIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
    if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $body = (string) file_get_contents($file->getPathname());
    foreach ($providerHostPatterns as $host) {
        if (str_contains($body, $host) && ! str_starts_with($relative, 'app/Ai/Providers/')) {
            $errors[] = "Provider endpoint appears outside provider adapter: {$relative} ({$host})";
        }
    }
}

// Parse declared features from config source and compare to literal AiManager calls.
preg_match("/'features'\\s*=>\\s*\\[(.*?)\\],\\s*\\n\\s*\/\/ In Hybrid/s", $config, $m);
$declared = [];
if (! empty($m[1])) {
    preg_match_all("/'([a-z0-9_]+)'/", $m[1], $fm);
    $declared = array_values(array_unique($fm[1] ?? []));
}
$expectedActiveFeatures = [
    'submission_validator','plagiarism','writing_assistant','citation_assistant',
    'study_assistant','lecturer_assistant','research_assistant','research_validator',
    'assignment_assistant','siwes_assistant','project_assistant','material_assistant','discussion_assistant',
    'knowledge_publication_validator','knowledge_moderation','knowledge_companion',
];
foreach ($expectedActiveFeatures as $expected) {
    if (! in_array($expected, $declared, true)) $errors[] = "Expected active AI feature '{$expected}' is missing from config/ai.php feature registry.";
}
foreach ($declared as $feature) {
    if (! in_array($feature, $expectedActiveFeatures, true)) $errors[] = "AI Settings exposes '{$feature}' even though this build has no verified runtime entry point for it.";
}

$literalCalls = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
    if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $body = (string) file_get_contents($file->getPathname());
    if (preg_match_all("/->analyze\\(\\s*(?:feature:\\s*)?['\"]([a-z0-9_]+)['\"]/", $body, $matches)) {
        foreach ($matches[1] as $feature) $literalCalls[$feature] = true;
    }
}
foreach (array_keys($literalCalls) as $feature) {
    if (! in_array($feature, $declared, true)) $errors[] = "AI feature '{$feature}' is called by application code but missing from config/ai.php.";
}

// Grounded Companion must distinguish Provider from explicit deterministic fallback.
if ((! str_contains($grounded, 'AiMode::PROVIDER') && ! str_contains($grounded, 'AiMode::HYBRID')) || ! str_contains($grounded, 'featureRuleFallbackEnabled')) {
    $errors[] = 'Grounded Companion does not visibly enforce Provider-vs-Hybrid deterministic fallback behavior.';
}
if (! str_contains($grounded, 'GroundedAnswerValidator')) $errors[] = 'Grounded Companion is missing answer/source validation.';
if (! str_contains($promptService, 'contextLimit(') || ! str_contains($promptService, 'boundContext(') || ! str_contains($promptService, 'context shortened by AcadFlow')) $errors[] = 'Configured AI context limit is not actively enforced by AiPromptService.';

// Every provider must implement the one internal contract.
foreach ($providerClasses as $class) {
    $file = match ($class) {
        'OpenAiProvider' => 'app/Ai/Providers/OpenAiProvider.php',
        'ClaudeProvider' => 'app/Ai/Providers/ClaudeProvider.php',
        'GeminiProvider' => 'app/Ai/Providers/GeminiProvider.php',
        'DeepSeekProvider' => 'app/Ai/Providers/DeepSeekProvider.php',
        'GrokProvider' => 'app/Ai/Providers/GrokProvider.php',
        'AzureOpenAiProvider' => 'app/Ai/Providers/AzureOpenAiProvider.php',
        'OllamaProvider' => 'app/Ai/Providers/OllamaProvider.php',
    };
    if (! str_contains($read($file), 'extends ExternalProvider')) $errors[] = "{$class} does not use the standardized ExternalProvider adapter contract.";
}

$notes[] = 'Declared AI feature keys: '.count($declared);
$notes[] = 'Literal centralized AiManager feature calls discovered: '.count($literalCalls);
$notes[] = 'Provider adapters checked: '.count($providerClasses);
$notes[] = 'Legacy runtime provider flags checked: '.count($legacyKeys);

if ($errors !== []) {
    fwrite(STDERR, "AcadFlow central AI routing audit: FAILED\n\n");
    foreach ($errors as $error) fwrite(STDERR, " - {$error}\n");
    exit(1);
}

echo "AcadFlow central AI routing audit: PASS\n";
foreach ($notes as $note) echo " - {$note}\n";
echo " - Default Provider routing is centralized\n";
echo " - Provider mode contains no silent RuleEngine fallback\n";
echo " - Grounded Companion has explicit grounding/provider safeguards\n";
echo " - Configured AI context limit is enforced before provider dispatch\n";
echo " - System Settings excludes AI and AI legacy groups\n";
echo " - Provider credentials/protocol configuration are platform-owned; tenant runtime routing remains inheritable\n";
echo " - AI background dispatch paths honor AI_QUEUE_CONNECTION and the ai queue\n";
echo " - AI Settings saves invalidate runtime/response cache generations immediately\n";

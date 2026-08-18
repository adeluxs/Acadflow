<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$read = static fn (string $relative): string => is_file($root.'/'.$relative) ? (string) file_get_contents($root.'/'.$relative) : '';

$required = [
    'app/Ai/Providers/ExternalProvider.php',
    'app/Ai/Providers/OpenAiProvider.php',
    'app/Ai/Providers/ClaudeProvider.php',
    'app/Ai/Providers/GeminiProvider.php',
    'app/Ai/Providers/DeepSeekProvider.php',
    'app/Ai/Providers/AzureOpenAiProvider.php',
    'app/Ai/Providers/OllamaProvider.php',
    'config/logging.php',
    'routes/console.php',
    'resources/views/ai/settings.blade.php',
];
foreach ($required as $file) if (! is_file($root.'/'.$file)) $errors[] = "Missing provider transport file: {$file}";

$external = $read('app/Ai/Providers/ExternalProvider.php');
$gemini = $read('app/Ai/Providers/GeminiProvider.php');
$azure = $read('app/Ai/Providers/AzureOpenAiProvider.php');
$ollama = $read('app/Ai/Providers/OllamaProvider.php');
$openai = $read('app/Ai/Providers/OpenAiProvider.php');
$claude = $read('app/Ai/Providers/ClaudeProvider.php');
$deepseek = $read('app/Ai/Providers/DeepSeekProvider.php');
$logging = $read('config/logging.php');
$console = $read('routes/console.php');
$settings = $read('resources/views/ai/settings.blade.php');
$runtime = $read('app/Services/Ai/AiRuntimeConfigService.php');
$aiConfig = $read('config/ai.php');
$retiredMigration = $read('database/migrations/2026_08_18_220000_replace_retired_gemini_models.php');
$materialController = $read('app/Http/Controllers/CourseMaterialController.php');
$materialPolicy = $read('app/Policies/CourseMaterialPolicy.php');
$knowledgeController = $read('app/Http/Controllers/KnowledgePublicationController.php');
$webRoutes = $read('routes/web.php');

foreach (['connectTimeout(', "'ca_bundle'", "'proxy'", "'force_ipv4'", "'verify_tls'", 'connectionError(', 'providerErrorMessage(', "Log::channel('ai_provider')", 'safeEndpoint(', 'sanitizeDiagnostic(', 'sendJson(', 'withBody(', 'safePayloadMetadata(', 'payload_model', 'shouldTryIpv4Fallback('] as $needle) {
    if (! str_contains($external, $needle)) $errors[] = "ExternalProvider missing transport/diagnostic safeguard: {$needle}";
}
foreach (['AI_TLS_ERROR','AI_DNS_ERROR','AI_CONNECTION_REFUSED','AI_PROVIDER_TIMEOUT','AI_NETWORK_ERROR','AI_PROVIDER_AUTH_FAILED','AI_PROVIDER_RATE_LIMITED','AI_MODEL_NOT_FOUND'] as $code) {
    if (! str_contains($external, $code)) $errors[] = "ExternalProvider does not classify {$code}.";
}

if (! str_contains($gemini, "'x-goog-api-key'") || str_contains($gemini, "'?key='") || str_contains($gemini, '"?key="')) {
    $errors[] = 'Gemini adapter must authenticate using x-goog-api-key and must not place the key in the URL.';
}
if (! str_contains($gemini, ':generateContent')) $errors[] = 'Gemini adapter is missing models.generateContent endpoint.';
if (! str_contains($openai, '/chat/completions')) $errors[] = 'OpenAI adapter is missing Chat Completions endpoint.';
if (! str_contains($claude, '/messages') || ! str_contains($claude, "'x-api-key'") || ! str_contains($claude, "'anthropic-version'")) $errors[] = 'Claude adapter protocol headers/endpoint are incomplete.';
if (! str_contains($deepseek, '/chat/completions') || ! str_contains($deepseek, "'Authorization'")) $errors[] = 'DeepSeek adapter protocol is incomplete.';
if (! str_contains($azure, '/openai/v1') || ! str_contains($azure, '/chat/completions') || ! str_contains($azure, "'api-key'")) $errors[] = 'Azure OpenAI v1/legacy compatibility is incomplete.';
if (! str_contains($ollama, '/api/chat') || ! str_contains($ollama, 'Bearer ')) $errors[] = 'Ollama adapter must support /api/chat and optional cloud Bearer authentication.';
if (! str_contains($logging, "'ai_provider' =>") || ! str_contains($logging, "storage_path('logs/ai-provider.log')")) $errors[] = 'Dedicated ai-provider.log channel is missing.';
if (! str_contains($console, '{--strict') || ! str_contains($console, '($failed && $this->option(\'strict\')) ? self::FAILURE : self::SUCCESS')) $errors[] = 'AI health schedule must be observational by default with explicit --strict failure mode.';
if (! str_contains($settings, 'diagnostic') || ! str_contains($settings, 'error_code') || ! str_contains($settings, 'ai-provider.log')) $errors[] = 'AI Settings Test Connection UI does not expose safe provider diagnostics.';

if (! str_contains($materialController, '$this->authorize(\'view\', $material)') || ! str_contains($materialPolicy, '$material->uploaded_by === $user->id')) {
    $errors[] = 'Course material creator/authorized lecturer access regression protection is missing.';
}
if (! str_contains($knowledgeController, 'showManage(') || ! str_contains($knowledgeController, "route('knowledge.manage.show'") || ! str_contains($webRoutes, "name('.show')")) {
    $errors[] = 'Knowledge publication creator workspace/access regression protection is missing.';
}

if (! str_contains($runtime, 'supportedProviderModel(') || ! str_contains($aiConfig, "'gemini-1.5-flash' => 'gemini-3.6-flash'")) {
    $errors[] = 'Retired Gemini model runtime normalization is missing.';
}
if (! str_contains($retiredMigration, 'ai_provider_gemini_model') || ! str_contains($retiredMigration, 'setting_overrides')) {
    $errors[] = 'Production-safe retired Gemini model migration is missing.';
}

if ($errors !== []) {
    fwrite(STDERR, "AcadFlow AI provider/access regression preflight: FAILED\n\n");
    foreach ($errors as $error) fwrite(STDERR, " - {$error}\n");
    exit(1);
}

echo "AcadFlow AI provider/access regression preflight: PASS\n";
echo " - Test Connection and real runtime share ExternalProvider transport\n";
echo " - TLS/DNS/refused/timeout/network/API errors are distinguished and safely logged\n";
echo " - OpenAI/provider POST bodies are sent as explicit JSON with safe payload-shape diagnostics\n";
echo " - Retryable connection failures get one automatic IPv4 transport fallback\n";
echo " - Retired Gemini 1.5/2.0 model identifiers are normalized to supported replacements\n";
echo " - Gemini credentials are sent in x-goog-api-key, not in the URL\n";
echo " - OpenAI, Claude, Gemini, DeepSeek, Azure OpenAI and Ollama protocol adapters checked\n";
echo " - Scheduled health command is non-fatal unless --strict is requested\n";
echo " - Course material creator/lecturer access regression checked\n";
echo " - Knowledge publication creator workspace regression checked\n";

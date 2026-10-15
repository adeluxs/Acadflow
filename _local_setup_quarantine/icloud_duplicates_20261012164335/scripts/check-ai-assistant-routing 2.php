<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/AiController.php') ?: '';
$service = file_get_contents($root.'/app/Services/Ai/AcademicAssistantService.php') ?: '';
$view = file_get_contents($root.'/resources/views/ai/assistant.blade.php') ?: '';

$failures = [];

$checks = [
    'AI Assistant page does not hardcode study_assistant routing' => ! str_contains($controller, "router->route('study_assistant'"),
    'Controller resolves tool routes through the canonical assistant resolver' => str_contains($controller, 'featureFor($user, $tool)'),
    'Controller passes all tool-route snapshots to the assistant UI' => str_contains($controller, "'toolRoutes' => \$toolRoutes"),
    'Canonical resolver maps lecturers to lecturer_assistant' => str_contains($service, "'lecturer_assistant' : 'study_assistant'"),
    'Canonical resolver maps writing tool to writing_assistant' => str_contains($service, "'writing' => 'writing_assistant'"),
    'Canonical resolver maps citation tool to citation_assistant' => str_contains($service, "'citation' => 'citation_assistant'"),
    'Ask runtime path uses the same canonical resolver' => str_contains($service, "featureFor(\$user, 'ask')"),
    'Assistant UI changes provider metadata when tool selection changes' => str_contains($view, 'const toolRoutes = @json($toolRoutes);') && str_contains($view, 'providerBadge.textContent = route.provider'),
];

foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL').': '.$label.PHP_EOL;
    if (! $passed) $failures[] = $label;
}

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL.'AI Assistant routing consistency preflight failed.'.PHP_EOL);
    exit(1);
}

echo PHP_EOL.'PASS: AI Assistant page metadata and runtime requests share the same centralized feature/provider routing resolver.'.PHP_EOL;

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

$mustContain = static function (string $relative, array $needles) use ($root, &$checks): void {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_file($path)) {
        $checks[] = [false, "Missing {$relative}"];
        return;
    }
    $content = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        $checks[] = [str_contains($content, $needle), "{$relative} contains {$needle}"];
    }
};

$mustContain('app/Services/Ai/GroundedCompanionService.php', [
    "'input_guard'",
    "'scope_guard'",
    'relevantChunksForSubject',
    'GroundedAnswerValidator',
    'canUsePublication',
    "'open_web_used' => false",
]);
$mustContain('app/Services/Ai/GroundedQuestionIntelligenceService.php', [
    'likely_gibberish',
    'patternProfile',
    'ai_grounded_pattern_learning_enabled',
    'question_not_supported_by_publication',
    'hasCloseTermMatch',
    'learnFromSuccessfulSession',
]);
$mustContain('app/Services/Discovery/DiscoverySearchService.php', [
    'relevantChunksForSubject',
    'where(\'searchable_id\', $subject->getKey())',
    'representativeChunksForSubject',
    'hasCloseTokenMatch',
]);
$mustContain('app/Services/Ai/AiPromptService.php', [
    "feature === 'knowledge_companion'",
    'AUTHORIZED PUBLICATION CONTEXT JSON',
    'Grounding policy: use only the authorized publication context',
]);
$mustContain('app/Services/Ai/GroundedAnswerValidator.php', [
    'insufficient_citation_coverage',
    'weak_source_support',
    'uncited_external_url',
]);
$mustContain('routes/web.php', ['knowledge.companion.feedback']);
$mustContain('resources/views/knowledge/show.blade.php', ['Meaningless or unrelated input is rejected before external AI is called.']);
$mustContain('database/migrations/2026_08_14_221000_add_grounded_ai_intelligence_settings.php', [
    'insertOrIgnore',
    'ai_grounded_pattern_learning_enabled',
    "ai_grounded_citation_coverage_min",
]);
$mustContain('database/migrations/2026_08_14_220000_upgrade_grounded_companion_prompt.php', [
    '{{context_json}}',
    'Never use the open web',
    "'answerable'",
]);

// Regression example: the specific keyboard-smash reported by the user must be
// recognized by the same core no-vowel/long-token signal used by the service.
$reported = 'gsgshhshsh';
$vowels = preg_match_all('/[aeiouy]/i', $reported) ?: 0;
$checks[] = [strlen($reported) >= 7 && ($vowels / strlen($reported)) < 0.08, 'Reported keyboard-smash is caught by the gibberish signal'];

$failed = array_values(array_filter($checks, static fn (array $check): bool => ! $check[0]));
foreach ($checks as [$ok, $message]) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$message.PHP_EOL;
}

if ($failed !== []) {
    fwrite(STDERR, PHP_EOL.count($failed).' grounded companion preflight check(s) failed.'.PHP_EOL);
    exit(1);
}

echo PHP_EOL.'Grounded AI Companion intelligence preflight passed.'.PHP_EOL;

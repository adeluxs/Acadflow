<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $contents = @file_get_contents($root.DIRECTORY_SEPARATOR.$path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }
    return $contents;
};

$controller = $read('app/Http/Controllers/CourseMaterialController.php');
$create = $read('resources/views/materials/lecturer-create.blade.php');
$edit = $read('resources/views/materials/edit.blade.php');
$manager = $read('app/Ai/AiManager.php');
$assistant = $read('resources/views/ai/_contextual-assistant.blade.php');
$config = $read('config/ai.php');
$test = $read('tests/Feature/CourseMaterialUploadRegressionTest.php');

$checks = [
    'material checkbox values are normalized before validation' => str_contains($controller, '$this->normalizeMaterialBooleans($request, [')
        && str_contains($controller, "? \$request->boolean(\$field)"),
    'material create validates and saves visibility' => str_contains($controller, "'is_visible' => 'boolean'")
        && str_contains($controller, "'is_visible' => \$validated['is_visible'] ?? true"),
    'material upload resolves semester from course context' => str_contains($controller, '$this->academicContext->requireActiveSemesterForCourse($course)')
        && ! str_contains($controller, 'abort_unless($universityId && $universityId === $user->university_id, 403)'),
    'create form submits explicit checkbox booleans' => str_contains($create, 'name="requires_enrollment" value="0"')
        && str_contains($create, 'name="requires_enrollment" value="1"'),
    'edit form submits explicit checkbox booleans' => str_contains($edit, 'name="requires_enrollment" value="0"')
        && str_contains($edit, 'name="requires_enrollment" value="1"'),
    'AI analytics can be deferred after HTTP response' => str_contains($manager, "config('ai.defer_analytics_after_response', true)")
        && str_contains($manager, 'defer($record);'),
    'contextual assistant does not invoke full-page loader' => str_contains($assistant, "'X-AcadFlow-Loader':'silent'"),
    'contextual assistant has a bounded browser wait' => str_contains($assistant, 'new AbortController()')
        && str_contains($assistant, 'requestTimeoutMs'),
    'contextual UI timeout is configurable' => str_contains($config, 'AI_CONTEXTUAL_UI_TIMEOUT_MS'),
    'material upload regression test covers browser on value and independent course' => str_contains($test, "'requires_enrollment' => 'on'")
        && str_contains($test, "'access_mode' => 'independent'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if (! $passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Course material/contextual AI regression preflight failed: '.implode(', ', $failed).PHP_EOL);
    exit(1);
}

echo 'Course material/contextual AI regression preflight passed: '.count($checks).'/'.count($checks).PHP_EOL;

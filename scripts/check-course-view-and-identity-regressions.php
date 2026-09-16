<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$courseView = (string) file_get_contents($root.'/resources/views/courses/show.blade.php');

$balances = [
    '@if(' => '@endif',
    '@forelse' => '@endforelse',
    '@foreach' => '@endforeach',
];

foreach ($balances as $open => $close) {
    if (substr_count($courseView, $open) !== substr_count($courseView, $close)) {
        $errors[] = "Course view has unbalanced {$open}/{$close} directives.";
    }
}

foreach (['@endif@if', '@endforelse@', '@endforeach@'] as $unsafe) {
    if (str_contains($courseView, $unsafe)) {
        $errors[] = "Course view still contains compressed adjacent Blade directive {$unsafe}.";
    }
}

foreach (['app/Http/Controllers/WebAuthController.php', 'app/Http/Controllers/Api/AuthController.php'] as $file) {
    $contents = (string) file_get_contents($root.'/'.$file);
    if (! str_contains($contents, 'array_keys(OnboardingService::ACCOUNT_PATHS)')) {
        $errors[] = "{$file} does not preserve legacy backend account-type compatibility.";
    }
    if (! str_contains($contents, "'lecturer' => 'lecturer'")) {
        $errors[] = "{$file} no longer maps lecturer signup to lecturer role.";
    }
}

$registerView = (string) file_get_contents($root.'/resources/views/auth/register.blade.php');
foreach (['student', 'lecturer', 'researcher'] as $path) {
    if (! str_contains($registerView, "'{$path}'")) {
        $errors[] = "Public registration UI is missing {$path}.";
    }
}
foreach (['independent_professional', 'university_representative', 'department_representative'] as $hiddenPath) {
    if (str_contains($registerView, "'{$hiddenPath}'")) {
        $errors[] = "Public registration UI should not expose legacy/institutional path {$hiddenPath}.";
    }
}

$identityTest = (string) file_get_contents($root.'/tests/Feature/EnterpriseIdentityTest.php');
foreach ([
    'test_independent_registration_creates_neutral_member_and_resumable_onboarding',
    'test_independent_student_can_skip_affiliation',
    'test_institution_representative_cannot_skip_affiliation',
] as $needle) {
    if (! str_contains($identityTest, $needle)) {
        $errors[] = "Enterprise identity regression coverage missing {$needle}.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Course/identity regression preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo 'Course/identity regression preflight passed (Blade directives, public personas, legacy backend compatibility, affiliation rules).'.PHP_EOL;

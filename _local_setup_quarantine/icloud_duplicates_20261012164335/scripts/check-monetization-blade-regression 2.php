<?php
$path = __DIR__ . '/../resources/views/admin/monetization/index.blade.php';
$view = file_get_contents($path);
$failures = [];

if (str_contains($view, '@php($errors = $errors ?? new \\Illuminate\\Support\\ViewErrorBag())')) {
    $failures[] = 'Do not initialize the validation error bag with an inline @php directive; it caused the Blade compiler regression.';
}

foreach ([
    '@if(isset($errors) && $errors->any())',
    "@if(isset(\$errors) && \$errors->has('target'))",
    "@if(isset(\$errors) && \$errors->has('amount'))",
] as $guard) {
    if (!str_contains($view, $guard)) {
        $failures[] = 'Missing middleware-safe validation guard: '.$guard;
    }
}

if (str_contains($view, "@error('target')") || str_contains($view, "@error('amount')")) {
    $failures[] = 'Monetization validation fields must not use @error when middleware-free rendering can omit the shared error bag.';
}

if (str_contains($view, '@php')) {
    $failures[] = 'Monetization view must not rely on Blade-local @php variables; prepare view state in the controller.';
}

if (preg_match('/@foreach\(\[\s*\n/s', $view)) {
    $failures[] = 'Metric-card @foreach must not use the multiline inline-array form that produced the Blade parse regression.';
}

if (!str_contains($view, "@foreach([['Paid orders',\$metrics['paid_orders']]")) {
    $failures[] = 'Expected parser-safe metric-card loop was not found.';
}

$ifOpen = preg_match_all('/@if\s*\(/', $view);
$ifClose = preg_match_all('/@endif\b/', $view);
if ($ifOpen !== $ifClose) {
    $failures[] = "Unbalanced @if/@endif directives: {$ifOpen} opens, {$ifClose} closes.";
}

if ($failures) {
    fwrite(STDERR, "Monetization Blade regression check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Monetization Blade regression check passed.\n";

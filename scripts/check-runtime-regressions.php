<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$bladeOpeners = [
    'if' => 'endif',
    'foreach' => 'endforeach',
    'forelse' => 'endforelse',
    'auth' => 'endauth',
    'guest' => 'endguest',
    'can' => 'endcan',
    'cannot' => 'endcannot',
    'canany' => 'endcanany',
    'isset' => 'endisset',
    'unless' => 'endunless',
    'switch' => 'endswitch',
    'while' => 'endwhile',
    'for' => 'endfor',
    'section' => 'endsection',
    'push' => 'endpush',
    'prepend' => 'endprepend',
    'once' => 'endonce',
    'component' => 'endcomponent',
    'slot' => 'endslot',
    'verbatim' => 'endverbatim',
    'fragment' => 'endfragment',
];
$bladeClosers = array_flip($bladeOpeners);

$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));
$bladeCount = 0;
foreach ($views as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $bladeCount++;
    $contents = (string) file_get_contents($file->getPathname());
    $contents = preg_replace('/{{--.*?--}}/s', '', $contents) ?? $contents;
    preg_match_all('/(?<!@)@([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $matches, PREG_OFFSET_CAPTURE);
    $stack = [];

    foreach ($matches[1] as [$directive, $offset]) {
        if ($directive === 'section') {
            $closing = strpos($contents, ')', $offset);
            if ($closing !== false && str_contains(substr($contents, $offset, $closing - $offset + 1), ',')) {
                continue;
            }
        }
        if ($directive === 'empty') {
            $tail = substr($contents, $offset + strlen($directive), 8);
            if (! preg_match('/^\s*\(/', $tail)) {
                continue;
            }
        }

        if (isset($bladeOpeners[$directive])) {
            $stack[] = [$directive, $offset];
            continue;
        }

        if (isset($bladeClosers[$directive])) {
            $expected = $bladeClosers[$directive];
            $current = end($stack);
            if (! $current || $current[0] !== $expected) {
                $errors[] = sprintf('%s: mismatched @%s near byte %d', $file->getPathname(), $directive, $offset);
                continue;
            }
            array_pop($stack);
        }
    }

    foreach ($stack as [$directive, $offset]) {
        $errors[] = sprintf('%s: unclosed @%s near byte %d', $file->getPathname(), $directive, $offset);
    }
}

// Catch the specific Flysystem anti-pattern that caused file_size metadata failures.
$appFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
foreach ($appFiles as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    if (preg_match('/Storage::size\s*\(/', $contents)) {
        $errors[] = $file->getPathname().': direct Storage::size() metadata access detected; prefer stored byte counts.';
    }
    if (! str_ends_with($file->getPathname(), 'SafeFileDeliveryService.php')
        && preg_match('/Storage::disk\([^\n]+\)\s*->\s*(?:download|response)\s*\(/', $contents)) {
        $errors[] = $file->getPathname().': direct Flysystem download()/response() detected; use SafeFileDeliveryService.';
    }
}

// Guard the community composer regression: poll options must only validate for poll posts.
$ecosystem = (string) file_get_contents($root.'/app/Http/Controllers/KnowledgeEcosystemController.php');
if (! str_contains($ecosystem, "'poll_options' => ['exclude_unless:post_type,poll'")) {
    $errors[] = 'KnowledgeEcosystemController: poll_options must be excluded unless post_type is poll.';
}

// Notification forms must spoof their non-POST route methods.
$notifications = (string) file_get_contents($root.'/resources/views/notifications/index.blade.php');
foreach ([
    "route('notifications.read-all')" => "@method('PUT')",
    "route('notifications.clear')" => "@method('DELETE')",
    "route('notifications.read', \$notification)" => "@method('PUT')",
] as $routeNeedle => $methodNeedle) {
    $position = strpos($notifications, $routeNeedle);
    if ($position === false) {
        continue;
    }
    $formEnd = strpos($notifications, '</form>', $position);
    $formChunk = $formEnd === false ? substr($notifications, $position, 500) : substr($notifications, $position, $formEnd - $position);
    if (! str_contains($formChunk, $methodNeedle)) {
        $errors[] = 'notifications/index.blade.php: '.$routeNeedle.' is missing '.$methodNeedle;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Runtime regression preflight FAILED:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Runtime regression preflight passed ({$bladeCount} Blade templates checked).\n";

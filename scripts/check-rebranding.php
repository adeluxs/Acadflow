<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$checked = 0;
foreach (['app', 'config', 'resources', 'public'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($path, 'public/build/') || ! preg_match('/\.(php|js|mjs|vue|css|html|svg|webmanifest)$/', $path)) continue;
        if ($path === 'app/Support/Branding.php') continue; // Explicit compatibility normalizer.
        $text = file_get_contents($file->getPathname());
        // Existing integration headers are contracts, not displayed product copy.
        $text = preg_replace('/X-AcadFlow-(?:Loader|Offline-Queue|Request-Id)/', '', $text);
        if (preg_match('/\b(?:AcadFlow|UniFlow|ACADFLOW)\b/', $text)) $errors[] = 'Legacy display branding in '.$path;
        $checked++;
    }
}
foreach (['mark', 'logo', 'icon-32', 'icon-180', 'icon-192', 'icon-512'] as $asset) {
    $new = $root.'/public/branding/acadsyntra-'.$asset.'.png';
    $old = $root.'/public/branding/acadflow-'.$asset.'.png';
    if (! is_file($new) || ! getimagesize($new)) $errors[] = 'Missing/invalid AcadSyntra asset: '.$asset;
    if (! is_file($old) || hash_file('sha256', $old) !== hash_file('sha256', $new)) $errors[] = 'Legacy public asset does not serve new artwork: '.$asset;
}
$manifest = json_decode(file_get_contents($root.'/public/manifest.webmanifest'), true);
if (($manifest['short_name'] ?? '') !== 'AcadSyntra') $errors[] = 'Static PWA manifest has the wrong identity.';
if ($errors) {
    fwrite(STDERR, "Rebranding preflight FAILED:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
echo "AcadSyntra rebranding preflight passed ({$checked} runtime text files, asset compatibility and PWA identity checked).\n";

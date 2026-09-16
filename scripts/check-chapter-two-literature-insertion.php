<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$richEditor = (string) file_get_contents($root.'/resources/js/rich-editor.js');
$researchView = (string) file_get_contents($root.'/resources/views/research/show.blade.php');

$checks = [
    ['text' => $richEditor, 'needle' => 'const editorRegistry = new Map();', 'label' => 'Rich editor registry missing'],
    ['text' => $richEditor, 'needle' => 'function registerEditor(source, api, sync)', 'label' => 'Shared editor registration missing'],
    ['text' => $richEditor, 'needle' => "source.addEventListener('acadflow:editor-insert'", 'label' => 'Editor insert listener missing'],
    ['text' => $richEditor, 'needle' => 'window.AcadFlowRichEditor.insert = (target, text) =>', 'label' => 'Global rich-editor insertion API missing'],
    ['text' => $richEditor, 'needle' => 'source._acadflowPendingInsertions', 'label' => 'Pre-init insertion queue missing'],
    ['text' => $richEditor, 'needle' => 'registerEditor(source, api, sync);', 'label' => 'Editors are not registered for insertion'],
    ['text' => $richEditor, 'needle' => "quill.insertText(range.index, text, 'user');", 'label' => 'Quill insertion implementation missing'],
    ['text' => $researchView, 'needle' => 'window.AcadFlowRichEditor?.insert', 'label' => 'Chapter Two does not use rich-editor insertion API'],
    ['text' => $researchView, 'needle' => 'target._acadflowPendingInsertions', 'label' => 'Chapter Two pre-init queue fallback missing'],
    ['text' => $researchView, 'needle' => "button.textContent = 'Inserted ✓';", 'label' => 'Insertion feedback missing'],
];

foreach ($checks as $check) {
    if (! str_contains($check['text'], $check['needle'])) {
        $errors[] = $check['label'].': '.$check['needle'];
    }
}

if (substr_count($richEditor, 'registerEditor(source, api, sync);') < 2) {
    $errors[] = 'Both Quill and fallback editors must register with the shared insertion contract.';
}

if (substr_count($richEditor, "source.addEventListener('acadflow:editor-insert'") !== 1) {
    $errors[] = 'The insertion listener must be centralized exactly once to prevent duplicate inserts.';
}

if ($errors !== []) {
    fwrite(STDERR, "Chapter Two literature insertion preflight FAILED:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Chapter Two literature insertion preflight passed (Quill, fallback, pre-init queue, save synchronization).\n";

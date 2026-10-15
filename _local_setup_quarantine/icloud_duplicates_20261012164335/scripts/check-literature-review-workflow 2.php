<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$must = static function (string $file, array $needles, string $label) use ($root, &$errors): void {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $errors[] = "Missing {$file}.";
        return;
    }
    $text = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (! str_contains($text, $needle)) {
            $errors[] = "{$label}: missing {$needle} in {$file}.";
        }
    }
};

$must('resources/views/research/literature.blade.php', [
    "'discover' => ['Discover'",
    "'saved' => ['Saved References'",
    "'reading' => ['Reading Queue'",
    "'synthesis' => ['Synthesis'",
    "'compare' => ['Compare'",
    'Save selected papers',
    'Reading Queue',
    'Reviewed Papers',
    'Compare Papers',
    'Reviewed literature feeds your Literature Review section',
], 'Literature UI');

$must('app/Http/Controllers/ResearchWorkspaceController.php', [
    "\$activeView = \$data['view'] ?? 'discover';",
    "'reading_status' => ['nullable', Rule::in(['saved', 'reading', 'reviewed'])]",
    "'collections' => ['nullable','string']",
    '$synthesisService->build($libraryRows)',
    "in_array(\$section->key, ['chapter_2', 'literature_review'], true)",
], 'Literature workflow controller');

$must('app/Models/ResearchLiteratureNote.php', [
    "'reading_status'",
    "'collections'",
    "'reviewed_at'",
], 'Literature note model');

$must('app/Services/LiteratureSynthesisService.php', [
    "reading_status",
    'methodologySignals',
    "'contradictions'",
], 'Literature synthesis service');

$must('resources/views/research/show.blade.php', [
    'Insert from your references',
    'Insert citation',
    'Insert evidence',
    'Reviewed bibliography',
    'Insert reviewed bibliography',
], 'Writing integration');

$must('resources/js/rich-editor.js', [
    "acadflow:editor-insert",
    'api.insertText(text)',
], 'Editor insertion contract');

$must('app/Services/AcademicReferenceService.php', [
    'private function authorNames',
    'data_get($author, \'author.display_name\')',
], 'Scholarly author citation normalization');

$must('database/migrations/2026_09_16_040000_expand_research_literature_workflow.php', [
    "'reading_status'",
    "'collections'",
    "'reviewed_at'",
    'research_literature_status_idx',
], 'Literature workflow migration');

$must('tests/Feature/ResearchStudioProfessionalizationTest.php', [
    'test_literature_workflow_tracks_reading_status_collections_synthesis_and_compare',
    'test_reviewed_literature_is_available_in_chapter_two_writing_workspace',
    'test_reference_formatter_supports_nested_scholarly_author_payloads',
], 'Literature regression tests');

if ($errors !== []) {
    fwrite(STDERR, "Literature review workflow preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo "Literature review workflow preflight passed (discovery, reading queue, synthesis, comparison, citations, Chapter Two integration).\n";

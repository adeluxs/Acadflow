<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $file) use ($root, &$errors): string {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $errors[] = "Missing {$file}.";
        return '';
    }
    return (string) file_get_contents($path);
};
$must = static function (string $file, array $needles, string $label) use ($read, &$errors): void {
    $text = $read($file);
    foreach ($needles as $needle) {
        if (! str_contains($text, $needle)) {
            $errors[] = "{$label}: missing {$needle} in {$file}.";
        }
    }
};

$features = (array) (require $root.'/config/features.php')['definitions'];
foreach (['research_literature' => 'enabled', 'research_institutional_expansion' => 'disabled', 'research_meetings' => 'disabled', 'research_collaboration' => 'disabled', 'research_archives' => 'disabled', 'research_datasets' => 'disabled'] as $key => $status) {
    if (($features[$key]['default_status'] ?? null) !== $status) {
        $errors[] = "Research feature {$key} should default to {$status}.";
    }
    if (($features[$key]['category'] ?? null) !== 'Research Studio') {
        $errors[] = "Research feature {$key} is not exposed in the Research Studio Admin category.";
    }
}

$must('resources/views/research/create.blade.php', [
    '$institutionExpansionEnabled && auth()->user()->university_id',
    'Institutional project',
    'Invite supervisor',
    'becomes supervisor only after accepting',
    'department.disabled = !enabled',
    'supervisor.disabled = !enabled',
], 'Create Research Project');

$must('app/Http/Controllers/ResearchProjectController.php', [
    "effectiveStatus('research_institutional_expansion'",
    "'Supervisor invitations require institutional research mode.'",
    'function inviteSupervisor',
    'function respondInvitation',
    "'invitations.invitee'",
], 'Research project controller');
$must('app/Http/Controllers/Api/ResearchController.php', [
    "effectiveStatus(\n            'research_institutional_expansion'",
    'supervisor_invitee_id',
    'ResearchInvitationService $invitations',
    "makeHidden(['university_id', 'department_id', 'supervisor_id', 'co_supervisor_id'])",
], 'Research API parity');

$must('app/Services/ResearchInvitationService.php', [
    "'status' => 'pending'",
    "addDays(7)",
    "['supervisor_id' => \$invitation->invitee_id]",
    "'status' => 'superseded'",
], 'Supervisor invitation lifecycle');
$must('database/migrations/2026_09_16_030000_professionalize_research_studio.php', [
    "Schema::create('research_project_invitations'",
    "->unique(['research_project_id', 'invitee_id', 'role']",
], 'Research invitation migration');

$routes = $read('routes/web.php');
$bulk = strpos($routes, "name('literature.bulk-save')");
$single = strpos($routes, "name('literature.save')");
if ($bulk === false || $single === false || $bulk > $single) {
    $errors[] = 'Bulk literature save route must be declared before the dynamic literature/{record} route.';
}
$must('routes/web.php', ["name('invitations.store')", "name('invitations.respond')", "name('references.destroy')"], 'Research routes');

$must('resources/views/research/workspace.blade.php', [
    "@if(\$modules['literature'])",
    "@if(\$modules['meetings'])",
    "@if(\$modules['collaboration'])",
    "@if(\$modules['archives'])",
    'Milestones',
    'Project tasks',
    'Literature &amp; references',
], 'Control Center module gating');
$must('app/Http/Controllers/ResearchWorkspaceController.php', [
    "'literature' => \$isEnabled('research_literature')",
    "'meetings' => \$isEnabled('research_meetings')",
    "'collaboration' => \$isEnabled('research_collaboration')",
    "'archives' => \$isEnabled('research_archives')",
    "'datasets' => \$isEnabled('research_datasets')",
    'function saveReferences',
    'function removeReference',
    "'doi:'.\$normalizedDoi",
    "'scholarly:'.\$record->provider",
], 'Research workspace controller');

$must('resources/views/research/literature.blade.php', [
    'Literature &amp; References',
    'Save selected papers',
    'Project Library',
    'Reading Queue',
    'Synthesis',
    'Compare Papers',
    'My Summary',
    'Methodology',
    'Key Findings',
    'Research Gap / Relevance',
    'Contradictions / Comparison',
    "name=\"reading_status\"",
    "name=\"collections\"",
    'Reviewed literature feeds your Literature Review section',
    'does not bypass publisher or access restrictions',
], 'Professional reference library');
$must('app/Services/LiteratureSynthesisService.php', [
    'methodologySignals',
    "'themes'",
    "'findings'",
    "'limitations'",
    "'gaps'",
    "'contradictions'",
], 'Literature synthesis');
$must('resources/views/research/show.blade.php', [
    'Insert from your references',
    'Reviewed literature',
    'data-literature-insert',
    'research-editor-',
    "['chapter_2', 'literature_review']",
], 'Chapter Two literature integration');
$must('resources/js/rich-editor.js', [
    'acadflow:editor-insert',
    'api.insertText(text)',
], 'Rich editor literature insertion');
$must('database/migrations/2026_09_16_040000_expand_research_literature_workflow.php', [
    "'reading_status'",
    "'collections'",
    "'reviewed_at'",
    'research_literature_status_idx',
], 'Literature workflow migration');
$must('app/Services/Scholarly/ScholarlyDiscoveryService.php', [
    "return 'doi:'.\$doi",
    "return 'title:'.trim(\$title).'|'",
    "'year_from'",
    "'year_to'",
], 'Scholarly discovery');

$scholarlyConfig = $read('config/scholarly.php');
foreach (['openalex', 'crossref', 'semantic_scholar', 'core', 'doaj', 'pubmed', 'arxiv'] as $provider) {
    if (! str_contains($scholarlyConfig, "'{$provider}' =>")) {
        $errors[] = "Missing scholarly provider {$provider}.";
    }
}

$must('tests/Feature/ResearchStudioProfessionalizationTest.php', [
    'test_institutional_controls_are_hidden_until_admin_enables_research_expansion',
    'test_supervisor_is_invited_and_not_assigned_until_acceptance',
    'test_doi_less_papers_are_saved_as_distinct_project_references_and_notes_reopen',
    'test_control_center_defaults_to_core_research_operations_only',
], 'Research Studio regression tests');

if ($errors !== []) {
    fwrite(STDERR, "Research Studio professionalization preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo "Research Studio professionalization preflight passed (Admin gating, invitation supervision, focused control center, multi-source literature library, API parity).\n";

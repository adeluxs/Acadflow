<?php

$root = dirname(__DIR__);
$checks = [
    ['app/Services/ResearchWritingExperienceService.php', "'chapter_1' =>", 'chapter-aware writing guidance exists'],
    ['app/Services/ResearchWritingExperienceService.php', "'chapter_2' =>", 'literature-review guidance exists'],
    ['app/Services/ResearchWritingExperienceService.php', "'chapter_3' =>", 'methodology guidance exists'],
    ['app/Services/ResearchWritingExperienceService.php', "'chapter_4' =>", 'results guidance exists'],
    ['app/Services/ResearchWritingExperienceService.php', "'chapter_5' =>", 'conclusion/discussion guidance exists'],
    ['app/Services/ResearchWritingExperienceService.php', "'ai_prompts' => \$aiPrompts", 'writing guidance exposes the stable snake_case AI prompt key'],
    ['app/Http/Controllers/ResearchProjectController.php', '$activeSection = $writing->activeSection', 'selected section is resolved server-side'],
    ['app/Http/Controllers/ResearchProjectController.php', '$sectionJourney = $writing->journey', 'template-driven chapter navigator is prepared'],
    ['resources/views/research/show.blade.php', 'Chapter navigator', 'professional chapter navigator is rendered'],
    ['resources/views/research/show.blade.php', 'Recommended structure', 'chapter-specific professional guidance is rendered'],
    ['resources/views/research/show.blade.php', "\$writingGuidance['ai_prompts'] ?? []", 'optional AI prompt guidance cannot crash the writing workspace'],
    ['resources/views/research/show.blade.php', 'Insert from your references', 'Chapter Two literature integration remains present'],
    ['resources/views/research/show.blade.php', '@if($institutionExpansionEnabled && $research->workspace_mode === \'institutional\')', 'institution supervision is feature gated'],
    ['resources/views/research/show.blade.php', '@if($datasetsEnabled && $isResultsWritingSection)', 'dataset UI is Admin-gated and results-specific'],
    ['resources/views/research/workspace.blade.php', 'Research journey', 'control center renders project journey'],
    ['resources/views/research/workspace.blade.php', 'Next action', 'control center highlights next action'],
    ['app/Http/Controllers/ResearchWorkspaceController.php', "assertResearchFeatureEnabled('research_datasets'", 'dataset endpoint is feature gated'],
    ['app/Http/Controllers/ResearchWorkspaceController.php', "assertResearchFeatureEnabled('research_meetings'", 'meeting endpoint is feature gated'],
    ['app/Http/Controllers/ResearchWorkspaceController.php', "assertResearchFeatureEnabled('research_collaboration'", 'collaboration endpoint is feature gated'],
    ['app/Http/Controllers/ResearchWorkspaceController.php', "assertResearchFeatureEnabled('research_archives'", 'archive endpoint is feature gated'],
    ['app/Http/Controllers/ResearchSpecializedController.php', "assertFeatureEnabled('siwes_module'", 'SIWES endpoint is feature gated'],
    ['app/Http/Controllers/ResearchSpecializedController.php', "assertFeatureEnabled('seminar_module'", 'seminar endpoint is feature gated'],
    ['tests/Feature/ResearchStudioProfessionalWorkflowTest.php', 'test_writing_workspace_focuses_one_selected_chapter_with_professional_guidance', 'runtime regression coverage is included'],
];

$failed = [];
foreach ($checks as [$file, $needle, $label]) {
    $content = @file_get_contents($root.'/'.$file);
    if ($content === false || ! str_contains($content, $needle)) {
        $failed[] = $label.' ['.$file.']';
    }
}

$show = file_get_contents($root.'/resources/views/research/show.blade.php');
if (str_contains($show, '@foreach($research->sections as $section)')) {
    $failed[] = 'writing workspace still renders every chapter as one long page';
}

if ($failed) {
    fwrite(STDERR, "Research Studio professional workflow preflight FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Research Studio professional workflow preflight passed (template-driven control center, focused chapter editor, Admin-gated advanced modules).\n";

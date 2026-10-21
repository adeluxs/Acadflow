<?php $__env->startSection('title', $research->title); ?>
<?php $__env->startSection('page-title', 'Writing workspace'); ?>
<?php $__env->startSection('page-subtitle', $research->title); ?>

<?php $__env->startSection('content'); ?>
<?php
    $currentStage = $research->workflowInstance?->currentStage;
    $nextStage = $research->workflowInstance?->definition?->stages?->first(fn($stage) => $currentStage && $stage->position > $currentStage->position);
    $activeKey = strtolower((string) ($activeSection?->key ?? ''));
    $activeTitle = strtolower((string) ($activeSection?->title ?? ''));
    $isLiteratureWritingSection = $activeSection && (in_array($activeKey, ['chapter_2', 'literature_review'], true) || str_contains($activeTitle, 'literature'));
    $isReferencesWritingSection = $activeSection && (str_contains($activeKey, 'reference') || str_contains($activeTitle, 'reference') || str_contains($activeTitle, 'bibliograph'));
    $isResultsWritingSection = $activeSection && ($activeKey === 'chapter_4' || str_contains($activeTitle, 'result') || str_contains($activeTitle, 'analysis'));
?>

<div class="studio-shell studio-writing-workspace space-y-4" data-writing-workspace>
    <nav class="studio-writing-navigation flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Project workspace">
        <a href="<?php echo e(route('research.workspace', $research)); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Control center</a>
        <a href="<?php echo e(route('research.show', ['research' => $research, 'section' => $activeSection?->uuid])); ?>" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Writing workspace</a>
        <?php if($literatureEnabled): ?>
            <a href="<?php echo e(route('research.literature.search', $research)); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Literature &amp; references</a>
        <?php endif; ?>
        <a href="<?php echo e(route('research.plan', $research)); ?>" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">Plan & library</a>
        <details class="studio-export-menu">
            <summary>Export document</summary>
            <div><a href="<?php echo e(route('research.export.docx', $research)); ?>">Download DOCX</a><a href="<?php echo e(route('research.export.pdf', $research)); ?>">Download PDF</a><a href="<?php echo e(route('research.export.html', $research)); ?>">Preview / export</a></div>
        </details>
    </nav>

    <details class="studio-writing-overview overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <summary class="studio-disclosure-summary"><span><strong>Project overview</strong><span class="block text-xs font-normal text-slate-500"><?php echo e(number_format((float) $research->progress)); ?>% complete · <?php echo e($currentStage?->name ?? ucwords(str_replace('_', ' ', $research->status))); ?></span></span><span class="studio-disclosure-hint">Details &amp; workflow</span></summary>
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 p-5 text-white sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-200">Professional writing workspace</p>
                    <h1 class="mt-2 text-2xl font-black"><?php echo e($writingGuidance['title']); ?></h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300"><?php echo e($writingGuidance['description']); ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('validate', $research)): ?>
                        <form method="POST" action="<?php echo e(route('research.validate', $research)); ?>"><?php echo csrf_field(); ?><button class="rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs font-bold text-white hover:bg-white/15">Run validation</button></form>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('transition', $research)): ?>
                        <?php if($nextStage): ?>
                            <form method="POST" action="<?php echo e(route('research.transition', $research)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="target_stage" value="<?php echo e($nextStage->key); ?>"><input type="hidden" name="action" value="advance"><button class="rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-950">Move to <?php echo e($nextStage->name); ?></button></form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-300">
                <span>Overall progress <strong class="text-white"><?php echo e(number_format((float) $research->progress)); ?>%</strong></span>
                <span>Current stage <strong class="text-white"><?php echo e($currentStage?->name ?? ucwords(str_replace('_',' ', $research->status))); ?></strong></span>
                <span><?php echo e($sectionJourney->count()); ?> configured sections</span>
                <?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?>
                    <span class="rounded-full bg-indigo-400/15 px-2.5 py-1 font-bold text-indigo-100">Institutional workspace</span>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <div class="studio-writing-actions">
        <p><span class="studio-writing-dot" aria-hidden="true"></span> Your space to write <span class="studio-writing-actions-note">Choose a chapter, then make it yours.</span></p>
        <div class="flex flex-wrap gap-2"><a href="#writing-tools" class="studio-button-secondary" data-writing-open-tools>Writing tools</a><button type="button" class="studio-button" data-writing-focus-toggle aria-pressed="false" hidden>Focus writing</button></div>
    </div>

    <div class="studio-writing-layout">
        <details class="studio-chapter-nav" data-writing-chapters <?php if(!request()->filled('section')): ?> open <?php endif; ?>>
            <summary class="studio-disclosure-summary"><span><strong>Chapter navigator</strong><span class="studio-chapter-current"><?php echo e($activeSection?->title ?? 'Choose a section'); ?></span></span><span class="studio-disclosure-hint">Change chapter</span></summary>
            <div class="space-y-4 p-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Research document</p><h2 class="mt-1 font-black text-slate-950">Chapter navigator</h2></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500"><?php echo e($sectionJourney->count()); ?></span>
                </div>
                <div class="mt-4 space-y-2">
                    <?php $__currentLoopData = $sectionJourney; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php ($sectionNav = $step['section']); ?>
                        <?php ($isActive = $activeSection && $sectionNav->id === $activeSection->id); ?>
                        <a href="<?php echo e(route('research.show', ['research' => $research, 'section' => $sectionNav->uuid])); ?>" <?php if($isActive): ?> aria-current="page" <?php endif; ?> data-writing-chapter class="block rounded-xl border px-3 py-3 transition <?php echo e($isActive ? 'border-indigo-300 bg-indigo-50 ring-1 ring-indigo-100' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50'); ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full <?php echo e($step['progress'] >= 100 ? 'bg-emerald-100 text-emerald-700' : ($isActive ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500')); ?> text-[11px] font-black"><?php echo e($step['progress'] >= 100 ? '✓' : $loop->iteration); ?></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold leading-5 text-slate-900"><?php echo e($step['label']); ?></span>
                                    <span class="block truncate text-[11px] text-slate-500"><?php echo e($sectionNav->title); ?></span>
                                </span>
                            </div>
                            <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full <?php echo e($step['progress'] >= 100 ? 'bg-emerald-500' : 'bg-indigo-500'); ?>" style="width:<?php echo e($step['progress']); ?>%"></div></div>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Project progress</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" style="width:<?php echo e(min(100, max(0, (float) $research->progress))); ?>%"></div></div>
                <p class="mt-2 text-xs text-slate-500"><?php echo e(number_format((float) $research->progress)); ?>% complete</p>
            </section>
            </div>
        </details>

        <div class="studio-writing-document min-w-0 space-y-5" role="region" aria-label="Chapter writing area">
            <?php if($activeSection): ?>
                <section class="studio-writing-paper overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="border-b border-slate-100 bg-gradient-to-r from-white to-slate-50 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600"><?php echo e($writingGuidance['eyebrow']); ?></p>
                                <h2 class="mt-1 text-xl font-black text-slate-950"><?php echo e($activeSection->title); ?></h2>
                                <p class="mt-1 text-xs text-slate-500">Version <?php echo e($activeSection->document->version_number); ?> · <?php echo e(ucwords(str_replace('_',' ', $activeSection->status))); ?><?php echo e($activeSection->is_required ? ' · Required' : ''); ?></p>
                            </div>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $research)): ?>
                                <form method="POST" action="<?php echo e(route('research.sections.approve', [$research, $activeSection])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="expected_version" value="<?php echo e($activeSection->document->version_number); ?>"><button class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Approve section</button></form>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="p-5">
                        <?php if(auth()->user()->can('update', $research) && !$activeSection->locked_at): ?>
                            <form method="POST" action="<?php echo e(route('research.sections.save', [$research, $activeSection])); ?>" class="space-y-3" data-research-autosave><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><input type="hidden" name="expected_version" value="<?php echo e($activeSection->document->version_number); ?>">
                                <textarea id="research-editor-<?php echo e($activeSection->uuid); ?>" name="body" rows="18" data-rich-editor data-editor-height="560px" data-ai-url="<?php echo e(route('ai.writing')); ?>" data-ai-enabled="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestions_enabled', true, auth()->user()?->university_id) ? '1' : '0'); ?>" data-ai-min-chars="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestion_min_chars', 60, auth()->user()?->university_id)); ?>" data-ai-delay="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestion_delay_ms', 1600, auth()->user()?->university_id)); ?>" class="w-full rounded-xl border-slate-300 font-serif leading-7" placeholder="Write <?php echo e(strtolower($activeSection->title)); ?> here..."><?php echo e(old('body', $activeSection->document->body)); ?></textarea>
                                <div class="flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span data-autosave-status role="status" class="text-xs leading-5 text-slate-500">Changes save automatically as recoverable versions.</span>
                                    <button type="submit" class="studio-button">Save version</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <article class="prose max-w-none"><?php echo app(\App\Services\RichTextSanitizer::class)->sanitize((string) $activeSection->document->body); ?></article>
                        <?php endif; ?>
                    </div>
                </section>

                <details class="studio-panel studio-writing-secondary" <?php if(request()->has('versions_page')): ?> open <?php endif; ?>><summary class="cursor-pointer font-bold text-sm">Section version history</summary><div class="mt-4 space-y-3"><?php $__currentLoopData = $sectionVersions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><details class="rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer text-xs">Version <?php echo e($version->version_number); ?> · <?php echo e($version->created_at?->format('d M Y H:i')); ?></summary><article class="prose mt-3 max-w-none text-sm"><?php echo app(\App\Services\RichTextSanitizer::class)->sanitize((string) $version->body); ?></article><?php if(auth()->user()->can('update', $research) && !$activeSection->locked_at): ?><form method="POST" action="<?php echo e(route('research.sections.versions.restore', [$research, $activeSection, $version])); ?>" class="mt-3"><?php echo csrf_field(); ?><button class="studio-button-secondary">Restore as new version</button></form><?php endif; ?></details><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php echo e($sectionVersions->links()); ?></div></details>
                <details class="studio-writing-secondary rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" <?php if(request()->has('comments_page') || $errors->has('body') || $errors->has('description')): ?> open <?php endif; ?>>
                    <summary class="cursor-pointer font-bold text-sm">Comments and corrections <span class="ml-2 rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500"><?php echo e($sectionComments->total()); ?></span></summary>
                    <p class="mt-3 text-xs text-slate-500">Keep feedback attached to the section it applies to.</p>
                    <div class="mt-4 space-y-3">
                        <?php $__empty_1 = true; $__currentLoopData = $sectionComments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="rounded-xl bg-slate-50 p-3"><div class="flex justify-between gap-3 text-xs text-slate-500"><span><?php echo e($comment->user?->full_name); ?> · <?php echo e(ucfirst($comment->type)); ?></span><span><?php echo e($comment->created_at?->diffForHumans()); ?></span></div><p class="mt-2 text-sm leading-6 text-slate-700"><?php echo e($comment->body); ?></p></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <p class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No comments yet.</p>
                        <?php endif; ?>
                    </div>
                    <?php echo e($sectionComments->links()); ?>

                    <form method="POST" action="<?php echo e(route('research.sections.comment', [$research, $activeSection])); ?>" class="mt-3 flex flex-col gap-2 sm:flex-row"><?php echo csrf_field(); ?><input name="body" required class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm" placeholder="Add a review comment" aria-label="Review comment"><button class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold">Comment</button></form>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $research)): ?>
                        <form method="POST" action="<?php echo e(route('research.sections.corrections', [$research, $activeSection])); ?>" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]"><?php echo csrf_field(); ?><select name="type" class="rounded-xl border-slate-300 text-sm"><option value="general">General correction</option><option value="rewrite">Rewrite</option><option value="methodology">Methodology</option><option value="references">References</option><option value="formatting">Formatting</option><option value="academic_language">Academic language</option></select><input name="description" required class="rounded-xl border-slate-300 text-sm" placeholder="Describe the required correction"><button class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-bold text-white">Request</button></form>
                    <?php endif; ?>
                </details>
            <?php else: ?>
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm"><h2 class="text-lg font-black text-slate-900">No writing sections configured</h2><p class="mt-2 text-sm text-slate-500">This project template does not contain any sections yet.</p></section>
            <?php endif; ?>

            <div id="research-assistant" class="studio-writing-secondary">
                <?php echo $__env->make('ai._contextual-assistant', [
                    'assistantFeature' => 'research_assistant',
                    'assistantEndpoint' => route('ai.context.research', $research),
                    'assistantDescription' => $activeSection ? 'Use the authorized project context to review the current section without inventing research evidence.' : null,
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>

        <details class="studio-writing-tools" id="writing-tools">
            <summary class="studio-disclosure-summary"><span><strong>Writing tools &amp; guidance</strong><span class="block text-xs font-normal text-slate-500">Structure, references, research data and review prompts</span></span><span class="studio-disclosure-hint">Explore tools</span></summary>
            <div class="studio-writing-tools-grid">
            <?php if($activeSection): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Recommended structure</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use this as a professional checklist, not a forced university format.</p>
                    <div class="mt-4 space-y-2">
                        <?php $__currentLoopData = $writingGuidance['checklist']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5"><span class="mt-0.5 h-4 w-4 shrink-0 rounded border border-slate-300 bg-white"></span><span class="text-xs font-semibold leading-5 text-slate-700"><?php echo e($item); ?></span></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                        <?php if($writingGuidance['checklist']): ?>
                            <div class="mt-4 border-t border-slate-100 pt-4"><p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">Insert heading</p><div class="flex flex-wrap gap-2"><?php $__currentLoopData = $writingGuidance['checklist']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" data-editor-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e($item); ?>" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-600 hover:bg-slate-50"><?php echo e($item); ?></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <?php if($literatureEnabled && $isLiteratureWritingSection): ?>
                    <section class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Insert from your references</p><h3 class="mt-1 font-black text-indigo-950">Reviewed literature</h3></div><span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-indigo-700"><?php echo e($chapterTwoLiterature->count()); ?></span></div>
                        <p class="mt-2 text-xs leading-5 text-indigo-900/70">Only papers you marked Reviewed are shown. AcadSyntra inserts your own review notes with the project citation; verify and rewrite the wording before saving.</p>
                        <div class="mt-4 max-h-[480px] space-y-3 overflow-y-auto pr-1">
                            <?php $__empty_1 = true; $__currentLoopData = $chapterTwoLiterature; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $literatureRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <article class="rounded-xl border border-indigo-100 bg-white p-3">
                                    <p class="text-sm font-bold leading-5 text-slate-900"><?php echo e($literatureRow['reference']->title); ?></p>
                                    <p class="mt-1 text-xs font-semibold text-indigo-600"><?php echo e($literatureRow['citation']); ?></p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e($literatureRow['citation']); ?>" class="rounded-lg border border-indigo-200 px-2 py-2 text-xs font-bold text-indigo-700">Insert citation</button>
                                        <?php if(filled($literatureRow['note']->findings ?: $literatureRow['note']->summary)): ?><button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e(trim(($literatureRow['note']->findings ?: $literatureRow['note']->summary).' '.$literatureRow['citation'])); ?>" class="rounded-lg bg-indigo-600 px-2 py-2 text-xs font-bold text-white">Insert evidence</button><?php endif; ?>
                                        <?php if(filled($literatureRow['note']->methodology)): ?><button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e(trim($literatureRow['note']->methodology.' '.$literatureRow['citation'])); ?>" class="rounded-lg border border-slate-200 px-2 py-2 text-xs font-bold text-slate-600">Methodology</button><?php endif; ?>
                                        <?php if(filled($literatureRow['note']->research_gap)): ?><button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e(trim($literatureRow['note']->research_gap.' '.$literatureRow['citation'])); ?>" class="rounded-lg border border-slate-200 px-2 py-2 text-xs font-bold text-slate-600">Research gap</button><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="rounded-xl border border-dashed border-indigo-200 bg-white/70 p-4 text-center"><p class="text-sm font-bold text-slate-800">No reviewed references yet.</p><p class="mt-1 text-xs leading-5 text-slate-500">Review papers in the Literature Library and mark them Reviewed before inserting evidence here.</p></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo e(route('research.literature.search', ['research' => $research, 'view' => 'saved'])); ?>" class="mt-4 block rounded-xl border border-indigo-200 bg-white px-3 py-2.5 text-center text-xs font-bold text-indigo-700">Open Literature Library</a>
                    </section>
                <?php elseif($literatureEnabled && $isReferencesWritingSection): ?>
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Reference list</p><h3 class="mt-1 font-black text-emerald-950">Reviewed bibliography</h3></div><span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-emerald-700"><?php echo e($chapterTwoLiterature->count()); ?></span></div>
                        <p class="mt-2 text-xs leading-5 text-emerald-900/70">Insert formatted references for reviewed papers using <?php echo e(strtoupper($citationStyle)); ?> style.</p>
                        <?php if($chapterTwoLiterature->isNotEmpty()): ?>
                            <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($activeSection->uuid); ?>" data-editor-text="<?php echo e($chapterTwoLiterature->pluck('bibliography')->implode("\n\n")); ?>" class="mt-4 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white">Insert reviewed bibliography</button>
                            <div class="mt-4 max-h-[390px] space-y-2 overflow-y-auto pr-1"><?php $__currentLoopData = $chapterTwoLiterature; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $literatureRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="rounded-xl border border-emerald-100 bg-white p-3 text-xs leading-5 text-slate-700"><?php echo e($literatureRow['bibliography']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                        <?php else: ?>
                            <div class="mt-4 rounded-xl border border-dashed border-emerald-200 bg-white/70 p-4 text-center text-xs text-slate-600">No reviewed references are ready for the bibliography yet.</div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if($datasetsEnabled && $isResultsWritingSection): ?>
                    <section class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-cyan-700">Dataset support</p><h3 class="mt-1 font-black text-cyan-950">Research data</h3></div><span class="rounded-full bg-white px-2 py-1 text-xs font-black text-cyan-700"><?php echo e($research->datasets->count()); ?></span></div>
                        <p class="mt-2 text-xs leading-5 text-cyan-900/70">Dataset Management is enabled by the administrator. Upload supporting data only when it is appropriate for your research design.</p>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                            <form method="POST" action="<?php echo e(route('research.datasets.store', $research)); ?>" enctype="multipart/form-data" class="mt-4 space-y-2"><?php echo csrf_field(); ?><input name="name" required class="w-full rounded-xl border-cyan-200 bg-white text-sm" placeholder="Dataset name"><input type="file" name="file" required class="w-full rounded-xl border border-cyan-200 bg-white p-2 text-xs"><select name="access_level" class="w-full rounded-xl border-cyan-200 bg-white text-sm"><option value="project">Project only</option><option value="supervisors">Supervisors</option><?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?><option value="institution">Institution</option><?php endif; ?><option value="public">Public</option></select><button class="w-full rounded-xl bg-cyan-700 px-3 py-2.5 text-xs font-bold text-white">Upload dataset</button></form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="rounded-2xl border border-violet-200 bg-violet-50/50 p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-violet-600">Section-aware AI</p>
                    <h3 class="mt-1 font-black text-violet-950">Useful review prompts</h3>
                    <div class="mt-3 space-y-2"><?php $__currentLoopData = ($writingGuidance['ai_prompts'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prompt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" data-research-ai-prompt="<?php echo e($prompt); ?>" class="block w-full rounded-xl border border-violet-100 bg-white px-3 py-2.5 text-left text-xs font-semibold leading-5 text-violet-900 hover:border-violet-200"><?php echo e($prompt); ?></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                    <a href="#research-assistant" class="mt-3 block rounded-xl bg-violet-600 px-3 py-2.5 text-center text-xs font-bold text-white">Ask Research Assistant</a>
                </section>
            <?php endif; ?>

            <?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?>
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Institutional supervision</p>
                    <?php if($research->supervisor): ?>
                        <h3 class="mt-2 font-black text-indigo-950"><?php echo e($research->supervisor->full_name); ?></h3><p class="mt-1 text-xs text-indigo-800">Accepted supervisor<?php echo e($research->department?->name ? ' · '.$research->department->name : ''); ?></p>
                    <?php elseif($pendingSupervisorInvitation): ?>
                        <h3 class="mt-2 font-black text-indigo-950">Invitation pending</h3><p class="mt-1 text-xs leading-5 text-indigo-800">Waiting for <?php echo e($pendingSupervisorInvitation->invitee?->full_name); ?> to accept.</p>
                    <?php else: ?>
                        <h3 class="mt-2 font-black text-indigo-950">Invite a supervisor</h3>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                            <?php if($supervisorCandidates->isNotEmpty()): ?>
                                <form method="POST" action="<?php echo e(route('research.invitations.store', $research)); ?>" class="mt-3 space-y-2"><?php echo csrf_field(); ?><select name="supervisor_invitee_id" required class="w-full rounded-xl border-indigo-200 bg-white text-sm"><option value="">Choose lecturer</option><?php $__currentLoopData = $supervisorCandidates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($candidate->id); ?>"><?php echo e($candidate->full_name); ?><?php echo e($candidate->department?->name ? ' · '.$candidate->department->name : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><textarea name="supervisor_message" rows="2" maxlength="1000" class="w-full rounded-xl border-indigo-200 bg-white text-sm" placeholder="Optional invitation message"></textarea><button class="w-full rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-bold text-white">Send supervision invitation</button></form>
                            <?php else: ?>
                                <p class="mt-2 text-xs leading-5 text-indigo-800">No eligible lecturers are currently available.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if($research->latestValidationReport): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Latest validation</p><div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-xl bg-blue-50 p-3"><p class="text-[10px] font-bold uppercase text-blue-600">Readiness</p><p class="mt-1 text-lg font-black text-blue-950"><?php echo e(number_format((float)$research->latestValidationReport->readiness_score,1)); ?></p></div><div class="rounded-xl bg-amber-50 p-3"><p class="text-[10px] font-bold uppercase text-amber-600">Similarity</p><p class="mt-1 text-lg font-black text-amber-950"><?php echo e(number_format((float)$research->latestValidationReport->similarity_score,1)); ?>%</p></div></div></section>
            <?php endif; ?>

            <?php if($publishingEnabled): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $research)): ?>
                    <form method="POST" action="<?php echo e(route('research.publish', $research)); ?>" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><?php echo csrf_field(); ?><p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Publication</p><h3 class="mt-1 font-black text-emerald-950">Publish approved research</h3><p class="mt-2 text-xs leading-5 text-emerald-800">Creates a moderated Knowledge Hub draft while preserving the source-project link.</p><select name="category_id" class="mt-3 w-full rounded-xl border-emerald-200 text-sm"><option value="">No category</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category->id); ?>"><?php echo e($category->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><select name="visibility" class="mt-2 w-full rounded-xl border-emerald-200 text-sm"><?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?><option value="institution">Institution only</option><?php endif; ?><option value="public">Public</option></select><button class="mt-3 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white">Create linked publication</button></form>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </details>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-literature-insert], [data-editor-insert]');
    if (button) {
        const targetId = button.dataset.editorTarget || '';
        const target = document.getElementById(targetId);
        const rawText = String(button.dataset.editorText || '').trim();
        if (!target || !rawText) return;
        const text = button.hasAttribute('data-editor-insert') && !button.hasAttribute('data-literature-insert') ? `\n${rawText}\n` : rawText;
        let accepted = false;
        if (window.AcadFlowRichEditor?.insert) {
            accepted = window.AcadFlowRichEditor.insert(target, text) === true;
        } else {
            target._acadflowPendingInsertions = target._acadflowPendingInsertions || [];
            target._acadflowPendingInsertions.push(text);
            accepted = true;
        }
        if (!accepted) return;
        const originalLabel = button.dataset.originalLabel || button.textContent.trim();
        button.dataset.originalLabel = originalLabel;
        button.textContent = 'Inserted ✓';
        button.disabled = true;
        setTimeout(() => { button.textContent = originalLabel; button.disabled = false; }, 1200);
        return;
    }

    const promptButton = event.target.closest('[data-research-ai-prompt]');
    if (!promptButton) return;
    const question = document.querySelector('#research-assistant [data-ai-question]');
    if (!question) return;
    question.value = promptButton.dataset.researchAiPrompt || '';
    question.dispatchEvent(new Event('input', {bubbles:true}));
    document.getElementById('research-assistant')?.scrollIntoView({behavior:'smooth', block:'start'});
    setTimeout(() => question.focus(), 350);
});

document.querySelectorAll('[data-literature-insert], [data-editor-insert]').forEach((button) => {
    button.dataset.originalLabel = button.textContent.trim();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/show.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', $research->title); ?>
<?php $__env->startSection('page-title', $research->title); ?>
<?php $__env->startSection('page-subtitle', ($research->researchType?->name ?? 'Research').' · '.ucwords(str_replace('_',' ', $research->status))); ?>
<?php $__env->startSection('content'); ?>
<?php
    $currentStage = $research->workflowInstance?->currentStage;
    $nextStage = $research->workflowInstance?->definition?->stages?->first(fn($stage) => $currentStage && $stage->position > $currentStage->position);
?>
<div class="space-y-6">
    <div class="grid gap-5 md:grid-cols-2 <?php echo e($institutionExpansionEnabled && $research->workspace_mode === 'institutional' ? 'xl:grid-cols-4' : 'xl:grid-cols-3'); ?>">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Progress</p><p class="mt-2 text-3xl font-semibold"><?php echo e(number_format((float)$research->progress)); ?>%</p><div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" style="width:<?php echo e(min(100,(float)$research->progress)); ?>%"></div></div></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Current stage</p><p class="mt-2 font-semibold"><?php echo e($currentStage?->name ?? ucwords(str_replace('_',' ', $research->status))); ?></p><p class="mt-1 text-sm text-slate-500">Workflow-controlled</p></div>
        <?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Supervision</p><p class="mt-2 font-semibold"><?php echo e($research->supervisor?->full_name ?? 'Invitation pending'); ?></p><p class="mt-1 text-sm text-slate-500"><?php echo e($research->department?->name ?: 'Department not set'); ?></p></div>
        <?php endif; ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Latest validation</p><p class="mt-2 font-semibold"><?php echo e($research->latestValidationReport?->readiness_score !== null ? number_format((float)$research->latestValidationReport->readiness_score, 1).' readiness' : 'Not run'); ?></p><p class="mt-1 text-sm text-slate-500">Similarity: <?php echo e($research->latestValidationReport?->similarity_score !== null ? number_format((float)$research->latestValidationReport->similarity_score,1).'%' : '—'); ?></p></div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="<?php echo e(route('research.workspace',$research)); ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Project control center</a>
        <?php if($literatureEnabled): ?>
            <a href="<?php echo e(route('research.literature.search',$research)); ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Literature &amp; references</a>
        <?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('validate', $research)): ?>
            <form method="POST" action="<?php echo e(route('research.validate', $research)); ?>"><?php echo csrf_field(); ?><button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Run validation & similarity</button></form>
        <?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('transition', $research)): ?>
            <?php if($nextStage): ?>
            <form method="POST" action="<?php echo e(route('research.transition', $research)); ?>" class="flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="target_stage" value="<?php echo e($nextStage->key); ?>"><input type="hidden" name="action" value="advance"><button class="rounded-xl border border-blue-300 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700">Move to <?php echo e($nextStage->name); ?></button></form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if($publishingEnabled && $research->publications->isNotEmpty()): ?>
            <a href="<?php echo e(route('knowledge.manage.edit', $research->publications->first())); ?>" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">Open linked publication</a>
        <?php endif; ?>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Writing workspace</p><p class="mt-1 text-sm text-slate-600">Jump directly to a research section while keeping every section in one versioned workspace.</p></div>
            <select class="rounded-xl border-slate-300 text-sm font-semibold text-slate-700" onchange="if(this.value){location.hash='section-'+this.value}">
                <option value="">Go to section…</option>
                <?php $__currentLoopData = $research->sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionNav): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($sectionNav->key); ?>"><?php echo e($sectionNav->title); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
    </section>

    <?php echo $__env->make('ai._contextual-assistant', [
        'assistantFeature' => 'research_assistant',
        'assistantEndpoint' => route('ai.context.research', $research),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            <?php $__currentLoopData = $research->sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $isLiteratureWritingSection = in_array($section->key, ['chapter_2', 'literature_review'], true);
                    $isReferencesWritingSection = $section->key === 'references';
                ?>
                <section id="section-<?php echo e($section->key); ?>" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><h2 class="font-semibold text-slate-900"><?php echo e($section->title); ?></h2><p class="mt-1 text-xs text-slate-500">Version <?php echo e($section->document->version_number); ?> · <?php echo e(ucwords(str_replace('_',' ', $section->status))); ?> <?php if($section->is_required): ?> · Required <?php endif; ?></p></div>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $research)): ?>
                            <form method="POST" action="<?php echo e(route('research.sections.approve', [$research, $section])); ?>"><?php echo csrf_field(); ?><button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Approve section</button></form>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                            <div class="<?php echo e($literatureEnabled && ($isLiteratureWritingSection || $isReferencesWritingSection) ? 'grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]' : ''); ?>">
                                <form method="POST" action="<?php echo e(route('research.sections.save', [$research, $section])); ?>" class="space-y-3"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                                    <textarea id="research-editor-<?php echo e($section->uuid); ?>" name="body" rows="14" data-rich-editor data-editor-height="<?php echo e(($isLiteratureWritingSection || $isReferencesWritingSection) ? '480px' : '340px'); ?>" data-ai-url="<?php echo e(route('ai.writing')); ?>" data-ai-enabled="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestions_enabled', true, auth()->user()?->university_id) ? '1' : '0'); ?>" data-ai-min-chars="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestion_min_chars', 60, auth()->user()?->university_id)); ?>" data-ai-delay="<?php echo e(\App\Services\SettingService::get('ai_editor_suggestion_delay_ms', 1600, auth()->user()?->university_id)); ?>" class="w-full rounded-xl border-slate-300 font-serif leading-7" placeholder="Write <?php echo e(strtolower($section->title)); ?> here..."><?php echo e(old('body', $section->document->body)); ?></textarea>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><span class="text-xs text-slate-500">Insertions stay in the editor until you save this recoverable version.</span><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save version</button></div>
                                </form>

                                <?php if($literatureEnabled && $isLiteratureWritingSection): ?>
                                    <aside class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div><p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Insert from your references</p><h3 class="mt-1 font-black text-indigo-950">Reviewed literature</h3></div>
                                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-indigo-700"><?php echo e($chapterTwoLiterature->count()); ?></span>
                                        </div>
                                        <p class="mt-2 text-xs leading-5 text-indigo-900/70">Only papers you marked Reviewed are shown. AcadFlow inserts your own review notes with the project citation; verify the wording before saving.</p>

                                        <div class="mt-4 max-h-[520px] space-y-3 overflow-y-auto pr-1">
                                            <?php $__empty_1 = true; $__currentLoopData = $chapterTwoLiterature; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $literatureRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <article class="rounded-xl border border-indigo-100 bg-white p-3">
                                                    <p class="text-sm font-bold leading-5 text-slate-900"><?php echo e($literatureRow['reference']->title); ?></p>
                                                    <p class="mt-1 text-xs font-semibold text-indigo-600"><?php echo e($literatureRow['citation']); ?></p>
                                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                                        <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($section->uuid); ?>" data-editor-text="<?php echo e($literatureRow['citation']); ?>" class="rounded-lg border border-indigo-200 px-2.5 py-2 text-xs font-bold text-indigo-700">Insert citation</button>
                                                        <?php if(filled($literatureRow['note']->findings ?: $literatureRow['note']->summary)): ?>
                                                            <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($section->uuid); ?>" data-editor-text="<?php echo e(trim(($literatureRow['note']->findings ?: $literatureRow['note']->summary).' '.$literatureRow['citation'])); ?>" class="rounded-lg bg-indigo-600 px-2.5 py-2 text-xs font-bold text-white">Insert evidence</button>
                                                        <?php endif; ?>
                                                        <?php if(filled($literatureRow['note']->methodology)): ?>
                                                            <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($section->uuid); ?>" data-editor-text="<?php echo e(trim($literatureRow['note']->methodology.' '.$literatureRow['citation'])); ?>" class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-bold text-slate-600">Methodology</button>
                                                        <?php endif; ?>
                                                        <?php if(filled($literatureRow['note']->research_gap)): ?>
                                                            <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($section->uuid); ?>" data-editor-text="<?php echo e(trim($literatureRow['note']->research_gap.' '.$literatureRow['citation'])); ?>" class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-bold text-slate-600">Research gap</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <div class="rounded-xl border border-dashed border-indigo-200 bg-white/70 p-4 text-center">
                                                    <p class="text-sm font-bold text-slate-800">No reviewed references yet.</p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-500">Review papers in the Literature Library and mark them Reviewed before inserting evidence here.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?php echo e(route('research.literature.search', ['research' => $research, 'view' => 'saved'])); ?>" class="mt-4 block rounded-xl border border-indigo-200 bg-white px-3 py-2.5 text-center text-xs font-bold text-indigo-700">Open Literature Library</a>
                                    </aside>
                                <?php elseif($literatureEnabled && $isReferencesWritingSection): ?>
                                    <aside class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div><p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Reference list</p><h3 class="mt-1 font-black text-emerald-950">Reviewed bibliography</h3></div>
                                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-emerald-700"><?php echo e($chapterTwoLiterature->count()); ?></span>
                                        </div>
                                        <p class="mt-2 text-xs leading-5 text-emerald-900/70">Insert formatted references for the reviewed papers used in your literature review. The project citation style is <?php echo e(strtoupper($citationStyle)); ?>.</p>
                                        <?php if($chapterTwoLiterature->isNotEmpty()): ?>
                                            <button type="button" data-literature-insert data-editor-target="research-editor-<?php echo e($section->uuid); ?>" data-editor-text="<?php echo e($chapterTwoLiterature->pluck('bibliography')->implode("

")); ?>" class="mt-4 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white">Insert reviewed bibliography</button>
                                            <div class="mt-4 max-h-[430px] space-y-2 overflow-y-auto pr-1">
                                                <?php $__currentLoopData = $chapterTwoLiterature; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $literatureRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="rounded-xl border border-emerald-100 bg-white p-3 text-xs leading-5 text-slate-700"><?php echo e($literatureRow['bibliography']); ?></div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-4 rounded-xl border border-dashed border-emerald-200 bg-white/70 p-4 text-center text-xs text-slate-600">No reviewed references are ready for the bibliography yet.</div>
                                        <?php endif; ?>
                                    </aside>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <article class="prose max-w-none"><?php echo app(\App\Services\RichTextSanitizer::class)->sanitize((string) $section->document->body); ?></article>
                        <?php endif; ?>

                        <div class="mt-6 border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-semibold">Comments and corrections</h3>
                            <div class="mt-3 space-y-3">
                                <?php $__empty_1 = true; $__currentLoopData = $section->document->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <div class="rounded-xl bg-slate-50 p-3"><div class="flex justify-between text-xs text-slate-500"><span><?php echo e($comment->user?->full_name); ?> · <?php echo e(ucfirst($comment->type)); ?></span><span><?php echo e($comment->created_at?->diffForHumans()); ?></span></div><p class="mt-2 text-sm"><?php echo e($comment->body); ?></p></div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <p class="text-sm text-slate-500">No comments yet.</p> <?php endif; ?>
                            </div>
                            <form method="POST" action="<?php echo e(route('research.sections.comment', [$research, $section])); ?>" class="mt-3 flex gap-2"><?php echo csrf_field(); ?><input name="body" required class="flex-1 rounded-xl border-slate-300 text-sm" placeholder="Add a review comment"><button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Comment</button></form>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $research)): ?>
                            <form method="POST" action="<?php echo e(route('research.sections.corrections', [$research, $section])); ?>" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]"><?php echo csrf_field(); ?><select name="type" class="rounded-xl border-slate-300 text-sm"><option value="general">General correction</option><option value="rewrite">Rewrite</option><option value="methodology">Methodology</option><option value="references">References</option><option value="formatting">Formatting</option><option value="academic_language">Academic language</option></select><input name="description" required class="rounded-xl border-slate-300 text-sm" placeholder="Describe the required correction"><button class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-white">Request</button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <aside class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Project details</h3><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Owner</dt><dd class="font-medium"><?php echo e($research->owner?->full_name); ?></dd></div><div><dt class="text-slate-500">Research area</dt><dd><?php echo e($research->research_area ?: '—'); ?></dd></div><div><dt class="text-slate-500">Keywords</dt><dd><?php echo e(implode(', ', $research->keywords ?? []) ?: '—'); ?></dd></div><div><dt class="text-slate-500">Expected completion</dt><dd><?php echo e($research->expected_completion_date?->format('M j, Y') ?? '—'); ?></dd></div></dl></div>

            <?php if($institutionExpansionEnabled && $research->workspace_mode === 'institutional'): ?>
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Institutional supervision</p>
                    <?php if($research->supervisor): ?>
                        <h3 class="mt-2 font-bold text-indigo-950"><?php echo e($research->supervisor->full_name); ?></h3>
                        <p class="mt-1 text-sm text-indigo-900/75">Accepted supervisor<?php echo e($research->department?->name ? ' · '.$research->department->name : ''); ?></p>
                    <?php elseif($pendingSupervisorInvitation): ?>
                        <h3 class="mt-2 font-bold text-indigo-950">Invitation pending</h3>
                        <p class="mt-1 text-sm text-indigo-900/75">Waiting for <?php echo e($pendingSupervisorInvitation->invitee?->full_name); ?> to accept the supervision request.</p>
                        <?php if($pendingSupervisorInvitation->expires_at): ?>
                            <p class="mt-2 text-xs text-indigo-700">Expires <?php echo e($pendingSupervisorInvitation->expires_at->diffForHumans()); ?>.</p>
                        <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                        <p class="mt-3 text-xs text-indigo-800">The project will not assign a supervisor until the invited lecturer accepts.</p>
                    <?php endif; ?>
                    <?php else: ?>
                        <h3 class="mt-2 font-bold text-indigo-950">Invite a supervisor</h3>
                        <p class="mt-1 text-sm leading-6 text-indigo-900/75">Choose an eligible lecturer from this institution. They become supervisor only after accepting.</p>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $research)): ?>
                            <?php if($supervisorCandidates->isNotEmpty()): ?>
                                <form method="POST" action="<?php echo e(route('research.invitations.store', $research)); ?>" class="mt-4 space-y-3">
                                    <?php echo csrf_field(); ?>
                                    <select name="supervisor_invitee_id" required class="w-full rounded-xl border-indigo-200 bg-white text-sm">
                                        <option value="">Choose lecturer</option>
                                        <?php $__currentLoopData = $supervisorCandidates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($candidate->id); ?>"><?php echo e($candidate->full_name); ?><?php echo e($candidate->department?->name ? ' · '.$candidate->department->name : ''); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                    <textarea name="supervisor_message" rows="2" maxlength="1000" class="w-full rounded-xl border-indigo-200 bg-white text-sm" placeholder="Optional message about the research"></textarea>
                                    <button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Send supervision invitation</button>
                                </form>
                            <?php else: ?>
                                <p class="mt-3 rounded-xl bg-white/70 p-3 text-sm text-indigo-900">No eligible lecturers are currently available in this institution.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if($research->latestValidationReport): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Validation report</h3><p class="mt-3 text-sm text-slate-600"><?php echo e($research->latestValidationReport->summary); ?></p><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl bg-blue-50 p-3"><p class="text-xs text-blue-700">Readiness</p><p class="text-xl font-semibold text-blue-900"><?php echo e(number_format((float)$research->latestValidationReport->readiness_score,1)); ?></p></div><div class="rounded-xl bg-amber-50 p-3"><p class="text-xs text-amber-700">Similarity</p><p class="text-xl font-semibold text-amber-900"><?php echo e(number_format((float)$research->latestValidationReport->similarity_score,1)); ?>%</p></div></div></div>
            <?php endif; ?>

            <?php if($publishingEnabled): ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $research)): ?>
            <form method="POST" action="<?php echo e(route('research.publish', $research)); ?>" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><?php echo csrf_field(); ?><h3 class="font-semibold text-emerald-900">Publish approved research</h3><p class="mt-2 text-sm text-emerald-800">Creates a moderated Knowledge Hub draft and preserves the permanent source-project link.</p><select name="category_id" class="mt-4 w-full rounded-xl border-emerald-200 text-sm"><option value="">No category</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category->id); ?>"><?php echo e($category->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><select name="visibility" class="mt-3 w-full rounded-xl border-emerald-200 text-sm"><option value="institution">Institution only</option><option value="public">Public</option></select><button class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">Create linked publication</button></form>
            <?php endif; ?>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-literature-insert]');
    if (!button) return;

    const targetId = button.dataset.editorTarget || '';
    const target = document.getElementById(targetId);
    const text = String(button.dataset.editorText || '').trim();
    if (!target || !text) return;

    let accepted = false;
    if (window.AcadFlowRichEditor?.insert) {
        accepted = window.AcadFlowRichEditor.insert(target, text) === true;
    } else {
        // The rich-editor bundle is loaded dynamically. Queue the insertion on the
        // textarea so it is consumed as soon as Quill/fallback finishes booting.
        target._acadflowPendingInsertions = target._acadflowPendingInsertions || [];
        target._acadflowPendingInsertions.push(text);
        accepted = true;
    }

    if (!accepted) return;

    const originalLabel = button.dataset.originalLabel || button.textContent.trim();
    button.textContent = 'Inserted ✓';
    button.disabled = true;
    setTimeout(() => {
        button.textContent = originalLabel;
        button.disabled = false;
    }, 1200);
});

document.querySelectorAll('[data-literature-insert]').forEach((button) => {
    button.dataset.originalLabel = button.textContent.trim();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/research/show.blade.php ENDPATH**/ ?>
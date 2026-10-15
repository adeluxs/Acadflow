<?php $__env->startSection('title', 'Research project control center'); ?>
<?php $__env->startSection('page-title', 'Research project control center'); ?>
<?php $__env->startSection('page-subtitle', $research->title); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <nav class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <a href="<?php echo e(route('research.workspace', $research)); ?>" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Control center</a>
        <a href="<?php echo e(route('research.show', $research)); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Writing workspace</a>
        <?php if($modules['literature']): ?>
            <a href="<?php echo e(route('research.literature.search', $research)); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Literature &amp; references</a>
        <?php endif; ?>
        <?php if($modules['siwes'] || $modules['seminar']): ?>
            <a href="<?php echo e(route('research.specialized.show', $research)); ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Specialized workspace</a>
        <?php endif; ?>
        <a href="<?php echo e(route('research.export.html', $research)); ?>" class="ml-auto rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export research</a>
    </nav>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 px-5 py-6 text-white sm:px-7">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-indigo-200">
                        <span>Research Studio</span>
                        <span class="text-white/30">•</span>
                        <span><?php echo e($research->researchType?->name ?? 'Research project'); ?></span>
                    </div>
                    <h1 class="mt-3 text-2xl font-black leading-tight sm:text-3xl"><?php echo e($research->title); ?></h1>
                    <?php if($research->abstract): ?>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300"><?php echo e(str(strip_tags($research->abstract))->limit(220)); ?></p>
                    <?php elseif($research->research_area): ?>
                        <p class="mt-3 text-sm text-slate-300"><?php echo e($research->research_area); ?></p>
                    <?php endif; ?>

                    <?php if(!empty($research->keywords)): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php $__currentLoopData = array_slice($research->keywords ?? [], 0, 6); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $keyword): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-slate-100"><?php echo e($keyword); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="<?php echo e(route('research.show', ['research' => $research, 'section' => $nextWritingAction['section']?->uuid])); ?>" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-slate-950 shadow-sm">Continue writing</a>
                    <?php if($modules['literature']): ?>
                        <a href="<?php echo e(route('research.literature.search', $research)); ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/15">Search literature</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold text-slate-300">
                    <span>Overall project progress</span>
                    <span><?php echo e(number_format((float) $research->progress)); ?>%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-indigo-400" style="width:<?php echo e(min(100, max(0, (float) $research->progress))); ?>%"></div>
                </div>
            </div>
        </div>

        <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
            <div class="p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Research sections</p>
                <p class="mt-2 text-2xl font-black text-slate-950"><?php echo e($workspaceStats['approved_sections']); ?>/<?php echo e($workspaceStats['total_sections']); ?></p>
                <p class="mt-1 text-xs text-slate-500">approved</p>
            </div>
            <div class="p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Open tasks</p>
                <p class="mt-2 text-2xl font-black text-slate-950"><?php echo e($workspaceStats['open_tasks']); ?></p>
                <p class="mt-1 text-xs text-slate-500">actions remaining</p>
            </div>
            <?php if($modules['literature']): ?>
                <div class="p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Literature</p>
                    <p class="mt-2 text-2xl font-black text-slate-950"><?php echo e($literatureStatus['reviewed'] ?? 0); ?></p>
                    <p class="mt-1 text-xs text-slate-500">reviewed · <?php echo e($workspaceStats['references']); ?> saved</p>
                </div>
            <?php else: ?>
                <div class="p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Workspace mode</p>
                    <p class="mt-2 text-sm font-black text-slate-950">Independent research</p>
                    <p class="mt-1 text-xs text-slate-500">Personal research workspace</p>
                </div>
            <?php endif; ?>
            <div class="p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Next milestone</p>
                <p class="mt-2 text-sm font-black text-slate-950"><?php echo e($workspaceStats['next_milestone']?->title ?? 'No pending milestone'); ?></p>
                <?php if($workspaceStats['next_milestone']?->due_at): ?>
                    <p class="mt-1 text-xs text-slate-500">Due <?php echo e($workspaceStats['next_milestone']->due_at->format('M j, Y')); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Research journey</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Your document, section by section</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">The navigator follows this project's configured research template, so different research types can use different structures without changing existing projects.</p>
                </div>
                <a href="<?php echo e(route('research.show', $research)); ?>" class="shrink-0 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Open writing workspace</a>
            </div>

            <div class="mt-5 space-y-3">
                <?php $__empty_1 = true; $__currentLoopData = $sectionJourney; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php ($section = $step['section']); ?>
                    <a href="<?php echo e(route('research.show', ['research' => $research, 'section' => $section->uuid])); ?>" class="group grid gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30 sm:grid-cols-[42px_minmax(0,1fr)_120px] sm:items-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full <?php echo e($step['status_tone'] === 'emerald' ? 'bg-emerald-100 text-emerald-700' : ($step['status_tone'] === 'amber' ? 'bg-amber-100 text-amber-700' : ($step['status_tone'] === 'blue' ? 'bg-blue-100 text-blue-700' : ($step['status_tone'] === 'indigo' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-500')))); ?> text-sm font-black">
                            <?php if($step['progress'] >= 100): ?> ✓ <?php else: ?> <?php echo e($loop->iteration); ?> <?php endif; ?>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate font-bold text-slate-900"><?php echo e($section->title); ?></span>
                            <span class="mt-0.5 block text-xs text-slate-500"><?php echo e($step['status_label']); ?><?php echo e($section->is_required ? ' · Required' : ''); ?></span>
                        </span>
                        <span>
                            <span class="mb-1 flex justify-between text-[10px] font-bold uppercase tracking-wide text-slate-400"><span>Progress</span><span><?php echo e($step['progress']); ?>%</span></span>
                            <span class="block h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-indigo-500" style="width:<?php echo e($step['progress']); ?>%"></span></span>
                        </span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">This project has no configured writing sections yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Next action</p>
                <h2 class="mt-2 text-lg font-black text-slate-950"><?php echo e($nextWritingAction['label']); ?></h2>
                <p class="mt-2 text-sm leading-6 text-slate-600"><?php echo e($nextWritingAction['description']); ?></p>
                <?php if($nextWritingAction['section']): ?>
                    <a href="<?php echo e(route('research.show', ['research' => $research, 'section' => $nextWritingAction['section']->uuid])); ?>" class="mt-4 block rounded-xl bg-indigo-600 px-4 py-2.5 text-center text-sm font-black text-white">Continue writing</a>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Quick actions</p>
                <?php if($modules['literature']): ?>
                    <form method="GET" action="<?php echo e(route('research.literature.search', $research)); ?>" class="mt-3 flex gap-2">
                        <input name="q" class="min-w-0 flex-1 rounded-xl border-slate-200 text-xs" placeholder="Search academic papers...">
                        <button class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-black text-white">Search</button>
                    </form>
                <?php endif; ?>
                <div class="mt-3 space-y-2">
                    <?php if($modules['literature']): ?>
                        <a href="<?php echo e(route('research.literature.search', $research)); ?>" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Search literature</a>
                        <a href="<?php echo e(route('research.literature.search', ['research' => $research, 'view' => 'saved'])); ?>" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">View saved references</a>
                    <?php endif; ?>
                    <a href="#research-assistant" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Research Assistant</a>
                    <a href="<?php echo e(route('research.export.html', $research)); ?>" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Preview / export research</a>
                </div>
            </section>

            <?php if($modules['literature']): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3"><h3 class="font-black text-slate-900">Reading progress</h3><span class="text-xs font-bold text-slate-400"><?php echo e($workspaceStats['references']); ?> total</span></div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xl font-black text-slate-900"><?php echo e($literatureStatus['saved'] ?? 0); ?></p><p class="text-[10px] font-bold uppercase text-slate-400">Saved</p></div>
                        <div class="rounded-xl bg-amber-50 p-3"><p class="text-xl font-black text-amber-800"><?php echo e($literatureStatus['reading'] ?? 0); ?></p><p class="text-[10px] font-bold uppercase text-amber-600">Reading</p></div>
                        <div class="rounded-xl bg-emerald-50 p-3"><p class="text-xl font-black text-emerald-800"><?php echo e($literatureStatus['reviewed'] ?? 0); ?></p><p class="text-[10px] font-bold uppercase text-emerald-600">Reviewed</p></div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if($modules['institutional'] && $research->workspace_mode === 'institutional'): ?>
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Institutional workspace</p>
                    <p class="mt-2 text-sm font-bold text-indigo-950">Institutional research features are active for this project.</p>
                    <p class="mt-1 text-xs leading-5 text-indigo-800/75">Supervision, department and institution-linked controls appear only because the administrator enabled Institutional Expansion.</p>
                </section>
            <?php endif; ?>
        </aside>
    </div>

    <div id="research-assistant">
        <?php echo $__env->make('ai._contextual-assistant', [
            'assistantFeature' => 'research_assistant',
            'assistantEndpoint' => route('ai.context.research', $research),
            'assistantDescription' => 'Use the current research plan, milestones, tasks, literature notes, sections and authorized research evidence to get source-aware guidance without fabricating academic work.',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Milestones</h2>
                    <p class="mt-1 text-sm text-slate-500">Track the major stages that determine overall project progress.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500"><?php echo e($research->milestones->count()); ?></span>
            </div>
            <div class="mt-4 space-y-3">
                <?php $__empty_1 = true; $__currentLoopData = $research->milestones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $milestone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <form method="POST" action="<?php echo e(route('research.milestones.update', [$research, $milestone])); ?>" class="grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-[1fr_150px_auto] sm:items-center">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div>
                            <p class="font-semibold text-slate-900"><?php echo e($milestone->title); ?></p>
                            <p class="text-xs text-slate-500"><?php echo e($milestone->weight); ?>% · Due <?php echo e($milestone->due_at?->format('M j, Y') ?? 'not set'); ?></p>
                        </div>
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            <?php $__currentLoopData = ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'blocked' => 'Blocked']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php if($milestone->status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">Save</button>
                    </form>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">No configured milestones yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-lg font-black text-slate-900">Project tasks</h2>
                <p class="mt-1 text-sm text-slate-500">Break the research into clear, trackable actions.</p>
            </div>
            <form method="POST" action="<?php echo e(route('research.tasks.store', $research)); ?>" class="mt-4 grid gap-3">
                <?php echo csrf_field(); ?>
                <input name="title" required class="rounded-xl border-slate-300" placeholder="Task title">
                <textarea name="description" class="rounded-xl border-slate-300" placeholder="What needs to be done?"></textarea>
                <div class="grid gap-3 sm:grid-cols-3">
                    <select name="assigned_to" class="rounded-xl border-slate-300">
                        <option value="">Unassigned</option>
                        <?php $__currentLoopData = $participants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $participant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($participant->id); ?>"><?php echo e($participant->full_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <select name="priority" class="rounded-xl border-slate-300">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="low">Low</option>
                    </select>
                    <input type="datetime-local" name="due_at" class="rounded-xl border-slate-300">
                </div>
                <button class="rounded-xl bg-indigo-600 px-4 py-2.5 font-bold text-white">Add task</button>
            </form>

            <div class="mt-5 space-y-2">
                <?php $__empty_1 = true; $__currentLoopData = $research->tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <form method="POST" action="<?php echo e(route('research.tasks.update', [$research, $task])); ?>" class="flex flex-col gap-3 rounded-xl bg-slate-50 p-3 sm:flex-row sm:items-center">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="flex-1">
                            <p class="font-semibold text-slate-900"><?php echo e($task->title); ?></p>
                            <p class="text-xs text-slate-500"><?php echo e(str($task->priority)->headline()); ?> · <?php echo e($task->due_at?->diffForHumans() ?? 'No deadline'); ?></p>
                        </div>
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            <?php $__currentLoopData = ['open','in_progress','blocked','completed','cancelled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($status); ?>" <?php if($task->status === $status): echo 'selected'; endif; ?>><?php echo e(str($status)->headline()); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700">Update</button>
                    </form>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-sm text-slate-500">No project tasks yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if($modules['meetings']): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-lg font-black text-slate-900">Research meetings & action items</h2>
                <p class="mt-1 text-sm text-slate-500">Schedule supervision/team meetings, record attendance and turn decisions into follow-up actions.</p>
            </div>
            <form method="POST" action="<?php echo e(route('research.meetings.store', $research)); ?>" class="mt-4 grid gap-3 md:grid-cols-2">
                <?php echo csrf_field(); ?>
                <input type="datetime-local" name="scheduled_at" required class="rounded-xl border-slate-300">
                <input type="number" name="duration_minutes" value="60" min="15" max="480" class="rounded-xl border-slate-300">
                <input name="location" class="rounded-xl border-slate-300" placeholder="Physical location">
                <input type="url" name="online_url" class="rounded-xl border-slate-300" placeholder="Online meeting URL">
                <textarea name="agenda" class="rounded-xl border-slate-300 md:col-span-2" placeholder="Meeting agenda"></textarea>
                <select name="attendee_ids[]" multiple class="rounded-xl border-slate-300 md:col-span-2">
                    <?php $__currentLoopData = $participants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $participant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($participant->id); ?>"><?php echo e($participant->full_name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <button class="rounded-xl bg-blue-600 px-4 py-2.5 font-bold text-white md:col-span-2">Schedule meeting</button>
            </form>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <?php $__empty_1 = true; $__currentLoopData = $research->meetings->sortByDesc('scheduled_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $meeting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900"><?php echo e($meeting->scheduled_at?->toDayDateTimeString() ?? 'Schedule pending'); ?></p>
                                <p class="text-sm text-slate-500"><?php echo e($meeting->location ?: $meeting->online_url ?: 'Location not set'); ?> · <?php echo e(str($meeting->status)->headline()); ?></p>
                            </div>
                            <a href="<?php echo e(route('research.meetings.calendar', [$research, $meeting])); ?>" class="shrink-0 text-sm font-bold text-blue-600">Calendar</a>
                        </div>
                        <?php if($meeting->agenda): ?>
                            <p class="mt-3 text-sm leading-6 text-slate-700"><?php echo e($meeting->agenda); ?></p>
                        <?php endif; ?>
                        <div class="mt-3 text-xs text-slate-500">Attendees: <?php echo e($meeting->attendees->pluck('user.full_name')->filter()->implode(', ') ?: 'None selected'); ?></div>

                        <?php if($meeting->status !== 'completed'): ?>
                            <form method="POST" action="<?php echo e(route('research.meetings.complete', [$research, $meeting])); ?>" class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                                <?php echo csrf_field(); ?>
                                <textarea name="notes" class="w-full rounded-lg border-slate-300" placeholder="Meeting notes"></textarea>
                                <div class="flex flex-wrap gap-3">
                                    <?php $__currentLoopData = $meeting->attendees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attendee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <label class="text-sm text-slate-700"><input type="checkbox" name="attendance[<?php echo e($attendee->user_id); ?>]" value="1"> <?php echo e($attendee->user?->full_name); ?></label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white">Complete meeting</button>
                            </form>
                        <?php endif; ?>

                        <?php $__currentLoopData = $meeting->actionItemRecords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="mt-2 flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-950">
                                <span class="flex-1"><?php echo e($item->title); ?> · <?php echo e($item->assignee?->full_name ?: 'Unassigned'); ?></span>
                                <?php if($item->status !== 'completed'): ?>
                                    <form method="POST" action="<?php echo e(route('research.action-items.complete', [$research, $item])); ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <button class="font-bold text-emerald-700">Complete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 lg:col-span-2">No research meetings scheduled yet.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if($modules['collaboration']): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-lg font-black text-slate-900">Collaboration & contribution evidence</h2>
                <p class="mt-1 text-sm text-slate-500">Manage formal project contributors and compare declared contribution with recorded writing activity.</p>
            </div>
            <?php if($candidateMembers->isNotEmpty()): ?>
                <form method="POST" action="<?php echo e(route('research.members.store', $research)); ?>" class="mt-4 grid gap-3 lg:grid-cols-2">
                    <?php echo csrf_field(); ?>
                    <select name="user_id" class="rounded-xl border-slate-300 lg:col-span-2">
                        <?php $__currentLoopData = $candidateMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($member->id); ?>"><?php echo e($member->full_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <select name="role" class="rounded-xl border-slate-300">
                        <?php $__currentLoopData = ['lead_author','author','data_analyst','research_assistant','advisor']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($role); ?>"><?php echo e(str($role)->headline()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <input type="number" step="0.01" min="0" max="100" name="contribution_percent" class="rounded-xl border-slate-300" placeholder="Declared contribution %">
                    <div class="grid grid-cols-2 gap-2 text-sm text-slate-700 lg:col-span-2">
                        <?php $__currentLoopData = ['write','comment','manage_references','manage_datasets','view_private','assign_tasks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label><input type="checkbox" name="permissions[]" value="<?php echo e($permission); ?>"> <?php echo e(str($permission)->headline()); ?></label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <button class="rounded-xl bg-slate-900 px-4 py-2.5 font-bold text-white lg:col-span-2">Add or update collaborator</button>
                </form>
            <?php else: ?>
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No eligible institutional collaborators are available for this project.</p>
            <?php endif; ?>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th class="py-2 text-left">Member</th><th>Role</th><th>Measured</th><th>Declared</th></tr></thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $contributions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-t border-slate-100">
                                <td class="py-2 font-medium text-slate-800"><?php echo e($row['name']); ?></td>
                                <td class="text-center"><?php echo e(str($row['role'])->headline()); ?></td>
                                <td class="text-center"><?php echo e($row['measured_percent']); ?>%</td>
                                <td class="text-center"><?php echo e($row['declared_percent'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="py-5 text-center text-slate-500">No contributor records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if($modules['archives']): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-lg font-black text-slate-900">Approved-version archive & amendments</h2>
                <p class="mt-1 text-sm text-slate-500">Use this governance layer when an approved project needs an immutable snapshot and controlled post-approval changes.</p>
            </div>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $research)): ?>
                <form method="POST" action="<?php echo e(route('research.archives.seal', $research)); ?>" class="mt-4">
                    <?php echo csrf_field(); ?>
                    <button class="rounded-xl bg-emerald-600 px-4 py-2.5 font-bold text-white">Seal approved version</button>
                </form>
            <?php endif; ?>

            <div class="mt-4 space-y-2">
                <?php $__empty_1 = true; $__currentLoopData = $research->archives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $archive): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <a class="block rounded-xl bg-slate-50 p-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="<?php echo e(route('research.archives.download', [$research, $archive])); ?>">Archive v<?php echo e($archive->version); ?> · <?php echo e($archive->status); ?> · <?php echo e(str($archive->checksum)->limit(18)); ?></a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-sm text-slate-500">No sealed archive versions yet.</p>
                <?php endif; ?>
            </div>

            <?php if($research->archives->isNotEmpty()): ?>
                <form method="POST" action="<?php echo e(route('research.amendments.store', $research)); ?>" class="mt-5 space-y-2">
                    <?php echo csrf_field(); ?>
                    <textarea name="reason" required class="w-full rounded-xl border-slate-300" placeholder="Reason and controlled changes requested"></textarea>
                    <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Request amendment</button>
                </form>
            <?php endif; ?>

            <?php $__currentLoopData = $research->amendments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $amendment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="mt-3 rounded-xl border border-slate-200 p-3">
                    <p class="font-semibold text-slate-900"><?php echo e($amendment->reason); ?></p>
                    <p class="text-xs text-slate-500"><?php echo e(str($amendment->status)->headline()); ?></p>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $research)): ?>
                        <?php if($amendment->status === 'pending'): ?>
                            <form method="POST" action="<?php echo e(route('research.amendments.review', [$research, $amendment])); ?>" class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <?php echo csrf_field(); ?>
                                <select name="decision" class="rounded-lg border-slate-300"><option value="approve">Approve</option><option value="reject">Reject</option></select>
                                <input name="review_note" class="flex-1 rounded-lg border-slate-300" placeholder="Review note">
                                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">Record</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/workspace.blade.php ENDPATH**/ ?>
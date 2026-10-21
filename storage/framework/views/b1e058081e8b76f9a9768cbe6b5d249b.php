<?php $__env->startSection('title', 'Study notes'); ?>
<?php $__env->startSection('page-title', 'Study notes'); ?>
<?php $__env->startSection('page-subtitle', 'Save and revisit what you learn'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-4xl space-y-6">
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <form method="GET" action="<?php echo e(route('study-notes.index')); ?>" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-xs font-bold text-slate-600">Filter by course
            <select name="course_id" class="mt-1.5 block rounded-xl border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="">All courses</option>
                <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($course->id); ?>" <?php if(request('course_id') == $course->id): echo 'selected'; endif; ?>><?php echo e($course->code); ?> — <?php echo e($course->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </label>
    </form>

    <?php echo $__env->make('student._help', [
        'title' => 'How to use study notes',
        'steps' => [
            'Give the note a title, optionally attach a course, and write or paste your content.',
            'Click Save note — then filter your notes by course using the selector above.',
            'Keep useful AI answers and your own summaries in one place so revision is easier.',
        ],
        'tip' => 'AI answers are starting points — check them against your course material before relying on them.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <form method="POST" action="<?php echo e(route('study-notes.store')); ?>" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <h2 class="text-base font-black text-slate-900">Add a note</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Title
                <input type="text" name="title" required maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
            <label class="text-xs font-bold text-slate-600">Course (optional)
                <select name="course_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">No course</option>
                    <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($course->id); ?>"><?php echo e($course->code); ?> — <?php echo e($course->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        </div>
        <label class="block text-xs font-bold text-slate-600">Content
            <textarea name="content" rows="4" required maxlength="200000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Write your note or paste an AI answer you want to keep."></textarea>
        </label>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save note</button>
    </form>

    <section class="space-y-3">
        <?php $__empty_0 = true; $__currentLoopData = $notes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-black text-slate-900"><?php echo e($note->title); ?></h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <?php echo e($note->course?->code ?? 'General'); ?> · <?php echo e(str_replace('_', ' ', $note->source)); ?> · <?php echo e($note->created_at?->diffForHumans()); ?>

                        </p>
                    </div>
                    <form method="POST" action="<?php echo e(route('study-notes.destroy', $note)); ?>" onsubmit="return confirm('Delete this note?');">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('DELETE'); ?>
                        <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">Delete</button>
                    </form>
                </div>
                <div class="prose prose-sm mt-3 max-w-none prose-slate"><?php echo $note->content; ?></div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                No study notes yet. Save useful AI answers or write your own.
            </div>
        <?php endif; ?>

        <div class="pt-2"><?php echo e($notes->links()); ?></div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/study-notes.blade.php ENDPATH**/ ?>
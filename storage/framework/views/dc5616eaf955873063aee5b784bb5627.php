<?php $__env->startSection('title', 'Edit material'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $authoringUser = auth()->user();
    $authoringRuntime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    $writingSuggestionsEnabled = $authoringUser
        && $authoringRuntime->featureEnabled('writing_assistant', $authoringUser->university_id);
?>
<div class="container mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6">
        <a href="<?php echo e(route('materials.show', [$course, $material])); ?>" class="text-sm font-semibold text-slate-500 hover:text-indigo-700">← Back to material</a>
        <h1 class="mt-3 text-2xl font-black text-slate-900">Edit material</h1>
        <p class="mt-1 text-sm text-slate-500"><?php echo e($course->code); ?> — <?php echo e($course->name); ?></p>
    </div>

    <form method="POST" action="<?php echo e(route('lecturer.materials.update', [$course, $material])); ?>" enctype="multipart/form-data" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <div>
            <label class="block text-sm font-bold text-slate-700">Title *</label>
            <input type="text" name="title" value="<?php echo e(old('title', $material->title)); ?>" required maxlength="255"
                   class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Description</label>
            <textarea name="description" rows="3" maxlength="20000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><?php echo e(old('description', $material->description)); ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Material type *</label>
            <select name="type" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <?php $__currentLoopData = ['lecture_note', 'slides', 'reading', 'video', 'assignment', 'exam', 'reference', 'other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($typeValue); ?>" <?php if(old('type', $material->type) === $typeValue): echo 'selected'; endif; ?>><?php echo e(ucfirst(str_replace('_', ' ', $typeValue))); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-slate-700">Topic</label>
                <input type="text" name="topic" value="<?php echo e(old('topic', $material->topic)); ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700">Week number</label>
                <input type="number" name="week_number" value="<?php echo e(old('week_number', $material->week_number)); ?>" min="1" max="20" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Sort order</label>
            <input type="number" name="sequence_order" value="<?php echo e(old('sequence_order', $material->sequence_order)); ?>" min="0" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <?php if($material->hasFile()): ?>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <p class="font-bold text-slate-800">Uploaded file</p>
                <p class="mt-1 break-all text-slate-600"><?php echo e($material->file_name ?: $material->file_path); ?></p>
                <a href="<?php echo e(route('materials.download', [$course, $material])); ?>" class="mt-2 inline-block text-xs font-bold text-indigo-700">Download current file</a>
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-bold text-slate-700">Written content</label>
            <?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <textarea id="materialContent" name="content" rows="14" data-rich-editor data-editor-height="380px"
                <?php if($writingSuggestionsEnabled): ?> data-ai-url="<?php echo e(route('ai.writing')); ?>" data-ai-enabled="1" data-ai-min-chars="180" data-ai-delay="2200" <?php endif; ?>
                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><?php echo e(old('content', $material->content)); ?></textarea>
            <p class="mt-1 text-xs text-slate-500">Edit the material directly. Formatting is preserved and safely sanitized when saved.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Visibility</label>
            <div class="mt-2 space-y-2 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" <?php if(old('is_public', $material->is_public)): echo 'checked'; endif; ?>> Make publicly accessible
                </label>
                <br>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="requires_enrollment" value="0">
                    <input type="checkbox" name="requires_enrollment" value="1" <?php if(old('requires_enrollment', $material->requires_enrollment)): echo 'checked'; endif; ?>> Require enrollment to access
                </label>
                <br>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_visible" value="0">
                    <input type="checkbox" name="is_visible" value="1" <?php if(old('is_visible', $material->is_visible)): echo 'checked'; endif; ?>> Visible to students
                </label>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Update material</button>
            <a href="<?php echo e(route('materials.show', [$course, $material])); ?>" class="rounded-xl bg-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-300">Cancel</a>
        </div>
    </form>

    <?php echo $__env->make('materials._lecturer_ai_panel', [
        'aiEndpoint' => route('lecturer.ai.material', [$course, $material]),
        'editorTargetId' => 'materialContent',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $material)): ?>
        <form method="POST" action="<?php echo e(route('lecturer.materials.destroy', [$course, $material])); ?>" class="mt-6" onsubmit="return confirm('Delete this material permanently?');">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">Delete material</button>
        </form>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/materials/edit.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Edit Discussion'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Edit Discussion</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="<?php echo e(route('discussions.update', [$course, $discussion])); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Title *</label>
                <input type="text" name="title" value="<?php echo e(old('title', $discussion->title)); ?>" 
                       class="w-full px-3 py-2 border rounded <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       required maxlength="255">
                <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Content *</label>
                <textarea name="content" rows="6" 
                          class="w-full px-3 py-2 border rounded <?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                          required><?php echo e(old('content', $discussion->content)); ?></textarea>
                <?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Priority</label>
                <select name="priority" class="w-full px-3 py-2 border rounded">
                    <option value="low" <?php echo e($discussion->priority === 'low' ? 'selected' : ''); ?>>Low</option>
                    <option value="normal" <?php echo e($discussion->priority === 'normal' ? 'selected' : ''); ?>>Normal</option>
                    <option value="high" <?php echo e($discussion->priority === 'high' ? 'selected' : ''); ?>>High</option>
                </select>
            </div>

            <?php if($tags->count() > 0): ?>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        <?php $__currentLoopData = $tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="tag_ids[]" value="<?php echo e($tag->id); ?>" 
                                       <?php echo e($discussion->tags->contains($tag->id) ? 'checked' : ''); ?>

                                       class="rounded border-gray-300">
                                <span class="ml-2 text-sm"><?php echo e($tag->name); ?></span>
                            </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Update Discussion
                </button>
                <a href="<?php echo e(route('discussions.show', [$course, $discussion])); ?>" 
                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/discussions/edit.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Schedule Defense'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Schedule Defense</h1>
        <p class="text-gray-600 mt-2"><?php echo e($submission->title); ?> (<?php echo e(ucfirst($submission->type)); ?>)</p>
    </div>

    <?php if($existingDefense): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="font-bold text-blue-900 mb-2">Existing Defense Scheduled</h3>
            <p class="text-blue-800">
                <strong>Date & Time:</strong> <?php echo e($existingDefense->scheduled_at->format('F d, Y H:i')); ?><br>
                <strong>Venue:</strong> <?php echo e($existingDefense->venue ?? 'Not specified'); ?><br>
                <strong>Duration:</strong> <?php echo e($existingDefense->duration_minutes); ?> minutes<br>
                <strong>Status:</strong> <?php echo e(ucfirst($existingDefense->status)); ?>

            </p>
        </div>
    <?php endif; ?>

    <form action="<?php echo e(route('defenses.store', $submission)); ?>" method="POST" class="bg-white rounded-lg shadow p-8">
        <?php echo csrf_field(); ?>

        <!-- Scheduled At -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Defense Date & Time *</label>
            <input type="datetime-local" name="scheduled_at" 
                   value="<?php echo e(old('scheduled_at', $existingDefense ? $existingDefense->scheduled_at->format('Y-m-d\TH:i') : '')); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   required>
            <?php $__errorArgs = ['scheduled_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <!-- Duration -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes) *</label>
            <input type="number" name="duration_minutes" 
                   value="<?php echo e(old('duration_minutes', $existingDefense->duration_minutes ?? 30)); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   min="15" max="180" required>
            <p class="text-xs text-gray-500 mt-1">15-180 minutes</p>
            <?php $__errorArgs = ['duration_minutes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <!-- Venue -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Venue</label>
            <input type="text" name="venue" 
                   value="<?php echo e(old('venue', $existingDefense->venue ?? '')); ?>"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="e.g., Room 101, Main Building">
            <?php $__errorArgs = ['venue'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="4" 
                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Any additional notes for the defense..."><?php echo e(old('notes', $existingDefense->notes ?? '')); ?></textarea>
            <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="text-red-600 text-sm mt-1"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                <?php echo e($existingDefense ? 'Update Defense' : 'Schedule Defense'); ?>

            </button>

            <a href="<?php echo e(route('submissions.show', $submission)); ?>" 
               class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                Cancel
            </a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/defenses/schedule.blade.php ENDPATH**/ ?>
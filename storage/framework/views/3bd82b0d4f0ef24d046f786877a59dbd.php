<?php $__env->startSection('title', $course->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6"><?php echo e($course->code); ?> - <?php echo e($course->name); ?></h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Course Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <div class="text-gray-500 text-sm">Credits</div>
                    <div><?php echo e($course->credit_hours); ?></div>
                </div>
                <div>
                    <div class="text-gray-500 text-sm">Status</div>
                    <span class="px-2 inline-flex text-sm font-semibold rounded-full <?php echo e($course->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo e($course->is_active ? 'Active' : 'Inactive'); ?>

                    </span>
                </div>
            </div>

            <h2 class="text-lg font-bold mb-4">Course Lecturers (<?php echo e($course->lecturerAssignments->count()); ?>)</h2>
            <?php if($course->lecturerAssignments->count() > 0): ?>
                <div class="space-y-3 mb-4">
                    <?php $__currentLoopData = $course->lecturerAssignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <span class="text-indigo-600 font-semibold"><?php echo e(substr($assignment->user->full_name, 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo e($assignment->user->full_name); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo e($assignment->user->email); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if($assignment->is_coordinator): ?>
                                    <span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded">Coordinator</span>
                                <?php endif; ?>
                                <form method="POST" action="<?php echo e(route('admin.courses.lecturers.destroy', [$course, $assignment])); ?>" onsubmit="return confirm('Are you sure you want to remove this lecturer?');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 mb-4">No lecturers assigned to this course.</p>
            <?php endif; ?>

            <!-- Assign Lecturer Form -->
            <div class="border-t pt-4">
                <h3 class="text-md font-semibold mb-3">Assign New Lecturer</h3>
                <form method="POST" action="<?php echo e(route('admin.courses.lecturers.store', $course)); ?>" class="flex gap-2">
                    <?php echo csrf_field(); ?>
                    <select name="user_id" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select a lecturer...</option>
                        <?php $__currentLoopData = $lecturers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lecturer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(! $course->lecturerAssignments->contains('user_id', $lecturer->id)): ?>
                                <option value="<?php echo e($lecturer->id); ?>"><?php echo e($lecturer->full_name); ?> (<?php echo e($lecturer->email); ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1 text-sm text-gray-700">
                            <input type="checkbox" name="is_coordinator" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Coordinator
                        </label>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                        Assign
                    </button>
                </form>
            </div>
        </div>

        <!-- Enrolled Students -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold mb-4">Enrolled Students (<?php echo e($course->enrollments_count); ?>)</h2>
            <?php if($enrollments->total() > 0): ?>
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Student</th>
                            <th class="px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php $__empty_0 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                            <tr>
                                <td class="px-4 py-2"><?php echo e($enrollment->user->email); ?></td>
                                <td class="px-4 py-2"><?php echo e($enrollment->status); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                            <tr><td colspan="2" class="px-4 py-2">No enrollments</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if($enrollments->hasPages()): ?>
                    <div class="mt-5"><?php echo e($enrollments->links()); ?></div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-gray-500">No enrollments</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/courses/admin-show.blade.php ENDPATH**/ ?>